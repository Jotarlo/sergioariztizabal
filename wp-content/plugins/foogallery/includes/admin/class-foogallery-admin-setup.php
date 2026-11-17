<?php
/**
 * Small, package-independent guide for activating FooGallery PRO.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only setup state. The SDK and the running package remain authoritative.
 */
class FooGallery_Admin_Setup {
	/** How long a user's explicit trial request remains useful during email confirmation. */
	const TRIAL_INTENT_TTL = DAY_IN_SECONDS;
	/** First PRO package expected to include the shared setup page. */
	const SHARED_SETUP_MIN_PRO_VERSION = '3.3.30';

	/** Per-site, per-user trial intent key. */
	private static function trial_intent_key() {
		return 'foogallery_trial_intent_' . get_current_user_id();
	}

	/** Per-site, per-user installer feedback key. */
	private static function install_feedback_key() {
		return 'foogallery_install_feedback_' . get_current_user_id();
	}

	/** Nonce-protected server-side PRO installation entry. */
	public static function install_pro_url() {
		return admin_url( 'admin-post.php?action=foogallery_install_pro' );
	}

	/** Authorize only the current FooGallery Free product with a usable paid entitlement. */
	public static function can_install_pro() {
		if ( ! is_admin() || is_network_admin() || ! current_user_can( 'manage_options' ) || ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) || ! wp_is_file_mod_allowed( 'plugin_install' ) || ! function_exists( 'foogallery_fs' ) ) {
			return false;
		}
		$fs = foogallery_fs();
		if ( ! is_object( $fs ) || ! method_exists( $fs, 'is__premium_only' ) || $fs->is__premium_only() || ! method_exists( $fs, 'is_registered' ) || ! $fs->is_registered() || ! method_exists( $fs, 'has_release_on_freemius' ) || ! $fs->has_release_on_freemius() || ! method_exists( $fs, 'premium_plugin_basename' ) || ! method_exists( $fs, 'get_premium_slug' ) || 'foogallery-premium' !== $fs->get_premium_slug() ) {
			return false;
		}
		$basename = $fs->premium_plugin_basename();
		if ( 'foogallery-premium/foogallery.php' !== $basename || file_exists( WP_PLUGIN_DIR . '/' . $basename ) ) {
			return false;
		}
		$trial_plan = method_exists( $fs, 'is_trial' ) && $fs->is_trial() && method_exists( $fs, 'get_trial_plan' ) ? $fs->get_trial_plan() : false;
		$paid_trial = is_object( $trial_plan ) && method_exists( $trial_plan, 'is_free' ) && ! $trial_plan->is_free();
		$license    = method_exists( $fs, 'has_any_active_valid_license' ) && $fs->has_any_active_valid_license();
		return ( $paid_trial || $license ) && method_exists( $fs, 'can_use_premium_code' ) && $fs->can_use_premium_code();
	}

	/** Install the authenticated SDK product on the server; never accept a URL or module ID. */
	public static function handle_install_pro() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! self::can_install_pro() ) {
			wp_die( esc_html__( 'PRO installation is unavailable for this site or user.', 'foogallery' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'foogallery_install_pro' );
		$code = 'install_failed';
		try {
			$fs = foogallery_fs();
			if ( $fs instanceof Freemius && class_exists( 'FS_Plugin_Updater' ) ) {
				// The SDK supplies the package URL. This temporary hook handles its storage
				// redirect without weakening the Freemius transport guard for any request.
				add_filter( 'upgrader_pre_download', array( __CLASS__, 'download_pro_package' ), 10, 4 );
				add_filter( 'upgrader_source_selection', array( __CLASS__, 'validate_pro_source' ), 20, 3 );
				try {
					$result = FS_Plugin_Updater::instance( $fs )->install_and_activate_plugin();
				} finally {
					remove_filter( 'upgrader_pre_download', array( __CLASS__, 'download_pro_package' ), 10 );
					remove_filter( 'upgrader_source_selection', array( __CLASS__, 'validate_pro_source' ), 20 );
				}
				if ( is_array( $result ) && ! isset( $result['message'] ) ) {
					$basename = $fs->premium_plugin_basename();
					if ( is_string( $basename ) && '' !== $basename && 0 === validate_file( $basename ) && file_exists( WP_PLUGIN_DIR . '/' . $basename ) && is_plugin_active( $basename ) ) {
						$pro_file   = WP_PLUGIN_DIR . '/' . $basename;
						$pro_folder = dirname( $pro_file );
						$version    = get_file_data( $pro_file, array( 'version' => 'Version' ), 'plugin' );
						$has_setup  = file_exists( $pro_folder . '/includes/admin/class-foogallery-admin-setup.php' ) && isset( $version['version'] ) && version_compare( $version['version'], self::SHARED_SETUP_MIN_PRO_VERSION, '>=' );
						wp_safe_redirect( $has_setup ? foogallery_admin_url_for_page( 'foogallery-license-trial' ) : admin_url( 'plugins.php?activate=true' ) );
						exit;
					}
					$code = 'activation_failed';
				}
				if ( is_array( $result ) && isset( $result['code'] ) && is_string( $result['code'] ) ) {
					$code = sanitize_key( $result['code'] );
				}
			}
		} catch ( Throwable $error ) {
			// The SDK can report transport or filesystem failures without exposing details here.
			$code = 'install_exception';
		}
		set_transient( self::install_feedback_key(), $code, 5 * MINUTE_IN_SECONDS );
		$failure_url = is_plugin_active( plugin_basename( FOOGALLERY_FILE ) ) ? foogallery_admin_url_for_page( 'foogallery-license-trial' ) : admin_url( 'plugins.php' );
		wp_safe_redirect( $failure_url );
		exit;
	}

	/** Require an unambiguous HTTPS URL on the expected exact host.
	 *
	 * @param mixed  $url  SDK package or HTTP Location URL.
	 * @param string $host Allowed hostname.
	 * @return bool
	 */
	private static function safe_installer_url( $url, $host ) {
		if ( ! is_string( $url ) || ! preg_match( '~\Ahttps://[a-z0-9.-]+(?:/|\?|\z)~i', $url ) || preg_match( '/[\x00-\x20\\\\]/', $url ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		return is_array( $parts ) && isset( $parts['scheme'], $parts['host'] )
			&& 'https' === strtolower( $parts['scheme'] ) && strtolower( $parts['host'] ) === $host
			&& ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) && ! isset( $parts['port'] ) && ! isset( $parts['fragment'] )
			&& false !== wp_http_validate_url( $url );
	}

	/** Stream an authenticated FooGallery SDK package through one checked storage redirect.
	 *
	 * This filter is installed only around the authorized SDK install call. All other
	 * upgrader packages and SDK requests retain their native transport behavior.
	 *
	 * @param false|string|WP_Error $reply      Prior filter result.
	 * @param string                $package    SDK package URL.
	 * @param WP_Upgrader           $upgrader   Running upgrader.
	 * @param array                 $hook_extra Upgrader action and type.
	 * @return false|string|WP_Error
	 */
	public static function download_pro_package( $reply, $package, $upgrader, $hook_extra ) {
		if ( false !== $reply || ! self::can_install_pro() || ! ( $upgrader instanceof Plugin_Upgrader ) || ! isset( $upgrader->skin->options['extra']['slug'] ) || ! is_array( $hook_extra ) || ! isset( $hook_extra['type'], $hook_extra['action'] ) || 'plugin' !== $hook_extra['type'] || 'install' !== $hook_extra['action'] ) {
			return $reply;
		}
		$fs = foogallery_fs();
		if ( ! method_exists( $fs, 'get_premium_slug' ) || $fs->get_premium_slug() !== $upgrader->skin->options['extra']['slug'] || ! method_exists( $fs, 'get_site' ) ) {
			return $reply;
		}
		$site  = $fs->get_site();
		$parts = is_string( $package ) ? wp_parse_url( $package ) : false;
		if ( ! is_object( $site ) || ! isset( $site->id ) || ! ctype_digit( (string) $site->id ) || ! self::safe_installer_url( $package, 'api.freemius.com' ) || ! is_array( $parts ) || ! isset( $parts['path'] ) || ! preg_match( '~\A/v1/installs/([1-9][0-9]*)/updates/[1-9][0-9]*\.zip\z~', $parts['path'], $matches ) || (string) $site->id !== $matches[1] ) {
			return new WP_Error( 'foogallery_pro_package_url', 'Invalid FooGallery PRO package URL.' );
		}

		// A streamed first request avoids holding a ZIP in PHP memory when the API
		// serves it directly. WordPress HTTP performs TLS and unsafe-host checks.
		$url = $package;
		for ( $hop = 0; $hop < 2; $hop++ ) {
			$temp = wp_tempnam( 'foogallery-pro.zip' );
			if ( ! $temp ) {
				return new WP_Error( 'foogallery_pro_temp_file', 'Could not create a temporary package file.' );
			}
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'     => 300,
					'stream'      => true,
					'filename'    => $temp,
					'sslverify'   => true,
					'redirection' => 0,
				)
			);
			if ( is_wp_error( $response ) ) {
				wp_delete_file( $temp );
				return new WP_Error( 'foogallery_pro_download_failed', 'FooGallery PRO could not be downloaded.' );
			}
			$status = wp_remote_retrieve_response_code( $response );
			if ( 200 === $status ) {
				if ( ! file_exists( $temp ) || 0 === filesize( $temp ) ) {
					wp_delete_file( $temp );
					return new WP_Error( 'foogallery_pro_empty_package', 'FooGallery PRO returned an empty package.' );
				}
				return $temp;
			}
			wp_delete_file( $temp );
			if ( 0 === $hop && in_array( $status, array( 302, 303, 307, 308 ), true ) ) {
				$location = wp_remote_retrieve_header( $response, 'location' );
				if ( self::safe_installer_url( $location, 's3-us-west-2.amazonaws.com' ) ) {
					$url = $location;
					continue;
				}
			}
			return new WP_Error( 'foogallery_pro_redirect_blocked', 'FooGallery PRO package redirect was blocked.' );
		}
		return new WP_Error( 'foogallery_pro_redirect_blocked', 'FooGallery PRO package redirect was blocked.' );
	}

	/** Reject a ZIP whose extracted main file is not the FooGallery PRO plugin.
	 *
	 * @param string|WP_Error $source        Extracted plugin directory.
	 * @param string          $remote_source Temporary extraction directory.
	 * @param WP_Upgrader     $upgrader      Running upgrader.
	 * @return string|WP_Error
	 */
	public static function validate_pro_source( $source, $remote_source, $upgrader ) {
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		if ( ! ( $upgrader instanceof Plugin_Upgrader ) || ! isset( $upgrader->skin->options['extra']['slug'] ) || 'foogallery-premium' !== $upgrader->skin->options['extra']['slug'] || ! self::can_install_pro() ) {
			return $source;
		}
		$folder = untrailingslashit( $source );
		$file   = trailingslashit( $folder ) . 'foogallery.php';
		if ( 'foogallery-premium' !== basename( $folder ) || ! is_file( $file ) ) {
			return new WP_Error( 'foogallery_pro_unexpected_package', 'Downloaded package is not FooGallery PRO.' );
		}
		$headers = get_file_data(
			$file,
			array(
				'name'   => 'Plugin Name',
				'domain' => 'Text Domain',
			),
			'plugin'
		);
		if ( 'FooGallery (Premium)' !== $headers['name'] || 'foogallery' !== $headers['domain'] ) {
			return new WP_Error( 'foogallery_pro_unexpected_package', 'Downloaded package is not FooGallery PRO.' );
		}
		return $source;
	}

	/** Safe administrator-facing feedback with a manual recovery path.
	 *
	 * @param string $code SDK or local failure code.
	 * @return string
	 */
	private static function install_feedback_message( $code ) {
		if ( in_array( $code, array( 'unable_to_connect_to_filesystem', 'fs_unavailable', 'mkdir_failed', 'could_not_create_dir' ), true ) ) {
			return __( 'PRO could not be installed because WordPress could not write plugin files. Check filesystem access, then retry or upload the PRO ZIP manually.', 'foogallery' );
		}
		if ( in_array( $code, array( 'http_request_failed', 'download_failed', 'no_package', 'foogallery_pro_download_failed', 'foogallery_pro_redirect_blocked', 'foogallery_pro_empty_package', 'foogallery_pro_package_url' ), true ) ) {
			return __( 'PRO could not be downloaded. Check this server’s connection to Freemius, then retry or upload the PRO ZIP manually.', 'foogallery' );
		}
		if ( 'foogallery_pro_unexpected_package' === $code ) {
			return __( 'The downloaded package did not match FooGallery PRO. Retry the installation or upload the PRO ZIP manually.', 'foogallery' );
		}
		return __( 'PRO could not be installed or activated. Check WordPress plugin permissions and server connectivity, then retry or upload the PRO ZIP manually.', 'foogallery' );
	}

	/** Nonce-protected local entry to the trial connection flow. */
	public static function start_trial_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=foogallery_start_trial' ), 'foogallery_start_trial' );
	}

	/** Whether the SDK still offers plan selection for this connected site. */
	public static function trial_selection_available() {
		$fs = function_exists( 'foogallery_fs' ) ? foogallery_fs() : false;
		if ( ! is_object( $fs ) || ! method_exists( $fs, 'is_registered' ) ) {
			return false;
		}
		if ( ! $fs->is_registered() ) {
			// Connect first; the SDK will decide this site's eligibility afterward.
			return true;
		}
		return method_exists( $fs, 'has_trial_plan' ) && $fs->has_trial_plan()
			&& method_exists( $fs, 'is_trial_utilized' ) && ! $fs->is_trial_utilized()
			&& method_exists( $fs, 'is_paying_or_trial' ) && ! $fs->is_paying_or_trial();
	}

	/** Redirect an explicit trial request through SDK connection before pricing. */
	public static function handle_start_trial() {
		if ( ! is_admin() || is_network_admin() || ! current_user_can( 'manage_options' ) || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			wp_die( esc_html__( 'You cannot start this trial from here.', 'foogallery' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'foogallery_start_trial' );
		set_transient( self::trial_intent_key(), 1, self::TRIAL_INTENT_TTL );
		$destination = self::trial_intent_destination();
		wp_safe_redirect( $destination );
		exit;
	}

	/** Choose an SDK route without changing trial or license state. */
	public static function trial_intent_destination() {
		$setup = foogallery_admin_url_for_page( 'foogallery-license-trial' );
		$fs    = function_exists( 'foogallery_fs' ) ? foogallery_fs() : false;
		if ( ! is_object( $fs ) || ! method_exists( $fs, 'is_registered' ) ) {
			return $setup;
		}
		if ( $fs->is_registered() ) {
			if ( ( method_exists( $fs, 'is_trial' ) && $fs->is_trial() ) || ( method_exists( $fs, 'has_any_active_valid_license' ) && $fs->has_any_active_valid_license() ) || ( method_exists( $fs, 'can_use_premium_code' ) && $fs->can_use_premium_code() ) ) {
				delete_transient( self::trial_intent_key() );
				return $setup;
			}
			if ( ! self::trial_selection_available() ) {
				delete_transient( self::trial_intent_key() );
				return $setup;
			}
			$url = method_exists( $fs, 'get_trial_url' ) ? self::safe_sdk_url( $fs->get_trial_url() ) : '';
			if ( '' !== $url ) {
				delete_transient( self::trial_intent_key() );
				return $url;
			}
			return $setup;
		}
		if ( method_exists( $fs, 'is_pending_activation' ) && $fs->is_pending_activation() ) {
			return $setup;
		}
		$url = method_exists( $fs, 'is_anonymous' ) && $fs->is_anonymous() && method_exists( $fs, 'get_reconnect_url' ) ? $fs->get_reconnect_url() : ( method_exists( $fs, 'get_activation_url' ) ? $fs->get_activation_url() : '' );
		$url = self::safe_sdk_url( $url );
		return '' !== $url ? $url : $setup;
	}

	/** Recover only the old, unsigned checkout return that cannot run SDK sync. */
	public static function redirect_unregistered_trial_return() {
		$url = self::unregistered_trial_return_url();
		if ( '' !== $url ) {
			wp_safe_redirect( $url );
			exit;
		}
	}

	/** Return the shared page only for the precise unsigned, unregistered SDK callback. */
	public static function unregistered_trial_return_url() {
		global $pagenow;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Count only identifies an unsigned SDK return; no action is dispatched.
		if ( ! is_admin() || is_network_admin() || wp_doing_ajax() || ! current_user_can( 'manage_options' ) || 'edit.php' !== $pagenow || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] || 3 !== count( $_GET ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Exact, read-only route recovery; no SDK action is dispatched.
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Exact, read-only route recovery.
		$post_type = isset( $_GET['post_type'] ) && is_string( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Exact, read-only route recovery.
		$action = isset( $_GET['fs_action'] ) && is_string( $_GET['fs_action'] ) ? sanitize_key( wp_unslash( $_GET['fs_action'] ) ) : '';
		if ( 'foogallery' !== $page || FOOGALLERY_CPT_GALLERY !== $post_type || 'foogallery_sync_license' !== $action ) {
			return '';
		}
		$fs = function_exists( 'foogallery_fs' ) ? foogallery_fs() : false;
		if ( ! is_object( $fs ) || ! method_exists( $fs, 'is_registered' ) || $fs->is_registered() ) {
			return '';
		}
		return add_query_arg( 'foogallery_return', 'trial', foogallery_admin_url_for_page( 'foogallery-license-trial' ) );
	}
	/**
	 * Hide SDK success guidance already covered by the shared setup page.
	 * Freemius keeps ownership of the notice queue and dismissal state.
	 *
	 * @param bool  $show   Whether the SDK would display the notice.
	 * @param array $notice Freemius notice data, including manager, ID and type.
	 * @return bool
	 */
	public static function filter_sdk_admin_notice( $show, $notice ) {
		global $pagenow;

		if ( true !== $show || ! is_array( $notice ) || ! is_admin() || is_network_admin() || wp_doing_ajax() || 'edit.php' !== $pagenow ) {
			return $show;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice visibility on the current page.
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice visibility on the current page.
		$post_type = isset( $_GET['post_type'] ) && is_string( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		$screen    = get_current_screen();
		if ( 'foogallery-license-trial' !== $page || FOOGALLERY_CPT_GALLERY !== $post_type || ! $screen || 'foogallery_page_foogallery-license-trial' !== $screen->id ) {
			return $show;
		}

		if ( ! isset( $notice['manager_id'], $notice['id'], $notice['type'] ) || 'foogallery' !== $notice['manager_id'] || 'success' !== $notice['type'] ) {
			return $show;
		}

		// These SDK lifecycle confirmations repeat the page's connection, trial, license and PRO setup guidance.
		return ! in_array( $notice['id'], array( 'activation_complete', 'premium_activated', 'plan_upgraded', 'trial_started', 'license_activated' ), true );
	}

	/**
	 * Redirect a saved License & Trial link while the SDK owns activation mode.
	 * WordPress checks plugin page access before admin_init, so run on admin_menu.
	 *
	 * @return void
	 */
	public static function redirect_activation_mode_bookmark() {
		$url = self::activation_mode_redirect_url();
		if ( '' !== $url ) {
			wp_safe_redirect( $url );
			exit;
		}
	}

	/**
	 * Decide whether the exact saved setup URL needs the SDK activation page.
	 * The trial return hint is passive navigation, never an SDK action.
	 *
	 * @return string Safe local activation URL, or an empty string.
	 */
	public static function activation_mode_redirect_url() {
		global $pagenow;
		if ( ! is_admin() || is_network_admin() || ! current_user_can( 'manage_options' ) || wp_doing_ajax() || 'edit.php' !== $pagenow || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation decision; action requests are excluded.
		if ( ! isset( $_GET['page'], $_GET['post_type'] ) || ! in_array( count( $_GET ), array( 2, 3 ), true ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact literal comparison of a passive navigation hint.
		if ( 3 === count( $_GET ) && ( ! isset( $_GET['foogallery_return'] ) || ! is_string( $_GET['foogallery_return'] ) || 'trial' !== wp_unslash( $_GET['foogallery_return'] ) ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation decision.
		$page = is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation decision.
		$post_type = is_string( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		if ( 'foogallery-license-trial' !== $page || FOOGALLERY_CPT_GALLERY !== $post_type || ! function_exists( 'foogallery_fs' ) ) {
			return '';
		}
		$fs = foogallery_fs();
		if ( ! is_object( $fs ) || ! method_exists( $fs, 'is__premium_only' ) || ! method_exists( $fs, 'is_activation_mode' ) || ! $fs->is_activation_mode() || ! method_exists( $fs, 'get_activation_url' ) ) {
			return '';
		}
		$premium = $fs->is__premium_only();
		if ( $premium ? ( method_exists( $fs, 'can_use_premium_code' ) && $fs->can_use_premium_code() ) : ( ! method_exists( $fs, 'is_registered' ) || $fs->is_registered() ) ) {
			return '';
		}
		$url          = self::safe_sdk_url( $premium ? $fs->get_activation_url( array( 'require_license' => 'true' ) ) : $fs->get_activation_url() );
		$admin_parts  = wp_parse_url( admin_url() );
		$target_parts = wp_parse_url( $url );
		if ( '' === $url || ! is_array( $admin_parts ) || ! is_array( $target_parts ) || ! isset( $admin_parts['host'], $admin_parts['scheme'], $target_parts['host'], $target_parts['scheme'] ) || $admin_parts['host'] !== $target_parts['host'] || $admin_parts['scheme'] !== $target_parts['scheme'] || isset( $target_parts['user'] ) || isset( $target_parts['pass'] ) || ( isset( $admin_parts['port'] ) ? $admin_parts['port'] : null ) !== ( isset( $target_parts['port'] ) ? $target_parts['port'] : null ) || '' === wp_validate_redirect( $url, '' ) ) {
			return '';
		}
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( is_string( $query ) ) {
			parse_str( $query, $params );
			if ( isset( $params['page'] ) && 'foogallery-license-trial' === $params['page'] ) {
				return '';
			}
		}
		return $url;
	}

	/**
	 * After SDK connection succeeds, show the shared setup state and next step.
	 *
	 * @param string $url SDK's original after-connect URL.
	 * @return string
	 */
	public static function after_successful_connect_url( $url ) {
		if ( ! is_admin() || is_network_admin() || ! current_user_can( 'manage_options' ) || ! function_exists( 'foogallery_fs' ) ) {
			return $url;
		}
		$fs = foogallery_fs();
		if ( ! is_object( $fs ) || ! method_exists( $fs, 'is_registered' ) || ! $fs->is_registered() ) {
			return $url;
		}
		if ( get_transient( self::trial_intent_key() ) ) {
			return self::trial_intent_destination();
		}
		return foogallery_admin_url_for_page( 'foogallery-license-trial' );
	}

	/** Keep a registered PRO administrator on the requested shared setup page.
	 *
	 * Freemius otherwise redirects a newly activated PRO package to its configured
	 * first-time Help page during admin_init, before the shared page loads.
	 *
	 * @param bool $redirect Whether the SDK would perform its activation redirect.
	 * @return bool
	 */
	public static function filter_sdk_redirect_on_activation( $redirect ) {
		global $pagenow;

		if ( true !== $redirect || ! is_admin() || is_network_admin() || wp_doing_ajax() || ! current_user_can( 'manage_options' ) || 'edit.php' !== $pagenow || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return $redirect;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation decision; SDK action requests are excluded.
		if ( 2 !== count( $_GET ) || ! isset( $_GET['page'], $_GET['post_type'] ) || ! is_string( $_GET['page'] ) || ! is_string( $_GET['post_type'] ) ) {
			return $redirect;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Exact read-only destination check.
		$page = sanitize_key( wp_unslash( $_GET['page'] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Exact read-only destination check.
		$post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) );
		if ( 'foogallery-license-trial' !== $page || FOOGALLERY_CPT_GALLERY !== $post_type || ! function_exists( 'foogallery_fs' ) ) {
			return $redirect;
		}
		$fs = foogallery_fs();
		return is_object( $fs ) && method_exists( $fs, 'is__premium_only' ) && $fs->is__premium_only() && method_exists( $fs, 'is_registered' ) && $fs->is_registered() ? false : $redirect;
	}

	/**
	 * Render shared navigation for the setup page and the optional PRO overview.
	 *
	 * @param string $active Current view: setup or overview.
	 * @return void
	 */
	public static function render_navigation( $active = 'setup' ) {
		if ( in_array( self::snapshot()['phase'], array( 'ready', 'trial_expired' ), true ) || ! self::overview_available() ) {
			return;
		}
		?>
		<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'License and trial navigation', 'foogallery' ); ?>">
			<a class="nav-tab<?php echo 'setup' === $active ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( foogallery_admin_url_for_page( 'foogallery-license-trial' ) ); ?>"><?php esc_html_e( 'Setup', 'foogallery' ); ?></a>
			<a class="nav-tab<?php echo 'overview' === $active ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( foogallery_admin_url_for_page( 'foogallery-trial-overview' ) ); ?>"><?php esc_html_e( 'PRO trial details', 'foogallery' ); ?></a>
		</nav>
		<?php
	}

	/** Whether the existing PRO overview has a usable registered callback. */
	public static function overview_available() {
		return class_exists( 'FooGallery_Admin_Trial_Experience' )
			&& is_callable( array( 'FooGallery_Admin_Trial_Experience', 'marketing_allowed' ) )
			&& FooGallery_Admin_Trial_Experience::marketing_allowed()
			&& is_callable( array( 'FooGallery_Admin_Trial_Experience', 'render_for_setup' ) )
			&& function_exists( 'get_plugin_page_hookname' )
			&& isset( $GLOBALS['_registered_pages'][ get_plugin_page_hookname( 'foogallery-trial-overview', foogallery_admin_menu_parent_slug() ) ] );
	}

	/**
	 * Use the SDK's native key entry UI for a PRO build without runtime access.
	 * The SDK owns the form, nonce, validation, and error messages.
	 *
	 * @return string Activation URL, or an empty string when unavailable.
	 */
	public static function license_activation_url() {
		if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'foogallery_fs' ) ) {
			return '';
		}
		$fs = foogallery_fs();
		if ( ! is_object( $fs ) || ! method_exists( $fs, 'is__premium_only' ) || ! $fs->is__premium_only() || ( method_exists( $fs, 'can_use_premium_code' ) && $fs->can_use_premium_code() ) ) {
			return '';
		}
		if ( method_exists( $fs, 'is_registered' ) && $fs->is_registered() ) {
			return method_exists( $fs, 'get_account_url' ) ? self::safe_sdk_url( $fs->get_account_url( false, array( 'activate_license' => 'true' ) ) ) : '';
		}
		$params = array( 'require_license' => 'true' );
		if ( method_exists( $fs, 'is_anonymous' ) && $fs->is_anonymous() && method_exists( $fs, 'get_reconnect_url' ) ) {
			return self::safe_sdk_url( $fs->get_reconnect_url( $params ) );
		}
		return method_exists( $fs, 'get_activation_url' ) ? self::safe_sdk_url( $fs->get_activation_url( $params ) ) : '';
	}

	/** Return the SDK's authenticated PRO download route only for recognized Free access. */
	public static function premium_download_url() {
		if ( ! current_user_can( 'manage_options' ) || ! function_exists( 'foogallery_fs' ) ) {
			return '';
		}
		$fs = foogallery_fs();
		if ( ! is_object( $fs ) || ! method_exists( $fs, 'is__premium_only' ) || $fs->is__premium_only() || ! method_exists( $fs, 'is_registered' ) || ! $fs->is_registered() || ! method_exists( $fs, '_get_latest_download_local_url' ) ) {
			return '';
		}
		$valid_license = method_exists( $fs, 'has_any_active_valid_license' ) && $fs->has_any_active_valid_license();
		$trial_plan    = method_exists( $fs, 'is_trial' ) && $fs->is_trial() && method_exists( $fs, 'get_trial_plan' ) ? $fs->get_trial_plan() : false;
		$paid_trial    = is_object( $trial_plan ) && method_exists( $trial_plan, 'is_free' ) && ! $trial_plan->is_free();
		return $valid_license || $paid_trial ? self::safe_sdk_url( $fs->_get_latest_download_local_url() ) : '';
	}

	/** Map the legacy overview URL to the shared FooGallery menu highlight.
	 *
	 * @param string|null $submenu_file Current submenu file slug.
	 * @return string|null
	 */
	public static function filter_submenu_file( $submenu_file ) {
		global $plugin_page;
		if ( 'foogallery-trial-overview' === $plugin_page ) {
			return 'foogallery-license-trial';
		}
		return $submenu_file;
	}
	/**
	 * Keep blocked PRO setup visible on gallery list and editor screens.
	 *
	 * @return void
	 */
	public static function render_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'edit-' . FOOGALLERY_CPT_GALLERY, FOOGALLERY_CPT_GALLERY ), true ) ) {
			return;
		}
		$state = self::snapshot();
		if ( ! in_array( $state['phase'], array( 'free_trial', 'free_entitled', 'pro_installed_inactive', 'pro_disconnected', 'pro_inactive', 'pro_no_entitlement' ), true ) ) {
			return;
		}
		?>
		<div class="notice notice-warning"><p>
			<?php esc_html_e( 'FooGallery PRO setup needs attention before trial or licensed features can run.', 'foogallery' ); ?>
			<a href="<?php echo esc_url( foogallery_admin_url_for_page( 'foogallery-license-trial' ) ); ?>"><?php esc_html_e( 'Review License & Trial', 'foogallery' ); ?></a>
		</p></div>
		<?php
	}

	/**
	 * Build the current admin setup state without changing a license or trial.
	 *
	 * @return array<string, bool|string>
	 */
	public static function snapshot() {
		$fs                = function_exists( 'foogallery_fs' ) ? foogallery_fs() : false;
		$premium           = is_object( $fs ) && method_exists( $fs, 'is__premium_only' ) && $fs->is__premium_only();
		$premium_basename  = is_object( $fs ) && method_exists( $fs, 'premium_plugin_basename' ) ? $fs->premium_plugin_basename() : '';
		$premium_installed = is_string( $premium_basename ) && '' !== $premium_basename && 0 === validate_file( $premium_basename ) && file_exists( WP_PLUGIN_DIR . '/' . $premium_basename );
		$trial             = is_object( $fs ) && method_exists( $fs, 'is_trial' ) && $fs->is_trial();
		$entitled          = is_object( $fs ) && method_exists( $fs, 'can_use_premium_code' ) && $fs->can_use_premium_code();
		$anonymous         = is_object( $fs ) && method_exists( $fs, 'is_anonymous' ) && $fs->is_anonymous();
		$registered        = is_object( $fs ) && method_exists( $fs, 'is_registered' ) && $fs->is_registered();
		$has_license       = is_object( $fs ) && method_exists( $fs, 'has_any_license' ) && $fs->has_any_license();
		$trial_state       = $premium && ! $entitled && class_exists( 'FooGallery_Trial_State', false ) ? FooGallery_Trial_State::snapshot() : array();

		if ( ! $premium ) {
			$phase = $premium_installed ? 'pro_installed_inactive' : ( $trial ? 'free_trial' : ( $entitled ? 'free_entitled' : 'free' ) );
		} elseif ( $entitled ) {
			$phase = 'ready';
		} elseif ( isset( $trial_state['phase'] ) && 'trial_expired' === $trial_state['phase'] ) {
			$phase = 'trial_expired';
		} elseif ( ! $registered ) {
			$phase = 'pro_disconnected';
		} elseif ( ! $entitled ) {
			$phase = $has_license ? 'pro_inactive' : 'pro_no_entitlement';
		}

		$state = array(
			'phase'             => $phase,
			'premium'           => (bool) $premium,
			'premium_installed' => (bool) $premium_installed,
			'premium_basename'  => $premium_installed ? $premium_basename : '',
			'trial'             => (bool) $trial,
			'entitled'          => (bool) $entitled,
			'registered'        => (bool) $registered,
			'anonymous'         => (bool) $anonymous,
		);

		/**
		 * Filter the presentation state for integration testing and site-specific guidance.
		 * Never use this filtered state to grant runtime feature access.
		 *
		 * @param array $state Read-only setup state.
		 */
		return apply_filters( 'foogallery_admin_setup_state', $state );
	}

	/**
	 * Render a persistent setup page for administrators.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view FooGallery setup.', 'foogallery' ) );
		}

		$state = self::snapshot();
		$phase = isset( $state['phase'] ) ? $state['phase'] : 'free';
		if ( in_array( $phase, array( 'ready', 'trial_expired' ), true ) && self::overview_available() && FooGallery_Admin_Trial_Experience::render_for_setup() ) {
			return;
		}
		$fs               = function_exists( 'foogallery_fs' ) ? foogallery_fs() : false;
		$download_url     = self::premium_download_url();
		$license_url      = self::license_activation_url();
		$connect_url      = is_object( $fs ) && ! empty( $state['anonymous'] ) && method_exists( $fs, 'get_reconnect_url' ) ? $fs->get_reconnect_url() : ( is_object( $fs ) && method_exists( $fs, 'get_activation_url' ) ? $fs->get_activation_url() : '' );
		$trial_url        = is_object( $fs ) && method_exists( $fs, 'get_trial_url' ) ? $fs->get_trial_url() : foogallery_admin_freetrial_url();
		$connect_url      = self::safe_sdk_url( $connect_url );
		$trial_url        = self::safe_sdk_url( $trial_url );
		$can_install_pro  = self::can_install_pro();
		$can_upload_pro   = current_user_can( 'install_plugins' ) && wp_is_file_mod_allowed( 'plugin_install' );
		$can_activate_pro = current_user_can( 'activate_plugins' );
		$file_mod_allowed = wp_is_file_mod_allowed( 'plugin_install' );
		$trial_intent     = (bool) get_transient( self::trial_intent_key() );
		$pending          = is_object( $fs ) && method_exists( $fs, 'is_pending_activation' ) && $fs->is_pending_activation();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only recovery explanation.
		$trial_return     = isset( $_GET['foogallery_return'] ) && is_string( $_GET['foogallery_return'] ) && 'trial' === sanitize_key( wp_unslash( $_GET['foogallery_return'] ) );
		$install_feedback = get_transient( self::install_feedback_key() );
		if ( $install_feedback ) {
			delete_transient( self::install_feedback_key() );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'FooGallery License & Trial', 'foogallery' ); ?></h1>
			<?php if ( is_string( $install_feedback ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( self::install_feedback_message( $install_feedback ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( ( $trial_intent || $trial_return ) && empty( $state['registered'] ) ) : ?>
				<div class="notice notice-info inline"><p><?php echo esc_html( $pending ? __( 'Your trial request is waiting for account confirmation. Check the email from Freemius, confirm this site, then return to check trial availability.', 'foogallery' ) : __( 'Connect this site to your FooGallery account first. You can choose a trial plan after connection if your site is eligible.', 'foogallery' ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'free' === $phase && ! empty( $state['registered'] ) && ! self::trial_selection_available() ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'A new trial is not available for this site. Review your FooGallery account for existing access or available plans.', 'foogallery' ); ?></p></div>
			<?php endif; ?>
			<?php self::render_navigation( 'setup' ); ?>
			<?php if ( 'ready' === $phase ) : ?>
				<p><?php esc_html_e( 'PRO is active. Trial details are unavailable with the current site settings.', 'foogallery' ); ?></p>
			<?php elseif ( 'trial_expired' === $phase ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Your PRO trial has ended. Your saved settings are preserved.', 'foogallery' ); ?></p></div>
			<?php elseif ( 'pro_installed_inactive' === $phase ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'The PRO plugin is installed but inactive. Activate it before PRO features can run.', 'foogallery' ); ?></p></div>
			<?php elseif ( 'free_trial' === $phase ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Your trial is active, but this is the free plugin. Install the PRO plugin before trial features can run.', 'foogallery' ); ?></p></div>
			<?php elseif ( 'free_entitled' === $phase ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'This site has a PRO entitlement, but the free plugin is running. Install the PRO plugin before licensed features can run.', 'foogallery' ); ?></p></div>
			<?php elseif ( 'pro_disconnected' === $phase ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'The PRO plugin is installed, but this site is not connected to your FooGallery account.', 'foogallery' ); ?></p></div>
			<?php elseif ( 'pro_inactive' === $phase ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'The PRO plugin is installed, but its license is not active on this site.', 'foogallery' ); ?></p></div>
			<?php elseif ( 'pro_no_entitlement' === $phase ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'The PRO plugin is installed, but the current account has no active PRO entitlement on this site.', 'foogallery' ); ?></p></div>
			<?php else : ?>
				<p><?php esc_html_e( 'This is the free plugin. A trial or paid license needs the PRO plugin before PRO features can run.', 'foogallery' ); ?></p>
			<?php endif; ?>

			<?php if ( 'ready' !== $phase ) : ?>
				<h2><?php esc_html_e( 'Set up access', 'foogallery' ); ?></h2>
				<?php if ( ! $state['premium'] ) : ?>
					<?php if ( 'pro_installed_inactive' === $phase ) : ?>
						<?php if ( $can_activate_pro ) : ?>
							<p><?php esc_html_e( 'Activate the installed PRO plugin, then return here to check the license or trial.', 'foogallery' ); ?></p>
						<?php else : ?>
							<p><?php echo esc_html( is_multisite() ? __( 'Ask a network administrator to activate the installed PRO plugin, then return here to check the license or trial.', 'foogallery' ) : __( 'Ask a site administrator to activate the installed PRO plugin, then return here to check the license or trial.', 'foogallery' ) ); ?></p>
						<?php endif; ?>
						<?php if ( $can_activate_pro && ! empty( $state['premium_basename'] ) ) : ?>
							<?php $activate_url = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $state['premium_basename'] ) ), 'activate-plugin_' . $state['premium_basename'] ); ?>
							<p><a class="button button-primary" href="<?php echo esc_url( $activate_url ); ?>"><?php esc_html_e( 'Activate installed PRO plugin', 'foogallery' ); ?></a></p>
						<?php endif; ?>
					<?php else : ?>
						<?php if ( $can_install_pro ) : ?>
							<p><?php esc_html_e( 'Install and activate PRO on this server. You can also download the ZIP for a manual install.', 'foogallery' ); ?></p>
						<?php elseif ( ! $file_mod_allowed ) : ?>
							<p><?php echo esc_html( is_multisite() ? __( 'Plugin installation is disabled on this network. Ask a network administrator or hosting provider to install and activate FooGallery PRO using the ZIP, then return here to check access.', 'foogallery' ) : __( 'Plugin installation is disabled on this site. Ask a site administrator or hosting provider to install and activate FooGallery PRO using the ZIP, then return here to check access.', 'foogallery' ) ); ?></p>
						<?php elseif ( ! $can_upload_pro || ! $can_activate_pro ) : ?>
							<p><?php echo esc_html( is_multisite() ? __( 'Ask a network administrator to install and activate FooGallery PRO. You can download the ZIP to share with them, then return here to check access.', 'foogallery' ) : __( 'Ask a site administrator to install and activate FooGallery PRO. You can download the ZIP to share with them, then return here to check access.', 'foogallery' ) ); ?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'Download the PRO ZIP, upload it under Plugins, activate it, then return here to check access.', 'foogallery' ); ?></p>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( 'pro_installed_inactive' !== $phase ) : ?>
						<?php if ( $can_install_pro ) : ?>
							<form method="post" action="<?php echo esc_url( self::install_pro_url() ); ?>">
								<?php wp_nonce_field( 'foogallery_install_pro' ); ?>
								<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Install and activate PRO on this server', 'foogallery' ); ?></button></p>
							</form>
						<?php endif; ?>
						<?php if ( $download_url ) : ?>
							<p><a class="button<?php echo $can_install_pro ? '' : ' button-primary'; ?>" href="<?php echo esc_url( $download_url ); ?>"><?php esc_html_e( 'Download PRO ZIP', 'foogallery' ); ?></a></p>
						<?php else : ?>
							<p><?php esc_html_e( 'Find your PRO ZIP in your FooPlugins website account. Available downloads depend on your trial or license status.', 'foogallery' ); ?></p>
							<p><a class="button button-primary" href="<?php echo esc_url( 'https://fooplugins.com/account/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open FooPlugins website account', 'foogallery' ); ?></a></p>
							<?php if ( $connect_url ) : ?>
								<p><a href="<?php echo esc_url( $connect_url ); ?>"><?php esc_html_e( 'Connect this site to your account', 'foogallery' ); ?></a></p>
							<?php endif; ?>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( 'free' === $phase && $trial_url && self::trial_selection_available() ) : ?>
					<p><a href="<?php echo esc_url( self::start_trial_url() ); ?>"><?php echo esc_html( ! empty( $state['registered'] ) ? __( 'Start your 7-day trial', 'foogallery' ) : __( 'Connect to check 7-day trial availability', 'foogallery' ) ); ?></a></p>
				<?php endif; ?>
					<?php if ( 'pro_installed_inactive' !== $phase && $can_upload_pro ) : ?>
					<p><a href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=upload' ) ); ?>"><?php esc_html_e( 'Upload the PRO ZIP', 'foogallery' ); ?></a></p>
				<?php endif; ?>
				<?php else : ?>
					<p><?php esc_html_e( 'Enter your existing license key in the FooGallery activation form. Freemius will verify the key and show any activation errors.', 'foogallery' ); ?></p>
					<?php if ( $license_url ) : ?>
						<p><a class="button button-primary" href="<?php echo esc_url( $license_url ); ?>"><?php esc_html_e( 'Activate PRO license', 'foogallery' ); ?></a></p>
					<?php else : ?>
						<p><?php esc_html_e( 'License activation is temporarily unavailable. Check that FooGallery can connect to its licensing service, then reload this page.', 'foogallery' ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Only show usable SDK navigation URLs.
	 *
	 * @param mixed $url URL returned by the SDK.
	 * @return string
	 */
	private static function safe_sdk_url( $url ) {
		if ( ! is_string( $url ) ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return '';
		}
		return esc_url_raw( $url );
	}
}
