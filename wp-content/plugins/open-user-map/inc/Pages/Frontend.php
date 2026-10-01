<?php

/**
 * @package OpenUserMapPlugin
 */
namespace OpenUserMapPlugin\Pages;

use OpenUserMapPlugin\Base\BaseController;
use OpenUserMapPlugin\Base\DiagnosticsReporter;
use OpenUserMapPlugin\Base\OptOutFromTemplateEnhancement;
class Frontend extends BaseController {
    public function register() {
        // WP 6.9 compatibility:
        // Automatically opt out of template enhancement buffer when OUM output
        // is likely to render on the current request.
        $opt_out_from_template_enhancement = new OptOutFromTemplateEnhancement();
        add_action( 'wp', array($opt_out_from_template_enhancement, 'maybe_disable_wp69_template_enhancement_buffer'), 1 );
        // Shortcodes
        add_action( 'init', array($this, 'set_shortcodes') );
        // Print concise diagnostics in page source when ?oum_debug=1 is present.
        $diagnostics_reporter = new DiagnosticsReporter($opt_out_from_template_enhancement);
        add_action( 'wp_footer', array($diagnostics_reporter, 'print_frontend_diagnostics_comment'), PHP_INT_MAX );
        // Footer containers (add-location form & fullscreen popup)
        // Rendered globally to work with page builder caching
        add_action( 'wp_footer', array($this, 'render_footer_containers') );
        // Enqueue AJAX script globally to work with page builder caching
        add_action( 'wp_footer', array($this, 'ensure_ajax_script_localized'), 5 );
        // Enqueue vote script globally to work with page builder caching (PRO)
        if ( oum_fs()->is__premium_only() && oum_fs()->can_use_premium_code() ) {
            if ( get_option( 'oum_enable_vote_feature' ) === 'on' ) {
                add_action( 'wp_footer', array($this, 'ensure_vote_script_localized'), 5 );
            }
        }
    }

    /**
     * Ensure AJAX script is localized even with page builder caching
     */
    public function ensure_ajax_script_localized() {
        if ( !$this->is_script_ready_for_localization( 'oum_frontend_ajax_js', 'oum_ajax' ) ) {
            return;
        }
        wp_localize_script( 'oum_frontend_ajax_js', 'oum_ajax', $this->oum_ajax_localization_data() );
        wp_localize_script( 'oum_frontend_ajax_js', 'oum_custom_strings', $this->oum_custom_strings() );
    }

    /**
     * Ensure vote script is localized even with page builder caching
     */
    public function ensure_vote_script_localized() {
        if ( !$this->is_script_ready_for_localization( 'oum_frontend_vote_js', 'oum_vote_nonce' ) ) {
            return;
        }
        $vote_nonce_data = array(
            'nonce' => wp_create_nonce( 'oum_vote_nonce' ),
        );
        $cookie_type_data = array(
            'type' => get_option( 'oum_vote_cookie_type', 'persistent' ),
        );
        wp_localize_script( 'oum_frontend_vote_js', 'oum_vote_nonce', $vote_nonce_data );
        wp_localize_script( 'oum_frontend_vote_js', 'oum_vote_cookie_type', $cookie_type_data );
    }

    /**
     * Check if script is enqueued and not already localized
     * 
     * @param string $script_handle The script handle to check
     * @param string $object_name The localized object name to check for
     * @return bool True if script needs localization, false otherwise
     */
    private function is_script_ready_for_localization( $script_handle, $object_name ) {
        // Check if script is enqueued
        if ( !wp_script_is( $script_handle, 'enqueued' ) && !wp_script_is( $script_handle, 'done' ) ) {
            return false;
        }
        global $wp_scripts;
        // Validate wp_scripts object and script registration
        if ( !$wp_scripts instanceof \WP_Scripts || !isset( $wp_scripts->registered[$script_handle] ) ) {
            return false;
        }
        // Check if already localized
        $script_data = $wp_scripts->registered[$script_handle];
        $is_already_localized = isset( $script_data->extra['data'] ) && strpos( $script_data->extra['data'], $object_name ) !== false;
        return !$is_already_localized;
    }

    /**
     * Render footer containers for map overlays
     * 
     * Outputs add-location form and fullscreen popup used by all maps.
     * Only renders on pages with maps (checks if CSS is enqueued).
     */
    public function render_footer_containers() {
        // Skip admin area
        if ( is_admin() ) {
            return;
        }
        // Only render if OUM CSS was enqueued (indicates map is present)
        if ( !wp_style_is( 'oum_frontend_css', 'enqueued' ) && !wp_style_is( 'oum_frontend_css', 'done' ) ) {
            return;
        }
        // Same visibility rule as partial-map-add-location.php (map "+ Add Location" button).
        if ( $this->should_show_add_location_form() ) {
            // Fallback for page-builder caching paths where shortcode assets may not run.
            $this->enqueue_add_location_dashicons();
        }
        require_once oum_get_template( 'partial-map-add-location.php' );
    }

    /**
     * Setup Shortcodes
     * 
     * Note: Page builder detection is handled in the individual rendering methods
     * via the is_page_builder_active() helper function. Shortcodes are always
     * registered, but will return a styled placeholder when rendered inside a
     * page builder interface.
     */
    public function set_shortcodes() {
        // Render Map
        add_shortcode( 'open-user-map', array($this, 'render_block_map') );
        // Shortcode: "Add Location" Form (only)
        add_shortcode( 'open-user-map-form', array($this, 'render_block_form') );
        // Inject Complianz attributes and the tile provider attribute on all OUM scripts
        add_filter(
            'script_loader_tag',
            function ( $tag, $handle, $source ) {
                if ( strpos( $handle, 'oum' ) === false ) {
                    return $tag;
                }
                // Parse existing attributes from the tag
                $existing_attrs = array();
                if ( preg_match_all(
                    '/(\\w+(?:-\\w+)*)=["\']([^"\']*)["\']/',
                    $tag,
                    $matches,
                    PREG_SET_ORDER
                ) ) {
                    foreach ( $matches as $match ) {
                        $existing_attrs[$match[1]] = $match[2];
                    }
                }
                // Extract src if present (fallback to $source parameter)
                $src = ( isset( $existing_attrs['src'] ) ? $existing_attrs['src'] : $source );
                // Build our custom attributes (these will override existing ones if present)
                $custom_attrs = array(
                    'src'           => esc_url( $src ),
                    'data-category' => 'functional',
                    'class'         => 'cmplz-native',
                    'id'            => esc_attr( $handle ) . '-js',
                );
                // Append the tile provider attribute if we stored a value for this handle during enqueue.
                if ( function_exists( 'wp_scripts' ) ) {
                    $scripts = wp_scripts();
                    if ( $scripts instanceof \WP_Scripts ) {
                        $tile_provider = $scripts->get_data( $handle, 'data-oum-tile-provider' );
                        if ( !empty( $tile_provider ) ) {
                            $custom_attrs['data-oum-tile-provider'] = esc_attr( $tile_provider );
                        }
                    }
                }
                // Merge: existing attributes first, then our custom ones (custom overrides)
                $all_attrs = array_merge( $existing_attrs, $custom_attrs );
                // Build attribute string
                $attr_parts = array();
                foreach ( $all_attrs as $key => $value ) {
                    $attr_parts[] = sprintf( '%s="%s"', esc_attr( $key ), esc_attr( $value ) );
                }
                return sprintf( '<script %s></script>', implode( ' ', $attr_parts ) );
            },
            10,
            3
        );
        // Prevent shortcode parsing by All In One SEO plugin
        add_filter( 'aioseo_disable_shortcode_parsing', '__return_true' );
        // Prevent shortcode parsing by Slim SEO plugin
        add_filter( 'slim_seo_skipped_shortcodes', function ( $shortcodes ) {
            $shortcodes[] = 'open-user-map';
            $shortcodes[] = 'open-user-map-location';
            $shortcodes[] = 'open-user-map-gallery';
            $shortcodes[] = 'open-user-map-list';
            $shortcodes[] = 'open-user-map-form';
            return $shortcodes;
        } );
        // Prevent block parsing by Slim SEO plugin
        add_filter( 'slim_seo_skipped_blocks', function ( $blocks ) {
            $blocks[] = 'open-user-map/map';
            return $blocks;
        } );
    }

}
