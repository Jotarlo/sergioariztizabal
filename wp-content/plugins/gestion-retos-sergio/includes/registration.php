<?php

defined( 'ABSPATH' ) || exit;

function saretos_platforms(): array {
	return array( 'tiktok' => 'TikTok', 'instagram' => 'Instagram', 'facebook' => 'Facebook' );
}

function saretos_phone( string $phone ): string {
	$phone = trim( $phone );
	if ( ! preg_match( '/^\+?[0-9 .()\-]{7,32}$/D', $phone ) ) {
		return '';
	}
	$digits = preg_replace( '/[^0-9]/', '', $phone );
	if ( str_starts_with( $digits, '00' ) && ! str_starts_with( $phone, '+' ) ) {
		$digits = substr( $digits, 2 );
	} elseif ( ! str_starts_with( $phone, '+' ) ) {
		if ( 10 === strlen( $digits ) && ! str_starts_with( $digits, '0' ) ) {
			$digits = '57' . $digits;
		} elseif ( ! preg_match( '/^57[1-9][0-9]{9}$/D', $digits ) ) {
			return '';
		}
	}
	if ( str_starts_with( $digits, '57' ) && ! preg_match( '/^57[1-9][0-9]{9}$/D', $digits ) ) {
		return '';
	}
	return preg_match( '/^[1-9][0-9]{7,14}$/D', $digits ) ? '+' . $digits : '';
}

function saretos_social_parts( string $platform, string $url ): ?array {
	$hosts = array(
		'tiktok'    => array( 'tiktok.com', 'www.tiktok.com', 'm.tiktok.com', 'vm.tiktok.com', 'vt.tiktok.com' ),
		'instagram' => array( 'instagram.com', 'www.instagram.com', 'm.instagram.com' ),
		'facebook'  => array( 'facebook.com', 'www.facebook.com', 'm.facebook.com', 'web.facebook.com', 'fb.watch' ),
	);
	$parts = wp_parse_url( saretos_web_url( $url ) );
	if ( ! $parts || strtolower( $parts['scheme'] ?? '' ) !== 'https' || isset( $parts['port'] )
		|| ! in_array( strtolower( $parts['host'] ?? '' ), $hosts[ $platform ] ?? array(), true ) ) {
		return null;
	}
	$parts['host'] = strtolower( $parts['host'] );
	return $parts;
}

function saretos_video_valid( string $platform, string $url ): bool {
	$parts = saretos_social_parts( $platform, $url );
	if ( ! $parts ) {
		return false;
	}
	$path = $parts['path'] ?? '';
	switch ( $platform ) {
		case 'tiktok':
			if ( in_array( $parts['host'], array( 'vm.tiktok.com', 'vt.tiktok.com' ), true ) ) {
				return (bool) preg_match( '~^/[\w-]+/?$~D', $path );
			}
			return (bool) preg_match( '~^/(?:@[\w.-]+/video/[0-9]+|t/[\w-]+)/?$~D', $path );
		case 'instagram':
			return (bool) preg_match( '~^/(?:reel|reels|p|tv)/[\w-]+/?$~D', $path );
		case 'facebook':
			if ( 'fb.watch' === $parts['host'] ) {
				return (bool) preg_match( '~^/[\w-]+/?$~D', $path );
			}
			if ( '/watch' === untrailingslashit( $path ) ) {
				return (bool) preg_match( '/(?:^|&)v=[0-9]+(?:&|$)/D', $parts['query'] ?? '' );
			}
			return (bool) preg_match( '~^/(?:(?:[\w.-]+/)?videos/[\w-]+|reels?/[\w-]+|share/[rv]/[\w-]+)/?$~D', $path );
	}
	return false;
}

function saretos_profile_valid( string $platform, string $profile ): bool {
	if ( preg_match( '/^@?[A-Za-z0-9_.-]{1,100}$/D', $profile ) ) {
		return true;
	}
	$parts = saretos_social_parts( $platform, $profile );
	$path = $parts['path'] ?? '/';
	return $parts && ! in_array( $parts['host'], array( 'vm.tiktok.com', 'vt.tiktok.com', 'fb.watch' ), true )
		&& '/' !== $path && ! saretos_video_valid( $platform, $profile );
}

function saretos_legal_links( array $challenge ): array {
	$settings = $challenge['settings'];
	$links = array( 'terms' => $settings['terms'], 'privacy' => $settings['privacy'] );
	if ( $challenge['is_demo'] ) {
		$url = saretos_page_url( 'public' );
		$links['terms'] = $links['terms'] ?: add_query_arg( 'documento', 'terminos', $url );
		$links['privacy'] = $links['privacy'] ?: add_query_arg( 'documento', 'datos', $url );
	}
	return $links;
}

function saretos_registration_values(): array {
	$values = array();
	foreach ( array( 'name', 'age', 'location', 'phone', 'email', 'profile' ) as $key ) {
		$values[ $key ] = mb_substr( trim( saretos_post( 'entry_' . $key ) ), 0, 2048 );
	}
	$values['videos'] = array();
	$network_fields = false;
	foreach ( saretos_platforms() as $platform => $label ) {
		$key = 'entry_video_' . $platform;
		$network_fields = $network_fields || array_key_exists( $key, $_POST );
		$values['videos'][ $platform ] = mb_substr( trim( saretos_post( $key ) ), 0, 2048 );
	}
	// Let visitors who opened a form before the upgrade finish their entry.
	$platform = saretos_post( 'entry_platform' );
	if ( ! $network_fields && array_key_exists( $platform, $values['videos'] ) ) {
		$values['videos'][ $platform ] = mb_substr( trim( saretos_post( 'entry_video' ) ), 0, 2048 );
	}
	foreach ( array( 'accept_terms', 'accept_privacy', 'share_video' ) as $key ) {
		$values[ $key ] = '1' === saretos_post( 'entry_' . $key );
	}
	return $values;
}

function saretos_entry_videos( array $entry ): array {
	$videos = json_decode( (string) ( $entry['socials'] ?? '' ), true );
	if ( is_array( $videos ) && $videos ) {
		return array_intersect_key( $videos, saretos_platforms() );
	}
	return empty( $entry['video'] ) ? array() : array( $entry['platform'] => $entry['video'] );
}

function saretos_register( array $challenge ): WP_Error {
	global $wpdb;
	if ( ! saretos_token_valid( saretos_post( 'saretos_token' ), 'register:' . $challenge['id'] ) ) {
		return new WP_Error( 'form', 'Actualiza el formulario e inténtalo de nuevo. Para enviarlo, habilita las cookies de esta página.', array( 'status' => 403 ) );
	}

	$values = saretos_registration_values();
	$name = sanitize_text_field( $values['name'] );
	$location = sanitize_text_field( $values['location'] );
	$age = $values['age'];
	$minimum = max( 1, (int) $challenge['settings']['minimum_age'] );
	$phone = saretos_phone( $values['phone'] );
	$email = strtolower( $values['email'] );
	$profile = $values['profile'];
	if ( ! $name || mb_strlen( $name ) > 180 || ! $location || mb_strlen( $location ) > 180 ) {
		return new WP_Error( 'identity', 'Escribe tu nombre completo y tu ciudad y departamento.' );
	}
	if ( ! preg_match( '/^[0-9]{1,3}$/D', $age ) || (int) $age < $minimum ) {
		return new WP_Error( 'age', $minimum > 1 ? sprintf( 'Debes tener al menos %d años según los términos del reto.', $minimum ) : 'Indica tu edad en años.' );
	}
	if ( ! $phone ) {
		return new WP_Error( 'phone', 'Escribe un número de WhatsApp válido. En Colombia usa 10 dígitos. Para otros países, incluye el prefijo con +.' );
	}
	if ( strlen( $email ) > 254 || ! is_email( $email ) || ! preg_match( '/^[\x21-\x7e]+$/D', $email ) ) {
		return new WP_Error( 'email', 'Escribe un correo electrónico válido.' );
	}
	$videos = array_filter( $values['videos'], static fn( $value ) => '' !== $value );
	if ( ! $videos ) {
		return new WP_Error( 'video', 'Agrega al menos un enlace directo a tu publicación en TikTok, Instagram o Facebook.' );
	}
	$profile_matches = false;
	foreach ( $videos as $platform => &$video ) {
		if ( ! saretos_video_valid( $platform, $video ) ) {
			return new WP_Error( 'video', sprintf( 'Pega un enlace HTTPS directo a la publicación de %s en su campo.', saretos_platforms()[ $platform ] ) );
		}
		$video = saretos_web_url( $video );
		$profile_matches = $profile_matches || saretos_profile_valid( $platform, $profile );
	}
	unset( $video );
	if ( strlen( $profile ) > 255 || ! $profile_matches ) {
		return new WP_Error( 'profile', 'Escribe tu nombre de usuario o un enlace al perfil de una de las redes incluidas.' );
	}
	if ( ! $values['accept_terms'] || ! $values['accept_privacy'] ) {
		return new WP_Error( 'consent', 'Para inscribirte debes aceptar los términos y autorizar el tratamiento de datos personales.' );
	}

	$links = saretos_legal_links( $challenge );
	$entries = saretos_table( 'entries' );
	$challenges = saretos_table( 'challenges' );
	$previous = $wpdb->suppress_errors();
	// One statement keeps the challenge window and its registration settings consistent with the insert.
	$saved = $wpdb->query( $wpdb->prepare(
		"INSERT INTO $entries
			(challenge_id, name, age, location, phone, email, platform, profile, video, socials, terms_url, privacy_url, share_video, registered_at)
		SELECT id, %s, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, UNIX_TIMESTAMP()
		FROM $challenges
		WHERE id = %d AND revision = %d AND active_slot = 1 AND closed = 0
			AND starts <= UNIX_TIMESTAMP() AND ends > UNIX_TIMESTAMP()",
		$name, (int) $age, $location, $phone, $email, array_key_first( $videos ),
		saretos_web_url( $profile ) ?: $profile, reset( $videos ), wp_json_encode( $videos ), $links['terms'], $links['privacy'],
		(int) $values['share_video'], $challenge['id'], $challenge['revision']
	) );
	$wpdb->suppress_errors( $previous );

	if ( 0 === $saved ) {
		return new WP_Error( 'changed', 'Las inscripciones se cerraron o el reto se actualizó. Abre de nuevo la página para verificar si aún puedes inscribirte.', array( 'status' => 409 ) );
	}
	if ( false === $saved ) {
		$matches = $wpdb->get_results( $wpdb->prepare(
			"SELECT email, phone FROM $entries WHERE challenge_id = %d AND (email = %s OR phone = %s)",
			$challenge['id'], $email, $phone
		), ARRAY_A );
		foreach ( $matches ?: array() as $match ) {
			if ( $match['email'] === $email ) {
				return new WP_Error( 'duplicate_email', 'Este correo ya está inscrito en el reto.', array( 'status' => 409 ) );
			}
		}
		if ( $matches ) {
			return new WP_Error( 'duplicate_phone', 'Este número de WhatsApp ya está inscrito en el reto.', array( 'status' => 409 ) );
		}
		return new WP_Error( 'save', 'No pudimos guardar la inscripción. Inténtalo nuevamente.', array( 'status' => 500 ) );
	}

	$expires = time() + 30 * MINUTE_IN_SECONDS;
	saretos_set_cookie( 'confirmation', saretos_token( 'confirmed:' . $challenge['id'], $expires ), $expires );
	wp_safe_redirect( add_query_arg( 'inscrito', '1', saretos_page_url( 'public' ) ), 303 );
	exit;
}

function saretos_registered( array $challenge ): bool {
	return saretos_token_valid( saretos_cookie( 'confirmation' ), 'confirmed:' . $challenge['id'] );
}

function saretos_registration_input( string $key, string $label, string $value, string $type, string $attributes = '', bool $required = true ): void {
	?>
	<div class="saretos-field">
		<label for="saretos-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
		<input id="saretos-<?php echo esc_attr( $key ); ?>" name="entry_<?php echo esc_attr( $key ); ?>" type="<?php echo esc_attr( $type ); ?>"
			value="<?php echo esc_attr( $value ); ?>" <?php echo $required ? 'required' : ''; ?> <?php echo $attributes; // Attributes are static or cast integers. ?>>
	</div>
	<?php
}

function saretos_registration_form( array $challenge, ?WP_Error $error ): void {
	$values = saretos_registration_values();
	$links = saretos_legal_links( $challenge );
	$minimum = max( 1, (int) $challenge['settings']['minimum_age'] );
	?>
	<section class="saretos-registration-section" aria-labelledby="saretos-register-title">
		<h2 id="saretos-register-title">Inscripción</h2>
		<?php saretos_public_error( $error ); ?>
		<form class="saretos-registration" method="post" action="<?php echo esc_url( saretos_page_url( 'public' ) ); ?>">
			<input type="hidden" name="saretos_action" value="register">
			<input type="hidden" name="saretos_token" value="<?php echo esc_attr( saretos_token( 'register:' . $challenge['id'], time() + HOUR_IN_SECONDS ) ); ?>">
			<div class="saretos-form-grid">
				<?php
				saretos_registration_input( 'name', 'Nombre completo', $values['name'], 'text', 'maxlength="180" autocomplete="name"' );
				saretos_registration_input( 'age', 'Edad', $values['age'], 'number', 'inputmode="numeric" step="1" min="' . $minimum . '" max="999"' );
				saretos_registration_input( 'location', 'Ciudad y departamento', $values['location'], 'text', 'maxlength="180"' );
				saretos_registration_input( 'phone', 'Número de WhatsApp', $values['phone'], 'tel', 'maxlength="32" autocomplete="tel" aria-describedby="saretos-phone-help"' );
				?>
				<p class="saretos-field-help" id="saretos-phone-help">En Colombia, 10 dígitos. Para otros países, agrega el prefijo con +.</p>
				<?php
				saretos_registration_input( 'email', 'Correo electrónico', $values['email'], 'email', 'maxlength="254" autocomplete="email"' );
				saretos_registration_input( 'profile', 'Usuario o perfil', $values['profile'], 'text', 'maxlength="255" autocapitalize="none" spellcheck="false"' );
				?>
			</div>
			<fieldset class="saretos-registration-links" aria-describedby="saretos-social-help">
				<legend>Enlaces de publicación</legend>
				<p id="saretos-social-help">Agrega al menos un enlace directo. Puedes incluir las tres redes.</p>
				<?php foreach ( saretos_platforms() as $key => $label ) :
					saretos_registration_input( 'video_' . $key, 'Enlace de ' . $label, $values['videos'][ $key ], 'url', 'maxlength="1024" aria-describedby="saretos-social-help"', false );
				endforeach; ?>
			</fieldset>
			<div class="saretos-consent">
				<label><input type="checkbox" name="entry_accept_terms" value="1" required <?php checked( $values['accept_terms'] ); ?>>
					<span>Acepto los <a href="<?php echo esc_url( $links['terms'] ); ?>" target="_blank" rel="noopener">términos y condiciones</a>.</span></label>
				<label><input type="checkbox" name="entry_accept_privacy" value="1" required <?php checked( $values['accept_privacy'] ); ?>>
					<span>Autorizo el <a href="<?php echo esc_url( $links['privacy'] ); ?>" target="_blank" rel="noopener">tratamiento de mis datos personales</a>.</span></label>
				<label><input type="checkbox" name="entry_share_video" value="1" <?php checked( $values['share_video'] ); ?>>
					<span>Autorizo a Sergio Aristizábal a compartir el video en sus redes dando crédito (opcional).</span></label>
			</div>
			<button type="submit" class="saretos-button">Enviar inscripción</button>
		</form>
	</section>
	<?php
}

function saretos_demo_document( string $document ): void {
	?>
	<section class="saretos-registration-section">
		<h2><?php echo 'terminos' === $document ? 'Términos de demostración' : 'Política de datos de demostración'; ?></h2>
		<p>Esta página sirve para probar el flujo de inscripción local con datos ficticios. Los documentos oficiales se configurarán antes de abrir el concurso.</p>
		<p><a href="<?php echo esc_url( saretos_page_url( 'public' ) ); ?>">Volver al reto</a></p>
	</section>
	<?php
}

function saretos_success_view( array $settings ): void {
	?>
	<section class="saretos-registration-section" role="status">
		<?php echo wp_kses_post( wpautop( $settings['success'] ?: 'Tu inscripción fue recibida correctamente.' ) ); ?>
		<?php if ( $settings['spotify'] ) : ?>
			<p><a class="saretos-button" href="<?php echo esc_url( $settings['spotify'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $settings['spotify_text'] ); ?></a></p>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( saretos_page_url( 'public' ) ); ?>">Volver al reto</a></p>
	</section>
	<?php
}
