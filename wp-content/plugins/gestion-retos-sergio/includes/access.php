<?php

defined( 'ABSPATH' ) || exit;

function saretos_cookie_name( string $kind ): string {
	return ( is_ssl() ? '__Host-' : '' ) . 'saretos_' . $kind . '_' . get_current_blog_id();
}

function saretos_cookie( string $kind ): string {
	$key = saretos_cookie_name( $kind );
	return isset( $_COOKIE[ $key ] ) && is_string( $_COOKIE[ $key ] ) ? wp_unslash( $_COOKIE[ $key ] ) : '';
}

function saretos_set_cookie( string $kind, string $value, int $expires ): bool {
	$key = saretos_cookie_name( $kind );
	$ok = setcookie( $key, $value, array(
		'expires'  => $expires,
		'path'     => '/',
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	) );
	if ( $ok ) {
		$_COOKIE[ $key ] = $value;
	}
	return $ok;
}

function saretos_guest(): string {
	$guest = saretos_cookie( 'browser' );
	return preg_match( '/^[a-f0-9]{64}$/D', $guest ) ? $guest : '';
}

function saretos_init_guest(): void {
	if ( ! saretos_guest() ) {
		saretos_set_cookie( 'browser', bin2hex( random_bytes( 32 ) ), time() + 12 * HOUR_IN_SECONDS );
	}
}

function saretos_signature( string $purpose, int $expires ): string {
	return hash_hmac( 'sha256', $purpose . ':' . $expires . ':' . get_current_blog_id() . ':' . saretos_guest(), wp_salt( 'nonce' ) );
}

function saretos_token( string $purpose, int $expires ): string {
	return $expires . '.' . saretos_signature( $purpose, $expires );
}

function saretos_token_valid( string $token, string $purpose ): bool {
	if ( ! saretos_guest() || ! preg_match( '/^([0-9]{1,12})\.([a-f0-9]{64})$/D', $token, $parts ) ) {
		return false;
	}
	$expires = (int) $parts[1];
	return $expires > time() && hash_equals( saretos_signature( $purpose, $expires ), $parts[2] );
}

