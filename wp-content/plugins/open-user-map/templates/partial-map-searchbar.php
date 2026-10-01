<?php
// Searchbar controls for the Advanced Filter "Searchbar" section.
// Relocates the on-map searchbar (Address, Marker, or Live Filter) into this panel.

if (!isset($oum_enable_searchbar) || $oum_enable_searchbar !== 'true') {
  return;
}

if (!isset($oum_searchbar_type) || $oum_searchbar_type === '') {
  $oum_searchbar_type = get_option('oum_searchbar_type') ? get_option('oum_searchbar_type') : 'address';
}

if (!isset($oum_searchmarkers_label) || $oum_searchmarkers_label === '') {
  $oum_searchmarkers_label = get_option('oum_searchmarkers_label') ? get_option('oum_searchmarkers_label') : $this->oum_get_default_label('searchmarkers');
}
?>

<div class="oum-searchbar-filter" data-searchbar-type="<?php echo esc_attr($oum_searchbar_type); ?>">
  <?php if ($oum_searchbar_type === 'markers'): ?>
    <div id="oum_search_marker"></div>
  <?php elseif ($oum_searchbar_type === 'live_filter'): ?>
    <input type="text" id="oum_filter_markers" placeholder="<?php echo esc_attr($oum_searchmarkers_label); ?>" />
  <?php else: ?>
    <div id="oum_search_address"></div>
  <?php endif; ?>
</div>
