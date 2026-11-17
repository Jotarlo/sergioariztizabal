<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor Compatibility Class
 * Date: 23/09/2019
 */
if ( ! class_exists( 'FooGallery_Elementor_Compatibility' ) ) {

    class FooGallery_Elementor_Compatibility {
        /** @var array|null Current editor data while handling Elementor's save hook. */
        private $saving_editor_data = null;

        function __construct() {
            add_action( 'elementor/editor/after_save', array( $this, 'save_elementor_data' ), 10, 2 );
            add_filter( 'foogallery_find_galleries_in_post', array( $this, 'find_galleries_in_post' ), 10, 2 );
            add_action( 'plugins_loaded', array( $this, 'init' ) );

            add_action( 'elementor/preview/enqueue_scripts', array( $this, 'enqueue_assets' ) );
            add_action( 'wp_ajax_foogallery_elementor_refresh_galleries', array( $this, 'ajax_refresh_galleries' ) );

            // Add attribute to foogallery image links to prevent Elementor from trasitioning to the image.
            if ( defined( 'ELEMENTOR_VERSION' ) ) {
                add_filter( 'foogallery_attachment_html_link_attributes', array( $this, 'add_elementor_prevent_transition' ), 10, 3 );
            }
        }

        function add_elementor_prevent_transition( $attributes, $args, $foogallery_attachment ) {
            $attributes['data-e-disable-page-transition'] = 'true';
            return $attributes;
        }

        function init() {
            if ( did_action( 'elementor/loaded' ) ) {
                add_action( 'elementor/widgets/widgets_registered', array( $this, 'init_widget' ) );
            }
        }

        public function init_widget() {

            // Include Widget files
            require_once( FOOGALLERY_PATH . 'includes/compatibility/elementor/class-elementor-foogallery-widget.php' );

            // Register widget
            \Elementor\Plugin::instance()->widgets_manager->register( new Elementor_FooGallery_Widget() );

        }

        public function enqueue_assets() {
            foogallery_enqueue_core_gallery_template_script();
            foogallery_enqueue_core_gallery_template_style();

	        wp_enqueue_script( 'foogallery-elementor', FOOGALLERY_URL . 'js/admin-foogallery-elementor.js', array('jquery'), FOOGALLERY_VERSION );

            // Pass admin URLs to JS.
            wp_localize_script( 'foogallery-elementor', 'FooGalleryElementor', [
                'editUrlBase' => admin_url( 'post.php?action=edit&post=' ),
                'newUrl'      => foogallery_admin_add_gallery_url(),
                'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
                'refreshNonce'=> wp_create_nonce( 'foogallery-elementor-refresh' ),
                'refreshError'=> esc_html__( 'Unable to refresh galleries. Please reload the page.', 'foogallery' ),
            ] );
        }

        public function ajax_refresh_galleries() {
            check_ajax_referer( 'foogallery-elementor-refresh', 'nonce' );

            if ( ! current_user_can( 'edit_posts' ) ) {
                wp_send_json_error( esc_html__( 'You are not allowed to refresh galleries.', 'foogallery' ), 403 );
            }

            if ( ! class_exists( 'Elementor_FooGallery_Widget' ) ) {
                require_once FOOGALLERY_PATH . 'includes/compatibility/elementor/class-elementor-foogallery-widget.php';
            }

            $options = Elementor_FooGallery_Widget::get_gallery_options();

            wp_send_json_success([
                'options' => $options,
            ]);
        }

        /**
         * Include persisted widgets when rebuilding references or saving via REST.
         *
         * @param array   $gallery_ids Gallery IDs found in post content.
         * @param WP_Post $post Saved post.
         * @return array Gallery IDs including Elementor widgets.
         */
        public function find_galleries_in_post( $gallery_ids, $post ) {
            // Elementor retains its data when a page switches back to WordPress.
            if ( null === $this->saving_editor_data && ! get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
                return $gallery_ids;
            }
            $data = null !== $this->saving_editor_data ? $this->saving_editor_data : get_post_meta( $post->ID, '_elementor_data', true );
            if ( is_string( $data ) ) {
                $data = json_decode( $data, true );
            }
            return array_merge( $gallery_ids, $this->find_galleries_recursive( $data ) );
        }

        function save_elementor_data( $post_id, $editor_data) {
            $this->saving_editor_data = is_array( $editor_data ) ? $editor_data : array();
            try {
                FooGallery_Gallery_Usage::sync_post( $post_id );
            } finally {
                $this->saving_editor_data = null;
            }
        }

        function find_galleries_recursive( $array ) {
            $found = array();
            if ( is_array( $array ) ) {
                foreach ( $array as $element ) {
                    if (array_key_exists('widgetType', $element) && $element['widgetType'] === 'shortcode') {

                        $shortcode = $element['settings']['shortcode'];

                        $gallery_ids = foogallery_extract_gallery_shortcodes($shortcode);

                        if (count($gallery_ids) > 0) {
                            $found = array_merge($found, array_keys($gallery_ids));
                        }
                    } else if ( array_key_exists( 'widgetType', $element) && $element['widgetType'] === 'foogallery' ) {

                        $found[] = intval( $element['settings']['gallery_id'] );

                    } else if ( array_key_exists( 'elements', $element ) && count( $element['elements'] ) > 0 ) {
                        $found = array_merge($found, $this->find_galleries_recursive( $element['elements'] ) );
                    }
                }
            }
            return $found;
        }
    }
}
