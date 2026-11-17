<?php
/**
 * Native Divi 5 integration and authenticated preview transport.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Registers native APIs only when Divi 5 provides them. */
class FooGallery_Divi5 {
	/** Register integration hooks without loading the legacy runtime. */
	public function __construct() {
		add_action( 'divi_module_library_modules_dependency_tree', array( $this, 'register_dependency' ) );
		add_action( 'divi_visual_builder_assets_before_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_foogallery_divi5_config', array( $this, 'config' ) );
		add_action( 'wp_ajax_foogallery_divi5_preview', array( $this, 'preview' ) );
	}

	/**
	 * Check whether the native registration API is available.
	 *
	 * @return bool
	 */
	public static function enabled() {
		return function_exists( 'et_builder_d5_enabled' ) && et_builder_d5_enabled()
			&& interface_exists( 'ET\\Builder\\Framework\\DependencyManagement\\Interfaces\\DependencyInterface' )
			&& class_exists( 'ET\\Builder\\Packages\\ModuleLibrary\\ModuleRegistration' )
			&& class_exists( 'ET\\Builder\\Packages\\Module\\Module' );
	}

	/**
	 * Strict positive decimal ID validation, without sign coercion or overflow.
	 *
	 * @param mixed $value Candidate ID.
	 * @return int Valid ID or zero.
	 */
	public static function gallery_id( $value ) {
		if ( ! is_string( $value ) && ! is_int( $value ) ) {
			return 0;
		}
		$value = (string) $value;
		if ( ! preg_match( '/^[1-9][0-9]*$/D', $value ) || strlen( $value ) > strlen( (string) PHP_INT_MAX ) ) {
			return 0;
		}
		$id = (int) $value;
		return (string) $id === $value ? $id : 0;
	}

	/**
	 * Check preview access separately from the terminating AJAX controller.
	 *
	 * @param array $request Unslashed request.
	 * @return int|false Authorized gallery ID or false.
	 */
	public static function authorize( $request ) {
		$nonce = isset( $request['nonce'] ) && is_string( $request['nonce'] ) ? $request['nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'foogallery-divi5' ) || ! ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) ) ) {
			return false;
		}
		$id   = self::gallery_id( $request['gallery_id'] ?? '' );
		$post = $id ? get_post( $id ) : null;
		return $post && FOOGALLERY_CPT_GALLERY === $post->post_type && 'trash' !== $post->post_status && current_user_can( 'read_post', $id ) ? $id : false;
	}

	/**
	 * Render through FooGallery's normal escaping and asset lifecycle.
	 *
	 * @param mixed $value Gallery ID.
	 * @return string Gallery output or empty for absent galleries.
	 */
	public static function gallery_markup( $value ) {
		$id   = self::gallery_id( $value );
		$post = self::get_gallery_post( $id );
		if ( ! $post || 'trash' === $post->post_status || ! is_post_status_viewable( $post->post_status ) ) {
			return '';
		}
		return self::render_gallery( $id );
	}

	/**
	 * Render a nonpublic gallery only for a reader in the authorized preview flow.
	 *
	 * @param mixed $value Gallery ID.
	 * @return string Gallery output or empty when it is not readable.
	 */
	private static function preview_gallery_markup( $value ) {
		$id   = self::gallery_id( $value );
		$post = self::get_gallery_post( $id );
		if ( ! $post || ! current_user_can( 'read_post', $id ) ) {
			return '';
		}

		ob_start();
		$loader = new FooGallery_Template_Loader();
		$loader->render_preview( $id );
		return ob_get_clean();
	}

	/**
	 * Find a valid, non-trash FooGallery post.
	 *
	 * @param int $id Gallery ID.
	 * @return WP_Post|null Gallery post or null.
	 */
	private static function get_gallery_post( $id ) {
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || FOOGALLERY_CPT_GALLERY !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}
		return $post;
	}

	/**
	 * Render a gallery after its caller has applied the appropriate visibility check.
	 *
	 * @param int $id Gallery ID.
	 * @return string Rendered gallery markup.
	 */
	private static function render_gallery( $id ) {
		ob_start();
		foogallery_render_gallery( $id );
		return ob_get_clean();
	}

	/**
	 * Add the module to Divi's dependency tree.
	 *
	 * @param object $tree Divi dependency tree.
	 */
	public function register_dependency( $tree ) {
		if ( self::enabled() ) {
			require_once __DIR__ . '/class-foogallery-divi5-module.php';
			$tree->add_dependency( new FooGallery_Divi5_Module() );
		}
	}

	/** Register the official app-window package shape. */
	public function enqueue() {
		if ( ! self::enabled() || ! class_exists( 'ET\\Builder\\VisualBuilder\\Assets\\PackageBuildManager' ) ) {
			return;
		}
		$config_url = add_query_arg(
			array(
				'action' => 'foogallery_divi5_config',
				'nonce'  => wp_create_nonce( 'foogallery-divi5' ),
			),
			admin_url( 'admin-ajax.php' )
		);
		\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
			array(
				'name'    => 'foogallery-divi5',
				'version' => FOOGALLERY_VERSION,
				'script'  => array(
					'src'                => add_query_arg( 'config', rawurlencode( $config_url ), FOOGALLERY_URL . 'js/admin-foogallery-divi5.js' ),
					'deps'               => array( 'divi-module-library', 'divi-vendor-wp-hooks', 'divi-module', 'divi-module-utils', 'divi-vendor-react' ),
					'enqueue_app_window' => true,
					'enqueue_top_window' => false,
				),
			)
		);
	}

	/** Send only readable gallery choices and public module metadata. */
	public function config() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below with the named nonce action.
		$request = wp_unslash( $_GET );
		$nonce   = isset( $request['nonce'] ) && is_string( $request['nonce'] ) ? $request['nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'foogallery-divi5' ) || ! ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' ) ) ) {
			wp_send_json_error( null, 403 );
		}
		$options = array( '' => array( 'label' => __( 'Select a gallery', 'foogallery' ) ) );
		foreach ( foogallery_get_all_galleries() as $gallery ) {
			if ( current_user_can( 'read_post', $gallery->ID ) ) {
				$options[ (string) $gallery->ID ] = array( 'label' => wp_strip_all_tags( $gallery->name ) );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read trusted bundled module metadata, never a URL.
		$metadata = json_decode( file_get_contents( __DIR__ . '/gallery/module.json' ), true );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Read the same trusted outline used by PHP registration.
		$conversion_outline = json_decode( file_get_contents( __DIR__ . '/gallery/conversion-outline.json' ), true );
		$metadata['title']  = __( 'FooGallery', 'foogallery' );
		$metadata['attributes']['galleryId']['settings']['innerContent']['item']['label'] = __( 'Gallery', 'foogallery' );
		wp_send_json_success(
			array(
				'metadata'          => $metadata,
				'conversionOutline' => $conversion_outline,
				'options'           => $options,
				'previewUrl'        => add_query_arg(
					array(
						'action' => 'foogallery_divi5_preview',
						'nonce'  => $nonce,
					),
					admin_url( 'admin-ajax.php' )
				),
				'placeholder'       => __( 'Please select a gallery to display.', 'foogallery' ),
			)
		);
	}

	/** Full document preview: WordPress prints and initializes gallery/PRO assets. */
	public function preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- authorize verifies nonce and capabilities.
		$id = self::authorize( wp_unslash( $_GET ) );
		if ( false === $id ) {
			status_header( 403 );
			echo '<p>' . esc_html__( 'Gallery preview unavailable.', 'foogallery' ) . '</p>';
			wp_die( '', '', array( 'response' => null ) );
		}
		nocache_headers();
		header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'Referrer-Policy: same-origin' );
		echo self::preview_document( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Complete escaped preview document.
		wp_die( '', '', array( 'response' => null ) );
	}

	/**
	 * Build a preview document after authorization; separate from request termination.
	 *
	 * @param int $id Authorized gallery ID.
	 * @return string Full preview document.
	 */
	public static function preview_document( $id ) {
		ob_start();
		// Render before the head so gallery-specific assets are already queued.
		$markup = self::preview_gallery_markup( $id );
		do_action( 'wp_enqueue_scripts' );
		echo '<!doctype html><html><head><meta charset="' . esc_attr( get_option( 'blog_charset' ) ) . '">';
		wp_head();
		echo '</head><body>';
		echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- FooGallery renders escaped markup.
		wp_footer();
		echo '</body></html>';
		return ob_get_clean();
	}
}
