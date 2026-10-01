<?php

/**
 * Add Location Form Overlay & Fullscreen Popup Container
 * 
 * Footer containers used by all maps. Self-contained template that
 * fetches data from options and has access to $this (BaseController).
 */
// Show add-location overlay when visitors are allowed or current user is admin
// (same rule as "+ Add Location" button in partial-map-render.php).
// Callers such as block-form.php may set $oum_show_add_location_button beforehand.
if ( !isset( $oum_show_add_location_button ) ) {
    $oum_enable_add_location = get_option( 'oum_enable_add_location', 'on' );
    $oum_show_add_location_button = $oum_enable_add_location === 'on' || current_user_can( 'manage_options' );
}
?>
<div class="open-user-map oum-container-for-fullscreen">
<?php 
if ( $oum_show_add_location_button ) {
    // Fetch required settings for the add-location overlay only
    $map_style = ( get_option( 'oum_map_style' ) ? get_option( 'oum_map_style' ) : 'OpenFreeMap.bright' );
    $oum_ui_color = ( get_option( 'oum_ui_color' ) ? get_option( 'oum_ui_color' ) : $this->oum_ui_color_default );
    $oum_title_label = ( get_option( 'oum_title_label' ) ? get_option( 'oum_title_label' ) : $this->oum_get_default_label( 'title' ) );
    $oum_map_label = ( get_option( 'oum_map_label' ) ? get_option( 'oum_map_label' ) : $this->oum_get_default_label( 'map' ) );
    $oum_address_label = ( get_option( 'oum_address_label' ) ? get_option( 'oum_address_label' ) : $this->oum_get_default_label( 'address' ) );
    $oum_description_label = ( get_option( 'oum_description_label' ) ? get_option( 'oum_description_label' ) : $this->oum_get_default_label( 'description' ) );
    $oum_upload_media_label = ( get_option( 'oum_upload_media_label' ) ? get_option( 'oum_upload_media_label' ) : $this->oum_get_default_label( 'upload_media' ) );
    $oum_marker_types_label = ( get_option( 'oum_marker_types_label' ) ? get_option( 'oum_marker_types_label' ) : $this->oum_get_default_label( 'marker_types' ) );
    $oum_enable_user_notification = get_option( 'oum_enable_user_notification' );
    $text_notify_me_on_publish_label = ( get_option( 'oum_user_notification_label' ) ? get_option( 'oum_user_notification_label' ) : $this->oum_get_default_label( 'user_notification' ) );
    $text_notify_me_on_publish_name = __( 'Your name', 'open-user-map' );
    $text_notify_me_on_publish_email = __( 'Your email', 'open-user-map' );
    $thankyou_headline = get_option( 'oum_thankyou_headline' );
    $thankyou_text = get_option( 'oum_thankyou_text' );
    // Get marker types for selection
    $types = get_terms( array(
        'taxonomy'   => 'oum-type',
        'hide_empty' => false,
    ) );
    if ( is_wp_error( $types ) ) {
        $types = array();
    }
    $types = \OpenUserMapPlugin\Base\BaseController::oum_sort_marker_categories_by_type( $types );
    // Get custom fields configuration
    $oum_custom_fields = get_option( 'oum_custom_fields' );
    $oum_route_icon_path = $this->plugin_path . 'assets/images/ico_route.svg';
    $oum_area_icon_path = $this->plugin_path . 'assets/images/ico_area.svg';
    $oum_route_icon_svg = ( is_readable( $oum_route_icon_path ) ? file_get_contents( $oum_route_icon_path ) : '' );
    $oum_area_icon_svg = ( is_readable( $oum_area_icon_path ) ? file_get_contents( $oum_area_icon_path ) : '' );
    $oum_location_type_shortcode_context = ( isset( $GLOBALS['oum_location_type_shortcode_context'] ) && is_array( $GLOBALS['oum_location_type_shortcode_context'] ) ? $GLOBALS['oum_location_type_shortcode_context'] : $this->oum_location_type_shortcode_context( array() ) );
    $oum_location_type_marker_enabled = $this->oum_location_type_enabled_in_context( 'point', $oum_location_type_shortcode_context );
    $oum_location_type_polyline_enabled = $this->oum_location_type_enabled_in_context( 'polyline', $oum_location_type_shortcode_context );
    $oum_location_type_polygon_enabled = $this->oum_location_type_enabled_in_context( 'polygon', $oum_location_type_shortcode_context );
    $oum_default_location_type = $this->oum_default_location_type_in_context( $oum_location_type_shortcode_context );
    $oum_enabled_location_type_count = count( array_filter( array($oum_location_type_marker_enabled, $oum_location_type_polyline_enabled, $oum_location_type_polygon_enabled) ) );
    ?>
  <div id="add-location-overlay" class="add-location">
    <div class="location-overlay-content">
      <div id="close-add-location-overlay">&#x2715;</div>
      <form id="oum_add_location" enctype="multipart/form-data">
        <h2 class="oum-add-location-headline"><?php 
    echo ( get_option( 'oum_form_headline' ) ? get_option( 'oum_form_headline' ) : __( 'Add a new location', 'open-user-map' ) );
    ?></h2>
        <h2 class="oum-edit-location-headline"><?php 
    echo __( 'Edit Location', 'open-user-map' );
    ?></h2>
        <?php 
    wp_nonce_field( 'oum_location', 'oum_location_nonce' );
    ?>

        <?php 
    if ( get_option( 'oum_enable_title', 'on' ) ) {
        ?>
          <?php 
        $maxlength = ( get_option( 'oum_title_maxlength' ) > 0 ? 'maxlength="' . get_option( 'oum_title_maxlength' ) . '"' : '' );
        ?>
          <label class="oum-label" for="oum_location_title"><?php 
        echo $oum_title_label;
        if ( get_option( 'oum_title_required', 'on' ) ) {
            ?><span class="oum-required-indicator">*</span><?php 
        }
        ?></label>
          <input type="text" id="oum_location_title" name="oum_location_title" <?php 
        if ( get_option( 'oum_title_required', 'on' ) ) {
            ?>required<?php 
        }
        ?> <?php 
        echo $maxlength;
        ?> />
        <?php 
    }
    ?>
        
        <?php 
    if ( $oum_enabled_location_type_count > 1 ) {
        ?>
          <label class="oum-label"><?php 
        echo esc_html__( 'What do you want to add?', 'open-user-map' );
        ?><span class="oum-required-indicator">*</span></label>
          <fieldset class="oum-location-type-control" data-oum-location-type-control>
            <?php 
        if ( $oum_location_type_marker_enabled ) {
            ?>
              <label class="oum-location-type-card" data-oum-location-type-card="point">
                <input type="radio" name="oum_location_type_choice" value="point" <?php 
            checked( $oum_default_location_type, 'point' );
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
                <input type="radio" name="oum_location_type_choice" value="polyline" <?php 
            checked( $oum_default_location_type, 'polyline' );
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
                <input type="radio" name="oum_location_type_choice" value="polygon" <?php 
            checked( $oum_default_location_type, 'polygon' );
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
    } else {
        ?>
          <label class="oum-label"><?php 
        echo $oum_map_label;
        ?><span class="oum-required-indicator">*</span></label>
        <?php 
    }
    ?>
        <div class="map-wrap">
          <div id="mapGetLocation" class="leaflet-map map-style_<?php 
    echo $map_style;
    ?>"<?php 
    echo $this->get_tile_provider_data_attribute( $map_style, 'container' );
    ?>></div>
        </div>
        <input type="hidden" id="oum_location_lat" name="oum_location_lat" required placeholder="<?php 
    echo __( 'Latitude', 'open-user-map' );
    ?>*" />
        <input type="hidden" id="oum_location_lng" name="oum_location_lng" required placeholder="<?php 
    echo __( 'Longitude', 'open-user-map' );
    ?>*" />
        <input type="hidden" id="oum_location_zoom" name="oum_location_zoom" value="" />
        <input type="hidden" id="oum_geometry_type" name="oum_geometry_type" value="<?php 
    echo esc_attr( $oum_default_location_type );
    ?>" />
        <input type="hidden" id="oum_location_geometry" name="oum_location_geometry" value="" />
        <input type="hidden" name="oum_location_type_marker_enabled" value="<?php 
    echo ( $oum_location_type_marker_enabled ? '1' : '0' );
    ?>" />
        <input type="hidden" name="oum_location_type_polyline_enabled" value="<?php 
    echo ( $oum_location_type_polyline_enabled ? '1' : '0' );
    ?>" />
        <input type="hidden" name="oum_location_type_polygon_enabled" value="<?php 
    echo ( $oum_location_type_polygon_enabled ? '1' : '0' );
    ?>" />
        <input type="hidden" name="oum_location_type_context_signature" value="<?php 
    echo esc_attr( $this->oum_location_type_context_signature( $oum_location_type_shortcode_context ) );
    ?>" />

        <input type="hidden" id="oum_post_id" name="oum_post_id" value="">
        <input type="hidden" id="oum_delete_location" name="oum_delete_location" value="">

        <?php 
    ?>

        
        <?php 
    if ( is_array( $oum_custom_fields ) ) {
        ?>
          <div class="oum_custom_fields_wrapper">
          <?php 
        foreach ( $oum_custom_fields as $index => $custom_field ) {
            ?>
            <?php 
            if ( $custom_field['label'] == '' && $custom_field['fieldtype'] != 'html' ) {
                continue;
            }
            $custom_field['fieldtype'] = ( isset( $custom_field['fieldtype'] ) ? $custom_field['fieldtype'] : 'text' );
            $custom_field['description'] = ( isset( $custom_field['description'] ) ? $custom_field['description'] : '' );
            $label = esc_attr( $custom_field['label'] ) . (( isset( $custom_field['required'] ) ? '<span class="oum-required-indicator">*</span>' : '' ));
            $description = ( $custom_field['description'] ? '<div class="oum_custom_field_description">' . wp_kses_post( $custom_field['description'] ) . '</div>' : '' );
            $maxlength = ( $custom_field['maxlength'] ? 'maxlength="' . $custom_field['maxlength'] . '"' : '' );
            $html = ( $custom_field['html'] ? $custom_field['html'] : '' );
            ?>

            <?php 
            if ( $custom_field['fieldtype'] == 'text' ) {
                ?>
              <div data-custom-field-label="<?php 
                echo esc_attr( $custom_field['label'] );
                ?>">
                <label class="oum-label"><?php 
                echo $label;
                ?></label>
                <input type="text" name="oum_location_custom_fields[<?php 
                echo $index;
                ?>]" <?php 
                echo ( isset( $custom_field['required'] ) ? 'required' : '' );
                ?> value="" <?php 
                echo $maxlength;
                ?> />
                <?php 
                echo $description;
                ?>
              </div>
            <?php 
            }
            ?>

            <?php 
            if ( $custom_field['fieldtype'] == 'link' ) {
                ?>
              <div data-custom-field-label="<?php 
                echo esc_attr( $custom_field['label'] );
                ?>">
                <label class="oum-label"><?php 
                echo $label;
                ?></label>
                <input type="url" name="oum_location_custom_fields[<?php 
                echo $index;
                ?>]" <?php 
                echo ( isset( $custom_field['required'] ) ? 'required' : '' );
                ?> value="" <?php 
                echo $maxlength;
                ?> />
                <?php 
                echo $description;
                ?>
              </div>
            <?php 
            }
            ?>

            <?php 
            if ( $custom_field['fieldtype'] == 'email' ) {
                ?>
              <div data-custom-field-label="<?php 
                echo esc_attr( $custom_field['label'] );
                ?>">
                <label class="oum-label"><?php 
                echo $label;
                ?></label>
                <input type="email" name="oum_location_custom_fields[<?php 
                echo $index;
                ?>]" <?php 
                echo ( isset( $custom_field['required'] ) ? 'required' : '' );
                ?> value="" <?php 
                echo $maxlength;
                ?> />
                <?php 
                echo $description;
                ?>
              </div>
            <?php 
            }
            ?>

            <?php 
            if ( $custom_field['fieldtype'] == 'checkbox' ) {
                ?>
              <div data-custom-field-label="<?php 
                echo esc_attr( $custom_field['label'] );
                ?>">
                <fieldset class="oum-checkbox-group <?php 
                echo ( isset( $custom_field['required'] ) ? 'is-required' : '' );
                ?>">
                  <legend><?php 
                echo $label;
                ?></legend>
                  <?php 
                $options = ( isset( $custom_field['options'] ) ? explode( '|', $custom_field['options'] ) : array() );
                foreach ( $options as $option_index => $option ) {
                    ?>
                    <div>
                      <label>
                        <input style="accent-color: <?php 
                    echo $oum_ui_color;
                    ?>" type="checkbox" name="oum_location_custom_fields[<?php 
                    echo $index;
                    ?>][]" value="<?php 
                    echo esc_attr( trim( $option ) );
                    ?>" <?php 
                    echo ( isset( $custom_field['required'] ) && $option_index === 0 ? 'required' : '' );
                    ?>>
                        <span><?php 
                    echo trim( $option );
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
              </div>
            <?php 
            }
            ?>

            <?php 
            if ( $custom_field['fieldtype'] == 'radio' ) {
                ?>
              <div data-custom-field-label="<?php 
                echo esc_attr( $custom_field['label'] );
                ?>">
                <fieldset class="<?php 
                echo ( isset( $custom_field['required'] ) ? 'is-required' : '' );
                ?>">
                  <legend><?php 
                echo $label;
                ?></legend>
                  <?php 
                $options = ( isset( $custom_field['options'] ) ? explode( '|', $custom_field['options'] ) : array() );
                foreach ( $options as $option ) {
                    ?>
                    <div>
                      <label>
                        <input style="accent-color: <?php 
                    echo $oum_ui_color;
                    ?>" type="radio" name="oum_location_custom_fields[<?php 
                    echo $index;
                    ?>]" value="<?php 
                    echo esc_attr( trim( $option ) );
                    ?>" <?php 
                    echo ( isset( $custom_field['required'] ) ? 'required' : '' );
                    ?>>
                        <span><?php 
                    echo trim( $option );
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
              </div>
            <?php 
            }
            ?>

            <?php 
            if ( $custom_field['fieldtype'] == 'select' ) {
                ?>
              <div data-custom-field-label="<?php 
                echo esc_attr( $custom_field['label'] );
                ?>">
                <label class="oum-label"><?php 
                echo $label;
                ?></label>
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
                if ( isset( $custom_field['emptyoption'] ) ) {
                    ?>
                    <option></option>
                  <?php 
                }
                foreach ( $options as $option ) {
                    ?>
                    <option value="<?php 
                    echo esc_attr( trim( $option ) );
                    ?>"><?php 
                    echo trim( $option );
                    ?></option>
                  <?php 
                }
                ?>
                </select>
                <?php 
                echo $description;
                ?>
              </div>
            <?php 
            }
            ?>

            <?php 
            if ( $custom_field['fieldtype'] == 'html' ) {
                ?>
              <div class="oum-custom-field-html">
                <?php 
                echo $html;
                ?>
              </div>
            <?php 
            }
            ?>

            <?php 
            if ( $custom_field['fieldtype'] == 'opening_hours' ) {
                ?>
              <?php 
                $use12hour = isset( $custom_field['use12hour'] ) && $custom_field['use12hour'];
                $placeholder = \OpenUserMapPlugin\Base\LocationController::get_opening_hours_input_example( $use12hour, true );
                $format_hint = \OpenUserMapPlugin\Base\LocationController::get_opening_hours_format_hint( $use12hour );
                // Use format_hint as default description if description is empty
                $final_description = ( empty( $description ) ? '<div class="oum_custom_field_description">' . wp_kses_post( $format_hint ) . '</div>' : $description );
                ?>
              <div data-custom-field-label="<?php 
                echo esc_attr( $custom_field['label'] );
                ?>" data-custom-field-type="opening_hours" data-custom-field-index="<?php 
                echo $index;
                ?>" data-use12hour="<?php 
                echo ( $use12hour ? '1' : '0' );
                ?>" class="oum-opening-hours-field">
                <label class="oum-label"><?php 
                echo $label;
                ?></label>
                <input type="text" 
                  name="oum_location_custom_fields[<?php 
                echo $index;
                ?>][hours]" 
                  class="oum-opening-hours-input" 
                  placeholder="<?php 
                echo esc_attr( $placeholder );
                ?>" 
                  <?php 
                echo ( isset( $custom_field['required'] ) ? 'required' : '' );
                ?> 
                  value="" 
                />
                <?php 
                echo $final_description;
                ?>
              </div>
            <?php 
            }
            ?>

          <?php 
        }
        ?>
          </div>
        <?php 
    }
    ?>
        

        <?php 
    if ( get_option( 'oum_enable_address', 'on' ) === 'on' ) {
        ?>
          <label class="oum-label" for="oum_location_address"><?php 
        echo $oum_address_label;
        ?></label>
          <input type="text" id="oum_location_address" name="oum_location_address" />
        <?php 
    }
    ?>

        <?php 
    if ( get_option( 'oum_enable_description', 'on' ) === 'on' ) {
        ?>
          <label class="oum-label" for="oum_location_text"><?php 
        echo $oum_description_label;
        echo ( get_option( 'oum_description_required' ) ? '<span class="oum-required-indicator">*</span>' : '' );
        ?></label>
          <textarea id="oum_location_text" name="oum_location_text" <?php 
        echo ( get_option( 'oum_description_required' ) ? 'required' : '' );
        ?>></textarea>
        <?php 
    }
    ?>
        
        <?php 
    if ( get_option( 'oum_enable_image', 'on' ) === 'on' || get_option( 'oum_enable_audio', '' ) === 'on' || oum_fs()->is__premium_only() && oum_fs()->can_use_premium_code() && get_option( 'oum_enable_video' ) === 'on' ) {
        ?>
          <label class="oum-label"><?php 
        echo $oum_upload_media_label;
        ?></label>
          <div class="oum_media">

          <?php 
        if ( get_option( 'oum_enable_image', 'on' ) === 'on' ) {
            ?>
            <div class="media-upload oum-image-upload">
              <div class="media-upload-top">
                <label for="oum_location_images" title="<?php 
            echo __( 'Upload Images', 'open-user-map' );
            ?>">
                  <span class="dashicons dashicons-format-image"></span>
                  <?php 
            $max_images = get_option( 'oum_max_image_uploads', 5 );
            if ( $max_images > 1 ) {
                ?>
                    <span class="multi-upload-indicator">+</span>
                  <?php 
            }
            ?>
                </label>
                <?php 
            if ( $max_images > 1 ) {
                ?>
                  <p class="oum-image-upload-description"><?php 
                echo sprintf( __( 'Add up to %d images to create a gallery for this location.', 'open-user-map' ), $max_images );
                ?></p>
                <?php 
            }
            ?>
              </div>
              <input type="file" 
                id="oum_location_images" 
                name="oum_location_images[]" 
                accept="image/*" 
                multiple 
                <?php 
            if ( get_option( 'oum_image_required' ) ) {
                ?>required<?php 
            }
            ?> 
                data-max-files="<?php 
            echo esc_attr( $max_images );
            ?>"
              />
              <input type="hidden" id="oum_remove_existing_image" name="oum_remove_existing_image" value="0" />
            </div>

            <div class="oum-image-preview-grid" id="oum_location_images_preview"></div>
          <?php 
        }
        ?>

          <?php 
        ?>

          <?php 
        if ( get_option( 'oum_enable_audio', '' ) === 'on' ) {
            ?>
            <div class="media-upload oum-audio-upload">
              <label style="color: #e82c71" for="oum_location_audio" title="<?php 
            echo __( 'Upload Audio', 'open-user-map' );
            ?>">
                <span class="dashicons dashicons-format-audio"></span>
              </label>
              <input type="file" 
                id="oum_location_audio" 
                name="oum_location_audio" 
                accept="audio/mp3,audio/mpeg3,audio/wav,audio/mp4,audio/mpeg,audio/x-m4a" 
                multiple="false"
              />
              <div class="preview">
                <div class="audio-preview"></div>
                <div id="oum_remove_audio" class="remove-upload">×</div>
              </div>
              <input type="hidden" id="oum_remove_existing_audio" name="oum_remove_existing_audio" value="1" />
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

        <input type="submit" id="oum_submit_btn" style="background-color: <?php 
    echo $oum_ui_color;
    ?>" value="<?php 
    echo ( get_option( 'oum_submit_button_label' ) ? get_option( 'oum_submit_button_label' ) : __( 'Submit location for review', 'open-user-map' ) );
    ?>" />

        <?php 
    if ( current_user_can( 'delete_oum-locations' ) ) {
        ?>
          <div id="oum_delete_location_btn"><span style="color: <?php 
        echo $oum_ui_color;
        ?>"><?php 
        echo __( 'Delete this location', 'open-user-map' );
        ?></span></div>
        <?php 
    }
    ?>

      </form>

      <div id="oum_add_location_error" style="display: none"></div>

      <div id="oum_add_location_thankyou" style="display: none">
        <h3><?php 
    echo ( $thankyou_headline ? $thankyou_headline : __( 'Thank you!', 'open-user-map' ) );
    ?></h3>
        <p class="oum-add-location-thankyou-text"><?php 
    echo ( $thankyou_text ? $thankyou_text : __( 'We will check your location suggestion and release it as soon as possible.', 'open-user-map' ) );
    ?></p>
      </div>
    </div>
  </div>
<?php 
}
?>
  <div id="location-fullscreen-container"><div class="location-content-wrap"></div><div id="close-location-fullscreen" onClick="oumMap.closePopup()">✕</div></div>
</div>