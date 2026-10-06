<?php
/**
 * Plugin Name: Agenda de Conciertos — Sergio Aristizábal
 * Description: Administra y muestra las próximas presentaciones del artista mediante el shortcode [agenda_conciertos_sergio].
 * Version: 1.0.3
 * Author: Sergio Aristizábal
 * License: GPL-2.0-or-later
 * Text Domain: agenda-conciertos-sergio
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SA_Concert_Agenda {
	const POST_TYPE = 'sa_concert';
	const OPTION    = 'sa_concert_agenda_options';
	const NONCE     = 'sa_concert_details_nonce';

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_concert' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_shortcode( 'agenda_conciertos_sergio', array( $this, 'shortcode' ) );
	}

	public static function activate() {
		$self = new self();
		$self->register_post_type();
		if ( ! get_option( self::OPTION ) ) {
			update_option( self::OPTION, $self->defaults() );
		}
		flush_rewrite_rules();
	}

	public function register_post_type() {
		register_post_type( self::POST_TYPE, array(
			'labels' => array(
				'name'          => 'Agenda de conciertos',
				'singular_name' => 'Concierto',
				'add_new_item'  => 'Agregar concierto',
				'edit_item'     => 'Editar concierto',
				'menu_name'     => 'Agenda de conciertos',
			),
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => true,
			'menu_icon'    => 'dashicons-calendar-alt',
			'supports'     => array( 'title', 'page-attributes' ),
			'show_in_rest' => false,
		) );
	}

	private function defaults() {
		return array(
			'background_id'   => 0,
			'title_first'     => 'PRÓXIMAS',
			'title_second'    => 'PRESENTACIONES',
			'message'         => 'Nos vemos muy pronto en tu ciudad.',
			'show_button'     => '1',
			'button_text'     => 'VER TODAS LAS PRESENTACIONES',
			'button_url'      => '',
			'items_limit'     => 7,
			'empty_message'   => 'Muy pronto anunciaremos nuevas presentaciones.',
		);
	}

	private function options() {
		return wp_parse_args( get_option( self::OPTION, array() ), $this->defaults() );
	}

	public function add_meta_boxes() {
		add_meta_box( 'sa-concert-details', 'Datos de la presentación', array( $this, 'concert_meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public function concert_meta_box( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		$date   = get_post_meta( $post->ID, '_sa_concert_date', true );
		$city   = get_post_meta( $post->ID, '_sa_concert_city', true );
		$region = get_post_meta( $post->ID, '_sa_concert_region', true );
		$type   = get_post_meta( $post->ID, '_sa_concert_type', true ) ?: 'publico';
		$active = get_post_meta( $post->ID, '_sa_concert_active', true );
		?>
		<p><label for="sa_concert_date"><strong>Fecha</strong></label><br><input type="date" id="sa_concert_date" name="sa_concert_date" value="<?php echo esc_attr( $date ); ?>" required></p>
		<p><label for="sa_concert_city"><strong>Municipio / ciudad</strong></label><br><input class="regular-text" type="text" id="sa_concert_city" name="sa_concert_city" value="<?php echo esc_attr( $city ); ?>" placeholder="Manizales" required></p>
		<p><label for="sa_concert_region"><strong>Departamento (opcional)</strong></label><br><input class="regular-text" type="text" id="sa_concert_region" name="sa_concert_region" value="<?php echo esc_attr( $region ); ?>" placeholder="Caldas"></p>
		<p><label for="sa_concert_type"><strong>Tipo de evento</strong></label><br><select id="sa_concert_type" name="sa_concert_type"><option value="publico" <?php selected( $type, 'publico' ); ?>>Público</option><option value="privado" <?php selected( $type, 'privado' ); ?>>Privado</option></select></p>
		<p><label><input type="checkbox" name="sa_concert_active" value="1" <?php checked( '1', $active ?: '1' ); ?>> Mostrar esta presentación en la agenda</label></p>
		<p class="description">El título es solo para identificar el registro en el administrador; no se muestra en el listado público.</p>
		<?php
	}

	public function save_concert( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$date = isset( $_POST['sa_concert_date'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_concert_date'] ) ) : '';
		if ( $date && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$date = '';
		}
		update_post_meta( $post_id, '_sa_concert_date', $date );
		update_post_meta( $post_id, '_sa_concert_city', isset( $_POST['sa_concert_city'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_concert_city'] ) ) : '' );
		update_post_meta( $post_id, '_sa_concert_region', isset( $_POST['sa_concert_region'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_concert_region'] ) ) : '' );
		update_post_meta( $post_id, '_sa_concert_type', isset( $_POST['sa_concert_type'] ) && 'privado' === $_POST['sa_concert_type'] ? 'privado' : 'publico' );
		update_post_meta( $post_id, '_sa_concert_active', isset( $_POST['sa_concert_active'] ) ? '1' : '0' );
	}

	public function add_settings_page() {
		add_submenu_page( 'edit.php?post_type=' . self::POST_TYPE, 'Diseño de agenda', 'Diseño de agenda', 'manage_options', 'sa-concert-agenda-design', array( $this, 'settings_page' ) );
	}

	public function register_settings() {
		register_setting( 'sa_concert_agenda_group', self::OPTION, array( $this, 'sanitize_options' ) );
	}

	public function sanitize_options( $input ) {
		$current = $this->options();
		return array(
			'background_id' => absint( $input['background_id'] ?? 0 ),
			'title_first'   => sanitize_text_field( $input['title_first'] ?? $current['title_first'] ),
			'title_second'  => sanitize_text_field( $input['title_second'] ?? $current['title_second'] ),
			'message'       => sanitize_textarea_field( $input['message'] ?? $current['message'] ),
			'show_button'   => isset( $input['show_button'] ) ? '1' : '0',
			'button_text'   => sanitize_text_field( $input['button_text'] ?? $current['button_text'] ),
			'button_url'    => esc_url_raw( $input['button_url'] ?? '' ),
			'items_limit'   => min( 50, max( 1, absint( $input['items_limit'] ?? 7 ) ) ),
			'empty_message' => sanitize_text_field( $input['empty_message'] ?? $current['empty_message'] ),
		);
	}

	public function settings_page() {
		$options = $this->options();
		$image   = $options['background_id'] ? wp_get_attachment_image_url( $options['background_id'], 'large' ) : '';
		?>
		<div class="wrap sa-agenda-settings"><h1>Diseño de agenda</h1><p>Estos datos controlan la apariencia del módulo público.</p>
		<form method="post" action="options.php"><?php settings_fields( 'sa_concert_agenda_group' ); ?>
		<table class="form-table" role="presentation">
		<tr><th scope="row">Imagen de fondo</th><td><input type="hidden" id="sa_background_id" name="<?php echo esc_attr( self::OPTION ); ?>[background_id]" value="<?php echo absint( $options['background_id'] ); ?>"><button type="button" class="button" id="sa-select-background">Seleccionar imagen</button> <button type="button" class="button-link-delete" id="sa-remove-background">Quitar</button><div id="sa-background-preview"><?php if ( $image ) : ?><img src="<?php echo esc_url( $image ); ?>" alt=""><?php endif; ?></div><p class="description">Recomendado: imagen horizontal de al menos 1800 × 800 px.</p></td></tr>
		<tr><th scope="row"><label for="sa_title_first">Título, primera línea</label></th><td><input class="regular-text" id="sa_title_first" name="<?php echo esc_attr( self::OPTION ); ?>[title_first]" value="<?php echo esc_attr( $options['title_first'] ); ?>"></td></tr>
		<tr><th scope="row"><label for="sa_title_second">Título, segunda línea</label></th><td><input class="regular-text" id="sa_title_second" name="<?php echo esc_attr( self::OPTION ); ?>[title_second]" value="<?php echo esc_attr( $options['title_second'] ); ?>"></td></tr>
		<tr><th scope="row"><label for="sa_message">Mensaje</label></th><td><textarea class="large-text" rows="3" id="sa_message" name="<?php echo esc_attr( self::OPTION ); ?>[message]"><?php echo esc_textarea( $options['message'] ); ?></textarea></td></tr>
		<tr><th scope="row">Botón “todas las presentaciones”</th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[show_button]" value="1" <?php checked( '1', $options['show_button'] ); ?>> Mostrar botón</label><p><input class="regular-text" name="<?php echo esc_attr( self::OPTION ); ?>[button_text]" value="<?php echo esc_attr( $options['button_text'] ); ?>" placeholder="Texto del botón"></p><p><input class="regular-text" type="url" name="<?php echo esc_attr( self::OPTION ); ?>[button_url]" value="<?php echo esc_attr( $options['button_url'] ); ?>" placeholder="https://... o /presentaciones/"></p></td></tr>
		<tr><th scope="row"><label for="sa_items_limit">Conciertos a mostrar</label></th><td><input type="number" min="1" max="50" id="sa_items_limit" name="<?php echo esc_attr( self::OPTION ); ?>[items_limit]" value="<?php echo absint( $options['items_limit'] ); ?>"></td></tr>
		<tr><th scope="row"><label for="sa_empty_message">Mensaje sin conciertos</label></th><td><input class="regular-text" id="sa_empty_message" name="<?php echo esc_attr( self::OPTION ); ?>[empty_message]" value="<?php echo esc_attr( $options['empty_message'] ); ?>"></td></tr>
		</table><?php submit_button( 'Guardar diseño' ); ?></form></div>
		<?php
	}

	public function admin_assets( $hook ) {
		if ( empty( $_GET['page'] ) || 'sa-concert-agenda-design' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'sa-concert-agenda-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.js', array( 'jquery' ), '1.0.0', true );
		wp_enqueue_style( 'sa-concert-agenda-admin', plugin_dir_url( __FILE__ ) . 'assets/admin.css', array(), '1.0.0' );
	}

	public function enqueue_assets() {
		// Elementor renders shortcodes after the page head. Enqueue early so the
		// presentation remains styled in both Elementor and the public page.
		wp_enqueue_style( 'sa-concert-agenda', plugin_dir_url( __FILE__ ) . 'assets/agenda.css', array(), '1.0.3' );
		wp_enqueue_style( 'sa-concert-agenda-mobile', plugin_dir_url( __FILE__ ) . 'assets/mobile.css', array( 'sa-concert-agenda' ), '1.0.3' );
	}

	public function columns( $columns ) {
		$columns['sa_date']     = 'Fecha';
		$columns['sa_location'] = 'Municipio';
		$columns['sa_type']     = 'Tipo';
		$columns['sa_status']   = 'Estado';
		return $columns;
	}

	public function column_content( $column, $post_id ) {
		if ( 'sa_date' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_sa_concert_date', true ) ?: '—' );
		} elseif ( 'sa_location' === $column ) {
			$place = get_post_meta( $post_id, '_sa_concert_city', true ); $region = get_post_meta( $post_id, '_sa_concert_region', true );
			echo esc_html( trim( $place . ( $region ? ', ' . $region : '' ), ', ' ) ?: '—' );
		} elseif ( 'sa_type' === $column ) {
			echo 'privado' === get_post_meta( $post_id, '_sa_concert_type', true ) ? 'Privado' : 'Público';
		} elseif ( 'sa_status' === $column ) {
			echo '1' === get_post_meta( $post_id, '_sa_concert_active', true ) ? '<span style="color:#16803c">Visible</span>' : '<span style="color:#777">Oculto</span>';
		}
	}

	private function concerts( $limit ) {
		$today = current_time( 'Y-m-d' );
		return get_posts( array( 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => $limit, 'meta_key' => '_sa_concert_date', 'meta_value' => $today, 'meta_compare' => '>=', 'orderby' => 'meta_value', 'order' => 'ASC', 'meta_type' => 'DATE', 'meta_query' => array( array( 'key' => '_sa_concert_active', 'value' => '1' ) ) ) );
	}

	private function formatted_date( $date ) {
		$time = strtotime( $date . ' 12:00:00' );
		return array( wp_date( 'd', $time ), strtoupper( wp_date( 'M', $time ) ) );
	}

	public function shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'limit' => 0 ), $atts, 'agenda_conciertos_sergio' );
		$options = $this->options();
		$limit   = absint( $atts['limit'] ) ?: absint( $options['items_limit'] );
		$bg      = $options['background_id'] ? wp_get_attachment_image_url( $options['background_id'], 'full' ) : '';
		$style   = $bg ? ' style="--sa-agenda-background:url(\'' . esc_url( $bg ) . '\')"' : '';
		$conferences = $this->concerts( $limit );
		ob_start();
		?>
		<section class="sa-concert-agenda"<?php echo $style; ?> aria-label="Próximas presentaciones"><div class="sa-concert-agenda__shade"></div><div class="sa-concert-agenda__content">
		<div class="sa-concert-agenda__intro"><h2><span><?php echo esc_html( $options['title_first'] ); ?></span><strong><?php echo esc_html( $options['title_second'] ); ?></strong></h2><span class="sa-concert-agenda__line"></span><p><?php echo nl2br( esc_html( $options['message'] ) ); ?></p><?php if ( '1' === $options['show_button'] && $options['button_url'] ) : ?><a class="sa-concert-agenda__button" href="<?php echo esc_url( $options['button_url'] ); ?>"><?php echo esc_html( $options['button_text'] ); ?></a><?php endif; ?></div>
		<div class="sa-concert-agenda__list"><?php if ( $conferences ) : foreach ( $conferences as $concert ) : $date = $this->formatted_date( get_post_meta( $concert->ID, '_sa_concert_date', true ) ); $city = get_post_meta( $concert->ID, '_sa_concert_city', true ); $region = get_post_meta( $concert->ID, '_sa_concert_region', true ); $kind = get_post_meta( $concert->ID, '_sa_concert_type', true ); ?><article class="sa-concert-agenda__item"><time datetime="<?php echo esc_attr( get_post_meta( $concert->ID, '_sa_concert_date', true ) ); ?>"><b><?php echo esc_html( $date[0] ); ?></b><span><?php echo esc_html( $date[1] ); ?></span></time><span class="sa-concert-agenda__divider"></span><svg class="sa-concert-agenda__pin" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6a2.5 2.5 0 0 1 0 5.5Z"/></svg><span class="sa-concert-agenda__place"><?php echo esc_html( $city . ( $region ? ', ' . $region : '' ) ); ?></span><span class="sa-concert-agenda__type sa-concert-agenda__type--<?php echo 'privado' === $kind ? 'private' : 'public'; ?>"><?php echo 'privado' === $kind ? 'Privado' : 'Público'; ?></span></article><?php endforeach; else : ?><p class="sa-concert-agenda__empty"><?php echo esc_html( $options['empty_message'] ); ?></p><?php endif; ?></div>
		</div></section>
		<?php
		return ob_get_clean();
	}
}

register_activation_hook( __FILE__, array( 'SA_Concert_Agenda', 'activate' ) );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
new SA_Concert_Agenda();
