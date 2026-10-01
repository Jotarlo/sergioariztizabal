<?php
/**
 * Plugin Name: SA Carrusel de Fotos
 * Description: Carrusel con fotos aleatorias de todas las galerías de FooGallery, con ampliación al hacer clic. Shortcode: [sa_carrusel_fotos] (también responde a [sergio_carrusel_foogallery]).
 * Version: 1.1.0
 * Author: JAL
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'SA_CAR_URL', plugin_dir_url( __FILE__ ) );
define( 'SA_CAR_VER', '1.1.0' );

function sa_car_defaults() {
	return array(
		'por_album' => 3, 'total' => 18, 'segundos' => 4, 'transicion' => 700, 'modo' => 'continuous', 'visibles' => 3,
		'autoplay' => 1, 'flechas' => 1, 'modal' => 1, 'borde' => '#d8aa00', 'alto' => 330, 'alto_m' => 280, 'excluir' => array(),
	);
}
function sa_car_opts() {
	return wp_parse_args( get_option( 'sa_car_opts', array() ), sa_car_defaults() );
}
function sa_car_galerias( $ids_only = false ) {
	return get_posts( array(
		'post_type' => 'foogallery', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC',
		'fields' => $ids_only ? 'ids' : 'all',
	) );
}

/* ---------- Ajustes ---------- */
add_action( 'admin_menu', function () {
	add_options_page( 'Carrusel de fotos', 'Carrusel de fotos', 'manage_options', 'sa-carrusel', 'sa_car_page' );
} );

function sa_car_page() {
	if ( isset( $_POST['sa_car_guardar'] ) ) {
		check_admin_referer( 'sa_car_guardar' );
		$modo = isset( $_POST['modo'] ) ? sanitize_key( wp_unslash( $_POST['modo'] ) ) : 'continuous';
		update_option( 'sa_car_opts', array(
			'por_album'  => min( 30, max( 1, (int) $_POST['por_album'] ) ),
			'total'      => min( 300, max( 0, (int) $_POST['total'] ) ),
			'segundos'   => min( 60, max( 1, (float) $_POST['segundos'] ) ),
			'transicion' => min( 3000, max( 100, (int) $_POST['transicion'] ) ),
			'modo'       => in_array( $modo, array( 'continuous', 'loop', 'bounce' ), true ) ? $modo : 'continuous',
			'visibles'   => min( 5, max( 1, (int) $_POST['visibles'] ) ),
			'autoplay'   => isset( $_POST['autoplay'] ) ? 1 : 0,
			'flechas'    => isset( $_POST['flechas'] ) ? 1 : 0,
			'modal'      => isset( $_POST['modal'] ) ? 1 : 0,
			'borde'      => (string) sanitize_hex_color( wp_unslash( $_POST['borde'] ) ),
			'alto'       => min( 900, max( 120, (int) $_POST['alto'] ) ),
			'alto_m'     => min( 900, max( 120, (int) $_POST['alto_m'] ) ),
			'excluir'    => isset( $_POST['excluir'] ) ? array_map( 'absint', (array) $_POST['excluir'] ) : array(),
		) );
		echo '<div class="updated"><p>Guardado.</p></div>';
	}
	$o = sa_car_opts();
	?>
	<div class="wrap"><h1>Carrusel de fotos</h1>
	<p>Muestra fotos aleatorias de todas las galerías de FooGallery. Úsalo con el shortcode <code>[sa_carrusel_fotos]</code> (el antiguo <code>[sergio_carrusel_foogallery]</code> también funciona).</p>
	<form method="post"><?php wp_nonce_field( 'sa_car_guardar' ); ?>
		<input type="hidden" name="sa_car_guardar" value="1">
		<table class="form-table">
			<tr><th>Fotos por álbum</th><td><input type="number" name="por_album" min="1" max="30" value="<?php echo esc_attr( $o['por_album'] ); ?>"><p class="description">Cuántas fotos al azar se toman de cada galería.</p></td></tr>
			<tr><th>Fotos en total</th><td><input type="number" name="total" min="0" max="300" value="<?php echo esc_attr( $o['total'] ); ?>"><p class="description">Máximo de fotos en el carrusel. 0 = sin límite.</p></td></tr>
			<tr><th>Movimiento</th><td>
				<label><input type="radio" name="modo" value="continuous" <?php checked( 'continuous', $o['modo'] ); ?>> Continuo: se desplaza sin detenerse y da la vuelta sin cortes</label><br>
				<label><input type="radio" name="modo" value="loop" <?php checked( 'loop', $o['modo'] ); ?>> Circular por pasos: avanza foto a foto y nunca se detiene</label><br>
				<label><input type="radio" name="modo" value="bounce" <?php checked( 'bounce', $o['modo'] ); ?>> Se devuelve: al llegar al final regresa hacia atrás</label></td></tr>
			<tr><th>Segundos por foto</th><td><input type="number" name="segundos" min="1" max="60" step="0.5" value="<?php echo esc_attr( $o['segundos'] ); ?>"><p class="description">En «Continuo» es lo que tarda cada foto en recorrer una posición (más alto = más lento). En los otros modos es el tiempo entre cambios.</p></td></tr>
			<tr><th>Duración de la transición (ms)</th><td><input type="number" name="transicion" min="100" max="3000" step="50" value="<?php echo esc_attr( $o['transicion'] ); ?>"><p class="description">Solo para «por pasos» y «Se devuelve» (700 = suave, 300 = rápido).</p></td></tr>
			<tr><th>Fotos visibles a la vez</th><td><input type="number" name="visibles" min="1" max="5" value="<?php echo esc_attr( $o['visibles'] ); ?>"><p class="description">En computador. En tablet se ven máximo 2 y en celular 1.</p></td></tr>
			<tr><th>Alto de las fotos (px)</th><td>Computador <input type="number" name="alto" min="120" max="900" value="<?php echo esc_attr( $o['alto'] ); ?>"> &nbsp; Celular <input type="number" name="alto_m" min="120" max="900" value="<?php echo esc_attr( $o['alto_m'] ); ?>"></td></tr>
			<tr><th>Color del borde y las flechas</th><td><input type="text" name="borde" value="<?php echo esc_attr( $o['borde'] ); ?>" placeholder="#d8aa00" size="10"><p class="description">Código hexadecimal, por ejemplo #d8aa00. Si lo dejas vacío no hay borde.</p></td></tr>
			<tr><th>Opciones</th><td>
				<label><input type="checkbox" name="autoplay" value="1" <?php checked( 1, (int) $o['autoplay'] ); ?>> Movimiento automático</label><br>
				<label><input type="checkbox" name="flechas" value="1" <?php checked( 1, (int) $o['flechas'] ); ?>> Mostrar flechas (no aplican en «Continuo»)</label><br>
				<label><input type="checkbox" name="modal" value="1" <?php checked( 1, (int) $o['modal'] ); ?>> Ampliar la foto a pantalla completa al hacer clic</label></td></tr>
			<tr><th>Galerías a excluir</th><td>
				<?php foreach ( sa_car_galerias() as $g ) : ?>
					<label style="display:inline-block;min-width:220px"><input type="checkbox" name="excluir[]" value="<?php echo (int) $g->ID; ?>" <?php checked( in_array( $g->ID, array_map( 'intval', (array) $o['excluir'] ), true ) ); ?>> <?php echo esc_html( $g->post_title ); ?></label>
				<?php endforeach; ?>
				<p class="description">Las marcadas no aparecen en el carrusel. Si no marcas ninguna, entran todas.</p></td></tr>
		</table>
		<?php submit_button(); ?>
	</form>
	<p><em>Si usas un plugin de caché, el orden aleatorio se renueva cuando se limpia la caché de la página.</em></p></div>
	<?php
}

/* ---------- Selección de fotos ---------- */
function sa_car_ids( $gal ) {
	$m   = get_post_meta( $gal, 'foogallery_attachments', true );
	$ids = is_array( $m ) ? array_map( 'intval', $m ) : array();
	if ( ! $ids && class_exists( 'FooGallery' ) ) {
		try {
			$g = FooGallery::get_by_id( $gal );
			if ( $g && method_exists( $g, 'attachments' ) ) {
				foreach ( $g->attachments() as $a ) { if ( ! empty( $a->ID ) ) { $ids[] = (int) $a->ID; } }
			}
		} catch ( Exception $e ) { $ids = array(); }
	}
	return $ids;
}

function sa_car_pick( $a ) {
	$gals = array_diff( sa_car_galerias( true ), array_map( 'intval', (array) $a['excluir'] ) );
	$pool = array();
	foreach ( $gals as $g ) {
		$ids = sa_car_ids( $g );
		shuffle( $ids );
		foreach ( array_slice( $ids, 0, $a['por_album'] ) as $id ) { $pool[ $id ] = get_the_title( $g ); }
	}
	$keys = array_keys( $pool );
	shuffle( $keys );
	if ( $a['total'] > 0 ) { $keys = array_slice( $keys, 0, $a['total'] ); }
	$out = array();
	foreach ( $keys as $id ) { $out[] = array( 'id' => $id, 't' => $pool[ $id ] ); }
	return $out;
}

/* ---------- Shortcode ---------- */
function sa_car_shortcode( $atts ) {
	$a = shortcode_atts( sa_car_opts(), $atts, 'sa_carrusel_fotos' );
	$a['por_album']  = max( 1, (int) $a['por_album'] );
	$a['total']      = max( 0, (int) $a['total'] );
	$a['segundos']   = max( 1, (float) $a['segundos'] );
	$a['transicion'] = max( 100, (int) $a['transicion'] );
	$a['visibles']   = min( 5, max( 1, (int) $a['visibles'] ) );
	$a['modo']       = in_array( $a['modo'], array( 'continuous', 'loop', 'bounce' ), true ) ? $a['modo'] : 'continuous';
	$modal           = (int) $a['modal'];
	$borde           = (string) sanitize_hex_color( $a['borde'] );

	$items = sa_car_pick( $a );
	if ( ! $items ) {
		return current_user_can( 'manage_options' ) ? '<p>Carrusel: no se encontraron fotos en las galerías de FooGallery.</p>' : '';
	}
	wp_enqueue_style( 'sa-carrusel', SA_CAR_URL . 'assets/carrusel.css', array(), SA_CAR_VER );
	wp_enqueue_script( 'sa-carrusel', SA_CAR_URL . 'assets/carrusel.js', array(), SA_CAR_VER, true );

	$style = '--ac:' . ( $borde ? $borde : '#d8aa00' ) . ';--bd:' . ( $borde ? $borde : 'transparent' ) . ';--h:' . (int) $a['alto'] . 'px;--hm:' . (int) $a['alto_m'] . 'px';
	$h  = '<div class="sac" style="' . esc_attr( $style ) . '" data-int="' . esc_attr( $a['segundos'] ) . '" data-dur="' . esc_attr( $a['transicion'] ) . '" data-mode="' . esc_attr( $a['modo'] ) . '" data-vis="' . esc_attr( $a['visibles'] ) . '" data-auto="' . ( (int) $a['autoplay'] ? '1' : '0' ) . '">';
	$h .= '<div class="sac-view"><div class="sac-track">';
	foreach ( $items as $it ) {
		$img = wp_get_attachment_image( $it['id'], 'large', false, array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => $it['t'] ) );
		if ( ! $img ) { continue; }
		if ( $modal ) {
			$h .= '<button type="button" class="sac-slide" data-full="' . esc_url( wp_get_attachment_image_url( $it['id'], 'full' ) ) . '" data-t="' . esc_attr( $it['t'] ) . '" aria-label="' . esc_attr( 'Ampliar foto de ' . $it['t'] ) . '">' . $img . '</button>';
		} else {
			$h .= '<div class="sac-slide">' . $img . '</div>';
		}
	}
	$h .= '</div></div>';
	if ( (int) $a['flechas'] ) {
		$h .= '<button type="button" class="sac-prev" aria-label="Anterior">&#8249;</button><button type="button" class="sac-next" aria-label="Siguiente">&#8250;</button>';
	}
	return $h . '</div>';
}
add_shortcode( 'sa_carrusel_fotos', 'sa_car_shortcode' );
add_shortcode( 'sergio_carrusel_foogallery', 'sa_car_shortcode' );
