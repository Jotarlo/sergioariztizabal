<?php

// Get pagination parameters
$count = ( isset( $block_attributes['number'] ) && !empty( $block_attributes['number'] ) ? intval( $block_attributes['number'] ) : 12 );
$paged = ( get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1 );
$image_list = array();
$query = array(
    'post_type'      => 'oum-location',
    'posts_per_page' => -1,
    'fields'         => 'ids',
);
$locations = get_posts( $query );
$target_url = ( isset( $block_attributes['url'] ) && $block_attributes['url'] != '' ? $block_attributes['url'] : '' );
// Collect all images from all locations
foreach ( $locations as $post_id ) {
    $image_string = get_post_meta( $post_id, '_oum_location_image', true );
    if ( $image_string ) {
        // Split multiple images
        $images = explode( '|', $image_string );
        foreach ( $images as $image ) {
            if ( !empty( trim( $image ) ) ) {
                $image_list[] = array(
                    'post_id'   => $post_id,
                    'image_url' => trim( $image ),
                );
            }
        }
    }
}
// Calculate pagination
$total_images = count( $image_list );
$total_pages = ceil( $total_images / $count );
$offset = ($paged - 1) * $count;
// Get current page images
$paginated_images = array_slice( $image_list, $offset, $count );
?>

<div class="open-user-map-image-gallery">
  <?php 
foreach ( $paginated_images as $image ) {
    ?>
    <div class="oum-gallery-item">
      <a href="<?php 
    echo add_query_arg( 'markerid', $image['post_id'], $target_url );
    ?>">
        <?php 
    // Convert relative path to absolute URL if needed
    $image_url = ( strpos( $image['image_url'], 'http' ) !== 0 ? site_url() . $image['image_url'] : $image['image_url'] );
    ?>
        <img src="<?php 
    echo esc_url( $image_url );
    ?>">
      </a>
    </div>
  <?php 
}
?>

  <?php 
if ( $total_pages > 1 ) {
    ?>
    <nav class="pagination oum-gallery-pagination">
      <?php 
    echo paginate_links( array(
        'current'   => $paged,
        'total'     => $total_pages,
        'prev_text' => __( '&laquo; Prev', 'open-user-map' ),
        'next_text' => __( 'Next &raquo;', 'open-user-map' ),
    ) );
    ?>
    </nav>
  <?php 
}
?>
</div>