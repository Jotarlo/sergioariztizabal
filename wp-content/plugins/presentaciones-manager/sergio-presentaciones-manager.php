<?php
/**
 * Plugin Name: Sergio Aristizábal – Presentaciones
 * Description: Administra videos de YouTube para la sección Presentaciones y los muestra con un shortcode responsive.
 * Version: 1.0.2
 * Author: Sergio Aristizábal
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SA_Presentaciones_Manager {
	// Identificador exclusivo para no interferir con el CPT del plugin del mapa.
	const POST_TYPE = 'sa_videos_pres';
	const NONCE      = 'sa_presentacion_meta';

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'maybe_seed_videos' ), 20 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
		add_shortcode( 'sergio_presentaciones_videos', array( $this, 'shortcode' ) );
	}

	public static function activate() {
		$self = new self();
		$self->register_post_type();
		flush_rewrite_rules();
		$self->seed_initial_videos();
	}

	public function maybe_seed_videos() {
		if ( ! get_option( 'sa_pm_initial_seeded' ) ) {
			$this->seed_initial_videos();
		}
	}

	private function seed_initial_videos() {
		// Importación inicial: solo se ejecuta si todavía no existen registros.
		if ( 0 === (int) wp_count_posts( self::POST_TYPE )->publish ) {
			$videos = array(
				array( 'Sergio Aristizábal en Manzanares', 'https://www.youtube-nocookie.com/embed/77Y4jhLfV4E' ),
				array( 'Presentación en Manzanares', 'https://www.youtube-nocookie.com/embed/31d-vQMqnaY' ),
				array( 'Falsa Compañía', 'https://www.youtube-nocookie.com/embed/iMC7apVG1tI' ),
				array( 'Madre Mía', 'https://www.youtube-nocookie.com/embed/S4VlcLtI84Q' ),
				array( 'Guerrero', 'https://www.youtube-nocookie.com/embed/dfS_lI4epQw' ),
				array( 'Esos Amores de Hoy', 'https://www.youtube-nocookie.com/embed/mi5bBDbA6v0' ),
				array( 'Ya No Me Nace', 'https://www.youtube-nocookie.com/embed/A4MPU10edxE' ),
				array( 'Humilde y Sencillo', 'https://www.youtube-nocookie.com/embed/GKptxud4IKw' ),
			);

			foreach ( $videos as $position => $video ) {
				$id = wp_insert_post(
					array(
						'post_type'   => self::POST_TYPE,
						'post_status' => 'publish',
						'post_title'  => $video[0],
						'menu_order'  => $position,
					),
					true
				);
				if ( ! is_wp_error( $id ) ) {
					update_post_meta( $id, '_sa_youtube_url', $video[1] );
					update_post_meta( $id, '_sa_active', '1' );
				}
			}
		}
		update_option( 'sa_pm_initial_seeded', '1', false );
	}

	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => 'Videos Presentaciones',
					'singular_name' => 'Video de presentación',
					'add_new_item'  => 'Agregar video',
					'edit_item'     => 'Editar video',
					'menu_name'     => 'Videos Presentaciones',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-video-alt3',
				'supports'     => array( 'title', 'page-attributes' ),
			)
		);
	}

	public function add_meta_boxes() {
		add_meta_box( 'sa-presentacion-data', 'Datos del video', array( $this, 'meta_box' ), self::POST_TYPE, 'normal', 'high' );
	}

	public function meta_box( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		$url      = get_post_meta( $post->ID, '_sa_youtube_url', true );
		$active   = get_post_meta( $post->ID, '_sa_active', true );
		$category = get_post_meta( $post->ID, '_sa_category', true );
		?>
		<p><label for="sa_youtube_url"><strong>Enlace de YouTube</strong></label><br>
		<input class="widefat" type="url" id="sa_youtube_url" name="sa_youtube_url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://www.youtube.com/watch?v=..."></p>
		<p><label for="sa_category"><strong>Categoría (opcional)</strong></label><br>
		<input class="regular-text" type="text" id="sa_category" name="sa_category" value="<?php echo esc_attr( $category ); ?>" placeholder="Presentaciones, Videoclips..."></p>
		<p><label><input type="checkbox" name="sa_active" value="1" <?php checked( '1', $active ?: '1' ); ?>> Video activo y visible en la página</label></p>
		<p class="description">El orden se controla con el campo <em>Orden</em> del panel Publicar. Menor número aparece primero.</p>
		<?php
	}

	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$url = isset( $_POST['sa_youtube_url'] ) ? esc_url_raw( wp_unslash( $_POST['sa_youtube_url'] ) ) : '';
		update_post_meta( $post_id, '_sa_youtube_url', $url );
		update_post_meta( $post_id, '_sa_active', isset( $_POST['sa_active'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_sa_category', isset( $_POST['sa_category'] ) ? sanitize_text_field( wp_unslash( $_POST['sa_category'] ) ) : '' );
	}

	public function columns( $columns ) {
		$columns['sa_status']   = 'Estado';
		$columns['sa_category'] = 'Categoría';
		$columns['sa_url']      = 'YouTube';
		return $columns;
	}

	public function column_content( $column, $post_id ) {
		if ( 'sa_status' === $column ) {
			echo get_post_meta( $post_id, '_sa_active', true ) === '1' ? '<span style="color:#16803c">Activo</span>' : '<span style="color:#777">Inactivo</span>';
		} elseif ( 'sa_category' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_sa_category', true ) ?: '—' );
		} elseif ( 'sa_url' === $column ) {
			echo esc_html( get_post_meta( $post_id, '_sa_youtube_url', true ) ?: '—' );
		}
	}

	public function enqueue_frontend() {
		if ( ! is_singular() ) {
			return;
		}
		wp_register_style( 'sa-presentaciones-manager', plugins_url( 'assets/presentaciones.css', __FILE__ ), array(), '1.0.2' );
		wp_enqueue_style( 'sa-presentaciones-manager' );
	}

	private function video_id( $url ) {
		$parts = wp_parse_url( trim( $url ) );
		if ( empty( $parts['host'] ) ) {
			return '';
		}
		$host = strtolower( preg_replace( '/^www\./', '', $parts['host'] ) );
		if ( 'youtu.be' === $host ) {
			return preg_replace( '/[^A-Za-z0-9_-].*/', '', ltrim( $parts['path'] ?? '', '/' ) );
		}
		if ( false !== strpos( $host, 'youtube.com' ) ) {
			if ( ! empty( $parts['query'] ) ) {
				parse_str( $parts['query'], $query );
				if ( ! empty( $query['v'] ) ) {
					return preg_replace( '/[^A-Za-z0-9_-].*/', '', $query['v'] );
				}
			}
			if ( preg_match( '#/(?:embed|shorts|live)/([A-Za-z0-9_-]{6,})#', $parts['path'] ?? '', $match ) ) {
				return $match[1];
			}
		}
		return '';
	}

	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'category' => '', 'limit' => -1 ), $atts, 'sergio_presentaciones_videos' );
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => intval( $atts['limit'] ),
			'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		);
		if ( '' !== trim( $atts['category'] ) ) {
			$args['meta_query'] = array( array( 'key' => '_sa_category', 'value' => sanitize_text_field( $atts['category'] ), 'compare' => 'LIKE' ) );
		}
		$posts = get_posts( $args );
		$out   = '<div class="sa-presentaciones-manager-grid" aria-label="Videos de presentaciones">';
		$count = 0;
		foreach ( $posts as $post ) {
			if ( '1' !== get_post_meta( $post->ID, '_sa_active', true ) ) {
				continue;
			}
			$id = $this->video_id( get_post_meta( $post->ID, '_sa_youtube_url', true ) );
			if ( ! $id ) {
				continue;
			}
			$count++;
			$out .= '<article class="sa-presentacion-video"><iframe src="https://www.youtube-nocookie.com/embed/' . esc_attr( $id ) . '" title="' . esc_attr( get_the_title( $post ) ) . '" loading="lazy" allowfullscreen></iframe><h3>' . esc_html( get_the_title( $post ) ) . '</h3></article>';
		}
		$out .= '</div>';
		return $count ? $out : '<p class="sa-presentaciones-empty">No hay videos activos.</p>';
	}
}

register_activation_hook( __FILE__, array( 'SA_Presentaciones_Manager', 'activate' ) );
new SA_Presentaciones_Manager();
