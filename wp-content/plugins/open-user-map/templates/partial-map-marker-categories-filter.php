<?php
// Shared Marker Categories filter controls.
// Rendered either in the on-map overlay or inside the Advanced Filter "Marker Categories" section.

if (empty($types) || (isset($oum_hide_filterbox) && $oum_hide_filterbox === 'true')) {
  return;
}

if (!isset($oum_marker_types_label) || $oum_marker_types_label === '') {
  $oum_marker_types_label = get_option('oum_marker_types_label') ? get_option('oum_marker_types_label') : $this->oum_get_default_label('marker_types');
}

if (!isset($oum_enable_toggle_all_categories)) {
  $oum_enable_toggle_all_categories = get_option('oum_enable_toggle_all_categories', 'off');
}

if (!isset($oum_ui_color)) {
  $oum_ui_color = get_option('oum_ui_color') ? get_option('oum_ui_color') : $this->oum_ui_color_default;
}

// Heading is shown in the Advanced Filter sidebar, not in the on-map overlay.
$oum_marker_categories_show_heading = !empty($oum_marker_categories_show_heading);

$oum_route_icon_path = $this->plugin_path . 'assets/images/ico_route.svg';
$oum_area_icon_path = $this->plugin_path . 'assets/images/ico_area.svg';
$oum_route_icon_svg = is_readable($oum_route_icon_path) ? file_get_contents($oum_route_icon_path) : '';
$oum_area_icon_svg = is_readable($oum_area_icon_path) ? file_get_contents($oum_area_icon_path) : '';
?>

<div class="oum-marker-categories-filter">
  <?php if ($oum_marker_categories_show_heading): ?>
    <div class="oum-label"><?php echo esc_html($oum_marker_types_label); ?></div>
  <?php endif; ?>

  <?php if ($oum_enable_toggle_all_categories === 'on'): ?>
    <div class="oum-toggle-all-wrapper">
      <label class="oum-toggle-all-label">
        <input style="accent-color: <?php echo $oum_ui_color; ?>" type="checkbox" id="oum-toggle-all" class="oum-toggle-all-checkbox">
        <span class="oum-toggle-all-text"><?php echo __('Select all', 'open-user-map'); ?></span>
      </label>
    </div>
  <?php endif; ?>

  <?php foreach ($types as $type): ?>

    <?php
    $category_type = \OpenUserMapPlugin\Base\BaseController::oum_marker_category_type($type->term_id);
    $category_color = sanitize_hex_color(get_term_meta($type->term_id, 'oum_marker_color', true));
    if (!$category_color) {
      $category_color = $oum_ui_color ? $oum_ui_color : '#e82c71';
    }

    if ($type->term_id && get_term_meta($type->term_id, 'oum_marker_icon', true)) {
      //get type marker icon from oum-type taxonomy
      $type_marker_icon = get_term_meta($type->term_id, 'oum_marker_icon', true);
      $type_marker_user_icon = get_term_meta($type->term_id, 'oum_marker_user_icon', true);
    } else {
      //get type marker icon from settings
      $type_marker_icon = $marker_icon;
      $type_marker_user_icon = $marker_user_icon;
    }

    if ($type_marker_icon == 'user1' && $type_marker_user_icon) {
      $icon = esc_url($type_marker_user_icon);
    } else {
      $icon = esc_url($this->plugin_url) . 'src/leaflet/images/marker-icon_' . esc_attr($type_marker_icon) . '-2x.png';
    }
    ?>

    <label>
      <input style="accent-color: <?php echo $oum_ui_color; ?>" type="checkbox" name="type" value="<?php echo esc_attr($type->term_taxonomy_id); ?>" checked>
      <?php if ($category_type === 'polyline'): ?>
        <span class="oum-filter-category-shape-icon" style="color: <?php echo esc_attr($category_color); ?>" aria-hidden="true"><?php echo $oum_route_icon_svg; ?></span>
      <?php elseif ($category_type === 'polygon'): ?>
        <span class="oum-filter-category-shape-icon" style="color: <?php echo esc_attr($category_color); ?>" aria-hidden="true"><?php echo $oum_area_icon_svg; ?></span>
      <?php else: ?>
        <img alt="category icon" src="<?php echo $icon; ?>">
      <?php endif; ?>
      <span class="oum-filter-category-name"><?php echo esc_html($type->name); ?></span>
    </label>

  <?php endforeach; ?>
</div>
