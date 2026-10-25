<?php
/**
 * Plugin Name: SA Mapa de Presentaciones
 * Description: Mapa interactivo de Colombia (departamentos y municipios). Se activa desde el admin y enlaza cada municipio a una galería de FooGallery. Shortcode: [sa_mapa_presentaciones]
 * Version: 1.2.2
 * Author: JAL
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'SA_MAPA_URL', plugin_dir_url( __FILE__ ) );
define( 'SA_MAPA_VER', '1.2.2' );

/* ---------- Tipo de contenido: una "Presentación" = un municipio activo ---------- */
add_action( 'init', function () {
	register_post_type( 'sa_presentacion', array(
		'labels'        => array(
			'name' => 'Mapa de presentaciones', 'singular_name' => 'Presentación', 'menu_name' => 'Mapa presentaciones',
			'add_new' => 'Añadir presentación', 'add_new_item' => 'Añadir presentación', 'edit_item' => 'Editar presentación',
			'all_items' => 'Todas las presentaciones', 'not_found' => 'Aún no hay presentaciones',
		),
		'public'        => false,
		'show_ui'       => true,
		'menu_icon'     => 'dashicons-location-alt',
		'menu_position' => 25,
		'supports'      => array( 'title' ),
	) );
} );

/* ---------- Ajustes ---------- */
function sa_mapa_opts() {
	$o = wp_parse_args( get_option( 'sa_mapa_opts', array() ), array( 'galeria_url' => '/galeria/', 'ver_album' => 1, 'orden' => 'recientes' ) );
	if ( 0 === strpos( $o['galeria_url'], '/' ) ) { $o['galeria_url'] = home_url( $o['galeria_url'] ); }
	return $o;
}
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=sa_presentacion', 'Ajustes del mapa', 'Ajustes', 'manage_options', 'sa-mapa-ajustes', function () {
		if ( isset( $_POST['sa_mapa_ajustes'] ) ) {
			check_admin_referer( 'sa_mapa_ajustes' );
			update_option( 'sa_mapa_opts', array(
				'galeria_url' => sanitize_text_field( wp_unslash( $_POST['galeria_url'] ) ),
				'ver_album'   => isset( $_POST['ver_album'] ) ? 1 : 0,
				'orden'       => ( isset( $_POST['orden'] ) && in_array( $_POST['orden'], array( 'recientes', 'antiguas', 'galeria', 'azar' ), true ) ) ? $_POST['orden'] : 'recientes',
			) );
			echo '<div class="updated"><p>Guardado.</p></div>';
		}
		$o = wp_parse_args( get_option( 'sa_mapa_opts', array() ), array( 'galeria_url' => '/galeria/', 'ver_album' => 1, 'orden' => 'recientes' ) );
		?>
		<div class="wrap"><h1>Ajustes del mapa de presentaciones</h1>
		<form method="post"><?php wp_nonce_field( 'sa_mapa_ajustes' ); ?>
			<input type="hidden" name="sa_mapa_ajustes" value="1">
			<table class="form-table">
				<tr><th>Página de la galería</th><td><input type="text" name="galeria_url" class="regular-text" value="<?php echo esc_attr( $o['galeria_url'] ); ?>"><p class="description">Los enlaces quedan como <code>/galeria/?album=ID_DE_LA_GALERÍA</code>.</p></td></tr>
				<tr><th>Botón «Ver álbum completo»</th><td><label><input type="checkbox" name="ver_album" value="1" <?php checked( 1, (int) $o['ver_album'] ); ?>> Mostrarlo en el visor de fotos de cada municipio</label></td></tr>
				<tr><th>Orden de las fotos en el visor</th><td><select name="orden">
					<option value="recientes" <?php selected( $o['orden'], 'recientes' ); ?>>Más recientes primero (por fecha de subida)</option>
					<option value="antiguas" <?php selected( $o['orden'], 'antiguas' ); ?>>Más antiguas primero</option>
					<option value="galeria" <?php selected( $o['orden'], 'galeria' ); ?>>El orden de la galería de FooGallery</option>
					<option value="azar" <?php selected( $o['orden'], 'azar' ); ?>>Al azar</option>
				</select></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<p>Para mostrar el mapa en una página usa el shortcode <code>[sa_mapa_presentaciones]</code>.</p></div>
		<?php
	} );
} );

/* ---------- Galerías de una presentación (una o varias) ---------- */
function sa_mapa_gals( $post_id ) {
	$g = get_post_meta( $post_id, '_sa_gals', true );
	if ( ! is_array( $g ) ) {
		$old = (int) get_post_meta( $post_id, '_sa_gal', true );
		$g   = $old ? array( $old ) : array();
	}
	return array_values( array_unique( array_filter( array_map( 'absint', $g ) ) ) );
}

/* ---------- Caja de edición ---------- */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'sa_mapa_box', 'Ubicación y galería', 'sa_mapa_box_html', 'sa_presentacion', 'normal', 'high' );
} );

function sa_mapa_box_html( $post ) {
	wp_nonce_field( 'sa_mapa_save', 'sa_mapa_nonce' );
	$dep  = get_post_meta( $post->ID, '_sa_dep', true );
	$mun  = get_post_meta( $post->ID, '_sa_mun', true );
	$sel  = sa_mapa_gals( $post->ID );
	$url  = get_post_meta( $post->ID, '_sa_url', true );
	$gals = get_posts( array( 'post_type' => 'foogallery', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	?>
	<p><label><strong>Departamento</strong><br>
		<select id="sa_dep" name="sa_dep" data-v="<?php echo esc_attr( $dep ); ?>" style="min-width:300px"><option>Cargando…</option></select></label></p>
	<p><label><strong>Municipio</strong><br>
		<select id="sa_mun" name="sa_mun" data-v="<?php echo esc_attr( $mun ); ?>" style="min-width:300px"></select></label></p>
	<p><strong>Galerías de FooGallery</strong> <span class="description">(marca una o varias; sus fotos se juntan en el visor)</span></p>
	<div style="max-height:240px;overflow:auto;border:1px solid #c3c4c7;background:#fff;padding:8px 14px;max-width:520px">
		<?php foreach ( $gals as $g ) : ?>
			<label style="display:block;margin:4px 0"><input type="checkbox" name="sa_gal[]" value="<?php echo (int) $g->ID; ?>" <?php checked( in_array( (int) $g->ID, $sel, true ) ); ?>> <?php echo esc_html( $g->post_title ); ?> <small>(ID <?php echo (int) $g->ID; ?>)</small></label>
		<?php endforeach; ?>
		<?php if ( ! $gals ) : ?><em>No se encontraron galerías de FooGallery (¿está activo el plugin?).</em><?php endif; ?>
	</div>
	<p><label><strong>Enlace personalizado (opcional)</strong><br>
		<input type="url" name="sa_url" class="large-text" value="<?php echo esc_attr( $url ); ?>" placeholder="Si lo llenas, se usa en vez del enlace a la galería"></label></p>
	<p class="description">El título es el nombre que se ve en el mapa. <strong>Publicada = activa</strong> (se pinta en dorado); en Borrador queda inactiva.</p>
	<?php
}

add_action( 'save_post_sa_presentacion', function ( $id ) {
	if ( ! isset( $_POST['sa_mapa_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sa_mapa_nonce'] ) ), 'sa_mapa_save' ) ) { return; }
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) ) { return; }
	update_post_meta( $id, '_sa_dep', isset( $_POST['sa_dep'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_dep'] ) ) : '' );
	update_post_meta( $id, '_sa_mun', isset( $_POST['sa_mun'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_mun'] ) ) : '' );
	update_post_meta( $id, '_sa_gals', isset( $_POST['sa_gal'] ) ? array_values( array_filter( array_map( 'absint', (array) $_POST['sa_gal'] ) ) ) : array() );
	delete_post_meta( $id, '_sa_gal' );
	update_post_meta( $id, '_sa_url', isset( $_POST['sa_url'] ) ? esc_url_raw( wp_unslash( $_POST['sa_url'] ) ) : '' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$s = get_current_screen();
	if ( ! $s || 'sa_presentacion' !== $s->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) { return; }
	wp_enqueue_script( 'sa-mapa-admin', SA_MAPA_URL . 'assets/admin.js', array(), SA_MAPA_VER, true );
	wp_add_inline_script( 'sa-mapa-admin', 'var SA_ADMIN=' . wp_json_encode( array( 'dataUrl' => SA_MAPA_URL . 'data/colombia-municipios.json' ) ) . ';', 'before' );
} );

/* ---------- Columnas en el listado ---------- */
add_filter( 'manage_sa_presentacion_posts_columns', function ( $c ) {
	return array( 'cb' => $c['cb'], 'title' => 'Nombre en el mapa', 'sa_dep' => 'Departamento', 'sa_mun' => 'Municipio', 'sa_gal' => 'Galería', 'date' => $c['date'] );
} );
add_action( 'manage_sa_presentacion_posts_custom_column', function ( $col, $id ) {
	if ( 'sa_dep' === $col ) { echo esc_html( ucwords( strtolower( get_post_meta( $id, '_sa_dep', true ) ) ) ); }
	if ( 'sa_mun' === $col ) { echo esc_html( ucwords( strtolower( get_post_meta( $id, '_sa_mun', true ) ) ) ); }
	if ( 'sa_gal' === $col ) { $t = array_map( 'get_the_title', sa_mapa_gals( $id ) ); echo $t ? esc_html( implode( ', ', $t ) ) : '—'; }
}, 10, 2 );

/* ---------- Fotos de la galería (todas, en tamaño completo) ---------- */
function sa_mapa_ids( $gal ) {
	$ids  = array();
	$meta = get_post_meta( $gal, 'foogallery_attachments', true );
	if ( is_array( $meta ) ) { $ids = array_map( 'intval', $meta ); }
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

/* El visor pide las fotos solo cuando se toca un municipio (así la página carga rápido). */
function sa_mapa_ajax_fotos() {
	$ids = array();
	$raw = isset( $_GET['gal'] ) ? sanitize_text_field( wp_unslash( $_GET['gal'] ) ) : '';
	foreach ( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) as $gal ) {
		$p = get_post( $gal );
		if ( $p && 'foogallery' === $p->post_type && 'publish' === $p->post_status ) { $ids = array_merge( $ids, sa_mapa_ids( $gal ) ); }
	}
	$ids   = array_values( array_unique( $ids ) );
	$o     = sa_mapa_opts();
	$orden = $o['orden'];
	if ( $ids && in_array( $orden, array( 'recientes', 'antiguas' ), true ) ) {
		$dir = 'recientes' === $orden ? 'DESC' : 'ASC';
		$ids = get_posts( array(
			'post_type' => 'attachment', 'post_status' => 'any', 'post__in' => $ids, 'numberposts' => -1,
			'orderby' => array( 'date' => $dir, 'ID' => $dir ), 'fields' => 'ids',
		) );
	} elseif ( 'azar' === $orden ) {
		shuffle( $ids );
	}
	$out = array();
	foreach ( $ids as $aid ) {
		$u = wp_get_attachment_image_url( (int) $aid, 'full' );
		if ( $u ) { $out[] = $u; }
	}
	wp_send_json( $out );
}
add_action( 'wp_ajax_sa_mapa_fotos', 'sa_mapa_ajax_fotos' );
add_action( 'wp_ajax_nopriv_sa_mapa_fotos', 'sa_mapa_ajax_fotos' );

/* ---------- Shortcode ---------- */
add_shortcode( 'sa_mapa_presentaciones', function () {
	$o     = sa_mapa_opts();
	$items = array();
	$posts = get_posts( array( 'post_type' => 'sa_presentacion', 'post_status' => 'publish', 'numberposts' => -1 ) );
	foreach ( $posts as $p ) {
		$dep = get_post_meta( $p->ID, '_sa_dep', true );
		$mun = get_post_meta( $p->ID, '_sa_mun', true );
		if ( ! $dep || ! $mun ) { continue; }
		$gals   = sa_mapa_gals( $p->ID );
		$url    = get_post_meta( $p->ID, '_sa_url', true );
		$links  = array();
		if ( $url ) {
			$links[] = array( 't' => '', 'u' => $url );
		} else {
			foreach ( $gals as $gid ) { $links[] = array( 't' => get_the_title( $gid ), 'u' => add_query_arg( 'album', $gid, $o['galeria_url'] ) ); }
		}
		$items[] = array(
			'd'     => $dep,
			'k'     => $mun,
			'm'     => get_the_title( $p ),
			'links' => $links,
			'gals'  => $gals,
		);
	}
	wp_enqueue_style( 'sa-mapa', SA_MAPA_URL . 'assets/map.css', array(), SA_MAPA_VER );
	wp_enqueue_script( 'sa-topojson', SA_MAPA_URL . 'assets/topojson-client.min.js', array(), '3.1.0', true );
	wp_enqueue_script( 'sa-mapa', SA_MAPA_URL . 'assets/map.js', array( 'sa-topojson' ), SA_MAPA_VER, true );
	wp_add_inline_script( 'sa-mapa', 'window.SA_MAPA=' . wp_json_encode( array( 'dataUrl' => SA_MAPA_URL . 'data/colombia-municipios.json', 'ajax' => admin_url( 'admin-ajax.php' ), 'items' => $items, 'showAlbum' => (bool) $o['ver_album'] ) ) . ';', 'before' );
	ob_start();
	?>
<div id="mp-wrap">
  <div class="zw"><svg id="mp-svg" xmlns="http://www.w3.org/2000/svg"></svg></div>
  <p id="mp-msg">Cargando mapa…</p>
  <div class="ov" id="mp-modal"><div class="box"><button class="x" aria-label="Cerrar">×</button><h3></h3><div class="zw"><svg id="mp-dsvg" xmlns="http://www.w3.org/2000/svg"></svg></div></div></div>
  <div class="ov" id="mp-photos" style="z-index:100000"><div class="vw">
    <button class="x" aria-label="Cerrar visor">×</button>
    <div class="vt"></div>
    <button class="vn vp" aria-label="Anterior">‹</button>
    <div class="vm"><img alt=""><span class="vl">Cargando fotos…</span></div>
    <button class="vn vs" aria-label="Siguiente">›</button>
    <div class="vf"><span class="vc"></span><span class="al"></span></div>
  </div></div>
</div>

	<?php
	return ob_get_clean();
} );
