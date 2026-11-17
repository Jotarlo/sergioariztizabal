<?php

defined( 'ABSPATH' ) || exit;

function saretos_table( string $suffix ): string {
	global $wpdb;
	return $wpdb->prefix . 'saretos_' . $suffix;
}

function saretos_install_schema(): bool {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$challenges = saretos_table( 'challenges' );
	$entries    = saretos_table( 'entries' );
	$collation  = $wpdb->get_charset_collate();

	dbDelta( "CREATE TABLE $challenges (
		id bigint unsigned NOT NULL AUTO_INCREMENT,
		title varchar(200) NOT NULL,
		settings longtext NOT NULL,
		secret_hash varchar(255) NOT NULL,
		starts bigint unsigned NOT NULL,
		ends bigint unsigned NOT NULL,
		active_slot tinyint unsigned DEFAULT NULL,
		closed tinyint unsigned NOT NULL DEFAULT 0,
		is_demo tinyint unsigned NOT NULL DEFAULT 0,
		revision bigint unsigned NOT NULL DEFAULT 1,
		rubric_version bigint unsigned NOT NULL DEFAULT 1,
		PRIMARY KEY  (id),
		UNIQUE KEY active_slot (active_slot)
	) ENGINE=InnoDB $collation;" );

	dbDelta( "CREATE TABLE $entries (
		id bigint unsigned NOT NULL AUTO_INCREMENT,
		challenge_id bigint unsigned NOT NULL,
		name varchar(180) NOT NULL,
		age smallint unsigned NOT NULL,
		location varchar(180) NOT NULL,
		phone varbinary(16) NOT NULL,
		email varbinary(254) NOT NULL,
		platform varchar(16) NOT NULL,
		profile varchar(255) NOT NULL,
		video varchar(1024) NOT NULL,
		socials longtext DEFAULT NULL,
		terms_url varchar(1024) NOT NULL,
		privacy_url varchar(1024) NOT NULL,
		share_video tinyint unsigned NOT NULL DEFAULT 0,
		registered_at bigint unsigned NOT NULL,
		review varchar(16) NOT NULL DEFAULT 'pending',
		score decimal(5,2) DEFAULT NULL,
		grading longtext DEFAULT NULL,
		revision bigint unsigned NOT NULL DEFAULT 1,
		PRIMARY KEY  (id),
		UNIQUE KEY challenge_email (challenge_id,email),
		UNIQUE KEY challenge_phone (challenge_id,phone),
		KEY ranking (challenge_id,score,registered_at,id)
	) ENGINE=InnoDB $collation;" );

	foreach ( array( $challenges, $entries ) as $table ) {
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
			return false;
		}
	}
	if ( 'socials' !== $wpdb->get_var( "SHOW COLUMNS FROM $entries WHERE Field = 'socials'" ) ) {
		return false;
	}
	update_option( 'saretos_schema_version', 2 );
	return true;
}

function saretos_maybe_upgrade(): void {
	if ( 2 > (int) get_option( 'saretos_schema_version' ) && ! saretos_install_schema() ) {
		add_action( 'admin_notices', static function (): void {
			if ( current_user_can( 'manage_options' ) ) {
				echo '<div class="notice notice-error"><p>Gestión de Retos no pudo actualizar la base de datos.</p></div>';
			}
		} );
	}
}

function saretos_install(): void {
	if ( ! saretos_install_schema() ) {
		wp_die( 'No se pudo preparar la base de datos de Gestión de Retos.' );
	}

	add_role( 'saretos_evaluator', 'Evaluador de retos', array( 'read' => true, 'saretos_evaluate' => true ) );
	$administrator = get_role( 'administrator' );
	if ( $administrator ) {
		$administrator->add_cap( 'saretos_evaluate' );
	}

	saretos_create_page( 'public', 'retos', 'Retos' );
	saretos_create_page( 'evaluation', 'evaluacion-retos', 'Evaluación de retos' );
}

function saretos_create_page( string $route, string $slug, string $title ): void {
	$option = 'saretos_page_' . $route;
	$id     = (int) get_option( $option );
	$page   = get_post( $id ?: -1 );
	if ( $page && 'page' === $page->post_type && $route === get_post_meta( $id, '_saretos_route', true ) ) {
		return;
	}

	// Protect existing pages at the same slug and avoid automatic menu insertion.
	$auto_add = has_action( 'transition_post_status', '_wp_auto_add_pages_to_menu' );
	if ( false !== $auto_add ) {
		remove_action( 'transition_post_status', '_wp_auto_add_pages_to_menu', $auto_add );
	}
	try {
		$id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => $slug,
				'post_title'  => $title,
				'meta_input'  => array( '_saretos_route' => $route ),
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			wp_die( 'No se pudo crear la página «' . esc_html( $title ) . '».' );
		}
		update_option( $option, $id, false );
	} finally {
		if ( false !== $auto_add ) {
			add_action( 'transition_post_status', '_wp_auto_add_pages_to_menu', $auto_add, 3 );
		}
	}
}

function saretos_defaults(): array {
	return array(
		'description' => '',
		'promotion'   => '',
		'prize'       => '',
		'button'      => 'Participar',
		'band_image'  => 0,
		'page_image'  => 0,
		'rules'       => '',
		'announcement' => '',
		'minimum_age' => '',
		'terms'       => '',
		'privacy'     => '',
		'spotify'     => '',
		'spotify_text' => 'Escuchar en Spotify',
		'success'     => 'Tu inscripción fue recibida correctamente.',
		'closed_text' => 'Las inscripciones han finalizado.',
		'criteria'    => array( array( 'label' => 'Puntuación general', 'weight' => 10000 ) ),
	);
}

function saretos_challenge( int $id = 0 ): ?array {
	global $wpdb;
	$table = saretos_table( 'challenges' );
	$sql   = $id ? $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) : "SELECT * FROM $table WHERE active_slot = 1";
	$row   = $wpdb->get_row( $sql, ARRAY_A );
	if ( ! $row ) {
		return null;
	}
	$row['settings'] = array_replace( saretos_defaults(), json_decode( $row['settings'], true ) ?: array() );
	$row['settings'] = saretos_split_legacy_event_info( $row['settings'] );
	return $row;
}

/** Separate the old date and winner paragraph from rules without losing its announcement. */
function saretos_split_legacy_event_info( array $settings ): array {
	if ( ! empty( $settings['announcement'] ) || empty( $settings['rules'] ) ) {
		return $settings;
	}
	$rules = $settings['rules'];
	$pattern = '/<p>\s*<strong>\s*Inscripciones:\s*<\/strong>.*?<strong>\s*Anuncio del ganador:\s*<\/strong>/is';
	if ( ! preg_match( $pattern, $rules, $match, PREG_OFFSET_CAPTURE ) ) {
		return $settings;
	}
	$tail = substr( $rules, $match[0][1] + strlen( $match[0][0] ) );
	if ( ! preg_match( '/^(.*?)<\/p>\s*$/is', $tail, $announcement ) ) {
		return $settings;
	}
	$settings['announcement'] = 'Anuncio del ganador: ' . trim( wp_strip_all_tags( $announcement[1] ) );
	$settings['rules'] = trim( substr( $rules, 0, $match[0][1] ) );
	return $settings;
}

function saretos_challenge_list(): array {
	global $wpdb;
	$table = saretos_table( 'challenges' );
	return $wpdb->get_results( "SELECT id, title, starts, ends, active_slot, closed, is_demo FROM $table ORDER BY id DESC", ARRAY_A ) ?: array();
}

function saretos_demo_allowed(): bool {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	return in_array( wp_get_environment_type(), array( 'local', 'development' ), true )
		|| in_array( $host, array( 'localhost', '127.0.0.1', '::1', '[::1]' ), true )
		|| str_ends_with( $host, '.local' ) || str_ends_with( $host, '.test' );
}

function saretos_open( ?array $challenge ): bool {
	$now = time();
	return $challenge && (int) $challenge['active_slot'] === 1 && ! $challenge['closed']
		&& $now >= (int) $challenge['starts'] && $now < (int) $challenge['ends']
		&& ( $challenge['is_demo'] ? saretos_demo_allowed() : saretos_ready( $challenge['settings'] ) );
}

function saretos_ready( array $settings ): bool {
	return (int) $settings['minimum_age'] >= 1 && $settings['terms'] && $settings['privacy'];
}

function saretos_timezone(): DateTimeZone {
	return new DateTimeZone( 'America/Bogota' );
}

function saretos_parse_date( string $date ): ?DateTimeImmutable {
	$value = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, saretos_timezone() );
	return $value && $value->format( 'Y-m-d' ) === $date && $value->getTimestamp() > 0 ? $value : null;
}

function saretos_page_url( string $route ): string {
	return (string) get_permalink( (int) get_option( 'saretos_page_' . $route, -1 ) );
}

function saretos_post( string $key ): string {
	return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
}

function saretos_query( string $key ): string {
	return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? wp_unslash( $_GET[ $key ] ) : '';
}

function saretos_web_url( string $value ): string {
	$value = trim( $value );
	if ( strlen( $value ) > 1024 || ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
		return '';
	}
	$parts = wp_parse_url( $value );
	return $parts && in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true )
		&& ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) ? esc_url_raw( $value, array( 'http', 'https' ) ) : '';
}
