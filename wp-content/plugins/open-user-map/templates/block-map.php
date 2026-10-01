<?php require oum_get_template('partial-map-init.php'); ?>

<?php
// Note: $oum_enable_advanced_filter and $oum_advanced_filter_layout are set in partial-map-init.php 
// and can be overridden by shortcode attributes
// Only add sidebar class if not using button or panel layout
$should_add_sidebar_class = ($oum_enable_advanced_filter && $oum_advanced_filter_layout !== 'button' && $oum_advanced_filter_layout !== 'panel');
?>

<div class="open-user-map <?php echo ($should_add_sidebar_class) ? 'oum-map-with-sidebar' : ''; ?>">

  <?php
  //TODO: manage variables from partial-map-init.php in a $oum_settings[]
  
  // Footer containers (add-location form, fullscreen popup) are rendered
  // via wp_footer in Frontend.php to work with page builder caching
  ?>

  <?php 
  // Sidebar layouts (left/right) include the advanced filter before the map container
  if($oum_advanced_filter_layout !== 'button' && $oum_advanced_filter_layout !== 'panel'): 
    require oum_get_template('partial-map-advanced-filter.php'); 
  endif; 
  ?>

  <!-- Map Container -->
  <div class="oum-map-container">
    <?php require oum_get_template('partial-map-render.php'); ?>
  </div>

</div>