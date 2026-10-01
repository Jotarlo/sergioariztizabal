<?php
/**
 * @package OpenUserMapPlugin
 */

namespace OpenUserMapPlugin\Base;

use OpenUserMapPlugin\Base\BaseController;

class Enqueue extends BaseController
{
    /**
     * Register WordPress hooks and actions for asset management
     * 
     * This method sets up all the WordPress hooks needed for registering
     * and enqueuing assets in both admin and frontend contexts.
     * 
     * @return void
     */
    public function register()
    {
        // Admin: Register assets that can be enqueued later (Leaflet for maps)
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));
        
        // Admin: Enqueue assets that must load on every admin page
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Frontend: Register assets for shortcodes (only enqueued when shortcodes render)
        add_action('wp_enqueue_scripts', array($this, 'register_frontend_assets'));
        
        // Frontend: Ensure late-enqueued styles are still output in head (FIXES AN ISSUE IN RARE CASES WHERE CSS DID NOT LOAD)
        // Shortcodes render during content output (after wp_head), so styles enqueued
        // in shortcode callbacks need to be caught and output before </head> closes.
        // Using priority 999 ensures this runs at the very end of wp_head.
        add_action('wp_head', array($this, 'output_late_enqueued_styles'), 999);
    }

    /**
     * Register admin assets (Leaflet CSS and JS) for conditional loading
     * 
     * This method registers Leaflet assets needed for admin pages that use maps
     * (settings page, location editor, region editor). These assets are registered
     * but only enqueued when needed via include_map_scripts().
     * 
     * @return void
     */
    public function register_admin_assets()
    {
        // Register Leaflet CSS files (same as frontend)
        wp_register_style(
            'oum_leaflet_css',
            $this->plugin_url . 'src/leaflet/leaflet.css',
            array(),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_gesture_css',
            $this->plugin_url . 'src/leaflet/leaflet-gesture-handling.min.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_markercluster_css',
            $this->plugin_url . 'src/leaflet/leaflet-markercluster.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_markercluster_default_css',
            $this->plugin_url . 'src/leaflet/leaflet-markercluster.default.css',
            array('oum_leaflet_markercluster_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_geosearch_css',
            $this->plugin_url . 'src/leaflet/geosearch.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_fullscreen_css',
            $this->plugin_url . 'src/leaflet/control.fullscreen.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_locate_css',
            $this->plugin_url . 'src/leaflet/leaflet-locate.min.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_search_css',
            $this->plugin_url . 'src/leaflet/leaflet-search.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_responsivepopup_css',
            $this->plugin_url . 'src/leaflet/leaflet-responsive-popup.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_draw_css',
            $this->plugin_url . 'src/leaflet/leaflet-draw/leaflet.draw.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_maplibre_css',
            $this->plugin_url . 'src/maplibre/maplibre-gl.css',
            array(),
            '5.24.0'
        );

        // Register Leaflet JS files (same as frontend)
        wp_register_script(
            'oum_map_loader_js',
            $this->plugin_url . 'src/js/frontend-map-loader.js',
            array(),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_polyfill_unfetch_js',
            $this->plugin_url . 'src/js/polyfills/unfetch.js',
            array('jquery'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_js',
            $this->plugin_url . 'src/leaflet/leaflet.js',
            array('oum_leaflet_polyfill_unfetch_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_providers_js',
            $this->plugin_url . 'src/leaflet/leaflet-providers.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_markercluster_js',
            $this->plugin_url . 'src/leaflet/leaflet-markercluster.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_subgroups_js',
            $this->plugin_url . 'src/leaflet/leaflet.featuregroup.subgroup.js',
            array('oum_leaflet_js', 'oum_leaflet_markercluster_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_geosearch_js',
            $this->plugin_url . 'src/leaflet/geosearch.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_locate_js',
            $this->plugin_url . 'src/leaflet/leaflet-locate.min.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_fullscreen_js',
            $this->plugin_url . 'src/leaflet/control.fullscreen.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_search_js',
            $this->plugin_url . 'src/leaflet/leaflet-search.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_gesture_js',
            $this->plugin_url . 'src/leaflet/leaflet-gesture-handling.min.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_responsivepopup_js',
            $this->plugin_url . 'src/leaflet/leaflet-responsive-popup.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_draw_js',
            $this->plugin_url . 'src/leaflet/leaflet-draw/leaflet.draw.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_global_leaflet_js',
            $this->plugin_url . 'src/leaflet/oum-global-leaflet.js',
            array('oum_leaflet_js', 'oum_leaflet_draw_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_maplibre_js',
            $this->plugin_url . 'src/maplibre/maplibre-gl.js',
            array(),
            '5.24.0',
            true
        );
        wp_register_script(
            'oum_leaflet_maplibre_js',
            $this->plugin_url . 'src/maplibre/leaflet-maplibre-gl.js',
            array('oum_leaflet_js', 'oum_maplibre_js'),
            '0.1.4',
            true
        );
        wp_register_script(
            'oum_map_styles_js',
            $this->plugin_url . 'src/js/oum-map-styles.js',
            array('oum_leaflet_providers_js'),
            $this->plugin_version,
            true
        );
    }

    /**
     * Enqueue admin assets that must load on every admin page
     * 
     * This method enqueues admin styles and scripts that are required
     * on all admin pages (general admin CSS, backend.js, media uploader API).
     * 
     * @return void
     */
    public function enqueue_admin_assets()
    {
        // Enqueue admin styles
        wp_enqueue_style('oum_style', $this->plugin_url . 'assets/style.css', array(), $this->plugin_version);
        wp_enqueue_style('wp-color-picker');

        // add media API (media uploader)
        if ( !did_action( 'wp_enqueue_media' ) ) {
            wp_enqueue_media();
        }

        // enqueue admin scripts
        wp_enqueue_script(
            'oum_script', 
            $this->plugin_url . 'src/js/backend.js',
            array('jquery', 'wp-color-picker'),
            $this->plugin_version,
            true
        );

        wp_localize_script('oum_script', 'oum_ajax', array(
            'oum_location_nonce' => wp_create_nonce('oum_location')
        ));

        // add JS translation for admin scripts
        wp_set_script_translations( 
            'oum_script', 
            'open-user-map', 
            $this->plugin_path . 'languages' 
        );
    }

    /**
     * Register all frontend CSS and JS assets for shortcodes
     * 
     * This method registers all frontend assets globally using wp_register_style() 
     * and wp_register_script(). Assets are only enqueued when shortcodes actually 
     * render, ensuring optimal performance and compatibility with page builders, 
     * Gutenberg, AJAX, and iframe previews.
     * 
     * @return void
     */
    public function register_frontend_assets()
    {
        // Register frontend CSS
        wp_register_style(
            'oum_frontend_css',
            $this->plugin_url . 'assets/frontend.css',
            array(),
            $this->plugin_version
        );

        // RTL overrides: loads assets/frontend-rtl.css after frontend.css on RTL sites
        wp_style_add_data( 'oum_frontend_css', 'rtl', true );
        wp_style_add_data( 'oum_frontend_css', 'path', $this->plugin_path . 'assets/frontend.css' );

        // Register Leaflet CSS files
        wp_register_style(
            'oum_leaflet_css',
            $this->plugin_url . 'src/leaflet/leaflet.css',
            array(),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_gesture_css',
            $this->plugin_url . 'src/leaflet/leaflet-gesture-handling.min.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_markercluster_css',
            $this->plugin_url . 'src/leaflet/leaflet-markercluster.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_markercluster_default_css',
            $this->plugin_url . 'src/leaflet/leaflet-markercluster.default.css',
            array('oum_leaflet_markercluster_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_geosearch_css',
            $this->plugin_url . 'src/leaflet/geosearch.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_fullscreen_css',
            $this->plugin_url . 'src/leaflet/control.fullscreen.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_locate_css',
            $this->plugin_url . 'src/leaflet/leaflet-locate.min.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_search_css',
            $this->plugin_url . 'src/leaflet/leaflet-search.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_responsivepopup_css',
            $this->plugin_url . 'src/leaflet/leaflet-responsive-popup.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_leaflet_draw_css',
            $this->plugin_url . 'src/leaflet/leaflet-draw/leaflet.draw.css',
            array('oum_leaflet_css'),
            $this->plugin_version
        );
        wp_register_style(
            'oum_maplibre_css',
            $this->plugin_url . 'src/maplibre/maplibre-gl.css',
            array(),
            '5.24.0'
        );

        // Register Leaflet JS files (with proper dependencies)
        wp_register_script(
            'oum_map_loader_js',
            $this->plugin_url . 'src/js/frontend-map-loader.js',
            array(),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_polyfill_unfetch_js',
            $this->plugin_url . 'src/js/polyfills/unfetch.js',
            array('jquery'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_js',
            $this->plugin_url . 'src/leaflet/leaflet.js',
            array('oum_leaflet_polyfill_unfetch_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_providers_js',
            $this->plugin_url . 'src/leaflet/leaflet-providers.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_markercluster_js',
            $this->plugin_url . 'src/leaflet/leaflet-markercluster.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_subgroups_js',
            $this->plugin_url . 'src/leaflet/leaflet.featuregroup.subgroup.js',
            array('oum_leaflet_js', 'oum_leaflet_markercluster_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_geosearch_js',
            $this->plugin_url . 'src/leaflet/geosearch.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_locate_js',
            $this->plugin_url . 'src/leaflet/leaflet-locate.min.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_fullscreen_js',
            $this->plugin_url . 'src/leaflet/control.fullscreen.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_search_js',
            $this->plugin_url . 'src/leaflet/leaflet-search.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_gesture_js',
            $this->plugin_url . 'src/leaflet/leaflet-gesture-handling.min.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_responsivepopup_js',
            $this->plugin_url . 'src/leaflet/leaflet-responsive-popup.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_leaflet_draw_js',
            $this->plugin_url . 'src/leaflet/leaflet-draw/leaflet.draw.js',
            array('oum_leaflet_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_global_leaflet_js',
            $this->plugin_url . 'src/leaflet/oum-global-leaflet.js',
            array('oum_leaflet_js', 'oum_leaflet_draw_js'),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_maplibre_js',
            $this->plugin_url . 'src/maplibre/maplibre-gl.js',
            array(),
            '5.24.0',
            true
        );
        wp_register_script(
            'oum_leaflet_maplibre_js',
            $this->plugin_url . 'src/maplibre/leaflet-maplibre-gl.js',
            array('oum_leaflet_js', 'oum_maplibre_js'),
            '0.1.4',
            true
        );
        wp_register_script(
            'oum_map_styles_js',
            $this->plugin_url . 'src/js/oum-map-styles.js',
            array('oum_leaflet_providers_js'),
            $this->plugin_version,
            true
        );

        // Register opening hours script (standalone module, no dependencies)
        // Must be registered before frontend_block_map_js since it depends on it
        wp_register_script(
            'oum_frontend_opening_hours_js',
            $this->plugin_url . 'src/js/frontend-opening-hours.js',
            array(),
            $this->plugin_version,
            true
        );

        // Pass locale weekday aliases so frontend validation accepts e.g. Do as well as Th
        wp_localize_script(
            'oum_frontend_opening_hours_js',
            'oum_opening_hours',
            LocationController::get_opening_hours_js_data()
        );

        // Register frontend block scripts
        wp_register_script(
            'oum_frontend_block_map_js',
            $this->plugin_url . 'src/js/frontend-block-map.js',
            array(
                'oum_frontend_opening_hours_js', // Opening hours module dependency
                'oum_leaflet_providers_js',
                'oum_leaflet_markercluster_js',
                'oum_leaflet_subgroups_js',
                'oum_leaflet_geosearch_js',
                'oum_leaflet_locate_js',
                'oum_leaflet_fullscreen_js',
                'oum_leaflet_search_js',
                'oum_leaflet_gesture_js',
                'oum_global_leaflet_js',
                'oum_map_styles_js'
            ),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_frontend_block_location_js',
            $this->plugin_url . 'src/js/frontend-block-location.js',
            array(
                'oum_leaflet_providers_js',
                'oum_leaflet_markercluster_js',
                'oum_leaflet_subgroups_js',
                'oum_leaflet_geosearch_js',
                'oum_leaflet_locate_js',
                'oum_leaflet_fullscreen_js',
                'oum_leaflet_search_js',
                'oum_leaflet_gesture_js',
                'oum_global_leaflet_js',
                'oum_map_styles_js'
            ),
            $this->plugin_version,
            true
        );
        wp_register_script(
            'oum_frontend_block_add_user_location_js',
            $this->plugin_url . 'src/js/frontend-block-add-user-location.js',
            array(
                'oum_leaflet_providers_js',
                'oum_leaflet_markercluster_js',
                'oum_leaflet_subgroups_js',
                'oum_leaflet_geosearch_js',
                'oum_leaflet_locate_js',
                'oum_leaflet_fullscreen_js',
                'oum_leaflet_search_js',
                'oum_leaflet_gesture_js',
                'oum_global_leaflet_js',
                'oum_map_styles_js'
            ),
            $this->plugin_version,
            true
        );

        wp_register_script(
            'oum_frontend_ajax_js',
            $this->plugin_url . 'src/js/frontend-ajax.js',
            array('jquery', 'oum_frontend_opening_hours_js'),
            $this->plugin_version,
            array(
                'strategy' => 'defer',
                'in_footer' => true
            )
        );
        wp_register_script(
            'oum_frontend_carousel_js',
            $this->plugin_url . 'src/js/frontend-carousel.js',
            array(),
            $this->plugin_version,
            array(
                'strategy' => 'defer',
                'in_footer' => true
            )
        );
        wp_register_script(
            'oum_frontend_gallery_js',
            $this->plugin_url . 'src/js/frontend-gallery.js',
            array('jquery', 'masonry', 'imagesloaded'),
            $this->plugin_version,
            array(
                'strategy' => 'defer',
                'in_footer' => true
            )
        );
        wp_register_script(
            'oum_frontend_vote_js',
            $this->plugin_url . 'src/js/frontend-vote.js',
            array('jquery'),
            $this->plugin_version,
            true
        );
    }

    /**
     * Output late-enqueued styles in the head
     * 
     * WordPress execution order:
     * 1. wp_enqueue_scripts (early) - we register styles here
     * 2. wp_head (outputs <head>) - styles are normally printed here
     * 3. Content rendering - shortcodes execute here and enqueue styles
     * 4. wp_footer
     * 
     * Since shortcodes render AFTER wp_head starts, styles enqueued in shortcode
     * callbacks would normally be output in the footer or not at all. This method
     * runs at the very end of wp_head (priority 999) to catch and output any
     * registered OUM styles that were enqueued during early content rendering.
     * 
     * @return void
     */
    public function output_late_enqueued_styles()
    {
        global $wp_styles;
        
        if (!($wp_styles instanceof \WP_Styles)) {
            return;
        }
        
        // List of OUM style handles that might be enqueued during shortcode rendering
        $oum_style_handles = array(
            'dashicons',
            'oum_frontend_css',
            'oum_leaflet_css',
            'oum_leaflet_gesture_css',
            'oum_leaflet_markercluster_css',
            'oum_leaflet_markercluster_default_css',
            'oum_leaflet_geosearch_css',
            'oum_leaflet_fullscreen_css',
            'oum_leaflet_locate_css',
            'oum_leaflet_search_css',
            'oum_leaflet_responsivepopup_css',
            'oum_maplibre_css',
        );
        
        // Output any registered OUM styles that haven't been output yet
        foreach ($oum_style_handles as $handle) {
            if (isset($wp_styles->registered[$handle])) {
                // Check if style was enqueued (in queue or already done)
                $is_queued = in_array($handle, $wp_styles->queue) || in_array($handle, $wp_styles->done);
                
                if ($is_queued) {
                    // Check if style was already output before we got here
                    $was_already_output = in_array($handle, $wp_styles->done);
                    
                    // Output style if it hasn't been output yet
                    if (!$was_already_output) {
                        // Output the style (do_item() will include inline styles automatically)
                        $wp_styles->do_item($handle);
                        $wp_styles->done[] = $handle; // Prevent duplicate output
                    }
                    
                    // Only manually output inline styles if the style was already output before we got here
                    // This handles cases where wp_add_inline_style() was called after the style was output
                    // If we called do_item() above, it already output the inline styles, so we skip this
                    if ($handle === 'oum_frontend_css' && $was_already_output) {
                        // Check if inline styles exist (added via wp_add_inline_style())
                        if (isset($wp_styles->registered[$handle]->extra['after']) && 
                            is_array($wp_styles->registered[$handle]->extra['after'])) {
                            // Output each inline style block
                            foreach ($wp_styles->registered[$handle]->extra['after'] as $inline_css) {
                                if (!empty($inline_css)) {
                                    echo '<style id="oum-inline-' . esc_attr($handle) . '">' . "\n";
                                    echo wp_kses_post($inline_css) . "\n";
                                    echo '</style>' . "\n";
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
