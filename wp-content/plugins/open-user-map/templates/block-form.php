<?php 
// Ensure attributes exist for partial-map-init.php
$block_attributes = is_array($block_attributes ?? null) ? $block_attributes : array();

// Load identical settings/data as the map shortcode
require oum_get_template('partial-map-init.php');
?>

<div class="open-user-map oum-inline-form oum-hide-map">
  <?php
    // Inline form block always renders the submission form (independent of map setting).
    $oum_show_add_location_button = true;

    // Render the inline form immediately
    require oum_get_template('partial-map-add-location.php');

    // Render main map container + inline JS variables (kept for config); map stays hidden via CSS
    require oum_get_template('partial-map-render.php');
  ?>
</div>

<script>
// Initialize the form map when the main map is ready
document.addEventListener('oum:map_initialized', function() {
  if (typeof OUMFormMap !== 'undefined') {
    OUMFormMap.init();
    if (typeof start_lat !== 'undefined' && typeof start_lng !== 'undefined' && typeof start_zoom !== 'undefined') {
      OUMFormMap.setView(start_lat, start_lng, start_zoom);
    }
    setTimeout(() => OUMFormMap.invalidateSize(), 200);
  }
});
</script> 