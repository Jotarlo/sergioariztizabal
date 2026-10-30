<?php
/**
 * Plugin Name: Sergio Aristizábal – Último lanzamiento
 * Description: Sección configurable de último lanzamiento para el Inicio, compatible con Elementor mediante widget y shortcode.
 * Version: 1.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Sitio Sergio Aristizábal
 * Text Domain: sergio-ultimo-lanzamiento
 */

defined( 'ABSPATH' ) || exit;

define( 'SA_UL_VERSION', '1.2.0' );
define( 'SA_UL_FILE', __FILE__ );
define( 'SA_UL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Valores iniciales de la sección.
 * La descripción permanece vacía para no añadir textos que el administrador no haya definido.
 */
function sa_ul_defaults() {
	return array(
		'title'       => '',
		'description' => '',
		'image_id'    => 0,
		'youtube_url' => '',
		'spotify_url' => '',
	);
}

function sa_ul_get_settings() {
	return wp_parse_args( (array) get_option( 'sa_ul_settings', array() ), sa_ul_defaults() );
}

function sa_ul_sanitize_settings( $input ) {
	$input  = is_array( $input ) ? $input : array();
	$output = sa_ul_defaults();

	$output['title']       = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
	$output['description'] = isset( $input['description'] ) ? wp_kses_post( $input['description'] ) : '';
	$output['image_id']    = isset( $input['image_id'] ) ? absint( $input['image_id'] ) : 0;
	$output['youtube_url'] = isset( $input['youtube_url'] ) ? esc_url_raw( trim( $input['youtube_url'] ) ) : '';
	$output['spotify_url'] = isset( $input['spotify_url'] ) ? esc_url_raw( trim( $input['spotify_url'] ) ) : '';

	return $output;
}

add_action( 'admin_menu', 'sa_ul_admin_menu' );
function sa_ul_admin_menu() {
	add_options_page(
		'Último lanzamiento',
		'Último lanzamiento',
		'manage_options',
		'sa-ultimo-lanzamiento',
		'sa_ul_render_settings_page'
	);
}

add_action( 'admin_init', 'sa_ul_register_settings' );
function sa_ul_register_settings() {
	register_setting(
		'sa_ul_settings_group',
		'sa_ul_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'sa_ul_sanitize_settings',
			'default'           => sa_ul_defaults(),
		)
	);

	add_settings_section(
		'sa_ul_main_section',
		'Datos del lanzamiento',
		'sa_ul_settings_section_callback',
		'sa-ultimo-lanzamiento'
	);

	add_settings_field( 'sa_ul_title', 'Título de la canción', 'sa_ul_title_field', 'sa-ultimo-lanzamiento', 'sa_ul_main_section' );
	add_settings_field( 'sa_ul_description', 'Descripción', 'sa_ul_description_field', 'sa-ultimo-lanzamiento', 'sa_ul_main_section' );
	add_settings_field( 'sa_ul_image', 'Foto', 'sa_ul_image_field', 'sa-ultimo-lanzamiento', 'sa_ul_main_section' );
	add_settings_field( 'sa_ul_youtube', 'Enlace de YouTube', 'sa_ul_youtube_field', 'sa-ultimo-lanzamiento', 'sa_ul_main_section' );
	add_settings_field( 'sa_ul_spotify', 'Enlace de Spotify', 'sa_ul_spotify_field', 'sa-ultimo-lanzamiento', 'sa_ul_main_section' );
}

function sa_ul_settings_section_callback() {
	echo '<p>Estos datos alimentan la sección del Inicio. El video se genera automáticamente a partir del enlace de YouTube.</p>';
}

function sa_ul_field_name( $key ) {
	return 'sa_ul_settings[' . $key . ']';
}

function sa_ul_title_field() {
	$settings = sa_ul_get_settings();
	printf(
		'<input type="text" class="regular-text" name="%1$s" value="%2$s" placeholder="Ej.: Falsa Compañía" />',
		esc_attr( sa_ul_field_name( 'title' ) ),
		esc_attr( $settings['title'] )
	);
}

function sa_ul_description_field() {
	$settings = sa_ul_get_settings();
	printf(
		'<textarea class="large-text" rows="4" name="%1$s" placeholder="Escribe aquí una breve descripción del lanzamiento.">%2$s</textarea><p class="description">Puedes dejarlo vacío si solo quieres mostrar el título, la foto y los enlaces.</p>',
		esc_attr( sa_ul_field_name( 'description' ) ),
		esc_textarea( $settings['description'] )
	);
}

function sa_ul_image_field() {
	$settings = sa_ul_get_settings();
	$image    = $settings['image_id'] ? wp_get_attachment_image_url( $settings['image_id'], 'medium' ) : '';
	?>
	<div class="sa-ul-image-picker">
		<input type="hidden" id="sa-ul-image-id" name="<?php echo esc_attr( sa_ul_field_name( 'image_id' ) ); ?>" value="<?php echo esc_attr( $settings['image_id'] ); ?>" />
		<button type="button" class="button" id="sa-ul-select-image">Seleccionar foto</button>
		<button type="button" class="button-link-delete" id="sa-ul-remove-image" <?php echo $image ? '' : 'hidden'; ?>>Quitar foto</button>
		<div class="sa-ul-image-preview" id="sa-ul-image-preview">
			<?php if ( $image ) : ?>
				<img src="<?php echo esc_url( $image ); ?>" alt="Vista previa de la foto seleccionada" />
			<?php endif; ?>
		</div>
	</div>
	<p class="description">La imagen aparecerá dentro del recuadro dorado/naranja de la sección.</p>
	<?php
}

function sa_ul_youtube_field() {
	$settings = sa_ul_get_settings();
	printf(
		'<input type="url" class="regular-text" name="%1$s" value="%2$s" placeholder="https://www.youtube.com/watch?v=..." /><p class="description">Acepta enlaces de YouTube, youtu.be, Shorts, Live o enlaces embed.</p>',
		esc_attr( sa_ul_field_name( 'youtube_url' ) ),
		esc_attr( $settings['youtube_url'] )
	);
}

function sa_ul_spotify_field() {
	$settings = sa_ul_get_settings();
	printf(
		'<input type="url" class="regular-text" name="%1$s" value="%2$s" placeholder="https://open.spotify.com/..." />',
		esc_attr( sa_ul_field_name( 'spotify_url' ) ),
		esc_attr( $settings['spotify_url'] )
	);
}

add_action( 'admin_enqueue_scripts', 'sa_ul_admin_assets' );
function sa_ul_admin_assets( $hook ) {
	if ( 'settings_page_sa-ultimo-lanzamiento' !== $hook ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_style( 'sa-ul-admin', SA_UL_URL . 'assets/css/admin.css', array(), SA_UL_VERSION );
	wp_enqueue_script( 'sa-ul-admin', SA_UL_URL . 'assets/js/admin.js', array(), SA_UL_VERSION, true );
}

function sa_ul_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>Último lanzamiento</h1>
		<p>Administra los contenidos que aparecerán en el Inicio de Sergio Aristizábal.</p>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'sa_ul_settings_group' );
			do_settings_sections( 'sa-ultimo-lanzamiento' );
			submit_button( 'Guardar lanzamiento' );
			?>
		</form>

		<hr />
		<h2>Cómo insertarlo</h2>
		<p>Con Elementor, busca el widget <strong>Sergio – Último lanzamiento</strong> en la categoría General y arrástralo a la página de Inicio.</p>
		<p>Como alternativa, usa un widget Shortcode con:</p>
		<code>[sergio_ultimo_lanzamiento]</code>
	</div>
	<?php
}

function sa_ul_get_youtube_id( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return '';
	}

	$parts = wp_parse_url( $url );
	$host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
	$path  = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';

	if ( in_array( $host, array( 'youtu.be', 'www.youtu.be' ), true ) && $path ) {
		return sa_ul_clean_youtube_id( strtok( $path, '/' ) );
	}

	if ( false !== strpos( $host, 'youtube.com' ) || false !== strpos( $host, 'youtube-nocookie.com' ) ) {
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query_args );
			if ( ! empty( $query_args['v'] ) ) {
				return sa_ul_clean_youtube_id( $query_args['v'] );
			}
		}

		if ( preg_match( '#^(?:embed|shorts|live)/([^/?]+)#', $path, $matches ) ) {
			return sa_ul_clean_youtube_id( $matches[1] );
		}
	}

	return '';
}

/**
 * YouTube IDs are case-sensitive; sanitize them without lowercasing.
 */
function sa_ul_clean_youtube_id( $value ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value );
}

function sa_ul_render( $settings = null ) {
	$settings = is_array( $settings ) ? wp_parse_args( $settings, sa_ul_defaults() ) : sa_ul_get_settings();

	if ( ! $settings['title'] && ! $settings['image_id'] && ! $settings['youtube_url'] && ! $settings['spotify_url'] ) {
		return '';
	}

	wp_enqueue_style( 'sa-ul-frontend', SA_UL_URL . 'assets/css/ultimo-lanzamiento.css', array(), SA_UL_VERSION );

	// Use the original attachment instead of a generated "large" derivative to avoid an additional quality loss.
	$image_url  = $settings['image_id'] ? wp_get_attachment_image_url( $settings['image_id'], 'full' ) : '';
	$youtube_id = sa_ul_get_youtube_id( $settings['youtube_url'] );
	$embed_url  = $youtube_id ? 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $youtube_id ) . '?rel=0&modestbranding=1' : '';
	$title_id   = wp_unique_id( 'sa-ul-release-title-' );

	ob_start();
	?>
	<section class="sa-ul-release" <?php echo $settings['title'] ? 'aria-labelledby="' . esc_attr( $title_id ) . '"' : 'aria-label="Último lanzamiento"'; ?>>
		<div class="sa-ul-release__inner">
			<div class="sa-ul-release__main">
				<div class="sa-ul-release__copy">
					<div class="sa-ul-release__copy-content">
						<p class="sa-ul-release__eyebrow">Último lanzamiento</p>
						<?php if ( $settings['title'] ) : ?>
							<h2 id="<?php echo esc_attr( $title_id ); ?>" class="sa-ul-release__title"><?php echo esc_html( $settings['title'] ); ?></h2>
						<?php endif; ?>
						<?php if ( $settings['description'] ) : ?>
							<div class="sa-ul-release__description"><?php echo wpautop( wp_kses_post( $settings['description'] ) ); ?></div>
						<?php endif; ?>
					</div>

					<div class="sa-ul-release__actions">
						<?php if ( $settings['youtube_url'] ) : ?>
							<a class="sa-ul-release__button sa-ul-release__button--primary" href="<?php echo esc_url( $settings['youtube_url'] ); ?>" target="_blank" rel="noopener noreferrer">Ver en YouTube</a>
						<?php endif; ?>
						<?php if ( $settings['spotify_url'] ) : ?>
							<a class="sa-ul-release__button sa-ul-release__button--secondary" href="<?php echo esc_url( $settings['spotify_url'] ); ?>" target="_blank" rel="noopener noreferrer">Escuchar en Spotify</a>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( $image_url ) : ?>
					<div class="sa-ul-release__photo-frame">
						<img class="sa-ul-release__photo" src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $settings['title'] ? 'Sergio Aristizábal – ' . $settings['title'] : 'Sergio Aristizábal' ); ?>" loading="lazy" />
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $embed_url ) : ?>
				<div class="sa-ul-release__video-wrap">
					<div class="sa-ul-release__video-heading">Video oficial</div>
					<div class="sa-ul-release__video">
						<iframe src="<?php echo esc_url( $embed_url ); ?>" title="<?php echo esc_attr( $settings['title'] ? 'Video oficial de ' . $settings['title'] : 'Video oficial de Sergio Aristizábal' ); ?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
					</div>
				</div>
			<?php elseif ( $settings['youtube_url'] ) : ?>
				<p class="sa-ul-release__video-warning">El enlace de YouTube no tiene un formato reconocible para insertar el video. El botón de YouTube seguirá funcionando.</p>
			<?php endif; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

add_shortcode( 'sergio_ultimo_lanzamiento', 'sa_ul_shortcode' );
function sa_ul_shortcode() {
	return sa_ul_render();
}

/**
 * Elementor registra el widget solo cuando Elementor está activo.
 * El shortcode sigue funcionando aunque Elementor no esté instalado.
 */
add_action( 'elementor/widgets/register', 'sa_ul_register_elementor_widget' );
function sa_ul_register_elementor_widget( $widgets_manager ) {
	if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
		return;
	}

	require_once plugin_dir_path( SA_UL_FILE ) . 'includes/class-sa-ul-elementor-widget.php';
	$widgets_manager->register( new SA_UL_Elementor_Widget() );
}
