<?php

defined( 'ABSPATH' ) || exit;

function saretos_admin_menu(): void {
	add_menu_page( 'Gestión de Retos', 'Retos', 'manage_options', 'saretos', 'saretos_admin_page', 'dashicons-awards' );
	add_submenu_page( 'saretos', 'Participantes y resultados', 'Participantes y resultados', 'manage_options', 'saretos-report', 'saretos_admin_report' );
	add_submenu_page( 'saretos', 'Evaluadores', 'Evaluadores', 'manage_options', 'saretos-evaluators', 'saretos_evaluators_page' );
}

function saretos_admin_assets( string $screen ): void {
	if ( isset( $_GET['page'] ) && 'saretos-report' === saretos_query( 'page' ) && current_user_can( 'manage_options' ) ) {
		wp_enqueue_style( 'saretos', plugins_url( 'assets/site.css', SARETOS_FILE ), array(), SARETOS_VERSION );
		saretos_grade_script();
	}
	if ( 'toplevel_page_saretos' !== $screen ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'saretos-media', plugins_url( 'assets/media.js', SARETOS_FILE ), array( 'jquery' ), SARETOS_VERSION, true );
}

function saretos_admin_url( array $args = array() ): string {
	return add_query_arg( array_merge( array( 'page' => 'saretos' ), $args ), admin_url( 'admin.php' ) );
}

function saretos_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$id = absint( saretos_query( 'edit' ) );
	$challenge = $id ? saretos_challenge( $id ) : null;
	if ( $id && ! $challenge ) {
		wp_die( 'El reto no existe.', '', array( 'response' => 404 ) );
	}
	$settings = $challenge['settings'] ?? saretos_defaults();
	?>
	<div class="wrap">
		<h1>Gestión de Retos</h1>
		<?php if ( '1' === saretos_query( 'guardado' ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Cambios guardados.</p></div>
		<?php endif; ?>
		<?php if ( '' !== saretos_query( 'ficticios_eliminados' ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Inscripciones ficticias eliminadas: <?php echo esc_html( absint( saretos_query( 'ficticios_eliminados' ) ) ); ?>.</p></div>
		<?php endif; ?>
		<h2>Retos e histórico <a class="page-title-action" href="<?php echo esc_url( saretos_admin_url( array( 'nuevo' => 1 ) ) ); ?>#saretos-edit">Nuevo reto</a></h2>
		<table class="widefat striped">
			<thead><tr><th scope="col">Reto</th><th scope="col">Inscripciones (Colombia)</th><th scope="col">Estado</th><th scope="col">Participantes y resultados</th></tr></thead>
			<tbody>
			<?php foreach ( saretos_challenge_list() as $item ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( saretos_admin_url( array( 'edit' => $item['id'] ) ) ); ?>#saretos-edit"><?php echo esc_html( $item['title'] ); ?></a>
						<?php if ( $item['is_demo'] ) : ?><strong> · Demostración</strong><?php endif; ?></td>
					<td><?php echo esc_html( wp_date( 'd/m/Y', (int) $item['starts'], saretos_timezone() ) . ' – ' . wp_date( 'd/m/Y', (int) $item['ends'] - 1, saretos_timezone() ) ); ?></td>
					<td><?php echo esc_html( saretos_status( $item ) ); ?></td>
					<td><a href="<?php echo esc_url( saretos_results_url( (int) $item['id'], true ) ); ?>">Consultar y evaluar</a></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<h2 id="saretos-edit"><?php echo $challenge ? 'Editar reto' : 'Nuevo reto'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="saretos_save">
			<input type="hidden" name="challenge_id" value="<?php echo esc_attr( $id ); ?>">
			<input type="hidden" name="revision" value="<?php echo esc_attr( $challenge['revision'] ?? 0 ); ?>">
			<?php wp_nonce_field( 'saretos_save_' . $id ); ?>
			<table class="form-table" role="presentation">
				<?php
				saretos_admin_input( 'title', 'Título', $challenge['title'] ?? '', 'text', 'required maxlength="200"' );
				saretos_admin_text( 'description', 'Descripción de la página interna', $settings['description'] );
				saretos_admin_text( 'promotion', 'Descripción breve de la banda', $settings['promotion'], 'Se muestra en la portada.' );
				saretos_admin_input( 'prize', 'Premio mostrado en la banda', $settings['prize'], 'text', 'maxlength="180"', 'Aparece junto al contador en la portada.' );
				saretos_admin_input( 'button', 'Botón de la banda', $settings['button'], 'text', 'required maxlength="80"' );
				saretos_admin_image( 'band_image', 'Fondo de la banda', (int) $settings['band_image'] );
				saretos_admin_image( 'page_image', 'Fotografía del banner de inscripción', (int) $settings['page_image'], 'Se muestra al inicio de /retos/, debajo del menú. Puedes cambiarla o quitarla desde la biblioteca de medios.' );
				saretos_admin_input( 'starts', 'Inicio', $challenge ? wp_date( 'Y-m-d', (int) $challenge['starts'], saretos_timezone() ) : '', 'date', 'required' );
				saretos_admin_input( 'ends', 'Fin (día incluido)', $challenge ? wp_date( 'Y-m-d', (int) $challenge['ends'] - 1, saretos_timezone() ) : '', 'date', 'required', 'El cierre se aplica a medianoche en Colombia, al terminar este día.' );
				saretos_admin_text( 'rules', 'Pasos para participar y requisitos', $settings['rules'], 'Este contenido aparece en «Cómo participar». Puedes organizar los pasos en una lista.' );
				saretos_admin_text( 'announcement', 'Información del anuncio y redes oficiales', $settings['announcement'], 'La fecha de inscripciones se muestra automáticamente con las fechas de Inicio y Fin configuradas arriba.' );
				saretos_admin_input( 'minimum_age', 'Edad mínima según los términos', $settings['minimum_age'], 'number', 'min="1" max="999" step="1"' );
				saretos_admin_input( 'terms', 'URL de términos y condiciones', $settings['terms'], 'url', 'maxlength="1024"' );
				saretos_admin_input( 'privacy', 'URL de política de datos personales', $settings['privacy'], 'url', 'maxlength="1024"' );
				saretos_admin_input( 'spotify', 'URL de Spotify', $settings['spotify'], 'url', 'maxlength="1024"' );
				saretos_admin_input( 'spotify_text', 'Texto del botón de Spotify', $settings['spotify_text'], 'text', 'maxlength="100"' );
				saretos_admin_text( 'success', 'Mensaje de confirmación', $settings['success'] );
				saretos_admin_text( 'closed_text', 'Aviso público de cierre', $settings['closed_text'] );
				$lines = array();
				foreach ( $settings['criteria'] as $criterion ) {
					$lines[] = $criterion['label'] . ' | ' . saretos_weight( (int) $criterion['weight'] );
				}
				saretos_admin_text( 'criteria', 'Criterios de evaluación', implode( "\n", $lines ), 'Escribe un criterio por línea con el formato Nombre | Peso. Los pesos deben sumar 100. Las puntuaciones guardadas conservan sus criterios y pesos.' );
				saretos_admin_checkbox( 'active', 'Activar reto', ! empty( $challenge['active_slot'] ), 'Desactiva el reto anterior antes de activar otro.' );
				saretos_admin_checkbox( 'closed', 'Cerrar inscripciones manualmente', ! empty( $challenge['closed'] ), 'Oculta la banda y muestra el aviso de cierre.' );
				if ( ! $challenge && saretos_demo_allowed() ) {
					saretos_admin_checkbox( 'is_demo', 'Reto de demostración', false, 'Solo para entornos locales o de desarrollo. Identifica todas sus inscripciones como ficticias.' );
				}
				?>
			</table>
			<?php submit_button( 'Guardar reto' ); ?>
		</form>
		<h2>Integración con la portada</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="saretos_settings">
			<?php wp_nonce_field( 'saretos_settings' ); ?>
			<table class="form-table" role="presentation">
				<?php saretos_admin_input( 'anchor', 'ID interno del contenedor siguiente en Elementor', get_option( 'saretos_anchor', '' ), 'text', 'maxlength="32" pattern="[a-zA-Z0-9_-]+"', 'La banda se inserta antes de ese contenedor en el inicio. Si usas un bloque o un widget de shortcode para ubicarla, deja este campo vacío y coloca [sa_retos_banda].' ); ?>
			</table>
			<?php submit_button( 'Guardar integración' ); ?>
		</form>
		<p><a href="<?php echo esc_url( saretos_page_url( 'public' ) ); ?>">Abrir la página de retos</a> · <a href="<?php echo esc_url( saretos_page_url( 'evaluation' ) ); ?>">Abrir evaluación</a></p>
		<?php if ( $challenge && $challenge['is_demo'] && saretos_demo_allowed() ) : ?>
			<h2>Inscripciones de demostración</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="saretos_purge_demo">
				<input type="hidden" name="challenge_id" value="<?php echo esc_attr( $id ); ?>">
				<?php wp_nonce_field( 'saretos_purge_demo_' . $id ); ?>
				<p><label><input type="checkbox" value="1" name="confirm_demo" required> Quiero eliminar las inscripciones ficticias y sus puntuaciones de este reto de demostración.</label></p>
				<?php submit_button( 'Eliminar inscripciones ficticias', 'delete' ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

function saretos_status( array $item ): string {
	if ( ! $item['active_slot'] ) {
		return 'Inactivo';
	}
	if ( $item['closed'] ) {
		return 'Cerrado manualmente';
	}
	if ( time() < (int) $item['starts'] ) {
		return 'Programado';
	}
	return time() < (int) $item['ends'] ? 'Vigente' : 'Finalizado';
}

function saretos_admin_input( string $key, string $label, $value, string $type, string $attributes = '', string $description = '' ): void {
	$id = 'saretos-' . $key;
	?>
	<tr><th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
		<input name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $id ); ?>" type="<?php echo esc_attr( $type ); ?>" class="regular-text"
			value="<?php echo esc_attr( $value ); ?>" <?php echo $attributes; // Attributes come from static declarations. ?>>
		<?php if ( $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
	</td></tr>
	<?php
}

function saretos_admin_text( string $key, string $label, string $value, string $description = '' ): void {
	$id = 'saretos-' . $key;
	?>
	<tr><th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
		<textarea name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $id ); ?>" class="large-text" rows="4"><?php echo esc_textarea( $value ); ?></textarea>
		<?php if ( $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
	</td></tr>
	<?php
}

function saretos_admin_checkbox( string $key, string $label, bool $value, string $description ): void {
	?>
	<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td>
		<label><input type="checkbox" name="<?php echo esc_attr( $key ); ?>" value="1" <?php checked( $value ); ?>> <?php echo esc_html( $label ); ?></label>
		<p class="description"><?php echo esc_html( $description ); ?></p>
	</td></tr>
	<?php
}

function saretos_admin_image( string $key, string $label, int $id, string $description = '' ): void {
	$url = wp_get_attachment_image_url( $id, 'medium' );
	?>
	<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td data-saretos-media>
		<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $id ); ?>">
		<p><img alt="" style="max-width:220px;height:auto" <?php if ( $url ) : ?>src="<?php echo esc_url( $url ); ?>"<?php else : ?>hidden<?php endif; ?>></p>
		<?php if ( $description ) : ?><p class="description"><?php echo esc_html( $description ); ?></p><?php endif; ?>
		<button type="button" class="button" data-media-select aria-label="<?php echo esc_attr( 'Seleccionar ' . strtolower( $label ) ); ?>">Seleccionar imagen</button>
		<button type="button" class="button-link-delete" data-media-clear <?php if ( ! $url ) : ?>hidden<?php endif; ?>>Quitar imagen</button>
	</td></tr>
	<?php
}

function saretos_admin_guard( string $action ): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'No tienes permiso para realizar esta acción.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( $action );
}

function saretos_admin_error( string $message, int $status = 400 ): void {
	wp_die( esc_html( $message ), 'Gestión de Retos', array( 'response' => $status, 'back_link' => true ) );
}

function saretos_weight( int $basis_points ): string {
	return rtrim( rtrim( number_format( $basis_points / 100, 2, '.', '' ), '0' ), '.' );
}

function saretos_parse_criteria( string $text ): array {
	$criteria = array();
	foreach ( preg_split( '/\R/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY ) ?: array() as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = explode( '|', $line );
		$label = sanitize_text_field( trim( $parts[0] ) );
		$weight = str_replace( ',', '.', trim( $parts[1] ?? '' ) );
		if ( count( $parts ) !== 2 || ! $label || strlen( $label ) > 160 || ! preg_match( '/^\d{1,3}(?:\.\d{1,2})?$/D', $weight ) ) {
			return array();
		}
		$basis_points = (int) round( (float) $weight * 100 );
		if ( $basis_points < 1 || $basis_points > 10000 || in_array( $label, array_column( $criteria, 'label' ), true ) ) {
			return array();
		}
		$criteria[] = array( 'label' => $label, 'weight' => $basis_points );
	}
	return count( $criteria ) <= 20 && array_sum( array_column( $criteria, 'weight' ) ) === 10000 ? $criteria : array();
}

function saretos_save_challenge(): void {
	global $wpdb;
	$id = absint( saretos_post( 'challenge_id' ) );
	saretos_admin_guard( 'saretos_save_' . $id );
	$old = $id ? saretos_challenge( $id ) : null;
	if ( $id && ! $old ) {
		saretos_admin_error( 'El reto no existe.', 404 );
	}

	$title = sanitize_text_field( saretos_post( 'title' ) );
	$starts = saretos_parse_date( saretos_post( 'starts' ) );
	$ends = saretos_parse_date( saretos_post( 'ends' ) );
	if ( '' === $title || strlen( $title ) > 200 || ! $starts || ! $ends || $ends < $starts ) {
		saretos_admin_error( 'Indica un título y un periodo de inscripción válido.' );
	}

	$demo = $old ? (int) $old['is_demo'] : (int) ( saretos_demo_allowed() && '1' === saretos_post( 'is_demo' ) );
	$active = '1' === saretos_post( 'active' );
	$closed = (int) ( '1' === saretos_post( 'closed' ) );

	$settings = saretos_defaults();
	foreach ( array( 'description', 'rules', 'announcement', 'success', 'closed_text' ) as $key ) {
		$settings[ $key ] = wp_kses_post( saretos_post( $key ) );
	}
	foreach ( array( 'button', 'spotify_text' ) as $key ) {
		$settings[ $key ] = sanitize_text_field( saretos_post( $key ) ) ?: $settings[ $key ];
	}
	$settings['promotion'] = sanitize_textarea_field( saretos_post( 'promotion' ) );
	$settings['prize'] = sanitize_text_field( saretos_post( 'prize' ) );
	if ( mb_strlen( $settings['prize'] ) > 180 ) {
		saretos_admin_error( 'El texto del premio no puede superar 180 caracteres.' );
	}
	foreach ( array( 'terms', 'privacy', 'spotify' ) as $key ) {
		$value = trim( saretos_post( $key ) );
		$settings[ $key ] = saretos_web_url( $value );
		if ( $value && ! $settings[ $key ] ) {
			saretos_admin_error( 'Los enlaces de documentos y de Spotify deben ser URL web completas.' );
		}
	}
	foreach ( array( 'band_image', 'page_image' ) as $key ) {
		$media = absint( saretos_post( $key ) );
		if ( $media && ! wp_attachment_is_image( $media ) ) {
			saretos_admin_error( 'Selecciona imágenes válidas de la biblioteca de medios.' );
		}
		$settings[ $key ] = $media;
	}
	$age = saretos_post( 'minimum_age' );
	if ( '' !== $age && ( ! preg_match( '/^\d{1,3}$/D', $age ) || (int) $age < 1 ) ) {
		saretos_admin_error( 'Indica una edad mínima válida.' );
	}
	$settings['minimum_age'] = '' === $age ? '' : (int) $age;
	if ( $active && ( ! $demo ? ! saretos_ready( $settings ) : ! saretos_demo_allowed() ) ) {
		saretos_admin_error( 'Antes de activar un reto real, configura la edad mínima y los enlaces de términos y política de datos. Los retos de demostración se activan en entornos de desarrollo.' );
	}

	$settings['criteria'] = saretos_parse_criteria( saretos_post( 'criteria' ) );
	if ( ! $settings['criteria'] ) {
		saretos_admin_error( 'Revisa los criterios. Usa nombres distintos y pesos positivos que sumen exactamente 100.' );
	}
	$version = $old ? (int) $old['rubric_version'] + (int) ( $old['settings']['criteria'] !== $settings['criteria'] ) : 1;
	$revision = absint( saretos_post( 'revision' ) );
	$data = array(
		'title'          => $title,
		'settings'       => wp_json_encode( $settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		'starts'         => $starts->getTimestamp(),
		'ends'           => $ends->modify( '+1 day' )->getTimestamp(),
		'active_slot'    => $active ? 1 : null,
		'closed'         => $closed,
		'is_demo'        => $demo,
		'revision'       => $revision + 1,
		'rubric_version' => $version,
	);
	if ( ! $old ) {
		// Installations from 1.0 require this legacy column on insert.
		$data['secret_hash'] = '';
	}

	$table = saretos_table( 'challenges' );
	$previous = $wpdb->suppress_errors();
	$result = $old ? $wpdb->update( $table, $data, array( 'id' => $id, 'revision' => $revision ) ) : $wpdb->insert( $table, $data );
	$new_id = (int) $wpdb->insert_id;
	$wpdb->suppress_errors( $previous );
	if ( ! $result ) {
		$current = saretos_challenge();
		if ( $active && $current && (int) $current['id'] !== $id ) {
			saretos_admin_error( 'Ya existe un reto activo. Desactívalo antes de activar este.', 409 );
		}
		saretos_admin_error( false === $result ? 'No se pudo guardar el reto.' : 'El reto cambió en otra sesión. Abre de nuevo el registro para editarlo.', false === $result ? 500 : 409 );
	}
	wp_safe_redirect( saretos_admin_url( array( 'edit' => $id ?: $new_id, 'guardado' => 1 ) ) . '#saretos-edit', 303 );
	exit;
}

function saretos_save_settings(): void {
	saretos_admin_guard( 'saretos_settings' );
	$anchor = trim( saretos_post( 'anchor' ) );
	if ( $anchor && ! preg_match( '/^[a-zA-Z0-9_-]{1,32}$/D', $anchor ) ) {
		saretos_admin_error( 'Indica un ID de Elementor válido.' );
	}
	update_option( 'saretos_anchor', $anchor, false );
	wp_safe_redirect( saretos_admin_url( array( 'guardado' => 1 ) ), 303 );
	exit;
}

function saretos_admin_report(): void {
	if ( current_user_can( 'manage_options' ) ) {
		echo '<div class="wrap">' . saretos_evaluation_view( true ) . '</div>'; // Escaped in the view.
	}
}

function saretos_purge_demo(): void {
	global $wpdb;
	$id = absint( saretos_post( 'challenge_id' ) );
	saretos_admin_guard( 'saretos_purge_demo_' . $id );
	if ( ! $id || ! saretos_demo_allowed() || '1' !== saretos_post( 'confirm_demo' ) ) {
		saretos_admin_error( 'La eliminación está disponible únicamente para inscripciones ficticias confirmadas en un entorno de desarrollo.', 403 );
	}
	$entries = saretos_table( 'entries' );
	$challenges = saretos_table( 'challenges' );
	$deleted = $wpdb->query( $wpdb->prepare( "DELETE e FROM $entries e JOIN $challenges c ON c.id = e.challenge_id WHERE c.id = %d AND c.is_demo = 1", $id ) );
	if ( false === $deleted ) {
		saretos_admin_error( 'No se pudieron eliminar las inscripciones ficticias.', 500 );
	}
	wp_safe_redirect( saretos_admin_url( array( 'edit' => $id, 'ficticios_eliminados' => $deleted ) ), 303 );
	exit;
}
