<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle adding the Items metabox to a gallery
 */
if ( ! class_exists( 'FooGallery_Admin_Gallery_MetaBox_Items' ) ) {

    class FooGallery_Admin_Gallery_MetaBox_Items {

		const PREVIEW_WINDOW_PAGE = 'foogallery-preview-window';

        /**
         * FooGallery_Admin_Gallery_MetaBox_Items constructor.
         */
        function __construct() {
			add_action( 'add_meta_boxes_' . FOOGALLERY_CPT_GALLERY, array( $this, 'add_items_metabox' ), 7 );

            //enqueue assets for the items metabox
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

			// Ajax call for generating a gallery preview
			add_action( 'wp_ajax_foogallery_preview', array( $this, 'ajax_gallery_preview' ) );

			//handle previews that have no attachments
			add_action( 'foogallery_template_no_attachments', array( $this, 'preview_no_attachments' ) );

			// A hidden admin page that shows the live preview in its own window.
			add_action( 'admin_menu', array( $this, 'register_preview_window_page' ) );
        }

		/**
		 * Register the hidden page used by the pop-out preview window.
		 */
		public function register_preview_window_page() {
			$hook = add_submenu_page(
				'',
				__( 'Gallery Preview', 'foogallery' ),
				__( 'Gallery Preview', 'foogallery' ),
				'edit_posts',
				self::PREVIEW_WINDOW_PAGE,
				'__return_null'
			);

			if ( $hook ) {
				add_action( 'load-' . $hook, array( $this, 'render_preview_window' ) );
			}
		}

		/**
		 * URL of the pop-out preview window for a gallery.
		 *
		 * @param int $foogallery_id Gallery ID.
		 * @return string
		 */
		private function preview_window_url( $foogallery_id ) {
			return add_query_arg(
				array(
					'page'          => self::PREVIEW_WINDOW_PAGE,
					'foogallery_id' => absint( $foogallery_id ),
				),
				admin_url( 'admin.php' )
			);
		}

		/**
		 * Output the pop-out preview window without the admin menu chrome.
		 *
		 * The editor sends its unsaved preview request to this window, which renders
		 * it with the same AJAX endpoint and front-end assets as the inline preview.
		 */
		public function render_preview_window() {
			$foogallery_id = isset( $_GET['foogallery_id'] ) ? absint( $_GET['foogallery_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view; access is checked below.
			if ( ! $foogallery_id || FOOGALLERY_CPT_GALLERY !== get_post_type( $foogallery_id ) || ! current_user_can( 'edit_post', $foogallery_id ) ) {
				wp_die( esc_html__( 'You do not have permission to preview this gallery.', 'foogallery' ) );
			}

			do_action( 'foogallery_enqueue_preview_dependencies' );
			foogallery_enqueue_core_gallery_template_style();
			foogallery_enqueue_core_gallery_template_script();
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style( 'foogallery-preview-viewports', FOOGALLERY_URL . 'css/admin-foogallery-preview-viewports.css', array(), FOOGALLERY_VERSION );
			wp_enqueue_style( 'foogallery-preview-window', FOOGALLERY_URL . 'css/admin-foogallery-preview-window.css', array( 'common', 'foogallery-preview-viewports', 'wp-color-picker' ), FOOGALLERY_VERSION );
			wp_enqueue_script( 'foogallery-preview-window', FOOGALLERY_URL . 'js/admin-foogallery-preview-window.js', array( 'jquery', 'wp-color-picker' ), FOOGALLERY_VERSION, true );
			wp_localize_script(
				'foogallery-preview-window',
				'FooGalleryPreviewWindow',
				array(
					'galleryId' => $foogallery_id,
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'strings'   => array(
						'background'   => __( 'Background', 'foogallery' ),
						'waiting'      => __( 'Waiting for the gallery editor…', 'foogallery' ),
						'noEditor'     => __( 'Open this gallery in the editor to see live changes.', 'foogallery' ),
						'loading'      => __( 'Updating…', 'foogallery' ),
						'live'         => __( 'Live', 'foogallery' ),
						'manual'       => __( 'Auto-update off', 'foogallery' ),
						'stale'        => __( 'Preview out of date', 'foogallery' ),
						'reconnecting' => __( 'Editor reloading…', 'foogallery' ),
						'failed'       => __( 'The preview could not be loaded. Try Refresh.', 'foogallery' ),
						'unsupported'  => __( 'This browser cannot sync the preview window.', 'foogallery' ),
					),
				)
			);

			$title = get_the_title( $foogallery_id );
			/* translators: %s: Gallery title. */
			iframe_header( esc_html( sprintf( __( 'Preview: %s', 'foogallery' ), '' !== $title ? $title : __( '(no title)', 'foogallery' ) ) ) );
			?>
			<div class="foogallery-preview-window">
				<div class="foogallery-preview-window-bar">
					<strong class="foogallery-preview-window-title"><?php echo esc_html( '' !== $title ? $title : __( '(no title)', 'foogallery' ) ); ?></strong>
					<span class="foogallery-preview-window-status" role="status"></span>
					<label class="foogallery-preview-auto-label"><input type="checkbox" role="switch" aria-checked="true" class="foogallery-preview-window-auto" checked /> <?php esc_html_e( 'Auto-update preview', 'foogallery' ); ?></label>
					<span class="foogallery-preview-window-width" aria-hidden="true"></span>
					<div class="foogallery-preview-window-viewports" role="group" aria-label="<?php esc_attr_e( 'Preview size', 'foogallery' ); ?>">
						<?php
						$viewports = array(
							'desktop' => array( 'dashicons-desktop', __( 'Desktop View', 'foogallery' ) ),
							'tablet'  => array( 'dashicons-tablet', __( 'Tablet View', 'foogallery' ) ),
							'mobile'  => array( 'dashicons-smartphone', __( 'Mobile View', 'foogallery' ) ),
						);
						foreach ( $viewports as $viewport => $button ) :
							?>
							<button type="button" class="button foogallery-preview-window-viewport<?php echo 'desktop' === $viewport ? ' is-active' : ''; ?>" data-viewport="<?php echo esc_attr( $viewport ); ?>" title="<?php echo esc_attr( $button[1] ); ?>" aria-label="<?php echo esc_attr( $button[1] ); ?>" aria-pressed="<?php echo 'desktop' === $viewport ? 'true' : 'false'; ?>">
								<span class="dashicons <?php echo esc_attr( $button[0] ); ?>" aria-hidden="true"></span>
							</button>
						<?php endforeach; ?>
					</div>
					<div class="foogallery-preview-window-background">
						<label for="foogallery-preview-window-background" class="screen-reader-text"><?php esc_html_e( 'Preview background color', 'foogallery' ); ?></label>
						<input type="text" id="foogallery-preview-window-background" value="#ffffff" data-default-color="#ffffff" />
					</div>
					<button type="button" class="button foogallery-preview-window-refresh">
						<span class="dashicons dashicons-update" aria-hidden="true"></span>
						<?php esc_html_e( 'Refresh', 'foogallery' ); ?>
					</button>
				</div>
				<div class="foogallery-preview-window-content">
					<div class="foogallery-preview-wrapper viewport-desktop">
						<div class="foogallery_preview_container"></div>
					</div>
				</div>
			</div>
			<?php
			iframe_footer();
			exit;
		}

		public function add_items_metabox( $post ) {
			add_meta_box(
				'foogallery_items',
				__( 'Gallery Items', 'foogallery' ) . '<span class="foogallery-gallery-items-metabox-title spinner is-active"></span>',
				array( $this, 'render_gallery_items_metabox' ),
				FOOGALLERY_CPT_GALLERY,
				'normal',
				'high'
			);
		}

		public function render_gallery_items_metabox( $post ) {
			$gallery = foogallery_admin_get_current_gallery( $post );

			//attempt to load default gallery settings from another gallery, as per FooGallery settings page
			$gallery->load_default_settings_if_new();

			//attempt to load default attachments from the settings page
			if ( $gallery->is_new() ) {
				$default_attachments = foogallery_get_setting( 'default_gallery_attachments' );
				if ( !empty($default_attachments) ) {
					$gallery->attachment_ids = explode( ',', $default_attachments );
				}
			}

			$this->prepare_invalid_datasource_for_recovery( $gallery );

			$mode = $gallery->get_meta( 'foogallery_items_view', 'manage' );

			if ( empty($mode) || $gallery->is_new() ) {
				$mode = 'manage';
			}
			$gallery_template = foogallery_get_gallery_template( $gallery->gallery_template );
			if ( ! empty( $gallery_template['promo_preview'] ) ) {
				$mode = 'preview';
			}
			$has_items = !$gallery->is_empty();

			do_action( 'foogallery_gallery_metabox_items', $gallery );
			?>
			<?php $this->render_invalid_datasource_notice( $gallery ); ?>
			<div class="foogallery-hidden foogallery-items-view-switch-container" data-view="<?php echo esc_attr( $mode ); ?>">
				<div class="foogallery-items-view-switch">
					<a href="#manage" data-value="manage" data-container=".foogallery-items-view-manage" class="<?php echo $mode==='manage' ? 'current' : ''; ?>"><?php esc_html_e('Manage Items', 'foogallery'); ?></a>
					<a href="#preview" data-value="preview" data-container=".foogallery-items-view-preview" class="<?php echo $mode==='preview' ? 'current' : ''; ?>"><?php esc_html_e('Gallery Preview', 'foogallery'); ?></a>
				</div>
				<div class="foogallery-preview-actions">
					<span class="foogallery-preview-toolbar-controls">
						<button type="button" class="foogallery-preview-refresh-btn" data-balloon="<?php esc_attr_e( 'Refresh preview', 'foogallery' ); ?>" data-refresh-balloon="<?php esc_attr_e( 'Refresh preview', 'foogallery' ); ?>" data-stale-balloon="<?php esc_attr_e( 'Refresh preview - out of date!', 'foogallery' ); ?>" data-balloon-pos="down" aria-label="<?php esc_attr_e( 'Refresh preview', 'foogallery' ); ?>">
							<span class="dashicons dashicons-update" aria-hidden="true"></span>
						</button>
						<span class="foogallery-preview-status screen-reader-text" role="status" aria-live="polite" data-manual="<?php esc_attr_e( 'Auto-update off', 'foogallery' ); ?>" data-stale="<?php esc_attr_e( 'Preview out of date', 'foogallery' ); ?>" data-failed="<?php esc_attr_e( 'Preview failed. Try Refresh preview.', 'foogallery' ); ?>"></span>
						<span class="foogallery-preview-icon-controls">
							<button type="button" class="foogallery-viewport-btn active" data-viewport="desktop" data-balloon="<?php esc_attr_e('Desktop View', 'foogallery'); ?>" data-balloon-pos="down" aria-label="<?php esc_attr_e('Desktop View', 'foogallery'); ?>" aria-pressed="true">
								<span class="dashicons dashicons-desktop" aria-hidden="true"></span>
							</button>
							<button type="button" class="foogallery-viewport-btn" data-viewport="tablet" data-balloon="<?php esc_attr_e('Tablet View', 'foogallery'); ?>" data-balloon-pos="down" aria-label="<?php esc_attr_e('Tablet View', 'foogallery'); ?>" aria-pressed="false">
								<span class="dashicons dashicons-tablet" aria-hidden="true"></span>
							</button>
							<button type="button" class="foogallery-viewport-btn" data-viewport="mobile" data-balloon="<?php esc_attr_e('Mobile View', 'foogallery'); ?>" data-balloon-pos="down" aria-label="<?php esc_attr_e('Mobile View', 'foogallery'); ?>" aria-pressed="false">
								<span class="dashicons dashicons-smartphone" aria-hidden="true"></span>
							</button>
							<button type="button" class="foogallery-preview-popout-btn" data-url="<?php echo esc_url( $this->preview_window_url( $post->ID ) ); ?>" data-balloon="<?php esc_attr_e( 'Open preview in a new window', 'foogallery' ); ?>" data-balloon-pos="down" aria-label="<?php esc_attr_e( 'Open preview in a new window', 'foogallery' ); ?>">
								<span class="dashicons dashicons-external" aria-hidden="true"></span>
							</button>
						</span>
						<span id="foogallery_preview_spinner" class="spinner"></span>
					</span>
					<label class="foogallery-preview-auto-label"><input type="checkbox" role="switch" aria-checked="true" class="foogallery-preview-auto" checked /> <?php esc_html_e( 'Auto-update preview', 'foogallery' ); ?></label>
				</div>
				<input type="hidden" id="foogallery_items_view_input" value="<?php echo esc_attr( $mode ); ?>" name="<?php echo esc_attr( FOOGALLERY_META_SETTINGS . '[foogallery_items_view]' ); ?>" /> <!-- phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -->
			</div>

			<div class="foogallery-items-view foogallery-items-view-manage <?php echo $mode==='manage' ? '' : 'hidden'; ?>">
				<input type="hidden" name="<?php echo esc_attr( FOOGALLERY_CPT_GALLERY ); ?>_nonce" id="<?php echo esc_attr( FOOGALLERY_CPT_GALLERY ); ?>_nonce" value="<?php echo esc_attr( wp_create_nonce( plugin_basename( FOOGALLERY_FILE ) ) ); ?>"/> <!-- phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -->
				<div class="foogallery-items-list">
					<div class="foogallery-items-empty <?php echo $has_items ? 'hidden' : ''; ?>" style="padding-top:20px; text-align: center">
						<p><?php esc_html_e('Your gallery is currently empty. Add items to see a preview.','foogallery'); ?></p>
					</div>
					<?php do_action( 'foogallery_gallery_metabox_items_list', $gallery ); ?>
				</div>
				<div class="foogallery-items-add <?php echo $has_items ? 'foogallery-hidden' : ''; ?>">
					<?php do_action( 'foogallery_gallery_metabox_items_add', $gallery ); ?>
				</div>
			</div>
			<div class="foogallery-items-view foogallery-items-view-preview <?php echo $mode==='preview' ? '' : 'foogallery-hidden'; ?>">
				<div class="foogallery-preview-paused" hidden>
					<span class="dashicons dashicons-external" aria-hidden="true"></span>
					<p><?php esc_html_e( 'Inline preview paused while the preview window is open.', 'foogallery' ); ?></p>
					<button type="button" class="button foogallery-preview-resume-btn"><?php esc_html_e( 'Resume here', 'foogallery' ); ?></button>
				</div>
				<!-- Wrap existing preview container -->
				<div class="foogallery-preview-wrapper viewport-desktop">
					<div class="foogallery_preview_container <?php echo $mode==='preview' ? '' : 'foogallery-preview-force-refresh'; ?>">
						<?php
						if ( ! empty( $gallery_template['promo_preview'] ) ) {
							$this->render_layout_promo_preview( $gallery_template );
						} elseif ( $has_items && 'preview' === $mode ) {
							$loader = new FooGallery_Template_Loader();
							$loader->render_preview( $gallery->ID );
						} else if ( $has_items && $mode === 'manage' ) {
							echo '<div style="padding:20px; text-align: center">';
							echo '<h3>' . esc_html__( 'Generating preview...', 'foogallery' ) . '</h3>';
							echo '</div>';
						} else {
							$this->render_empty_gallery_preview();
						}
						?>
					</div>
				</div>
                
				<div style="clear: both"></div>
				<?php wp_nonce_field( 'foogallery_preview', 'foogallery_preview', false ); ?>
			</div>
			<?php
		}

		private function prepare_invalid_datasource_for_recovery( $gallery ) {
			if ( ! is_object( $gallery ) || ! isset( $gallery->datasource_name ) ) {
				return;
			}

			$datasource_name = is_scalar( $gallery->datasource_name ) ? trim( (string) $gallery->datasource_name ) : '';
			if ( '' === $datasource_name ) {
				return;
			}

			$datasources = foogallery_gallery_datasources();
			if ( is_array( $datasources ) && array_key_exists( $datasource_name, $datasources ) ) {
				$this->prepare_invalid_datasource_value_for_recovery( $gallery );
				return;
			}

			$recovered_datasource_name = $this->recover_datasource_name( $datasource_name, $datasources );

			$gallery->_foogallery_invalid_datasource_name    = $datasource_name;
			$gallery->_foogallery_recovered_datasource_name  = $recovered_datasource_name;
			$gallery->_foogallery_recovered_datasource_label = $this->get_datasource_label( $recovered_datasource_name, $datasources );
			$gallery->datasource_name                        = $recovered_datasource_name;

			if ( foogallery_default_datasource() === $recovered_datasource_name || ! isset( $gallery->datasource_value ) || ! is_array( $gallery->datasource_value ) ) {
				$gallery->datasource_value = array();
			}
		}

		private function prepare_invalid_datasource_value_for_recovery( $gallery ) {
			if ( ! isset( $gallery->datasource_name ) || foogallery_default_datasource() === $gallery->datasource_name ) {
				return;
			}

			if ( ! isset( $gallery->datasource_value ) || empty( $gallery->datasource_value ) || is_array( $gallery->datasource_value ) ) {
				return;
			}

			if ( is_string( $gallery->datasource_value ) ) {
				$decoded_value = json_decode( stripslashes( $gallery->datasource_value ), true );
				if ( is_array( $decoded_value ) ) {
					$gallery->datasource_value = $decoded_value;
					return;
				}
			}

			$gallery->_foogallery_invalid_datasource_value   = true;
			$gallery->_foogallery_recovered_datasource_name  = $gallery->datasource_name;
			$gallery->_foogallery_recovered_datasource_label = $this->get_datasource_label( $gallery->datasource_name, foogallery_gallery_datasources() );
			$gallery->datasource_value                       = array();
		}

		private function recover_datasource_name( $datasource_name, $datasources ) {
			$candidates = array(
				str_replace( '-', '_', $datasource_name ),
				sanitize_key( str_replace( '-', '_', $datasource_name ) ),
			);

			if ( is_array( $datasources ) ) {
				foreach ( array_unique( array_filter( $candidates ) ) as $candidate ) {
					if ( array_key_exists( $candidate, $datasources ) ) {
						return $candidate;
					}
				}
			}

			$default_datasource = foogallery_default_datasource();
			if ( is_array( $datasources ) && array_key_exists( $default_datasource, $datasources ) ) {
				return $default_datasource;
			}

			if ( is_array( $datasources ) ) {
				foreach ( $datasources as $datasource_key => $datasource ) {
					return $datasource_key;
				}
			}

			return $default_datasource;
		}

		private function render_invalid_datasource_notice( $gallery ) {
			if ( ! is_object( $gallery ) ) {
				return;
			}

			$messages = array();

			if ( ! empty( $gallery->_foogallery_invalid_datasource_name ) ) {
				$messages[] = sprintf(
					/* translators: 1: invalid datasource value, 2: recovered datasource label, 3: recovered datasource value. */
					__( 'This gallery has an invalid datasource value "%1$s". FooGallery is temporarily showing the %2$s UI so you can recover it. Update the gallery to save the corrected datasource value "%3$s".', 'foogallery' ),
					$gallery->_foogallery_invalid_datasource_name,
					$gallery->_foogallery_recovered_datasource_label,
					$gallery->_foogallery_recovered_datasource_name
				);
			}

			if ( ! empty( $gallery->_foogallery_invalid_datasource_value ) ) {
				$messages[] = sprintf(
					/* translators: %s: recovered datasource label. */
					__( 'This gallery has an invalid datasource configuration value. FooGallery is temporarily showing the %s UI with empty source settings so you can recover it.', 'foogallery' ),
					$gallery->_foogallery_recovered_datasource_label
				);
			}

			if ( empty( $messages ) ) {
				return;
			}
			?>
			<div class="notice notice-error inline">
				<?php foreach ( $messages as $message ) { ?>
					<p><?php echo esc_html( $message ); ?></p>
				<?php } ?>
			</div>
			<?php
		}

		private function get_datasource_label( $datasource_name, $datasources ) {
			if ( is_array( $datasources ) && isset( $datasources[ $datasource_name ] ) && is_array( $datasources[ $datasource_name ] ) ) {
				$datasource = $datasources[ $datasource_name ];
				foreach ( array( 'menu', 'label', 'name' ) as $label_key ) {
					if ( ! empty( $datasource[ $label_key ] ) && is_scalar( $datasource[ $label_key ] ) ) {
						return (string) $datasource[ $label_key ];
					}
				}
			}

			return $datasource_name;
		}

		public function render_empty_gallery_preview( $message = '' ) {
			if ( empty($message) ) {
				$message = __( 'Please add media to your gallery to see a preview!', 'foogallery' );
			}
			echo '<div class="foogallery-preview-empty" style="padding:20px; text-align: center">';
			echo '<h3>' . esc_html__( 'Please add media to your gallery to see a preview!', 'foogallery' ) . '</h3>';
			echo '</div>';
		}

		/**
		 * Render a registered PRO example without using the gallery's media.
		 *
		 * @param array $template Registered gallery template and preview metadata.
		 */
		private function render_layout_promo_preview( $template ) {
			$preview = $template['promo_preview'];
			?>
			<div class="foogallery-layout-promo-preview">
				<div class="foogallery-layout-promo-preview-images">
					<?php foreach ( $preview['images'] as $image ) { ?>
						<figure>
							<img src="<?php echo esc_url( $image['src'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" />
						</figure>
					<?php } ?>
				</div>
				<p class="foogallery-layout-promo-preview-cta"><a class="button button-primary" href="<?php echo esc_url( $preview['demo_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php
					/* translators: %s: Gallery layout name. */
					echo esc_html( sprintf( __( 'Visit %s Demo', 'foogallery' ), $template['name'] ) );
					?> <span class="dashicons dashicons-external" aria-hidden="true"></span><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'foogallery' ); ?></span></a></p>
			</div>
			<?php
		}

		public function ajax_gallery_preview() {
			if ( ! check_ajax_referer( 'foogallery_preview', 'foogallery_preview_nonce', false ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Invalid security token.', 'foogallery' ) ),
					403
				);
			}

			$post_data = wp_unslash( $_POST );
			$foogallery_id = isset( $post_data['foogallery_id'] ) ? absint( $post_data['foogallery_id'] ) : 0;
			if ( ! $foogallery_id ) {
				wp_send_json_error(
					array( 'message' => __( 'Invalid gallery ID.', 'foogallery' ) ),
					400
				);
			}

			if ( ! current_user_can( 'edit_post', $foogallery_id ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Insufficient permissions.', 'foogallery' ) ),
					403
				);
			}

			$template = isset( $post_data['foogallery_template'] ) ? sanitize_key( $post_data['foogallery_template'] ) : foogallery_default_gallery_template();
			$template = apply_filters( 'foogallery_preview_template', $template, $foogallery_id );

			//check that the template supports previews
			$gallery_template = foogallery_get_gallery_template( $template );
			if ( ! empty( $gallery_template['promo_preview'] ) ) {
				$this->render_layout_promo_preview( $gallery_template );
			} elseif ( isset( $gallery_template['preview_support'] ) && true === $gallery_template['preview_support'] ) {

				global $foogallery_gallery_preview;

				$foogallery_gallery_preview = true;

				$attachment_ids = array();
				if ( isset( $post_data['foogallery_attachments'] ) ) {
					$attachment_ids = $post_data['foogallery_attachments'];
					if ( is_string( $attachment_ids ) ) {
						$attachment_ids = array_map( 'absint', array_filter( explode( ',', $attachment_ids ) ) );
					} else {
						$attachment_ids = array_map( 'absint', (array) $attachment_ids );
					}
				}

				$args = array(
					'template'       => $template,
					'attachment_ids' => $attachment_ids,
					'preview'        => true,
				);

				$args = $this->extract_preview_arguments( $args, $post_data, $template );

				$args = apply_filters( 'foogallery_preview_arguments', $args, $post_data, $template );
				$args = apply_filters( 'foogallery_preview_arguments-' . $template, $args, $post_data );

				if ( foogallery_is_debug() ) {
					echo '<pre style="display: none">' . esc_html__( 'Preview Debug Arguments:', 'foogallery' ) . '<br>' . esc_html( print_r( $args, true ) ) . '</pre> <!-- phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -->';
				}

				do_action( 'foogallery_preview_before_render', $foogallery_id, $args );

				$loader = new FooGallery_Template_Loader();
				$loader->render_preview( $foogallery_id, $args );

				do_action( 'foogallery_preview_after_render', $foogallery_id, $args );

				$foogallery_gallery_preview = false;

			} else {
				echo '<div style="padding:20px 50px 50px 50px; text-align: center">';
				echo '<h3>' . esc_html__( 'Preview not available!', 'foogallery' ) . '</h3>';
				echo esc_html__( 'Sorry, but this gallery layout does not support live previews. Please update the gallery in order to see what the gallery will look like.', 'foogallery' );
				echo '</div>';
			}

			wp_die();
		}

	    /**
         * Extract all the preview arguments from the post data
         *
	     * @param $args
	     * @param $post_data
	     * @param $template
	     *
	     * @return mixed
	     */
		private function extract_preview_arguments( $args, $post_data, $template ) {
		    if ( array_key_exists( FOOGALLERY_META_SETTINGS, $post_data ) ) {
			    $settings = $post_data[FOOGALLERY_META_SETTINGS];
				if ( array_key_exists( 'favorite_ids', $settings ) ) {
					$args['favorite_ids'] = implode( ',', foogallery_favorite_ids( $settings['favorite_ids'] ) );
				}
			    foreach ( $settings as $key => $value ) {
			        if ( strpos( $key, $template . '_' ) === 0 ) {
				        $args[ $this->str_replace_first( $template . '_', '', $key ) ] = $value;
			        }
			    }
		    }

			if ( array_key_exists( FOOGALLERY_META_SORT, $post_data ) ) {
				$args['sort'] = $this->sanitize_preview_sort( $post_data[FOOGALLERY_META_SORT] );
			}

            return $args;
		}

		/**
		 * Sanitize the preview sort value against the available sort options.
		 *
		 * @param string $sort The requested sort value.
		 *
		 * @return string
		 */
		private function sanitize_preview_sort( $sort ) {
			$sort = sanitize_text_field( $sort );

			if ( array_key_exists( $sort, foogallery_sorting_options() ) ) {
				return $sort;
			}

			return '';
		}

	    function str_replace_first($search, $replace, $subject) {
		    $pos = strpos($subject, $search);
		    if ($pos !== false) {
			    return substr_replace($subject, $replace, $pos, strlen($search));
		    }
		    return $subject;
	    }

		/**
		 * Handle gallery previews where there are no attachments
		 *
		 * @param $foogallery FooGallery
		 */
		public function preview_no_attachments( $foogallery ) {
			global $foogallery_gallery_preview;

			if ( isset( $foogallery_gallery_preview ) && true === $foogallery_gallery_preview ) {
				$message = '';
				if ( foogallery_default_datasource() !== $foogallery->datasource_name ) {
					$message = __( 'No items were found for this gallery.', 'foogallery' );
				}
				$this->render_empty_gallery_preview( $message );
			}
		}

		public function enqueue_assets() {
			$screen_id = foo_current_screen_id();

			//only include scripts if we on the foogallery add/edit page
			if ( FOOGALLERY_CPT_GALLERY === $screen_id ||
				'edit-' . FOOGALLERY_CPT_GALLERY === $screen_id ) {

				//enqueue any dependencies from extensions or gallery templates
				do_action( 'foogallery_enqueue_preview_dependencies' );
				//add core foogallery files for preview
				foogallery_enqueue_core_gallery_template_style();
				foogallery_enqueue_core_gallery_template_script();
				wp_enqueue_style( 'foogallery-preview-viewports', FOOGALLERY_URL . 'css/admin-foogallery-preview-viewports.css', array(), FOOGALLERY_VERSION );

				//make sure we have jquery UI sortable enqueued
				wp_enqueue_script( 'jquery-ui-sortable');
			}
		}
    }
}
