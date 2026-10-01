<div class="form-field">
  <label for="oum_marker_category_type"><?php 
echo esc_html__( 'Category Type', 'open-user-map' );
?></label>
  <?php 
?>
  <?php 
if ( !oum_fs()->is_plan_or_trial( 'pro' ) || !oum_fs()->is_premium() ) {
    ?>
    <input type="hidden" name="oum_marker_category_type" value="point">
    <select id="oum_marker_category_type" disabled>
      <option value="point" selected><?php 
    echo esc_html__( 'Marker', 'open-user-map' );
    ?></option>
      <option value="polyline"><?php 
    echo esc_html__( 'Line', 'open-user-map' );
    ?> PRO</option>
      <option value="polygon"><?php 
    echo esc_html__( 'Area', 'open-user-map' );
    ?> PRO</option>
    </select>
    <span class="oum-pro">PRO</span>
  <?php 
}
?>
  <p class="description"><?php 
echo esc_html__( 'Choose whether this category is used for markers, lines, or areas.', 'open-user-map' );
?></p>
</div>

<div data-oum-category-type-panel="point">
<div class="marker_icons">
  <?php 
$marker_icon = ( get_option( 'oum_marker_icon' ) ? get_option( 'oum_marker_icon' ) : 'default' );
$items = $this->marker_icons;
foreach ( $items as $val ) {
    $selected = ( $marker_icon == $val ? 'checked' : '' );
    echo "<label class='{$selected}'><div class='marker_icon_preview' data-style='{$val}'></div><input type='radio' name='oum_marker_icon' {$selected} value='{$val}'></label>";
}
?>

  <?php 
?>

  <?php 
if ( !oum_fs()->is_plan_or_trial( 'pro' ) || !oum_fs()->is_premium() ) {
    ?>

    <?php 
    //pro marker icons
    $pro_items = $this->pro_marker_icons;
    foreach ( $pro_items as $val ) {
        echo "<label class='pro-only label_marker_user_icon'><div class='marker_icon_preview' data-style='{$val}'></div>";
        echo "\n        <div class='icon_upload'>\n          <button disabled class='button button-secondary'>" . __( 'Upload Icon', 'open-user-map' ) . "</button>\n          <p class='description'>PNG, max. 100px</p>\n        </div>\n      ";
        echo "<a class='oum-gopro-text' href='" . oum_fs()->get_upgrade_url() . "'>" . __( 'Upgrade to PRO to use custom icons.', 'open-user-map' ) . "</a>";
        echo "</label>";
    }
    ?>

  <?php 
}
?>

</div>
</div>

<div data-oum-category-type-panel="vector">
<?php 
?>

<?php 
if ( !oum_fs()->is_plan_or_trial( 'pro' ) || !oum_fs()->is_premium() ) {
    ?>
  <div class="form-field oum-gopro-div">
    <label><?php 
    echo esc_html__( 'Line / Area color', 'open-user-map' );
    ?> <span class="oum-pro">PRO</span></label>
    <input type="text" value="#e82c71" placeholder="#e82c71" disabled>
    <p class="description"><?php 
    echo esc_html__( 'Used as the stroke and fill color for lines and areas in this Marker Category.', 'open-user-map' );
    ?></p>
    <a class="oum-gopro-text" href="<?php 
    echo oum_fs()->get_upgrade_url();
    ?>"><?php 
    echo esc_html__( 'Upgrade to PRO to customize colors for lines and areas.', 'open-user-map' );
    ?></a>
  </div>
<?php 
}
?>
</div>