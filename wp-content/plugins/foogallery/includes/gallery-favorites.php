<?php
/** Shared gallery favorite selection controls.
 *
 * @package FooGallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize a serialized gallery favorite selection.
 *
 * @param mixed $value Comma-separated attachment IDs.
 * @return int[]
 */
function foogallery_favorite_ids( $value ) {
	return foogallery_normalize_attachment_ids( is_scalar( $value ) ? (string) $value : array() );
}

/**
 * Resolve the gallery-wide favorite selection.
 *
 * @param array $settings Gallery settings.
 * @return int[]
 */
function foogallery_favorites_from_settings( $settings ) {
	return foogallery_favorite_ids( isset( $settings['favorite_ids'] ) ? $settings['favorite_ids'] : '' );
}

/**
 * Resolve favorites for rendering and unsaved shortcode previews.
 *
 * @return int[]
 */
function foogallery_current_favorite_ids() {
	global $current_foogallery, $current_foogallery_arguments;
	if ( is_array( $current_foogallery_arguments ) && array_key_exists( 'favorite_ids', $current_foogallery_arguments ) ) {
		return foogallery_favorite_ids( $current_foogallery_arguments['favorite_ids'] );
	}
	return isset( $current_foogallery ) ? foogallery_favorites_from_settings( (array) $current_foogallery->settings ) : array();
}

/**
 * Keep favorites through all template saves, including unsupported layouts.
 * Runs only inside the gallery's authorized save pipeline.
 *
 * @param array $settings Submitted settings.
 * @param int   $post_id Gallery ID.
 * @return array
 */
function foogallery_save_shared_favorites( $settings, $post_id ) {
	$previous = (array) get_post_meta( $post_id, FOOGALLERY_META_SETTINGS, true );
	if ( array_key_exists( 'favorite_ids', $settings ) ) {
		$settings['favorite_ids'] = implode( ',', foogallery_favorites_from_settings( $settings ) );
	} elseif ( array_key_exists( 'favorite_ids', $previous ) ) {
		$settings['favorite_ids'] = implode( ',', foogallery_favorites_from_settings( $previous ) );
	}
	unset( $settings['mosaic_prominent_ids'], $settings['masonry_favorite_ids'], $settings['mosaic_favorite_ids'] );
	return $settings;
}
add_filter( 'foogallery_save_gallery_settings', 'foogallery_save_shared_favorites', 20, 2 );

/**
 * Render the hidden setting consumed by the shared favorite stars.
 *
 * @param array  $field Field definition.
 * @param string $template Template slug.
 * @param string $key Unprefixed setting key.
 * @param object $gallery Optional gallery supplying the shared selection.
 */
function foogallery_render_favorites_field( $field, $template, $key, $gallery = null ) {
	$name  = $template . '_' . $key;
	$value = implode( ',', $gallery ? foogallery_favorites_from_settings( (array) $gallery->settings ) : foogallery_favorite_ids( $field['value'] ) );
	?>
	<input type="hidden" id="<?php echo esc_attr( 'FooGallerySettings_' . $name ); ?>" name="<?php echo esc_attr( FOOGALLERY_META_SETTINGS . '[favorite_ids]' ); ?>" value="<?php echo esc_attr( $value ); ?>"
		data-foogallery-favorites="<?php echo esc_attr( $template ); ?>" <?php disabled( ! empty( $field['disabled'] ) ); ?>
		data-label="<?php esc_attr_e( 'Mark as favorite', 'foogallery' ); ?>" data-selected-label="<?php esc_attr_e( 'Remove from favorites', 'foogallery' ); ?>" />
	<?php
}
