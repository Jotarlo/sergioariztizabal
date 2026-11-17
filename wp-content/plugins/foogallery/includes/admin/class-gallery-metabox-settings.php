<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle adding the Settings metabox to a gallery
 */


if ( ! class_exists( 'FooGallery_Admin_Gallery_MetaBox_Settings' ) ) {

    class FooGallery_Admin_Gallery_MetaBox_Settings {

        /**
         * FooGallery_Admin_Gallery_MetaBox_Settings constructor.
         */
        function __construct() {
			add_action( 'add_meta_boxes_' . FOOGALLERY_CPT_GALLERY, array( $this, 'add_settings_metabox' ), 8 );
			add_action( 'wp_ajax_foogallery_layout_settings', array( $this, 'ajax_layout_settings' ) );
			add_action( 'wp_ajax_foogallery_layout_settings_batch', array( $this, 'ajax_layout_settings_batch' ) );

            //enqueue assets for the new settings tabs
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

            //get the section slug
			add_filter( 'foogallery_gallery_settings_metabox_section_slug', array( $this, 'get_section_slug' ) );

            //set default settings tab icons
            add_filter( 'foogallery_gallery_settings_metabox_section_icon', array( $this, 'add_section_icons') );
        }

		public function add_settings_metabox( $post ) {
			add_meta_box(
				'foogallery_settings',
				__( 'Gallery Settings', 'foogallery' ),
				array( $this, 'render_gallery_settings_metabox' ),
				FOOGALLERY_CPT_GALLERY,
				'normal',
				'high'
			);
		}

		public function render_gallery_settings_metabox( $post ) {
			$gallery = foogallery_admin_get_current_gallery( $post );
		
			//attempt to load default gallery settings from another gallery, as per FooGallery settings page
			$gallery->load_default_settings_if_new();
		
			$gallery = apply_filters( 'foogallery_render_gallery_settings_metabox', $gallery );
		
			if ( true === apply_filters( 'foogallery_should_render_gallery_settings_metabox', true, $gallery ) ) {
		
				$settings = new FooGallery_Admin_Gallery_MetaBox_Settings_Helper( $gallery );
				?><input type="hidden" id="foogallery_layout_settings_nonce" value="<?php echo esc_attr( wp_create_nonce( 'foogallery_layout_settings_' . $post->ID ) ); ?>" data-loading="<?php echo esc_attr__( 'Loading gallery layout settings…', 'foogallery' ); ?>" data-error="<?php echo esc_attr__( 'Could not load gallery layout settings.', 'foogallery' ); ?>" data-timeout="<?php echo esc_attr__( 'Gallery layout settings took too long to load.', 'foogallery' ); ?>" data-retry="<?php echo esc_attr__( 'Retry', 'foogallery' ); ?>" data-cancel="<?php echo esc_attr__( 'Cancel layout change', 'foogallery' ); ?>" data-wait="<?php echo esc_attr__( 'Wait for gallery layout settings to load before saving.', 'foogallery' ); ?>" /><?php
				?><input type="hidden" id="foogallery-shared-favorites" name="<?php echo esc_attr( FOOGALLERY_META_SETTINGS . '[favorite_ids]' ); ?>" value="<?php echo esc_attr( implode( ',', foogallery_favorites_from_settings( (array) $gallery->settings ) ) ); ?>" /><?php
				$settings->render_gallery_settings();
			}
		
			do_action( 'foogallery_after_render_gallery_settings_metabox', $gallery );
		}

		/**
		 * Validate a settings request and restore the gallery editor's post context.
		 *
		 * @return array Gallery and previous editor globals.
		 */
		private function ajax_layout_settings_context() {
			$post_id = isset( $_POST['foogallery_id'] ) && is_scalar( $_POST['foogallery_id'] ) ? absint( wp_unslash( $_POST['foogallery_id'] ) ) : 0;
			if ( ! $post_id || ! isset( $_POST['nonce'] ) || ! is_string( $_POST['nonce'] ) || ! check_ajax_referer( 'foogallery_layout_settings_' . $post_id, 'nonce', false ) ) {
				wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'foogallery' ) ), 403 );
			}
			if ( FOOGALLERY_CPT_GALLERY !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'foogallery' ) ), 403 );
			}
			$had_gallery = array_key_exists( 'current_foogallery_admin', $GLOBALS );
			$previous_gallery = $had_gallery ? $GLOBALS['current_foogallery_admin'] : null;
			global $post;
			$previous_post = $post;
			try {
				$gallery = FooGallery::get_by_id( $post_id );
			} catch ( Throwable $error ) {
				$this->restore_ajax_layout_settings_context( $previous_post, $previous_gallery, $had_gallery );
				throw $error;
			}
			if ( ! $gallery ) {
				$this->restore_ajax_layout_settings_context( $previous_post, $previous_gallery, $had_gallery );
				wp_send_json_error( array( 'message' => __( 'Gallery not found.', 'foogallery' ) ), 404 );
			}
			global $current_foogallery_admin;
			$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Metabox filters and field hooks use the edited post context.
			$current_foogallery_admin = $gallery;
			try {
				$gallery->load_default_settings_if_new();
				$gallery = apply_filters( 'foogallery_render_gallery_settings_metabox', $gallery );
				$allowed = apply_filters( 'foogallery_should_render_gallery_settings_metabox', true, $gallery );
			} catch ( Throwable $error ) {
				$this->restore_ajax_layout_settings_context( $previous_post, $previous_gallery, $had_gallery );
				throw $error;
			}
			if ( true !== $allowed ) {
				$this->restore_ajax_layout_settings_context( $previous_post, $previous_gallery, $had_gallery );
				wp_send_json_error( array( 'message' => __( 'Layout settings are unavailable.', 'foogallery' ) ), 403 );
			}
			return array( $gallery, $previous_post, $previous_gallery, $had_gallery );
		}

		/** Restore the globals used by gallery field hooks after an AJAX render. */
		private function restore_ajax_layout_settings_context( $previous_post, $previous_gallery, $had_gallery ) {
			global $post, $current_foogallery_admin;
			$post = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the request's post context.
			if ( $had_gallery ) {
				$current_foogallery_admin = $previous_gallery;
			} else {
				unset( $GLOBALS['current_foogallery_admin'] );
			}
		}

		/**
		 * Return one registered layout's settings markup to its gallery editor.
		 *
		 * @return void
		 */
		public function ajax_layout_settings() {
			list( $gallery, $previous_post, $previous_gallery, $had_gallery ) = $this->ajax_layout_settings_context();
			$slug = isset( $_POST['template'] ) && is_string( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '';
			try {
				$settings = new FooGallery_Admin_Gallery_MetaBox_Settings_Helper( $gallery );
				ob_start();
				try {
					$rendered = $settings->render_gallery_settings_for_template( $slug );
					$html = ob_get_clean();
				} catch ( Throwable $error ) {
					ob_end_clean();
					throw $error;
				}
			} finally {
				$this->restore_ajax_layout_settings_context( $previous_post, $previous_gallery, $had_gallery );
			}
			if ( ! $rendered ) {
				wp_send_json_error( array( 'message' => __( 'Invalid gallery layout.', 'foogallery' ) ), 400 );
			}
			wp_send_json_success( array( 'html' => $html ) );
		}

		/**
		 * Return all other registered layouts as strings for a background cache.
		 *
		 * @return void
		 */
		public function ajax_layout_settings_batch() {
			list( $gallery, $previous_post, $previous_gallery, $had_gallery ) = $this->ajax_layout_settings_context();
			$exclude = isset( $_POST['exclude'] ) && is_string( $_POST['exclude'] ) ? sanitize_key( wp_unslash( $_POST['exclude'] ) ) : '';
			try {
				$settings = new FooGallery_Admin_Gallery_MetaBox_Settings_Helper( $gallery );
				$layouts = $settings->render_gallery_settings_except_template( $exclude );
			} finally {
				$this->restore_ajax_layout_settings_context( $previous_post, $previous_gallery, $had_gallery );
			}
			if ( false === $layouts ) {
				wp_send_json_error( array( 'message' => __( 'Invalid gallery layout.', 'foogallery' ) ), 400 );
			}
			wp_send_json_success( array( 'layouts' => $layouts ) );
		}

        /***
         * Enqueue the assets needed by the settings
         * @param $hook_suffix
         */
        function enqueue_assets( $hook_suffix ){
			if( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ) ) ) {
				$screen = get_current_screen();
		
				if ( is_object( $screen ) && FOOGALLERY_CPT_GALLERY === $screen->post_type ){
		
					//spectrum needed for the colorpicker field
					$url = FOOGALLERY_URL . 'lib/spectrum/spectrum.js';
					wp_enqueue_script( 'foogallery-spectrum', $url, array('jquery'), FOOGALLERY_VERSION );
					$url = FOOGALLERY_URL . 'lib/spectrum/spectrum.css';
					wp_enqueue_style( 'foogallery-spectrum', $url, array(), FOOGALLERY_VERSION );
		
					// Register, enqueue scripts and styles here
					wp_enqueue_script( 'foogallery-admin-settings', FOOGALLERY_URL . 'js/foogallery.admin.min.js', array('jquery'), FOOGALLERY_VERSION );
					wp_enqueue_style( 'foogallery-admin-settings', FOOGALLERY_URL . 'css/foogallery.admin.min.css', array(), FOOGALLERY_VERSION );
				}
			}
		}

		/**
		 * Returns the section slug that can be used in the settings tabs
		 * @param $section
		 * @return string
		 */
		function get_section_slug( $section ) {
			switch ( $section ) {
				case __('General', 'foogallery'):
					return 'general';
				case __('Advanced', 'foogallery'):
					return 'advanced';
				case __('Appearance', 'foogallery'):
					return 'appearance';
				case __('Video', 'foogallery'):
					return 'video';
				case __('Hover Effects', 'foogallery'):
					return 'hover effects';
				case __('Captions', 'foogallery'):
					return 'captions';
				case __('Paging', 'foogallery'):
					return 'paging';
				case __('Social', 'foogallery'):
					return 'social';
			}
			return strtolower( $section );
		}

        /**
         * Returns the Dashicon that can be used in the settings tabs
         *
         * @param string $section_slug
         * @return string
        */
        function add_section_icons( $section_slug ) {
            switch ( $section_slug ) {
                case 'general':
                    return 'dashicons-format-gallery';
                case 'advanced':
                    return 'dashicons-admin-tools';
                case 'appearance':
                    return 'dashicons-admin-appearance';
                case 'video':
                    return 'dashicons-video-alt3';
				case 'hover-effects':
                    return 'dashicons-star-filled';
                case 'captions':
                    return 'dashicons-editor-quote';
                case 'paging':
                    return 'dashicons-admin-page';
				case 'social':
					return 'dashicons-share';
            }
            return $section_slug;
        }
    }
}
