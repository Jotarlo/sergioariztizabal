<?php
/**
 * Local index of posts that embed galleries (independent of feature reporting).
 *
 * @package FooGallery
 */

defined( 'ABSPATH' ) || exit;

/** Maintain gallery references for admin, REST, imports and background updates. */
class FooGallery_Gallery_Usage {
	const VERSION        = 1;
	const VERSION_OPTION = 'foogallery_gallery_usage_version';
	const CURSOR_OPTION  = 'foogallery_gallery_usage_cursor';
	const REBUILD_HOOK   = 'foogallery_rebuild_gallery_usage';
	const GROUP          = 'foogallery-gallery-usage';
	const BATCH_SIZE     = 100;

	/** Register indexing and the one-time repair of existing references. */
	public static function register() {
		add_action( 'save_post', array( __CLASS__, 'sync_post' ), 10, 2 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_schedule_rebuild' ) );
		add_action( self::REBUILD_HOOK, array( __CLASS__, 'rebuild_batch' ) );
	}

	/**
	 * Discover direct references using the existing compatibility filters.
	 *
	 * @param WP_Post $post Post to inspect.
	 * @return int[] Gallery IDs.
	 */
	public static function find_galleries( $post ) {
		$galleries = array_keys( foogallery_extract_gallery_shortcodes( $post->post_content ) );
		$galleries = apply_filters( 'foogallery_find_galleries_in_post', $galleries, $post );
		return array_values(
			array_unique(
				array_filter(
					array_map( 'intval', (array) $galleries ),
					function ( $id ) {
						return $id > 0;
					}
				)
			)
		);
	}

	/**
	 * Replace a post's index after any supported save, including REST requests.
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Saved post, or null to load it.
	 */
	public static function sync_post( $post_id, $post = null ) {
		// The gallery management API validates/restores lifecycle mutations before writing metadata.
		if ( ! empty( $GLOBALS['foogallery_gallery_management_update'] ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$post = get_post( $post ? $post : $post_id );
		if ( ! $post || in_array( $post->post_type, array( FOOGALLERY_CPT_GALLERY, FOOGALLERY_CPT_ALBUM ), true ) || ! in_array( $post->post_type, foogallery_allowed_post_types_for_usage(), true ) ) {
			return;
		}

		$galleries = self::find_galleries( $post );
		delete_post_meta( $post_id, FOOGALLERY_META_POST_USAGE );
		delete_post_meta( $post_id, FOOGALLERY_META_POST_USAGE_CSS );
		foreach ( $galleries as $gallery_id ) {
			add_post_meta( $post_id, FOOGALLERY_META_POST_USAGE, $gallery_id, false );
			do_action( 'foogallery_attach_gallery_to_post', $post_id, $gallery_id );
		}
	}

	/** Queue or resume repair without scanning the site during an admin request. */
	public static function maybe_schedule_rebuild() {
		if ( self::VERSION === (int) get_option( self::VERSION_OPTION ) || ! current_user_can( 'manage_options' ) || FooGallery_Jobs::pending( self::REBUILD_HOOK, self::GROUP ) ) {
			return;
		}
		self::schedule_batch();
	}

	/** Queue the next cursor as an argument so a running batch can continue. */
	private static function schedule_batch() {
		FooGallery_Jobs::schedule( self::REBUILD_HOOK, array( (int) get_option( self::CURSOR_OPTION, 0 ) ), self::GROUP );
	}

	/**
	 * Repair a bounded set of existing posts, including stale positive matches.
	 *
	 * @param int|null $expected_cursor Cursor of the queued batch, or null for a manual repair.
	 * @return void
	 */
	public static function rebuild_batch( $expected_cursor = null ) {
		global $wpdb;
		$cursor = (int) get_option( self::CURSOR_OPTION, 0 );
		if ( self::VERSION === (int) get_option( self::VERSION_OPTION ) || ( null !== $expected_cursor && $cursor !== (int) $expected_cursor ) ) {
			return;
		}
		$types = array_values( array_diff( foogallery_allowed_post_types_for_usage(), array( FOOGALLERY_CPT_GALLERY, FOOGALLERY_CPT_ALBUM ) ) );
		$ids   = array();
		if ( $types ) {
			$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
			$args         = array_merge( array( $cursor ), $types, array( self::BATCH_SIZE ) );
			// Uncached keyset scan: every value is prepared; the dynamic part is only placeholders.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ($placeholders) ORDER BY ID ASC LIMIT %d", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery
			if ( $wpdb->last_error ) {
				return; // Keep the cursor so a later admin visit can retry safely.
			}
		}
		foreach ( $ids as $post_id ) {
			self::sync_post( (int) $post_id );
		}
		if ( count( $ids ) < self::BATCH_SIZE ) {
			update_option( self::VERSION_OPTION, self::VERSION, false );
			delete_option( self::CURSOR_OPTION );
		} else {
			update_option( self::CURSOR_OPTION, (int) end( $ids ), false );
			self::schedule_batch();
		}
	}
}
