<?php

$oum_all_locations = [];
/**
 * Clean UTF-8 encoding for location data
 */
if ( !function_exists( 'clean_utf8' ) ) {
    function clean_utf8(  $value  ) {
        if ( is_array( $value ) ) {
            return array_map( 'clean_utf8', $value );
        } elseif ( is_string( $value ) ) {
            // Guard against environments where mbstring is disabled.
            if ( function_exists( 'mb_convert_encoding' ) ) {
                return mb_convert_encoding( $value, 'UTF-8', 'UTF-8' );
                // Re-encode to valid UTF-8
            }
            return $value;
        } else {
            return $value;
        }
    }

}
foreach ( $locations_list as $location ) {
    // Full bubble HTML (filters: oum_location_bubble_image, oum_location_bubble_content) — used only to derive plain search text here; actual HTML loads via AJAX on popup open.
    $bubble_html = \OpenUserMapPlugin\Base\LocationMapBubbleBuilder::build_html( $location, $this->plugin_url );
    $content_plain = \OpenUserMapPlugin\Base\LocationMapBubbleBuilder::plain_search_text( $bubble_html );
    $oum_location = array(
        'title'             => html_entity_decode( esc_attr( $location['title'] ) ),
        'lat'               => esc_attr( $location['lat'] ),
        'lng'               => esc_attr( $location['lng'] ),
        'zoom'              => esc_attr( $location['zoom'] ),
        'geometry_type'     => ( isset( $location['geometry_type'] ) ? sanitize_key( $location['geometry_type'] ) : 'point' ),
        'geometry'          => ( isset( $location['geometry'] ) && is_array( $location['geometry'] ) ? $location['geometry'] : null ),
        'show_measurement'  => !empty( $location['show_measurement'] ),
        'category_color'    => ( isset( $location['category_color'] ) ? sanitize_hex_color( $location['category_color'] ) : '' ),
        'content'           => $content_plain,
        'icon'              => esc_attr( $location['icon'] ),
        'types'             => ( isset( $location['types'] ) ? $location['types'] : array() ),
        'post_id'           => esc_attr( $location['post_id'] ),
        'address'           => esc_attr( $location['address'] ),
        'text'              => wp_kses_post( $location['text'] ),
        'image'             => ( isset( $location['images'] ) && !empty( $location['images'] ) ? implode( '|', array_map( 'esc_url', $location['images'] ) ) : '' ),
        'audio'             => esc_url( $location['audio'] ),
        'video'             => esc_url( $location['video'] ),
        'custom_fields'     => $location['custom_fields'],
        'votes'             => ( isset( $location['votes'] ) ? intval( $location['votes'] ) : 0 ),
        'star_rating_avg'   => ( isset( $location['star_rating_avg'] ) ? floatval( $location['star_rating_avg'] ) : 0 ),
        'star_rating_count' => ( isset( $location['star_rating_count'] ) ? intval( $location['star_rating_count'] ) : 0 ),
    );
    // Adjust marker payload (not for replacing popup HTML; use oum_location_bubble_content for that).
    $oum_location = apply_filters( 'oum_location_data', $oum_location, $location['post_id'] );
    $oum_all_locations[] = $oum_location;
}
// Clean UTF-8 encoding for location data (Repair if needed)
$oum_all_locations_clean = clean_utf8( $oum_all_locations );
// Fixing height without unit
$oum_map_height = ( is_numeric( $oum_map_height ) ? $oum_map_height . 'px' : $oum_map_height );
$oum_map_height_mobile = ( is_numeric( $oum_map_height_mobile ) ? $oum_map_height_mobile . 'px' : $oum_map_height_mobile );
$custom_image_click_class = ( $map_style === 'CustomImage' && $oum_custom_image_clickable !== 'on' ? ' oum-custom-image-click-through' : '' );
?>

<div class="box-wrap map-size-<?php 
echo esc_attr( $map_size );
?> <?php 
if ( $oum_enable_regions == 'on' && $regions && count( $regions ) > 0 ) {
    ?>oum-regions-<?php 
    echo $oum_regions_layout_style;
    ?> <?php 
}
?>">
  <?php 
if ( $oum_enable_regions == 'on' && $regions && count( $regions ) > 0 ) {
    ?>
    <div class="tab-wrap">
      <div class="oum-tabs" id="nav-tab-<?php 
    echo $unique_id;
    ?>" role="tablist">
        <?php 
    $i = 0;
    ?>
        <?php 
    foreach ( $regions as $region ) {
        ?>

          <?php 
        $i++;
        $name = $region->name;
        $t_id = $region->term_id;
        $term_lat = get_term_meta( $t_id, 'oum_lat', true );
        $term_lng = get_term_meta( $t_id, 'oum_lng', true );
        $term_zoom = get_term_meta( $t_id, 'oum_zoom', true );
        ?>
          <div class="nav-item nav-link <?php 
        echo ( isset( $oum_start_region_name ) && $name == $oum_start_region_name ? 'active' : '' );
        ?> change_region" data-lat="<?php 
        echo esc_attr( $term_lat );
        ?>" data-lng="<?php 
        echo esc_attr( $term_lng );
        ?>" data-zoom="<?php 
        echo esc_attr( $term_zoom );
        ?>" data-toggle="tab" role="tab"><?php 
        echo esc_html( $name );
        ?></div>

        <?php 
    }
    ?>
      </div>
    </div>
  <?php 
}
?>

  <div class="map-wrap">
    <div class="oum-loading-overlay">
      <div class="oum-loading-spinner"></div>
    </div>
    <div id="map-<?php 
echo $unique_id;
?>" class="leaflet-map map-style_<?php 
echo esc_attr( $map_style );
echo esc_attr( $custom_image_click_class );
?>"<?php 
echo $this->get_tile_provider_data_attribute( $map_style, 'container' );
?>></div>

    <?php 
// Keep the searchbar on the map unless Advanced Filter is rendering it.
$show_searchbar_on_map = $oum_enable_searchbar === 'true' && empty( $show_searchbar_in_advanced_filter );
?>
    <?php 
if ( $show_searchbar_on_map && $oum_searchbar_type == 'markers' ) {
    ?>
      <div id="oum_search_marker"></div>
    <?php 
}
?>

    <?php 
if ( $show_searchbar_on_map && $oum_searchbar_type == 'live_filter' ) {
    ?>
      <input type="text" id="oum_filter_markers" class="oum-hidden" placeholder="<?php 
    echo esc_attr( $oum_searchmarkers_label );
    ?>" />
    <?php 
}
?>

    <?php 
// Show "Add Location" when visitors are allowed, or when "Just Me" is selected and the current user is an Admin.
$oum_show_add_location_button = $oum_enable_add_location === 'on' || current_user_can( 'manage_options' );
if ( $oum_show_add_location_button ) {
    ?>
    
      <?php 
    ?>

      <?php 
    if ( !oum_fs()->is_plan_or_trial( 'pro' ) || !oum_fs()->is_premium() ) {
        ?>

        <div id="open-add-location-overlay" class="open-add-location-overlay oum-hidden" style="background-color: <?php 
        echo $oum_ui_color;
        ?>"><span class="btn_icon">+</span><span class="btn_text"><?php 
        echo esc_attr( $oum_plus_button_label );
        ?></span></div>

      <?php 
    }
    ?>

    <?php 
}
?>

    <?php 
// Note: $oum_enable_advanced_filter and $oum_advanced_filter_layout are set in partial-map-init.php
// and can be overridden by shortcode attributes
if ( !isset( $oum_enable_advanced_filter ) ) {
    $oum_enable_advanced_filter = get_option( 'oum_enable_advanced_filter' );
}
if ( !isset( $oum_advanced_filter_layout ) ) {
    $oum_advanced_filter_layout = get_option( 'oum_advanced_filter_layout', 'left' );
}
if ( !isset( $oum_advanced_filter_sections ) ) {
    $oum_advanced_filter_sections = get_option( 'oum_advanced_filter_sections', array() );
}
$has_floating_filter = $oum_enable_advanced_filter && ($oum_advanced_filter_layout === 'button' || $oum_advanced_filter_layout === 'panel') && !empty( $oum_advanced_filter_sections );
// Hide the on-map overlay when Marker Categories live in the Advanced Filter.
$show_marker_filters = $types && $oum_hide_filterbox !== 'true' && empty( $show_marker_categories_in_advanced_filter );
if ( $has_floating_filter || $show_marker_filters ) {
    ?>
      <div class="oum-map-filter-wrapper">
        <?php 
    if ( $has_floating_filter ) {
        ?>
          <?php 
        require oum_get_template( 'partial-map-advanced-filter.php' );
        ?>
        <?php 
    }
    ?>

        <?php 
    if ( $show_marker_filters ) {
        ?>
          <div class="oum-filter-controls <?php 
        echo $oum_collapse_filter;
        ?> oum-hidden">
            <div class="oum-filter-toggle"></div>
            <div class="oum-filter-list">
              <div class="close-filter-list">&#x2715;</div>
              <?php 
        $oum_marker_categories_show_heading = false;
        require oum_get_template( 'partial-map-marker-categories-filter.php' );
        ?>
            </div>
          </div>
        <?php 
    }
    ?>
      </div>
    <?php 
}
?>

    <?php 
?>

    <script type="text/javascript" id="oum-init-map" data-category="functional" class="cmplz-native"<?php 
echo $this->get_tile_provider_data_attribute( $map_style, 'script' );
?>>

      map_el = `map-<?php 
echo $unique_id;
?>`;

      if(document.getElementById(map_el)) {
        /* Transfer PHP array to JS json */
        var oum_all_locations = <?php 
echo json_encode( $oum_all_locations_clean, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
?>;

        // Wait for the main initialization function to be available, then call it
        function oumWaitForMainInit() {
          if (typeof oumInitializeMap === 'function') {
            console.log('🗺️ Open User Map: Starting');
            
            // Initialize the map
            oumInitializeMap();
          } else {
            setTimeout(oumWaitForMainInit, 100);
          }
        }
        
        setTimeout(oumWaitForMainInit, 100);
      }

    </script>

    <script type="text/javascript" id="oum-inline-js" 
      data-category="functional" 
      class="cmplz-native" 
      data-minify="0"
      data-no-optimize="1"
      data-no-defer="1"
      data-no-combine="1"
      data-cfasync="false"
      data-pagespeed-no-defer
      data-boot="1">

      map_el = `map-<?php 
echo $unique_id;
?>`;

      if(document.getElementById(map_el)) {

        // Wait for OUMLoader to be defined
        function oumLoading() {
          if (typeof OUMLoader !== 'undefined') {
            // Initialize loader for this map
            OUMLoader.initLoader(map_el);

            // Add event listener for map initialization complete
            document.addEventListener('oum:map_initialized', function(e) {
              if (e.detail.mapId === map_el) {
                OUMLoader.setMapInitialized(map_el);
              }
            });
          } else {
            // If OUMLoader is not yet defined, wait and try again
            setTimeout(oumLoading, 100);
          }
        }

        // OUM Loading Spinner
        oumLoading();

        // OUM inline JS variables
        var mapStyle = <?php 
echo wp_json_encode( $map_style );
?>;
        var oum_tile_provider_mapbox_key = <?php 
echo wp_json_encode( (string) $oum_tile_provider_mapbox_key );
?>;
        var oum_tile_provider_carto_key = <?php 
echo wp_json_encode( (string) $oum_tile_provider_carto_key );
?>;
        var marker_icon_url = <?php 
echo wp_json_encode( (string) (( $marker_icon == 'user1' && $marker_user_icon ? esc_url( $marker_user_icon ) : esc_url( $this->plugin_url ) . 'src/leaflet/images/marker-icon_' . esc_attr( $marker_icon ) . '-2x.png' )) );
?>;
        var marker_shadow_url = <?php 
echo wp_json_encode( (string) (esc_url( $this->plugin_url ) . 'src/leaflet/images/marker-shadow.png') );
?>;
        var oum_enable_scrollwheel_zoom_map = <?php 
echo $oum_enable_scrollwheel_zoom_map;
?>;
        var oum_enable_cluster = <?php 
echo $oum_enable_cluster;
?>;
        var oum_enable_fullscreen = <?php 
echo $oum_enable_fullscreen;
?>;

        var oum_enable_searchbar = <?php 
echo $oum_enable_searchbar;
?>;
        var oum_searchbar_type = <?php 
echo wp_json_encode( (string) $oum_searchbar_type );
?>;

        var oum_geosearch_selected_provider = "";
        var oum_geosearch_provider = <?php 
echo wp_json_encode( (string) $oum_geosearch_provider );
?>;
        var oum_geosearch_provider_geoapify_key = <?php 
echo wp_json_encode( (string) $oum_geosearch_provider_geoapify_key );
?>;
        var oum_geosearch_provider_here_key = <?php 
echo wp_json_encode( (string) $oum_geosearch_provider_here_key );
?>;
        var oum_geosearch_provider_mapbox_key = <?php 
echo wp_json_encode( (string) $oum_geosearch_provider_mapbox_key );
?>;
        
        var oum_enable_searchaddress_button = <?php 
echo $oum_enable_searchaddress_button;
?>;
        var oum_searchaddress_label = <?php 
echo wp_json_encode( (string) $oum_searchaddress_label );
?>;

        var oum_enable_searchmarkers_button = <?php 
echo $oum_enable_searchmarkers_button;
?>;
        var oum_searchmarkers_label = <?php 
echo wp_json_encode( (string) $oum_searchmarkers_label );
?>;
        var oum_searchmarkers_zoom = <?php 
echo wp_json_encode( (string) $oum_searchmarkers_zoom );
?>;

        var oum_enable_currentlocation = <?php 
echo $oum_enable_currentlocation;
?>;
        var oum_action_after_submit = <?php 
echo wp_json_encode( (string) $oum_action_after_submit );
?>;
        var thankyou_redirect = <?php 
echo wp_json_encode( (string) $thankyou_redirect );
?>;
        var start_lat = Number(<?php 
echo wp_json_encode( (float) $start_lat );
?>);
        var start_lng = Number(<?php 
echo wp_json_encode( (float) $start_lng );
?>);
        var start_zoom = Number(<?php 
echo wp_json_encode( (float) $start_zoom );
?>);
        
        var oum_enable_fixed_map_bounds = <?php 
echo wp_json_encode( (string) $oum_enable_fixed_map_bounds );
?>;
        var oum_use_settings_start_location = <?php 
echo $oum_use_settings_start_location;
?>;
        var oum_has_regions = <?php 
echo ( $oum_enable_regions == 'on' && $regions && count( $regions ) > 0 ? 'true' : 'false' );
?>;
        var oum_enable_multiple_marker_types = <?php 
echo wp_json_encode( (string) $oum_enable_multiple_marker_types );
?>;
        var oum_hide_filterbox = <?php 
echo $oum_hide_filterbox;
?>;
        var oum_hide_location_popup = <?php 
echo ( isset( $oum_hide_location_popup ) && $oum_hide_location_popup === 'true' ? 'true' : 'false' );
?>;
        var oum_enable_address = <?php 
echo wp_json_encode( (string) $oum_enable_address );
?>;
        var oum_enable_address_autofill = <?php 
echo wp_json_encode( (string) $oum_enable_address_autofill );
?>;

        // WordPress timezone for opening hours calculations
        var oum_wordpress_timezone = `<?php 
$timezone_string = get_option( 'timezone_string' );
if ( $timezone_string ) {
    echo esc_js( $timezone_string );
} else {
    $gmt_offset = get_option( 'gmt_offset' );
    if ( $gmt_offset !== false ) {
        echo esc_js( 'UTC' . (( $gmt_offset >= 0 ? '+' : '' )) . $gmt_offset );
    } else {
        echo 'UTC';
    }
}
?>`;

        // Custom Image data
        var oum_custom_image_url = <?php 
echo wp_json_encode( (string) $oum_custom_image_url );
?>;
        var oum_custom_image_bounds = <?php 
echo $oum_custom_image_bounds;
?>;
        var oum_custom_image_hide_tiles = <?php 
echo ( $oum_custom_image_hide_tiles === 'on' ? 'true' : 'false' );
?>;
        var oum_custom_image_clickable = <?php 
echo ( $oum_custom_image_clickable === 'on' ? 'true' : 'false' );
?>;
        var oum_custom_image_background_color = <?php 
echo wp_json_encode( (string) $oum_custom_image_background_color );
?>;

        var oum_location = {};
        var oum_custom_css = '';
        var oum_custom_script = '';
        var oum_max_image_filesize = <?php 
echo esc_attr( $oum_max_image_filesize );
?>;
        var oumMap;
        var oumMap2;

        /**
         * Conditional Field Feature
         * 
         * @param {string} sourceField - The source field selector
         * @param {string} targetField - The target field selector
         * @param {array} condShow - The values that should show the target field
         * @param {array|null} condHide - The values that should hide the target field. If empty/null/undefined, the field will be hidden when condShow is not met.
         */
        var oumConditionalField = (sourceField, targetField, condShow, condHide = null) => {
            const sourceElements = document.querySelectorAll(sourceField); // Select all radios/checkboxes or single select
            const targetElementWrapper = document.querySelector(targetField)?.parentElement; /* works with custom fields only */

            // Check if both sourceElements and targetElementWrapper exist
            if (!sourceElements.length) {
                console.warn(`OUM: Source field(s) not found: ${sourceField}`);
                return;
            }

            if (!targetElementWrapper) {
                console.warn(`OUM: Target field wrapper not found: ${targetField}`);
                return;
            }

            /* Event listener for change */
            const onChangeHandler = function() {
                // Get selected values for checkboxes and single selected value for radios/select
                const selectedValues = Array.from(sourceElements)
                    .filter(element => element.checked || element.tagName === 'SELECT')
                    .map(element => element.value);

                const selectedValue = selectedValues[0]; // For radios and selects, we use only the first (and only) value

                console.log('OUM: run condition', {selectedValue, sourceField, targetField, condShow, condHide});
                
                // Show or hide target field based on the selected value(s)
                if (condShow.includes(selectedValue)) {
                    // Show the field if condShow condition is met
                    targetElementWrapper.style.display = 'block';
                } else if (condHide && Array.isArray(condHide) && condHide.length > 0 && condHide.includes(selectedValue)) {
                    // Hide the field if condHide is provided and condition is met
                    targetElementWrapper.style.display = 'none';
                } else if (!condHide || (Array.isArray(condHide) && condHide.length === 0)) {
                    // If condHide is empty/null/undefined, hide the field when condShow is not met
                    targetElementWrapper.style.display = 'none';
                }
            };

            /* Attach the event listener to each radio/checkbox or select */
            sourceElements.forEach(element => {
                element.addEventListener('change', onChangeHandler);
            });

            /* Trigger initially */
            onChangeHandler(); // Call it directly to set initial state
        };

        /**
         * Add Custom Styles
         */
        
        <?php 
if ( $oum_ui_color ) {
    ?>
          <?php 
    // Helper function to darken hex color by percentage (similar to SASS darken)
    if ( !function_exists( 'oum_darken_color' ) ) {
        function oum_darken_color(  $color, $percent  ) {
            $color = ltrim( $color, '#' );
            if ( strlen( $color ) == 3 ) {
                $color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
            }
            $rgb = array_map( 'hexdec', str_split( $color, 2 ) );
            foreach ( $rgb as &$c ) {
                $c = max( 0, min( 255, round( $c * (1 - $percent / 100) ) ) );
            }
            return '#' . implode( '', array_map( function ( $c ) {
                return str_pad(
                    dechex( $c ),
                    2,
                    '0',
                    STR_PAD_LEFT
                );
            }, $rgb ) );
        }

    }
    $oum_ui_color_darkened = oum_darken_color( $oum_ui_color, 10 );
    ?>
          /* custom color */
          oum_custom_css += `
            .open-user-map .add-location #close-add-location-overlay:hover {color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .box-wrap .map-wrap .open-add-location-overlay {background-color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .box-wrap .map-wrap .open-add-location-overlay:hover,
            .open-user-map .box-wrap .map-wrap .open-add-location-overlay:active {background-color: <?php 
    echo $oum_ui_color_darkened;
    ?> !important}
            .open-user-map .box-wrap .map-wrap .oum-filter-controls .oum-filter-list .close-filter-list:hover {color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .oum-advanced-filter-button .oum-advanced-filter-content .close-advanced-filter:hover,
            .open-user-map .oum-advanced-filter-panel .oum-advanced-filter-content .close-advanced-filter:hover {color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map input.oum-switch[type="checkbox"]:checked + label::before {background-color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .add-location .location-overlay-content #oum_add_location .oum-required-indicator {color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .add-location .location-overlay-content #oum_add_location h2 {color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .add-location .location-overlay-content #oum_add_location input[type=text]:focus,
            .open-user-map .add-location .location-overlay-content #oum_add_location input[type=email]:focus,
            .open-user-map .add-location .location-overlay-content #oum_add_location input[type=url]:focus,
            .open-user-map .add-location .location-overlay-content #oum_add_location textarea:focus,
            .open-user-map .add-location .location-overlay-content #oum_add_location select:focus {border-color: <?php 
    echo $oum_ui_color;
    ?> !important; box-shadow: 0 0 0 2px <?php 
    echo $oum_ui_color;
    ?>1a !important}
            .open-user-map .add-location .location-overlay-content #oum_add_location_thankyou h3 {color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .oum_location_text a {color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .oum_location_text .oum_vote_button_wrap .oum_vote_button.voted {background: <?php 
    echo $oum_ui_color;
    ?> !important; border-color: <?php 
    echo $oum_ui_color;
    ?> !important;}
            .open-user-map .oum-tabs {border-color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .oum-tabs .nav-item:hover {color: <?php 
    echo $oum_ui_color;
    ?> !important; border-color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .oum-tabs .nav-item.active {color: <?php 
    echo $oum_ui_color;
    ?> !important; border-color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .box-wrap .map-wrap .oum-attribution a {color: <?php 
    echo $oum_ui_color;
    ?> !important;}
            /* Submit Button */
            .open-user-map .add-location .location-overlay-content #oum_add_location input[type=submit] {background-color: <?php 
    echo $oum_ui_color;
    ?> !important; border-color: <?php 
    echo $oum_ui_color;
    ?> !important;}
            .open-user-map .add-location .location-overlay-content #oum_add_location input[type=submit]:hover,
            .open-user-map .add-location .location-overlay-content #oum_add_location input[type=submit]:active {background-color: <?php 
    echo $oum_ui_color_darkened;
    ?> !important;}
            /* Message CTA Buttons */
            .open-user-map .add-location .location-overlay-content #oum_add_location_thankyou button {background-color: <?php 
    echo $oum_ui_color;
    ?> !important; border-color: <?php 
    echo $oum_ui_color;
    ?> !important;}
            .open-user-map .add-location .location-overlay-content #oum_add_location_thankyou button:hover,
            .open-user-map .add-location .location-overlay-content #oum_add_location_thankyou button:active {background-color: <?php 
    echo $oum_ui_color_darkened;
    ?> !important;}
            .open-user-map .add-location .location-overlay-content .oum-delete-confirmation button {background-color: <?php 
    echo $oum_ui_color;
    ?> !important; border-color: <?php 
    echo $oum_ui_color;
    ?> !important;}
            .open-user-map .add-location .location-overlay-content .oum-delete-confirmation button:hover,
            .open-user-map .add-location .location-overlay-content .oum-delete-confirmation button:active {background-color: <?php 
    echo $oum_ui_color_darkened;
    ?> !important;}
            /* Media Section Colors */
            .open-user-map .add-location .location-overlay-content #oum_add_location .oum_media .media-upload label {color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .add-location .location-overlay-content #oum_add_location .oum_media .oum-image-upload .media-upload-top label .multi-upload-indicator {background: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .add-location .location-overlay-content #oum_add_location .oum_media .oum-video-upload input[type=text]:hover {border-color: <?php 
    echo $oum_ui_color;
    ?> !important}
            .open-user-map .add-location .location-overlay-content #oum_add_location .oum_media .oum-video-upload input[type=text]:focus {border-color: <?php 
    echo $oum_ui_color;
    ?> !important; box-shadow: 0 0 0 2px <?php 
    echo $oum_ui_color;
    ?>1a !important}
            .open-user-map .add-location .location-overlay-content #oum_add_location .oum_media .image-preview-placeholder {border-color: <?php 
    echo $oum_ui_color;
    ?> !important; background: <?php 
    echo $oum_ui_color;
    ?>0a !important}
            .open-user-map .add-location .location-overlay-content #oum_add_location .oum_media .oum-image-preview-grid .image-preview-item.dragging {border-color: <?php 
    echo $oum_ui_color;
    ?> !important}
            /* List Styles */
            .open-user-map-locations-list .oum-locations-list-item .oum_location_text a {color: <?php 
    echo $oum_ui_color;
    ?> !important} 
            .open-user-map-locations-list .oum-locations-list-item .oum_location_text .oum_vote_button_wrap .oum_vote_button.voted {background: <?php 
    echo $oum_ui_color;
    ?> !important; border-color: <?php 
    echo $oum_ui_color;
    ?> !important;}`;

        <?php 
}
?>

        <?php 
if ( $oum_map_height ) {
    ?>

          /* custom map height */
          oum_custom_css += <?php 
    echo wp_json_encode( '.open-user-map .box-wrap > .map-wrap {padding: 0 !important; height: ' . $oum_map_height . ' !important; aspect-ratio: unset !important;}' );
    ?>;

        <?php 
}
?>

        <?php 
if ( $oum_map_height_mobile ) {
    ?>

          /* custom map height */
          oum_custom_css += <?php 
    echo wp_json_encode( '@media screen and (max-width: 768px) {.open-user-map .box-wrap > .map-wrap {padding: 0 !important; height: ' . $oum_map_height_mobile . ' !important; aspect-ratio: unset !important;}}' );
    ?>;

        <?php 
}
?>

        var custom_style = document.createElement('style');

        if (custom_style.styleSheet) {
          custom_style.styleSheet.cssText = oum_custom_css;
        } else {
          custom_style.appendChild(document.createTextNode(oum_custom_css));
        }

        document.getElementsByTagName('head')[0].appendChild(custom_style);

        /* Add initial CSS to prevent flash of unstyled content */
        var initialStyles = document.createElement('style');
        initialStyles.textContent = `
          .oum-hidden {
            opacity: 0 !important;
            visibility: hidden !important;
            transition: opacity 0.3s ease, visibility 0.3s ease;
          }
          .oum-filter-controls,
          .open-add-location-overlay,
          .map-wrap #oum_filter_markers,
          .oum-advanced-filter-button,
          .oum-advanced-filter-panel,
          .oum-sidebar {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
          }
          .oum-filter-controls.visible,
          .open-add-location-overlay.visible,
          .map-wrap #oum_filter_markers.visible,
          .oum-advanced-filter-button.visible,
          .oum-advanced-filter-panel.visible,
          .oum-sidebar.visible {
            opacity: 1;
            visibility: visible;
          }
        `;
        document.head.appendChild(initialStyles);

      }
    </script>

  </div>

</div>
