<?php

defined( 'ABSPATH' ) || exit;

function saretos_is_page( string $route ): bool {
	$id = (int) get_option( 'saretos_page_' . $route );
	return $id > 0 && is_page( $id );
}

function saretos_route_request(): void {
	$public = saretos_is_page( 'public' );
	$evaluation = saretos_is_page( 'evaluation' );
	if ( $public || $evaluation || ( is_front_page() && ! is_feed() ) ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}
	if ( $evaluation ) {
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Referrer-Policy: same-origin', true );
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( saretos_evaluation_url() ), 302 );
			exit;
		}
		if ( ! current_user_can( 'saretos_evaluate' ) ) {
			status_header( 403 );
		}
		return;
	}
	if ( ! $public ) {
		return;
	}

	$challenge = saretos_challenge();
	$GLOBALS['saretos_public_challenge'] = $challenge;
	$request = $_SERVER['REQUEST_METHOD'] ?? 'GET';
	if ( 'POST' !== $request ) {
		if ( 'GET' === $request && saretos_open( $challenge ) ) {
			saretos_init_guest();
		}
		return;
	}
	if ( ! saretos_open( $challenge ) ) {
		status_header( 409 );
		return;
	}
	if ( 'register' === saretos_post( 'saretos_action' ) ) {
		$error = saretos_register( $challenge );
	} else {
		$error = new WP_Error( 'action', 'La solicitud no está disponible. Vuelve a abrir la página del reto.', array( 'status' => 400 ) );
	}
	$GLOBALS['saretos_public_error'] = $error;
	status_header( (int) ( $error->get_error_data()['status'] ?? 422 ) );
}

function saretos_evaluation_url(): string {
	$id = absint( saretos_query( 'reto' ) );
	$url = saretos_page_url( 'evaluation' );
	return $id ? add_query_arg( 'reto', $id, $url ) : $url;
}

function saretos_public_assets(): void {
	if ( is_front_page() || saretos_is_page( 'public' ) || saretos_is_page( 'evaluation' ) ) {
		wp_enqueue_style( 'saretos', plugins_url( 'assets/site.css', SARETOS_FILE ), array(), SARETOS_VERSION );
	}
	if ( is_front_page() && saretos_open( saretos_challenge() ) ) {
		wp_enqueue_script( 'saretos-countdown', plugins_url( 'assets/countdown.js', SARETOS_FILE ), array(), SARETOS_VERSION, true );
	}
	if ( saretos_is_page( 'public' ) ) {
		wp_enqueue_script( 'saretos-registration', plugins_url( 'assets/registration.js', SARETOS_FILE ), array(), SARETOS_VERSION, true );
	}
	if ( saretos_is_page( 'evaluation' ) && current_user_can( 'saretos_evaluate' ) ) {
		saretos_grade_script();
	}
}

function saretos_elementor_band( string $content ): string {
	$anchor = get_option( 'saretos_anchor', '' );
	if ( ! $anchor || ! is_front_page() || is_feed() || is_preview() || is_admin() || saretos_query( 'elementor-preview' ) ) {
		return $content;
	}
	$tags = new WP_HTML_Tag_Processor( $content );
	if ( ! $tags->next_tag( array( 'class_name' => 'elementor' ) ) || (int) $tags->get_attribute( 'data-elementor-id' ) !== get_queried_object_id() ) {
		return $content;
	}
	while ( $tags->next_tag( array( 'class_name' => 'elementor-element' ) ) ) {
		if ( $anchor !== $tags->get_attribute( 'data-id' ) ) {
			continue;
		}
		$band = saretos_render_band();
		if ( '' === $band ) {
			break;
		}
		// Insert outside Elementor's element cache. The temporary attribute locates the original tag.
		$tags->set_attribute( 'data-saretos-insertion', 'anchor' );
		$marked = $tags->get_updated_html();
		$marker = ' data-saretos-insertion="anchor"';
		$position = strpos( $marked, $marker );
		if ( false !== $position ) {
			$opening = strrpos( substr( $marked, 0, $position ), '<' );
			if ( false !== $opening ) {
				return substr( $marked, 0, $opening ) . $band . substr( $marked, $opening, $position - $opening )
					. substr( $marked, $position + strlen( $marker ) );
			}
		}
		break;
	}
	return $content;
}

function saretos_band_shortcode(): string {
	return is_front_page() && ! is_feed() && ! is_admin() && ! get_option( 'saretos_anchor', '' ) ? saretos_render_band() : '';
}

function saretos_image( int $id, string $size, bool $decorative ): string {
	$attributes = array( 'class' => 'saretos-art', 'loading' => 'eager', 'decoding' => 'async' );
	if ( $decorative ) {
		$attributes['alt'] = '';
	}
	return $id ? wp_get_attachment_image( $id, $size, false, $attributes ) : '';
}

function saretos_render_band(): string {
	$challenge = saretos_challenge();
	$url = saretos_page_url( 'public' );
	if ( ! $url || ! saretos_open( $challenge ) ) {
		return '';
	}
	$settings = $challenge['settings'];
	ob_start();
	?>
	<?php
	$start_date = wp_date( 'Y-m-d', (int) $challenge['starts'], saretos_timezone() );
	$end_date = wp_date( 'Y-m-d', (int) $challenge['ends'] - 1, saretos_timezone() );
	$start_label = wp_date( 'j M Y', (int) $challenge['starts'], saretos_timezone() );
	$end_label = wp_date( 'j M Y', (int) $challenge['ends'] - 1, saretos_timezone() );
	?>
	<section class="saretos saretos-band saretos-banner" aria-labelledby="saretos-promo-title">
		<?php echo saretos_image( (int) $settings['band_image'], 'full', true ); // Built by WordPress. ?>
		<div class="saretos-band-content">
			<div class="saretos-band-head">
				<h2 id="saretos-promo-title"><?php echo esc_html( $challenge['title'] ); ?></h2>
				<p class="saretos-band-period">Del <time datetime="<?php echo esc_attr( $start_date ); ?>"><?php echo esc_html( $start_label ); ?></time> al <time datetime="<?php echo esc_attr( $end_date ); ?>"><?php echo esc_html( $end_label ); ?></time></p>
			</div>
			<div class="saretos-band-details">
				<?php if ( $settings['promotion'] ) : ?><p class="saretos-band-promotion"><?php echo esc_html( $settings['promotion'] ); ?></p><?php endif; ?>
				<?php if ( $settings['prize'] ) : ?><p class="saretos-band-prize"><span>Premio</span><strong><?php echo esc_html( $settings['prize'] ); ?></strong></p><?php endif; ?>
			</div>
			<div class="saretos-band-countdown" data-saretos-countdown data-end="<?php echo esc_attr( (int) $challenge['ends'] * 1000 ); ?>" role="group" aria-label="Cuenta regresiva para finalizar el reto">
				<?php foreach ( array( 'days' => 'Días', 'hours' => 'Horas', 'minutes' => 'Minutos', 'seconds' => 'Segundos' ) as $unit => $label ) : ?>
					<div class="saretos-countdown-unit"><span data-countdown-<?php echo esc_attr( $unit ); ?> aria-hidden="true">00</span><small><?php echo esc_html( $label ); ?></small></div>
				<?php endforeach; ?>
			</div>
		</div>
		<a class="saretos-button" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $settings['button'] ); ?></a>
	</section>
	<?php
	return (string) ob_get_clean();
}

function saretos_page_content( string $content ): string {
	if ( is_admin() || is_feed() || ! is_main_query() || ! in_the_loop() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return $content;
	}
	if ( saretos_is_page( 'public' ) ) {
		return saretos_public_view();
	}
	if ( saretos_is_page( 'evaluation' ) ) {
		return saretos_evaluation_view();
	}
	return $content;
}

function saretos_astra_title( bool $enabled ): bool {
	return ( saretos_is_page( 'public' ) || saretos_is_page( 'evaluation' ) ) ? false : $enabled;
}

function saretos_astra_layout( string $layout ): string {
	return ( saretos_is_page( 'public' ) || saretos_is_page( 'evaluation' ) ) ? 'page-builder' : $layout;
}

function saretos_astra_sidebar( string $layout ): string {
	return ( saretos_is_page( 'public' ) || saretos_is_page( 'evaluation' ) ) ? 'no-sidebar' : $layout;
}

function saretos_page_robots( array $robots ): array {
	if ( saretos_is_page( 'evaluation' ) ) {
		unset( $robots['index'], $robots['follow'] );
		$robots['noindex'] = true;
		$robots['nofollow'] = true;
	}
	return $robots;
}

function saretos_surerank_robots( array $robots ): array {
	return saretos_is_page( 'evaluation' ) ? array( 'noindex', 'nofollow', 'noarchive' ) : $robots;
}

function saretos_hide_page_list( array $ids ): array {
	$id = (int) get_option( 'saretos_page_evaluation' );
	return $id ? array_merge( $ids, array( $id ) ) : $ids;
}

function saretos_hide_search( WP_Query $query ): void {
	if ( ! is_admin() && $query->is_main_query() && $query->is_search() ) {
		$excluded = (array) $query->get( 'post__not_in', array() );
		$query->set( 'post__not_in', saretos_hide_page_list( $excluded ) );
	}
}

function saretos_hide_sitemap( array $args, string $post_type ): array {
	if ( 'page' === $post_type ) {
		$args['post__not_in'] = saretos_hide_page_list( $args['post__not_in'] ?? array() );
	}
	return $args;
}

function saretos_is_header_menu( string $location ): bool {
	return in_array( $location, array( 'primary', 'mobile_menu' ), true );
}

function saretos_matches_menu_page( object $item, int $id, string $url ): bool {
	return $id && ( ( 'page' === ( $item->object ?? '' ) && (int) ( $item->object_id ?? 0 ) === $id )
		|| ( $url && untrailingslashit( (string) ( $item->url ?? '' ) ) === untrailingslashit( $url ) ) );
}

function saretos_nav_menu_items( array $items, stdClass $args ): array {
	$header = saretos_is_header_menu( $args->theme_location ?? '' );
	$evaluation_id = (int) get_option( 'saretos_page_evaluation' );
	$evaluation_url = saretos_page_url( 'evaluation' );
	$public_id = (int) get_option( 'saretos_page_public' );
	$public_url = $header ? saretos_page_url( 'public' ) : '';
	foreach ( $items as $key => $item ) {
		if ( saretos_matches_menu_page( $item, $evaluation_id, $evaluation_url )
			|| ( $header && saretos_matches_menu_page( $item, $public_id, $public_url ) ) ) {
			unset( $items[ $key ] );
		}
	}
	if ( ! $header || ! saretos_menu_page( $public_id ) ) {
		return $items;
	}

	$page = clone get_post( $public_id );
	$item = wp_setup_nav_menu_item( $page );
	$item->ID = -$public_id;
	$item->db_id = -$public_id;
	$item->menu_item_parent = 0;
	$item->title = 'Participar';
	$item->current = saretos_is_page( 'public' );
	$item->classes = array( 'menu-item', 'menu-item-type-post_type', 'menu-item-object-page', 'saretos-menu-link' );
	if ( $item->current ) {
		$item->classes[] = 'current-menu-item';
		$item->classes[] = 'current_page_item';
	}
	$items[] = $item;
	return $items;
}

function saretos_menu_page( int $id ): bool {
	return $id > 0 && 'publish' === get_post_status( $id ) && saretos_open( saretos_challenge() );
}

function saretos_page_menu_args( array $args ): array {
	$id = (int) get_option( 'saretos_page_public' );
	if ( $id && saretos_is_header_menu( $args['theme_location'] ?? '' ) ) {
		$args['exclude'] = implode( ',', array_merge( wp_parse_id_list( $args['exclude'] ?? '' ), array( $id ) ) );
	}
	return $args;
}

function saretos_page_menu_link( string $menu, array $args ): string {
	$id = (int) get_option( 'saretos_page_public' );
	if ( ! saretos_is_header_menu( $args['theme_location'] ?? '' ) || ! saretos_menu_page( $id ) ) {
		return $menu;
	}
	$end = strripos( $menu, '</ul>' );
	if ( false === $end ) {
		return $menu;
	}
	$current = saretos_is_page( 'public' );
	$item = sprintf(
		'<li class="page_item menu-item saretos-menu-link%s"><a class="menu-link" href="%s"%s>Participar</a></li>',
		$current ? ' current_page_item current-menu-item' : '',
		esc_url( saretos_page_url( 'public' ) ),
		$current ? ' aria-current="page"' : ''
	);
	return substr_replace( $menu, $item, $end, 0 );
}

function saretos_public_view(): string {
	$challenge = $GLOBALS['saretos_public_challenge'] ?? null;
	$error = $GLOBALS['saretos_public_error'] ?? null;
	ob_start();
	?>
	<div class="saretos saretos-page">
	<?php if ( ! saretos_open( $challenge ) ) : ?>
		<div class="saretos-shell saretos-narrow">
			<h1>Retos</h1>
			<?php if ( $challenge && ( $challenge['closed'] || time() >= (int) $challenge['ends'] ) && ( ! $challenge['is_demo'] || saretos_demo_allowed() ) ) : ?>
				<?php echo wp_kses_post( wpautop( $challenge['settings']['closed_text'] ?: 'Las inscripciones han finalizado.' ) ); ?>
			<?php elseif ( $challenge && time() < (int) $challenge['starts'] ) : ?>
				<p>Las inscripciones aún no están abiertas.</p>
			<?php else : ?>
				<p>En este momento no hay inscripciones abiertas.</p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<?php saretos_challenge_view( $challenge, $error ); ?>
	<?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

function saretos_public_error( ?WP_Error $error ): void {
	if ( $error ) {
		echo '<p class="saretos-notice saretos-error" role="alert" id="saretos-errors" tabindex="-1">' . esc_html( $error->get_error_message() ) . '</p>';
	}
}

function saretos_challenge_view( array $challenge, ?WP_Error $error ): void {
	$settings = $challenge['settings'];
	?>
	<section class="saretos-hero saretos-banner" aria-labelledby="saretos-title">
		<?php echo saretos_image( (int) $settings['page_image'], 'full', true ); // Built by WordPress. ?>
		<div class="saretos-hero-content">
			<h1 id="saretos-title"><?php echo esc_html( $challenge['title'] ); ?></h1>
			<?php echo wp_kses_post( wpautop( $settings['description'] ) ); ?>
		</div>
	</section>
	<div class="saretos-inner">
		<?php if ( $challenge['is_demo'] ) : ?>
			<p class="saretos-notice">Reto de demostración. Usa datos ficticios.</p>
		<?php endif; ?>
		<?php
		$document = saretos_query( 'documento' );
		if ( $challenge['is_demo'] && in_array( $document, array( 'terminos', 'datos' ), true ) ) :
			saretos_demo_document( $document );
		elseif ( '1' === saretos_query( 'inscrito' ) && saretos_registered( $challenge ) ) :
			saretos_success_view( $settings );
		else :
			if ( $settings['rules'] ) :
				?>
				<section aria-labelledby="saretos-rules">
					<h2 id="saretos-rules">Cómo participar</h2>
					<?php echo wp_kses_post( wpautop( $settings['rules'] ) ); ?>
				</section>
				<?php
			endif;
			$start_label = wp_date( 'j \\d\\e F \\d\\e Y', (int) $challenge['starts'], saretos_timezone() );
			$end_label = wp_date( 'j \\d\\e F \\d\\e Y', (int) $challenge['ends'] - 1, saretos_timezone() );
			?>
			<section class="saretos-event-info" aria-label="Fechas e información del reto">
				<p><strong>Inscripciones:</strong> del <?php echo esc_html( $start_label ); ?> al <?php echo esc_html( $end_label ); ?>.</p>
				<?php if ( $settings['announcement'] ) : ?><?php echo wp_kses_post( wpautop( $settings['announcement'] ) ); ?><?php endif; ?>
			</section>
			<?php
			saretos_registration_form( $challenge, $error );
			?>
		<?php endif; ?>
	</div>
	<?php
}


function saretos_public_body_class( array $classes ): array {
	if ( saretos_is_page( 'public' ) ) {
		$classes[] = 'saretos-public-page';
	}
	return $classes;
}
