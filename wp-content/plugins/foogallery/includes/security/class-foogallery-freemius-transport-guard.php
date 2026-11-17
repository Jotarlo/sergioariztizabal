<?php
/**
 * Enforce verified HTTPS for Freemius service traffic before SDK initialization.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'FooGallery_Freemius_Transport_Guard' ) ) {
	/**
	 * Transport policy for the canonical Freemius API endpoints.
	 *
	 * This is host-scoped, so it also protects requests made by other installed
	 * Freemius SDK consumers that use these same service hosts.
	 */
	final class FooGallery_Freemius_Transport_Guard {
		/**
		 * Register before FooGallery initializes Freemius.
		 *
		 * @return void
		 */
		public static function register() {
			add_filter( 'http_request_args', array( __CLASS__, 'filter_request_args' ), PHP_INT_MAX, 2 );
			add_filter( 'pre_http_request', array( __CLASS__, 'block_unsafe_request' ), PHP_INT_MAX, 3 );
		}

		/**
		 * Canonical service hosts supported by the bundled SDK.
		 *
		 * @return string[]
		 */
		private static function service_hosts() {
			$hosts = array( 'api.freemius.com', 'sandbox-api.freemius.com', 'wp.freemius.com' );

			// Respect a configured production API address while requiring an exact host match.
			if ( defined( 'WP_FS__ADDRESS' ) && is_string( WP_FS__ADDRESS ) ) {
				$address = WP_FS__ADDRESS;
				if ( 0 === strpos( $address, '://' ) ) {
					$address = 'https' . $address;
				}
				$parts = wp_parse_url( $address );
				if ( is_array( $parts ) && ! empty( $parts['host'] ) ) {
					$hosts[] = strtolower( rtrim( $parts['host'], '.' ) );
				}
			}

			return array_values( array_unique( $hosts ) );
		}

		/**
		 * Check for an exact canonical host in a URL authority.
		 *
		 * @param string $authority URL authority component.
		 * @param string $host      Canonical service hostname.
		 * @return bool
		 */
		private static function authority_has_host( $authority, $host ) {
			$authority = strtolower( $authority );
			$host       = strtolower( $host );
			$offset     = 0;

			while ( false !== ( $position = strpos( $authority, $host, $offset ) ) ) {
				$before = 0 === $position ? '' : substr( $authority, $position - 1, 1 );
				$after  = substr( $authority, $position + strlen( $host ), 1 );
				if ( ( '' === $before || '@' === $before ) && ( '' === $after || in_array( $after, array( ':', '/', '\\' ), true ) ) ) {
					return true;
				}
				$offset = $position + 1;
			}

			return false;
		}

		/**
		 * Determine whether a URL resolves to a protected service host.
		 *
		 * @param mixed $url Request URL.
		 * @return array|false Parsed URL parts, or false for unrelated/malformed URLs.
		 */
		private static function protected_url( $url ) {
			if ( ! is_string( $url ) || '' === $url ) {
				return false;
			}

			$authority = '';
			if ( preg_match( '~^[a-z][a-z0-9+.-]*://([^/?#]*)~i', $url, $authority_match ) ) {
				$authority = $authority_match[1];
			}

			$parts = wp_parse_url( $url );
			if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! isset( $parts['scheme'] ) ) {
				// WordPress must not attempt to parse a malformed URL whose authority
				// names a protected service. Keep unrelated malformed URLs out of scope.
				foreach ( self::service_hosts() as $service_host ) {
					if ( self::authority_has_host( $authority, $service_host ) ) {
						return array( '_foogallery_malformed' => true );
					}
				}
				return false;
			}

			$host = strtolower( rtrim( $parts['host'], '.' ) );
			if ( ! in_array( $host, self::service_hosts(), true ) ) {
				// Some URL parsers accept backslashes or whitespace as authority syntax.
				// If a canonical service host appears at an authority boundary, treat it
				// as protected and malformed instead of letting parser disagreement bypass policy.
				foreach ( self::service_hosts() as $service_host ) {
					if ( self::authority_has_host( $authority, $service_host ) ) {
						return array( '_foogallery_malformed' => true );
					}
				}
				return false;
			}

			// Reject URL syntax ambiguity only after the exact protected host is known.
			if ( preg_match( '/[\x00-\x20\\\\]/', $url ) ) {
				return array( '_foogallery_malformed' => true );
			}

			return $parts;
		}

		/**
		 * Require verified TLS and disable redirects for protected service calls.
		 *
		 * @param array  $args Request arguments.
		 * @param string $url  Request URL.
		 * @return array
		 */
		public static function filter_request_args( $args, $url ) {
			$parts = self::protected_url( $url );
			if ( false === $parts ) {
				return $args;
			}

			if ( ! empty( $parts['_foogallery_malformed'] ) || ! isset( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
				return $args;
			}

			$args['sslverify']   = true;
			$args['redirection'] = 0;
			return $args;
		}

		/**
		 * Fail closed for unsafe URLs targeting canonical service hosts.
		 *
		 * @param mixed  $preempt Existing preemptive response.
		 * @param array  $args    Request arguments.
		 * @param string $url     Request URL.
		 * @return mixed
		 */
		public static function block_unsafe_request( $preempt, $args, $url ) {
			if ( false !== $preempt ) {
				return $preempt;
			}

			$parts = self::protected_url( $url );
			if ( false === $parts ) {
				return $preempt;
			}

			if ( ! empty( $parts['_foogallery_malformed'] ) || ! isset( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || ( isset( $parts['port'] ) && 443 !== (int) $parts['port'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
				return new WP_Error( 'foogallery_freemius_insecure_transport', 'Freemius service requests require verified HTTPS.' );
			}

			// Validate the final arguments after every http_request_args filter.
			if ( ! is_array( $args ) || ! isset( $args['sslverify'] ) || true !== $args['sslverify'] || ! array_key_exists( 'redirection', $args ) || 0 !== $args['redirection'] ) {
				return new WP_Error( 'foogallery_freemius_insecure_transport', 'Freemius service requests require verified HTTPS.' );
			}

			return $preempt;
		}
	}
}

FooGallery_Freemius_Transport_Guard::register();
