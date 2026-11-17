<?php

defined( 'ABSPATH' ) || exit;

function saretos_grade_script(): void {
	wp_enqueue_script( 'saretos-evaluation', plugins_url( 'assets/evaluation.js', SARETOS_FILE ), array(), SARETOS_VERSION, true );
}

function saretos_review_states(): array {
	return array( 'pending' => 'Pendiente', 'verified' => 'Verificada', 'discarded' => 'Descartada' );
}

function saretos_results_url( int $id = 0, bool $admin = false, array $args = array() ): string {
	$url = $admin ? saretos_admin_url( array( 'page' => 'saretos-report' ) ) : saretos_page_url( 'evaluation' );
	if ( $id ) {
		$args['reto'] = $id;
	}
	return $args ? add_query_arg( $args, $url ) : $url;
}

function saretos_entry_filters( bool $from_post = false ): array {
	$search = $from_post ? saretos_post( 'buscar' ) : saretos_query( 'buscar' );
	$review = $from_post ? saretos_post( 'revision_estado' ) : saretos_query( 'revision_estado' );
	$graded = $from_post ? saretos_post( 'calificacion' ) : saretos_query( 'calificacion' );
	return array(
		'buscar'          => mb_substr( sanitize_text_field( $search ), 0, 120 ),
		'revision_estado' => isset( saretos_review_states()[ $review ] ) ? $review : '',
		'calificacion'    => in_array( $graded, array( 'graded', 'ungraded' ), true ) ? $graded : '',
	);
}

function saretos_entries_where( int $id, array $filters ): string {
	global $wpdb;
	$where = $wpdb->prepare( 'challenge_id = %d', $id );
	if ( $filters['buscar'] ) {
		$like = '%' . $wpdb->esc_like( $filters['buscar'] ) . '%';
		$phone = saretos_phone( $filters['buscar'] );
		$where .= $wpdb->prepare( ' AND (name LIKE %s OR email LIKE %s OR phone LIKE %s OR location LIKE %s OR profile LIKE %s)',
			$like, strtolower( $like ), $phone ?: $like, $like, $like
		);
	}
	if ( $filters['revision_estado'] ) {
		$where .= $wpdb->prepare( ' AND review = %s', $filters['revision_estado'] );
	}
	if ( $filters['calificacion'] ) {
		$where .= 'graded' === $filters['calificacion'] ? ' AND score IS NOT NULL' : ' AND score IS NULL';
	}
	return $where;
}

function saretos_grading( array $entry ): ?array {
	$grading = json_decode( (string) $entry['grading'], true );
	return is_array( $grading ) ? $grading : null;
}

function saretos_grader_label( array $reviewer ): string {
	$name = sanitize_text_field( (string) ( $reviewer['name'] ?? '' ) );
	$login = sanitize_user( (string) ( $reviewer['login'] ?? '' ), true );
	if ( $name && $login ) {
		return $name . ' (@' . $login . ')';
	}
	if ( $name ) {
		return $name;
	}
	$id = absint( $reviewer['id'] ?? 0 );
	if ( $id && ( ! $name || ! $login ) ) {
		$user = get_user_by( 'id', $id );
		if ( $user ) {
			$name = $name ?: sanitize_text_field( $user->display_name );
			$login = $login ?: sanitize_user( $user->user_login, true );
		}
	}
	if ( $name && $login ) {
		return $name . ' (@' . $login . ')';
	}
	return $id ? 'Usuario #' . $id : 'Evaluador no identificado';
}

function saretos_score_text( $score, bool $partial = false ): string {
	if ( null === $score ) {
		return $partial ? 'Calificación parcial' : 'Sin calificar';
	}
	return number_format_i18n( (float) $score, 2 ) . ' / 100';
}


function saretos_grade_error( string $message, int $status ): WP_Error {
	return new WP_Error( 'grade', $message, $status );
}

function saretos_update_entry() {
	global $wpdb;
	if ( ! current_user_can( 'saretos_evaluate' ) ) {
		return saretos_grade_error( 'No tienes permiso para evaluar retos.', 403 );
	}
	$id = absint( saretos_post( 'challenge_id' ) );
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! $id || ! wp_verify_nonce( saretos_post( '_wpnonce' ), 'saretos_grade_' . $id ) ) {
		return saretos_grade_error( 'La sesión o el formulario caducaron. Actualiza la página.', 403 );
	}

	$entry_id = absint( saretos_post( 'entry_id' ) );
	$revision = absint( saretos_post( 'revision' ) );
	$review = saretos_post( 'review' );
	if ( ! $entry_id || ! $revision || ! isset( saretos_review_states()[ $review ] ) ) {
		return saretos_grade_error( 'Selecciona un estado de revisión válido.', 422 );
	}
	$table = saretos_table( 'entries' );
	$entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE challenge_id = %d AND id = %d", $id, $entry_id ), ARRAY_A );
	$challenge = saretos_challenge( $id );
	if ( ! $entry || ! $challenge ) {
		return saretos_grade_error( 'La inscripción no está disponible para este reto.', 404 );
	}
	$grading = saretos_grading( $entry );
	$version = (int) ( $grading['version'] ?? $challenge['rubric_version'] );
	if ( $revision !== (int) $entry['revision'] || $version !== absint( saretos_post( 'rubric_version' ) ) ) {
		return saretos_grade_error( 'La inscripción o la rúbrica cambió. Abre de nuevo la página antes de guardar.', 409 );
	}

	$criteria = $grading['criteria'] ?? $challenge['settings']['criteria'];
	$scores = isset( $_POST['scores'] ) && is_array( $_POST['scores'] ) ? wp_unslash( $_POST['scores'] ) : array();
	$entered = array_filter( $scores, static fn( $value ) => is_string( $value ) && '' !== trim( $value ) );
	$final = null;
	if ( $entered || $grading ) {
		$user = wp_get_current_user();
		$reviewer = array(
			'id' => get_current_user_id(), 'name' => $user->display_name, 'login' => $user->user_login,
			'at' => time(),
		);
		$total = 0;
		$complete = true;
		foreach ( $criteria as $index => &$criterion ) {
			$value = $scores[ $index ] ?? '';
			if ( is_string( $value ) && '' !== trim( $value ) ) {
				if ( ! preg_match( '/^[0-9]{1,3}(?:[.,][0-9]{1,2})?$/D', $value ) ) {
					return saretos_grade_error( 'Cada criterio admite una puntuación entre 0 y 100.', 422 );
				}
				$points = (int) round( (float) str_replace( ',', '.', $value ) * 100 );
				if ( $points > 10000 ) {
					return saretos_grade_error( 'Cada puntuación debe estar entre 0 y 100.', 422 );
				}
				$score = $points / 100;
				$previous_points = isset( $criterion['score'] ) ? (int) round( (float) $criterion['score'] * 100 ) : null;
				if ( null === $previous_points || $previous_points !== $points ) {
					if ( isset( $criterion['score'] ) ) {
						$criterion['history'] = array_slice( array_merge( $criterion['history'] ?? array(), array( array(
							'score' => $criterion['score'], 'reviewer' => $criterion['reviewer'] ?? array( 'id' => $grading['grader'] ?? 0 ),
						) ) ), -10 );
					}
					$criterion['score'] = $score;
					$criterion['reviewer'] = $reviewer;
				}
			}
			if ( ! isset( $criterion['score'] ) ) {
				$complete = false;
				continue;
			}
			$total += (int) round( (float) $criterion['score'] * 100 ) * (int) $criterion['weight'];
		}
		unset( $criterion );
		if ( $complete ) {
			$final = number_format( intdiv( $total + 5000, 10000 ) / 100, 2, '.', '' );
		}
		$grading = array(
			'version' => $version, 'criteria' => $criteria,
			'grader' => $grading['grader'] ?? get_current_user_id(),
			'graded_at' => $grading['graded_at'] ?? time(), 'updated_at' => time(),
		);
	}

	$challenges = saretos_table( 'challenges' );
	$updated = $wpdb->query( $wpdb->prepare(
		"UPDATE $table e JOIN $challenges c ON c.id = e.challenge_id
		SET e.review = %s, e.score = NULLIF(%s, ''), e.grading = NULLIF(%s, ''), e.revision = e.revision + 1
		WHERE e.challenge_id = %d AND e.id = %d AND e.revision = %d
			AND (e.grading IS NOT NULL OR c.rubric_version = %d)",
		$review, $final ?? '', $grading ? wp_json_encode( $grading, JSON_UNESCAPED_UNICODE ) : '',
		$id, $entry_id, $revision, $version
	) );
	if ( ! $updated ) {
		return saretos_grade_error( false === $updated ? 'No se pudieron guardar los cambios.' : 'La inscripción o la rúbrica cambió. Actualiza la página.',
			false === $updated ? 500 : 409
		);
	}
	return array(
		'id'           => $entry_id,
		'challenge_id' => $id,
		'revision'     => $revision + 1,
		'score'        => saretos_score_text( $final, (bool) $grading ),
		'authors'      => saretos_criterion_authors( $grading ),
		'review'       => saretos_review_states()[ $review ],
		'message'      => 'Cambios guardados.',
	);
}

function saretos_grade_forbidden(): void {
	wp_send_json_error( array( 'message' => 'No tienes permiso para evaluar retos.' ), 403 );
}

function saretos_grade_ajax(): void {
	nocache_headers();
	$result = saretos_update_entry();
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ), (int) $result->get_error_data() );
	}
	$result['ranking'] = saretos_ranking( $result['challenge_id'] );
	wp_send_json_success( $result );
}

function saretos_grade_submit(): void {
	$result = saretos_update_entry();
	if ( is_wp_error( $result ) ) {
		saretos_admin_error( $result->get_error_message(), (int) $result->get_error_data() );
	}
	$admin = '1' === saretos_post( 'admin_report' ) && current_user_can( 'manage_options' );
	$args = array_merge( array_filter( saretos_entry_filters( true ) ), array(
		'paginacion' => max( 1, absint( saretos_post( 'paginacion' ) ) ),
		'guardado' => $result['id'],
	) );
	wp_safe_redirect( saretos_results_url( $result['challenge_id'], $admin, $args ) . '#saretos-entry-' . $result['id'], 303 );
	exit;
}

function saretos_evaluation_view( bool $admin = false ): string {
	global $wpdb;
	$title = $admin ? 'Participantes y resultados' : 'Evaluación de retos';
	ob_start();
	?>
	<div class="saretos saretos-page saretos-evaluation<?php echo $admin ? ' saretos-evaluation-admin' : ''; ?>">
		<div class="saretos-shell">
			<h1><?php echo esc_html( $title ); ?></h1>
			<?php if ( ! current_user_can( 'saretos_evaluate' ) ) : ?>
				<p>Esta página está reservada a evaluadores autorizados.</p>
			<?php else :
				$challenges = saretos_challenge_list();
				$id = absint( saretos_query( 'reto' ) );
				if ( ! $id && $challenges ) {
					$active = saretos_challenge();
					$id = (int) ( $active['id'] ?? $challenges[0]['id'] );
				}
				$challenge = $id ? saretos_challenge( $id ) : null;
				$filters = saretos_entry_filters();
				saretos_filter_view( $challenges, $id, $filters, $admin );
				if ( $challenge ) {
					?>
					<section class="saretos-ranking" aria-labelledby="saretos-ranking-title">
						<h2 id="saretos-ranking-title">Primeros cinco puestos</h2>
						<div data-saretos-ranking aria-live="polite"><?php echo saretos_ranking( $id ); // Escaped in the view. ?></div>
						<p>Se puede guardar cada criterio por separado. El ranking incluye solo calificaciones completas; los empates se resuelven por fecha de inscripción.</p>
					</section>
					<?php
					$table = saretos_table( 'entries' );
					$where = saretos_entries_where( $id, $filters );
					$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE $where" );
					$size = 25;
					$pages = max( 1, (int) ceil( $count / $size ) );
					$page = max( 1, min( $pages, absint( saretos_query( 'paginacion' ) ) ) );
					$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", $size, ( $page - 1 ) * $size ), ARRAY_A ) ?: array();
					saretos_entries_view( $challenge, $rows, $count, $filters, $page, $pages, $admin );
				} else {
					echo '<p>Selecciona un reto disponible para consultar sus inscripciones.</p>';
				}
			endif; ?>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}

function saretos_ranking( int $id ): string {
	global $wpdb;
	$table = saretos_table( 'entries' );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT name, score, grading FROM $table WHERE challenge_id = %d AND score IS NOT NULL AND review <> 'discarded'
		ORDER BY score DESC, registered_at ASC, id ASC LIMIT 5",
		$id
	), ARRAY_A );
	if ( ! $rows ) {
		return '<p>Aún no hay participantes con una calificación completa.</p>';
	}
	ob_start();
	?>
	<ol>
		<?php foreach ( $rows as $row ) : $grading = saretos_grading( $row ); ?>
			<li><div><span><?php echo esc_html( $row['name'] ); ?></span><strong><?php echo esc_html( saretos_score_text( $row['score'] ) ); ?></strong></div>
				<?php if ( $grading ) : ?><details class="saretos-ranking-detail"><summary>Ver criterios y evaluadores</summary><ul>
					<?php foreach ( $grading['criteria'] as $criterion ) : ?><li><span><?php echo esc_html( $criterion['label'] ); ?> · <?php echo esc_html( saretos_weight( (int) $criterion['weight'] ) ); ?>%</span><strong><?php echo esc_html( isset( $criterion['score'] ) ? saretos_score_text( $criterion['score'] ) : 'Pendiente' ); ?></strong><br><small><?php echo esc_html( isset( $criterion['reviewer'] ) ? saretos_grader_label( $criterion['reviewer'] ) : ( isset( $grading['grader'] ) ? 'Evaluación histórica en conjunto · Usuario #' . absint( $grading['grader'] ) : 'Sin registro histórico por criterio' ) ); ?></small></li><?php endforeach; ?>
				</ul></details><?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php
	return (string) ob_get_clean();
}

function saretos_filter_view( array $challenges, int $id, array $filters, bool $admin ): void {
	if ( ! $challenges ) {
		return;
	}
	$url = $admin ? admin_url( 'admin.php' ) : saretos_page_url( 'evaluation' );
	$permalink = $admin ? array( 'page' => 'saretos-report' ) : array();
	if ( ! $admin && wp_parse_url( $url, PHP_URL_QUERY ) ) {
		parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $permalink );
	}
	?>
	<form action="<?php echo esc_url( $url ); ?>" method="get" class="saretos-filters">
		<?php foreach ( $permalink as $key => $value ) : ?>
			<?php if ( is_string( $value ) ) : ?><input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>"><?php endif; ?>
		<?php endforeach; ?>
		<div class="saretos-field">
			<label for="saretos-filter-challenge">Reto</label>
			<select id="saretos-filter-challenge" name="reto">
				<?php foreach ( $challenges as $item ) : ?>
					<option value="<?php echo esc_attr( $item['id'] ); ?>" <?php selected( $id, $item['id'] ); ?>><?php echo esc_html( $item['title'] . ( $item['is_demo'] ? ' · Demostración' : '' ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="saretos-field"><label for="saretos-filter-search">Buscar participantes</label>
			<input id="saretos-filter-search" type="search" name="buscar" maxlength="120" value="<?php echo esc_attr( $filters['buscar'] ); ?>"></div>
		<div class="saretos-field"><label for="saretos-filter-status">Revisión</label>
			<select id="saretos-filter-status" name="revision_estado">
				<option value="">Todas</option>
				<?php foreach ( saretos_review_states() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $filters['revision_estado'] ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="saretos-field"><label for="saretos-filter-grade">Calificación</label>
			<select id="saretos-filter-grade" name="calificacion">
				<option value="">Todas</option>
				<option value="ungraded" <?php selected( 'ungraded', $filters['calificacion'] ); ?>>Sin calificar</option>
				<option value="graded" <?php selected( 'graded', $filters['calificacion'] ); ?>>Calificadas</option>
			</select>
		</div>
		<button type="submit" class="saretos-button">Filtrar</button>
	</form>
	<?php
}

function saretos_entries_view( array $challenge, array $rows, int $count, array $filters, int $page, int $pages, bool $admin ): void {
	$id = (int) $challenge['id'];
	$states = saretos_review_states();
	?>
	<section aria-labelledby="saretos-entries-title">
		<div class="saretos-report-heading">
			<h2 id="saretos-entries-title"><?php echo esc_html( $challenge['title'] ); ?></h2>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="saretos_csv">
					<input type="hidden" name="challenge_id" value="<?php echo esc_attr( $id ); ?>">
					<?php wp_nonce_field( 'saretos_csv_' . $id, '_wpnonce', false ); ?>
					<?php saretos_filter_fields( $filters ); ?>
					<button type="submit" class="saretos-button">Exportar CSV</button>
				</form>
			<?php endif; ?>
		</div>
		<p><?php echo esc_html( sprintf( _n( '%s inscripción', '%s inscripciones', $count ), number_format_i18n( $count ) ) ); ?><?php echo $challenge['is_demo'] ? ' · Datos ficticios' : ''; ?></p>
		<?php if ( ! $rows ) : ?>
			<p>No hay inscripciones que coincidan con la consulta.</p>
		<?php else : ?>
			<div class="saretos-table-area">
				<table class="saretos-table" role="table">
					<thead role="rowgroup">
						<tr role="row"><th scope="col" role="columnheader">Participante</th><th scope="col" role="columnheader">Video y perfil</th><th scope="col" role="columnheader">Evaluación</th></tr>
					</thead>
					<tbody role="rowgroup">
					<?php foreach ( $rows as $entry ) : ?>
						<tr role="row" id="saretos-entry-<?php echo esc_attr( $entry['id'] ); ?>">
							<td role="cell"><?php saretos_entry_identity( $entry, $states ); ?></td>
							<td role="cell"><?php saretos_entry_social( $entry ); ?></td>
							<td role="cell"><?php saretos_grade_form( $entry, $challenge, $states, $filters, $page, $admin ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
		<?php if ( $pages > 1 ) : ?>
			<nav aria-label="Páginas de inscripciones" class="saretos-pagination">
				<?php if ( $page > 1 ) : ?>
					<a href="<?php echo esc_url( saretos_results_url( $id, $admin, array_merge( array_filter( $filters ), array( 'paginacion' => $page - 1 ) ) ) ); ?>">Anterior</a>
				<?php endif; ?>
				<span><?php echo esc_html( sprintf( 'Página %1$d de %2$d', $page, $pages ) ); ?></span>
				<?php if ( $page < $pages ) : ?>
					<a href="<?php echo esc_url( saretos_results_url( $id, $admin, array_merge( array_filter( $filters ), array( 'paginacion' => $page + 1 ) ) ) ); ?>">Siguiente</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</section>
	<?php
}

function saretos_filter_fields( array $filters ): void {
	foreach ( $filters as $key => $value ) {
		printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $key ), esc_attr( $value ) );
	}
}

function saretos_entry_identity( array $entry, array $states ): void {
	?>
	<strong><?php echo esc_html( $entry['name'] ); ?></strong>
	<?php $grading = saretos_grading( $entry ); ?>
	<p><span data-review><?php echo esc_html( $states[ $entry['review'] ] ); ?></span> · <span data-score><?php echo esc_html( saretos_score_text( $entry['score'], (bool) $grading ) ); ?></span></p>
	<details>
		<summary>Datos de inscripción</summary>
		<dl>
			<dt>Edad</dt><dd><?php echo esc_html( $entry['age'] ); ?></dd>
			<dt>Ciudad y departamento</dt><dd><?php echo esc_html( $entry['location'] ); ?></dd>
			<dt>WhatsApp</dt><dd><?php echo esc_html( $entry['phone'] ); ?></dd>
			<dt>Correo electrónico</dt><dd><?php echo esc_html( $entry['email'] ); ?></dd>
			<dt>Fecha y hora (Colombia)</dt><dd><?php echo esc_html( wp_date( 'd/m/Y H:i:s', (int) $entry['registered_at'], saretos_timezone() ) ); ?></dd>
			<dt>Consentimientos obligatorios</dt><dd>Aceptados</dd>
			<dt>Autorización para compartir</dt><dd><?php echo $entry['share_video'] ? 'Sí' : 'No'; ?></dd>
		</dl>
	</details>
	<?php
}

function saretos_entry_social( array $entry ): void {
	foreach ( saretos_entry_videos( $entry ) as $platform => $url ) {
		$label = saretos_platforms()[ $platform ] ?? $platform;
		printf( '<p><a href="%s" target="_blank" rel="noopener noreferrer">Abrir video en %s</a></p>', esc_url( $url ), esc_html( $label ) );
	}
	?>
	<div>
		<?php if ( saretos_web_url( $entry['profile'] ) ) : ?>
			<a href="<?php echo esc_url( $entry['profile'] ); ?>" target="_blank" rel="noopener noreferrer">Abrir perfil</a>
		<?php else : ?>
			<?php echo esc_html( $entry['profile'] ); ?>
		<?php endif; ?>
	</div>
	<?php
}

function saretos_criterion_authors( ?array $grading ): array {
	$authors = array();
	foreach ( $grading['criteria'] ?? array() as $index => $criterion ) {
		if ( ! isset( $criterion['score'] ) ) {
			$authors[ $index ] = 'Pendiente de calificar';
			continue;
		}
		$authors[ $index ] = isset( $criterion['reviewer'] ) ? saretos_grader_label( $criterion['reviewer'] ) : ( isset( $grading['grader'] ) ? 'Evaluación histórica en conjunto · Usuario #' . absint( $grading['grader'] ) : 'Evaluación anterior' );
	}
	return $authors;
}

function saretos_grade_form( array $entry, array $challenge, array $states, array $filters, int $page, bool $admin ): void {
	$grading = saretos_grading( $entry );
	$criteria = $grading['criteria'] ?? $challenge['settings']['criteria'];
	$version = $grading['version'] ?? $challenge['rubric_version'];
	?>
	<form class="saretos-grade" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
		<input type="hidden" name="action" value="saretos_grade">
		<input type="hidden" name="challenge_id" value="<?php echo esc_attr( $challenge['id'] ); ?>">
		<input type="hidden" name="entry_id" value="<?php echo esc_attr( $entry['id'] ); ?>">
		<input type="hidden" name="revision" value="<?php echo esc_attr( $entry['revision'] ); ?>">
		<input type="hidden" name="rubric_version" value="<?php echo esc_attr( $version ); ?>">
		<input type="hidden" name="paginacion" value="<?php echo esc_attr( $page ); ?>">
		<input type="hidden" name="admin_report" value="<?php echo esc_attr( (int) $admin ); ?>">
		<?php wp_nonce_field( 'saretos_grade_' . $challenge['id'], '_wpnonce', false ); ?>
		<?php saretos_filter_fields( $filters ); ?>
		<div class="saretos-field">
			<label for="saretos-review-<?php echo esc_attr( $entry['id'] ); ?>">Estado de revisión</label>
			<select name="review" id="saretos-review-<?php echo esc_attr( $entry['id'] ); ?>">
				<?php foreach ( $states as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $entry['review'] ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<fieldset class="saretos-criteria">
			<legend><?php echo esc_html( 'Criterios · Versión ' . $version . ' · 0 a 100' ); ?></legend>
			<?php foreach ( $criteria as $index => $criterion ) :
				$key = 'saretos-criterion-' . $entry['id'] . '-' . $index;
				?>
				<div class="saretos-field">
					<label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $criterion['label'] . ' (' . saretos_weight( (int) $criterion['weight'] ) . '%)' ); ?></label>
					<input type="number" min="0" max="100" step=".01" inputmode="decimal" id="<?php echo esc_attr( $key ); ?>"
						name="scores[<?php echo esc_attr( $index ); ?>]" value="<?php echo esc_attr( $criterion['score'] ?? '' ); ?>">
				<small data-criterion-author="<?php echo esc_attr( $index ); ?>"><?php echo esc_html( isset( $criterion['reviewer'] ) ? saretos_grader_label( $criterion['reviewer'] ) : ( isset( $criterion['score'] ) ? ( isset( $grading['grader'] ) ? 'Evaluación histórica en conjunto · Usuario #' . absint( $grading['grader'] ) : 'Evaluación anterior' ) : 'Pendiente de calificar' ) ); ?></small>
				</div>
			<?php endforeach; ?>
		</fieldset>
		<button type="submit" class="saretos-button">Guardar</button>
		<p class="saretos-feedback" role="status" aria-live="polite"><?php echo absint( saretos_query( 'guardado' ) ) === (int) $entry['id'] ? 'Cambios guardados.' : ''; ?></p>
	</form>
	<?php
}

function saretos_csv_cell( $value ): string {
	$text = (string) $value;
	return preg_match( '/^(?:\s*[=+@-]|[\t\r\n])/u', $text ) ? "'" . $text : $text;
}

function saretos_csv(): void {
	global $wpdb;
	$id = absint( saretos_post( 'challenge_id' ) );
	saretos_admin_guard( 'saretos_csv_' . $id );
	if ( ! $id || ! saretos_challenge( $id ) ) {
		saretos_admin_error( 'El reto no existe.', 404 );
	}
	nocache_headers();
	header( 'Content-Type: text/csv; charset=UTF-8' );
	header( 'Content-Disposition: attachment; filename="reto-' . $id . '-participantes.csv"' );
	header( 'X-Content-Type-Options: nosniff' );
	$output = fopen( 'php://output', 'w' );
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, array( 'ID', 'Nombre completo', 'Edad', 'Ciudad y departamento', 'WhatsApp', 'Correo electrónico', 'Red social', 'Usuario o perfil',
		'URL del video', 'Términos aceptados (URL)', 'Política aceptada (URL)', 'Autorización para compartir', 'Inscripción (Colombia)', 'Estado', 'Puntuación (0–100)', 'Versión de criterios', 'Video TikTok', 'Video Instagram', 'Video Facebook', 'Detalle de criterios y evaluadores'
	), ';', '"', '' );
	$states = saretos_review_states();
	$platforms = saretos_platforms();
	$table = saretos_table( 'entries' );
	$where = saretos_entries_where( $id, saretos_entry_filters( true ) );
	$cursor = 0;
	do {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE $where AND id > %d ORDER BY id ASC LIMIT 250", $cursor ), ARRAY_A ) ?: array();
		foreach ( $rows as $entry ) {
			$cursor = (int) $entry['id'];
			$grading = saretos_grading( $entry );
			$values = array( $cursor, $entry['name'], $entry['age'], $entry['location'], $entry['phone'], $entry['email'], $platforms[ $entry['platform'] ],
				$entry['profile'], $entry['video'], $entry['terms_url'], $entry['privacy_url'], $entry['share_video'] ? 'Sí' : 'No',
				wp_date( 'Y-m-d H:i:s', (int) $entry['registered_at'], saretos_timezone() ), $states[ $entry['review'] ],
				null === $entry['score'] ? '' : str_replace( '.', ',', $entry['score'] ), $grading['version'] ?? '',
			saretos_entry_videos( $entry )['tiktok'] ?? '', saretos_entry_videos( $entry )['instagram'] ?? '', saretos_entry_videos( $entry )['facebook'] ?? '',
			implode( ' | ', array_map( static function ( $index, $criterion ) { return $criterion['label'] . ': ' . ( $criterion['score'] ?? 'Pendiente' ) . ' — ' . ( isset( $criterion['reviewer'] ) ? saretos_grader_label( $criterion['reviewer'] ) : ( isset( $criterion['score'] ) ? 'Evaluación histórica en conjunto' : 'Sin calificar' ) ); }, array_keys( $grading['criteria'] ?? array() ), $grading['criteria'] ?? array() ) ),
			);
			fputcsv( $output, array_map( 'saretos_csv_cell', $values ), ';', '"', '' );
		}
	} while ( count( $rows ) === 250 );
	fclose( $output );
	exit;
}
