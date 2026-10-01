<tr class="term-latlng">
    <?php
    // Set map style
    $map_style = get_option('oum_map_style') ? get_option('oum_map_style') : 'OpenFreeMap.bright';
    $oum_tile_provider_mapbox_key = get_option('oum_tile_provider_mapbox_key', '');
    $oum_tile_provider_carto_key = get_option('oum_tile_provider_carto_key', '');
    $t_id = $tag->term_id;
    $term_lat = get_term_meta($t_id, 'oum_lat', true);
    $term_lng = get_term_meta($t_id, 'oum_lng', true);
    $term_zoom = get_term_meta($t_id, 'oum_zoom', true);
    ?>
    <th scope="row">
        <label><?php echo __('Adjust Region view', 'open-user-map'); ?></label>
    </th>
    <td>
        <div class="form-field geo-coordinates-wrap">
            <div class="map-wrap">
                <div id="mapGetRegion" class="leaflet-map map-style_<?php echo esc_attr($map_style); ?><?php echo ($map_style === 'CustomImage' && get_option('oum_custom_image_clickable', '') !== 'on') ? ' oum-custom-image-click-through' : ''; ?>"></div>
            </div>
            <div class="input-wrap">
                <div class="latlng-wrap">
                    <div class="form-field lat-wrap">
                        <label class="meta-label" for="oum_lat">
                            <?php echo __('Lat', 'open-user-map'); ?>
                        </label>
                        <input type="text" readonly class="widefat" id="oum_lat" name="oum_lat" value="<?php echo esc_attr($term_lat) ? esc_attr($term_lat) : ''; ?>"></input>
                    </div>
                    <div class="form-field lng-wrap">
                        <label class="meta-label" for="oum_lng">
                            <?php echo __('Lng', 'open-user-map'); ?>
                        </label>
                        <input type="text" readonly class="widefat" id="oum_lng" name="oum_lng" value="<?php echo esc_attr($term_lng) ? esc_attr($term_lng) : ''; ?>"></input>
                    </div>
                    <div class="form-field zoom-wrap">
                        <label class="meta-label" for="oum_zoom">
                            <?php echo __('Zoom', 'open-user-map'); ?>
                        </label>
                        <input type="text" readonly class="widefat" id="oum_zoom" name="oum_zoom" value="<?php echo esc_attr($term_zoom) ? esc_attr($term_zoom) : ''; ?>"></input>
                    </div>
                </div>

                <div class="geo-coordinates-hint">
                    <strong><?php echo __('How to adjust the Region view:', 'open-user-map'); ?></strong>
                    <ol>
                    <li><?php echo __('Use the map to find your area of interest', 'open-user-map'); ?></li>
                    <li><?php echo __('Zoom and pan the map to set the perfect initial view', 'open-user-map'); ?><br><br><strong><?php echo __('Tip:', 'open-user-map'); ?></strong> <?php echo __('Hold down the Shift key + mouse to zoom in on an area.', 'open-user-map'); ?></li>
                </ol>
                </div>
            </div>

            <script type="text/javascript" data-category="functional" class="cmplz-native" id="oum-inline-js">
            const lat = '<?php echo esc_attr($term_lat) ? esc_attr($term_lat) : '0'; ?>';
            const lng = '<?php echo esc_attr($term_lng) ? esc_attr($term_lng) : '0'; ?>';
            const zoom = '<?php echo esc_attr($term_zoom) ? esc_attr($term_zoom) : '1'; ?>';
            const mapStyle = '<?php echo $map_style; ?>';
            window.oum_tile_provider_mapbox_key = <?php echo wp_json_encode((string) $oum_tile_provider_mapbox_key); ?>;
            window.oum_tile_provider_carto_key = <?php echo wp_json_encode((string) $oum_tile_provider_carto_key); ?>;
            let oum_geosearch_selected_provider = "";
            const oum_geosearch_provider = <?php echo wp_json_encode((string) (get_option('oum_geosearch_provider') ? get_option('oum_geosearch_provider') : 'osm')); ?>;
            const oum_geosearch_provider_geoapify_key = <?php echo wp_json_encode((string) get_option('oum_geosearch_provider_geoapify_key', '')); ?>;
            const oum_geosearch_provider_here_key = <?php echo wp_json_encode((string) get_option('oum_geosearch_provider_here_key', '')); ?>;
            const oum_geosearch_provider_mapbox_key = <?php echo wp_json_encode((string) get_option('oum_geosearch_provider_mapbox_key', '')); ?>;
            const oum_searchaddress_label = <?php echo wp_json_encode((string) (get_option('oum_searchaddress_label') ? get_option('oum_searchaddress_label') : $this->oum_get_default_label('searchaddress'))); ?>;

            // Custom Image data
            window.oum_custom_image_url = <?php echo wp_json_encode((string) get_option('oum_custom_image_url', '')); ?>;
            window.oum_custom_image_bounds = <?php
              $bounds = get_option('oum_custom_image_bounds', '');
              if (empty($bounds)) {
                echo '{}';
              } else {
                $bounds_array = maybe_unserialize($bounds);
                if (is_array($bounds_array)) {
                  echo json_encode($bounds_array);
                } else {
                  echo '{}';
                }
              }
            ?>;
            window.oum_custom_image_hide_tiles = <?php echo get_option('oum_custom_image_hide_tiles', '') === 'on' ? 'true' : 'false'; ?>;
            window.oum_custom_image_clickable = <?php echo get_option('oum_custom_image_clickable', '') === 'on' ? 'true' : 'false'; ?>;
            window.oum_custom_image_background_color = <?php echo wp_json_encode((string) get_option('oum_custom_image_background_color', '#ffffff')); ?>;
            </script>

            <?php 
            // load map base scripts
            $this->include_map_scripts( $map_style );

            wp_enqueue_script('oum_backend_region_js', $this->plugin_url . 'src/js/backend-region.js', array('oum_leaflet_providers_js', 'oum_leaflet_markercluster_js', 'oum_leaflet_subgroups_js', 'oum_leaflet_geosearch_js', 'oum_leaflet_locate_js', 'oum_leaflet_fullscreen_js', 'oum_leaflet_search_js', 'oum_leaflet_gesture_js', 'oum_global_leaflet_js', 'oum_map_styles_js'), $this->plugin_version);
            ?>
            
        </div>
    </td>
</tr>