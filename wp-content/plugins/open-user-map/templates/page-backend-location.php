<?php

$oum_route_icon_path = $this->plugin_path . 'assets/images/ico_route.svg';
$oum_area_icon_path = $this->plugin_path . 'assets/images/ico_area.svg';
$oum_route_icon_svg = ( is_readable( $oum_route_icon_path ) ? file_get_contents( $oum_route_icon_path ) : '' );
$oum_area_icon_svg = ( is_readable( $oum_area_icon_path ) ? file_get_contents( $oum_area_icon_path ) : '' );
$oum_location_type_marker_enabled = $this->oum_location_type_markers_enabled();
$oum_location_type_polyline_enabled = $this->oum_location_type_routes_enabled();
$oum_location_type_polygon_enabled = $this->oum_location_type_areas_enabled();
$oum_current_geometry_type_enabled = $this->oum_location_type_enabled( $geometry_type );
$oum_backend_geometry_type = ( $oum_current_geometry_type_enabled ? $geometry_type : $this->oum_default_location_type() );
$oum_enabled_location_type_count = count( array_filter( array($oum_location_type_marker_enabled, $oum_location_type_polyline_enabled, $oum_location_type_polygon_enabled) ) );
?>
<table class="form-table">
    <tbody>

        <tr valign="top">
            <th scope="row">
                <?php 
echo __( 'Marker', 'open-user-map' );
?>
            </th>
            <td>
                <div class="geo-coordinates-wrap">
                    <div class="map-wrap">
                        <div id="mapGetLocation" class="leaflet-map map-style_<?php 
echo esc_attr( $map_style );
echo ( $map_style === 'CustomImage' && get_option( 'oum_custom_image_clickable', '' ) !== 'on' ? ' oum-custom-image-click-through' : '' );
?>"></div>
                    </div>
                    <div class="input-wrap">
                        <div class="geo-coordinates-hint">
                            <div class="hint"><?php 
echo __( 'Use the map to edit this location or <a href="#" id="showLatLngInputs">edit location data manually</a>.', 'open-user-map' );
?></div>

                            <div class="latlng-wrap" id="latLngInputs" style="display: none;">
                                <div class="hint"><?php 
echo __( 'Edit location data manually:', 'open-user-map' );
?></div>
                                <div class="oum-latlng-fields">
                                    <div>
                                        <label class="meta-label" for="oum_location_lat">
                                            <?php 
echo __( 'Lat', 'open-user-map' );
?>
                                        </label>
                                        <input type="text" class="widefat" id="oum_location_lat" name="oum_location_lat" value="<?php 
echo esc_attr( $lat );
?>"></input>
                                    </div>
                                    <div>
                                        <label class="meta-label" for="oum_location_lng">
                                            <?php 
echo __( 'Lng', 'open-user-map' );
?>
                                        </label>
                                        <input type="text" class="widefat" id="oum_location_lng" name="oum_location_lng" value="<?php 
echo esc_attr( $lng );
?>"></input>
                                    </div>
                                    <div>
                                        <label class="meta-label" for="oum_location_zoom">
                                            <?php 
echo __( 'Zoom Level', 'open-user-map' );
?>
                                        </label>
                                        <input type="number" class="widefat" id="oum_location_zoom" name="oum_location_zoom" min="0" max="20" step="any" value="<?php 
echo esc_attr( $zoom );
?>"></input>
                                    </div>
                                </div>
                                <div class="oum-geometry-fields">
                                    <label class="meta-label" for="oum_location_geometry"><?php 
echo esc_html__( 'Geometry data (advanced)', 'open-user-map' );
?></label>
                                    <textarea class="widefat" id="oum_location_geometry" name="oum_location_geometry" rows="8" style="font-family:monospace;"><?php 
echo esc_textarea( $geometry );
?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="input-wrap">
                        <?php 
if ( $oum_enabled_location_type_count > 1 ) {
    ?>
                            <label class="meta-label"><?php 
    echo esc_html__( 'Location Shape', 'open-user-map' );
    ?></label>
                            <fieldset class="oum-location-type-control" data-oum-location-type-control>
                                <?php 
    if ( $oum_location_type_marker_enabled ) {
        ?>
                                    <label class="oum-location-type-card" data-oum-location-type-card="point">
                                        <input type="radio" name="oum_backend_geometry_type_choice" value="point" <?php 
        checked( $oum_backend_geometry_type, 'point' );
        ?>>
                                        <span class="oum-location-type-icon oum-location-type-icon-point" aria-hidden="true"></span>
                                        <span class="oum-location-type-title"><?php 
        echo esc_html__( 'Marker', 'open-user-map' );
        ?></span>
                                        <span class="oum-location-type-description"><?php 
        echo esc_html__( 'Single point', 'open-user-map' );
        ?></span>
                                    </label>
                                <?php 
    }
    ?>
                                <?php 
    if ( $oum_location_type_polyline_enabled ) {
        ?>
                                    <label class="oum-location-type-card" data-oum-location-type-card="polyline">
                                        <input type="radio" name="oum_backend_geometry_type_choice" value="polyline" <?php 
        checked( $oum_backend_geometry_type, 'polyline' );
        ?>>
                                        <span class="oum-location-type-icon oum-location-type-icon-polyline" aria-hidden="true"><?php 
        echo $oum_route_icon_svg;
        ?></span>
                                        <span class="oum-location-type-title"><?php 
        echo esc_html__( 'Line', 'open-user-map' );
        ?></span>
                                        <span class="oum-location-type-description"><?php 
        echo esc_html__( 'Connected path made from multiple points', 'open-user-map' );
        ?></span>
                                    </label>
                                <?php 
    }
    ?>
                                <?php 
    if ( $oum_location_type_polygon_enabled ) {
        ?>
                                    <label class="oum-location-type-card" data-oum-location-type-card="polygon">
                                        <input type="radio" name="oum_backend_geometry_type_choice" value="polygon" <?php 
        checked( $oum_backend_geometry_type, 'polygon' );
        ?>>
                                        <span class="oum-location-type-icon oum-location-type-icon-polygon" aria-hidden="true"><?php 
        echo $oum_area_icon_svg;
        ?></span>
                                        <span class="oum-location-type-title"><?php 
        echo esc_html__( 'Area', 'open-user-map' );
        ?></span>
                                        <span class="oum-location-type-description"><?php 
        echo esc_html__( 'Closed boundary with transparent fill', 'open-user-map' );
        ?></span>
                                    </label>
                                <?php 
    }
    ?>
                            </fieldset>
                        <?php 
}
?>
                        <input type="hidden" id="oum_geometry_type" name="oum_geometry_type" value="<?php 
echo esc_attr( $oum_backend_geometry_type );
?>">
                        <p
                            class="description"
                            data-oum-backend-location-type-help
                            data-point-help="<?php 
echo esc_attr__( 'Click on the map to set a single marker.', 'open-user-map' );
?>"
                            data-vector-help="<?php 
echo esc_attr__( 'Use the map controls to draw or edit this location geometry.', 'open-user-map' );
?>"
                        ></p>
                    </div>

                    <script type="text/javascript" data-category="functional" class="cmplz-native" id="oum-inline-js">
                    const lat = '<?php 
echo esc_attr( $lat );
?>';
                    const lng = '<?php 
echo esc_attr( $lng );
?>';
                    const zoom = '<?php 
echo esc_attr( $zoom );
?>';
                    const mapStyle = '<?php 
echo esc_attr( $map_style );
?>';
                    window.oum_tile_provider_mapbox_key = <?php 
echo wp_json_encode( (string) $oum_tile_provider_mapbox_key );
?>;
                    window.oum_tile_provider_carto_key = <?php 
echo wp_json_encode( (string) $oum_tile_provider_carto_key );
?>;
                    const oum_enable_currentlocation = '<?php 
echo ( get_option( 'oum_enable_currentlocation' ) ? true : false );
?>';
                    const enableCurrentLocation = oum_enable_currentlocation ? true : false;
                    let oum_geosearch_selected_provider = "";
                    const oum_geosearch_provider = <?php 
echo wp_json_encode( (string) (( get_option( 'oum_geosearch_provider' ) ? get_option( 'oum_geosearch_provider' ) : 'osm' )) );
?>;
                    const oum_geosearch_provider_geoapify_key = <?php 
echo wp_json_encode( (string) get_option( 'oum_geosearch_provider_geoapify_key', '' ) );
?>;
                    const oum_geosearch_provider_here_key = <?php 
echo wp_json_encode( (string) get_option( 'oum_geosearch_provider_here_key', '' ) );
?>;
                    const oum_geosearch_provider_mapbox_key = <?php 
echo wp_json_encode( (string) get_option( 'oum_geosearch_provider_mapbox_key', '' ) );
?>;
                    const oum_searchaddress_label = <?php 
echo wp_json_encode( (string) (( get_option( 'oum_searchaddress_label' ) ? get_option( 'oum_searchaddress_label' ) : $this->oum_get_default_label( 'searchaddress' ) )) );
?>;
                    const oum_enable_address = <?php 
echo wp_json_encode( (string) get_option( 'oum_enable_address', 'on' ) );
?>;
                    const oum_enable_address_autofill = <?php 
echo wp_json_encode( (string) get_option( 'oum_enable_address_autofill' ) );
?>;

                    <?php 
if ( $marker_icon == 'user1' && $marker_user_icon ) {
    ?>
                        const marker_icon_url = <?php 
    echo wp_json_encode( (string) esc_url( $marker_user_icon ) );
    ?>;
                    <?php 
} else {
    ?>
                        const marker_icon_url = <?php 
    echo wp_json_encode( (string) (esc_url( $this->plugin_url ) . 'src/leaflet/images/marker-icon_' . esc_attr( $marker_icon ) . '-2x.png') );
    ?>;
                    <?php 
}
?>

                    const marker_shadow_url = '<?php 
echo esc_url( $this->plugin_url );
?>src/leaflet/images/marker-shadow.png';

                    // Custom Image data
                    window.oum_custom_image_url = <?php 
echo wp_json_encode( (string) get_option( 'oum_custom_image_url', '' ) );
?>;
                    window.oum_custom_image_bounds = <?php 
$bounds = get_option( 'oum_custom_image_bounds', '' );
if ( empty( $bounds ) ) {
    echo '{}';
} else {
    $bounds_array = maybe_unserialize( $bounds );
    if ( is_array( $bounds_array ) ) {
        echo json_encode( $bounds_array );
    } else {
        echo '{}';
    }
}
?>;
                    window.oum_custom_image_hide_tiles = <?php 
echo ( get_option( 'oum_custom_image_hide_tiles', '' ) === 'on' ? 'true' : 'false' );
?>;
                    window.oum_custom_image_clickable = <?php 
echo ( get_option( 'oum_custom_image_clickable', '' ) === 'on' ? 'true' : 'false' );
?>;
                    window.oum_custom_image_background_color = <?php 
echo wp_json_encode( (string) get_option( 'oum_custom_image_background_color', '#ffffff' ) );
?>;
                    </script>

                    <?php 
// load map base scripts
$this->include_map_scripts( $map_style );
wp_enqueue_style( 'oum_leaflet_draw_css' );
wp_enqueue_script(
    'oum_backend_location_js',
    esc_url( $this->plugin_url ) . 'src/js/backend-location.js',
    array(
        'oum_leaflet_providers_js',
        'oum_leaflet_markercluster_js',
        'oum_leaflet_subgroups_js',
        'oum_leaflet_geosearch_js',
        'oum_leaflet_locate_js',
        'oum_leaflet_fullscreen_js',
        'oum_leaflet_search_js',
        'oum_leaflet_gesture_js',
        'oum_global_leaflet_js',
        'oum_map_styles_js'
    ),
    esc_attr( $this->plugin_version )
);
?>
                </div>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">
                <?php 
echo __( 'Subtitle', 'open-user-map' );
?>
            </th>
            <td>
                <input type="text" class="regular-text" id="oum_location_address" name="oum_location_address" value="<?php 
echo esc_attr( $address );
?>"></input>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">
                <?php 
echo __( 'Image', 'open-user-map' );
?>
            </th>
            <td>
                <a href="#" class="oum_upload_image_button button button-secondary"><?php 
echo __( 'Upload Image', 'open-user-map' );
?></a>
                <input type="hidden" id="oum_location_image" name="oum_location_image" value="<?php 
echo esc_attr( $image );
?>"></input>
                <br><br>
                <div id="oum_location_image_preview"></div>
                <p class="description"><?php 
echo __( 'Maximum 5 images. Images will be shown in a gallery.', 'open-user-map' );
?></p>
            </td>
        </tr>

        <?php 
?>

        <tr valign="top">
            <th scope="row">
                <?php 
echo __( 'Audio', 'open-user-map' );
?>
            </th>
            <td>
                <a href="#" class="oum_upload_audio_button button button-secondary"><?php 
echo __( 'Upload Audio', 'open-user-map' );
?></a>
                <input type="hidden" id="oum_location_audio" name="oum_location_audio" value="<?php 
echo esc_attr( $audio );
?>"></input>
                <br><br>
                <div id="oum_location_audio_preview" class="<?php 
echo $has_audio;
?>">
                    <?php 
echo $audio_tag;
?>
                    <div onclick="oumRemoveAudioUpload()" class="remove-upload">&times;</div>
                </div>
            </td>
        </tr>

        <tr valign="top">
            <th scope="row">
                <?php 
echo __( 'Description', 'open-user-map' );
?>
            </th>
            <td>
                <?php 
wp_editor( $text, 'oum_location_text', array(
    'tinymce'       => false,
    'quicktags'     => true,
    'media_buttons' => false,
) );
?>
            </td>
        </tr>

        <?php 
?>

        <?php 
if ( is_array( $active_custom_fields ) ) {
    ?>
            <?php 
    foreach ( $active_custom_fields as $index => $custom_field ) {
        ?>

                <?php 
        $custom_field['fieldtype'] = ( isset( $custom_field['fieldtype'] ) ? $custom_field['fieldtype'] : 'text' );
        $custom_field['description'] = ( isset( $custom_field['description'] ) ? $custom_field['description'] : '' );
        $label = esc_attr( $custom_field['label'] ) . (( isset( $custom_field['required'] ) ? '*' : '' ));
        $description = ( $custom_field['description'] ? '<div class="oum_custom_field_description">' . wp_kses_post( $custom_field['description'] ) . '</div>' : '' );
        ?>
                
                <?php 
        if ( $custom_field['fieldtype'] == 'text' ) {
            ?>

                    <tr valign="top" class="section-id_cf-<?php 
            echo $index;
            ?>">
                        <th scope="row">
                            <?php 
            echo $label;
            ?>
                        </th>
                        <td>
                            <input type="text" class="regular-text" name="oum_location_custom_fields[<?php 
            echo $index;
            ?>]" value="<?php 
            echo ( isset( $meta_custom_fields[$index] ) ? esc_attr( $meta_custom_fields[$index] ) : '' );
            ?>"></input>
                            <?php 
            echo $description;
            ?>
                        </td>
                    </tr>

                <?php 
        }
        ?>


                <?php 
        if ( $custom_field['fieldtype'] == 'link' ) {
            ?>

                    <tr valign="top" class="section-id_cf-<?php 
            echo $index;
            ?>">
                        <th scope="row">
                            <?php 
            echo $label;
            ?>
                        </th>
                        <td>
                            <input type="text" class="regular-text" name="oum_location_custom_fields[<?php 
            echo $index;
            ?>]" value="<?php 
            echo ( isset( $meta_custom_fields[$index] ) ? esc_attr( $meta_custom_fields[$index] ) : '' );
            ?>"></input>
                            <?php 
            echo $description;
            ?>
                        </td>
                    </tr>

                <?php 
        }
        ?>


                <?php 
        if ( $custom_field['fieldtype'] == 'email' ) {
            ?>

                    <tr valign="top" class="section-id_cf-<?php 
            echo $index;
            ?>">
                        <th scope="row">
                            <?php 
            echo $label;
            ?>
                        </th>
                        <td>
                            <input type="email" class="regular-text" name="oum_location_custom_fields[<?php 
            echo $index;
            ?>]" value="<?php 
            echo ( isset( $meta_custom_fields[$index] ) ? esc_attr( $meta_custom_fields[$index] ) : '' );
            ?>"></input>
                            <?php 
            echo $description;
            ?>
                        </td>
                    </tr>

                <?php 
        }
        ?>


                <?php 
        if ( $custom_field['fieldtype'] == 'checkbox' ) {
            ?>

                    <tr valign="top" class="section-id_cf-<?php 
            echo $index;
            ?>">
                        <th scope="row">
                            <?php 
            echo $label;
            ?>
                        </th>
                        <td>
                            <fieldset>
                                <?php 
            $options = ( isset( $custom_field['options'] ) ? explode( '|', $custom_field['options'] ) : array() );
            ?>
                                <?php 
            foreach ( $options as $option ) {
                ?>
                                    <div>
                                        <label>
                                            <input type="checkbox" name="oum_location_custom_fields[<?php 
                echo $index;
                ?>][]" value="<?php 
                echo esc_attr( $option );
                ?>" <?php 
                echo ( isset( $meta_custom_fields[$index] ) && is_array( $meta_custom_fields[$index] ) && in_array( esc_attr( $option ), $meta_custom_fields[$index] ) ? 'checked' : '' );
                ?>>
                                            <span><?php 
                echo $option;
                ?></span>
                                        </label>
                                    </div>
                                <?php 
            }
            ?>
                            </fieldset>
                            <?php 
            echo $description;
            ?>
                        </td>
                    </tr>

                <?php 
        }
        ?>


                <?php 
        if ( $custom_field['fieldtype'] == 'radio' ) {
            ?>

                    <tr valign="top" class="section-id_cf-<?php 
            echo $index;
            ?>">
                        <th scope="row">
                            <?php 
            echo $label;
            ?>
                        </th>
                        <td>
                            <fieldset>
                                <?php 
            $options = ( isset( $custom_field['options'] ) ? explode( '|', $custom_field['options'] ) : array() );
            ?>
                                <?php 
            foreach ( $options as $option ) {
                ?>
                                    <div>
                                        <label>
                                            <input type="radio" name="oum_location_custom_fields[<?php 
                echo $index;
                ?>]" value="<?php 
                echo esc_attr( $option );
                ?>" <?php 
                echo ( isset( $meta_custom_fields[$index] ) && esc_attr( $option ) == $meta_custom_fields[$index] ? 'checked' : '' );
                ?>>
                                            <span><?php 
                echo $option;
                ?></span>
                                        </label>
                                    </div>
                                <?php 
            }
            ?>
                            </fieldset>
                            <?php 
            echo $description;
            ?>
                        </td>
                    </tr>

                <?php 
        }
        ?>


                <?php 
        if ( $custom_field['fieldtype'] == 'select' ) {
            ?>

                    <tr valign="top" class="section-id_cf-<?php 
            echo $index;
            ?>">
                        <th scope="row">
                            <?php 
            echo $label;
            ?>
                        </th>
                        <td>
                            <select name="oum_location_custom_fields[<?php 
            echo $index;
            ?>]<?php 
            echo ( isset( $custom_field['multiple'] ) ? '[]' : '' );
            ?>" <?php 
            echo ( isset( $custom_field['required'] ) ? 'required' : '' );
            ?> <?php 
            echo ( isset( $custom_field['multiple'] ) ? 'multiple' : '' );
            ?>>
                                <?php 
            $options = ( isset( $custom_field['options'] ) ? explode( '|', $custom_field['options'] ) : array() );
            ?>
                                <?php 
            foreach ( $options as $option ) {
                ?>
                                    <?php 
                $current_val = ( isset( $meta_custom_fields[$index] ) ? $meta_custom_fields[$index] : '' );
                $is_selected = ( is_array( $current_val ) ? in_array( $option, $current_val ) : esc_attr( $option ) == $current_val );
                ?>
                                    <option value="<?php 
                echo esc_attr( $option );
                ?>" <?php 
                echo ( $is_selected ? 'selected' : '' );
                ?>><?php 
                echo $option;
                ?></option>
                                <?php 
            }
            ?>
                            </select>
                            <?php 
            echo $description;
            ?>
                        </td>
                    </tr>

                <?php 
        }
        ?>


                <?php 
        if ( $custom_field['fieldtype'] == 'opening_hours' ) {
            ?>

                    <tr valign="top" class="section-id_cf-<?php 
            echo $index;
            ?>">
                        <th scope="row">
                            <?php 
            echo $label;
            ?>
                        </th>
                        <td>
                            <?php 
            // Get stored value
            $stored_value = ( isset( $meta_custom_fields[$index] ) ? $meta_custom_fields[$index] : '' );
            $use12hour = isset( $custom_field['use12hour'] ) && $custom_field['use12hour'];
            // Convert JSON to input format using localized weekday abbreviations
            $hours_input = \OpenUserMapPlugin\Base\LocationController::convert_opening_hours_json_to_input( $stored_value, $use12hour, true );
            $placeholder = \OpenUserMapPlugin\Base\LocationController::get_opening_hours_input_example( $use12hour, true );
            $format_hint = \OpenUserMapPlugin\Base\LocationController::get_opening_hours_format_hint( $use12hour );
            ?>
                            <input type="text" 
                                class="regular-text oum-opening-hours-input" 
                                name="oum_location_custom_fields[<?php 
            echo $index;
            ?>][hours]" 
                                placeholder="<?php 
            echo esc_attr( $placeholder );
            ?>" 
                                value="<?php 
            echo esc_attr( $hours_input );
            ?>"
                            />
                            <small style="display: block; margin-top: 5px; color: #666;">
                                <?php 
            echo $format_hint;
            ?>
                            </small>
                            <?php 
            echo $description;
            ?>
                        </td>
                    </tr>

                <?php 
        }
        ?>

            <?php 
    }
    ?>
        <?php 
}
?>

        <?php 
?>

    </tbody>
</table>