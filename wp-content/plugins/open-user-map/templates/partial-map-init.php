<?php

if ( !function_exists( 'oum_sanitize_map_style_value' ) ) {
    function oum_sanitize_map_style_value(  $value, $fallback = 'OpenFreeMap.bright'  ) {
        $value = sanitize_text_field( $value );
        // Map style keys are provider identifiers like Esri.WorldStreetMap or CustomImage.
        return ( preg_match( '/^[A-Za-z0-9._-]+$/', $value ) ? $value : $fallback );
    }

}
if ( !function_exists( 'oum_sanitize_map_height_value' ) ) {
    function oum_sanitize_map_height_value(  $value  ) {
        $value = trim( sanitize_text_field( $value ) );
        if ( $value === '' ) {
            return '';
        }
        if ( is_numeric( $value ) ) {
            return $value . 'px';
        }
        // Keep CSS sizing intentionally narrow for shortcode input used inside inline CSS.
        return ( preg_match( '/^\\d+(?:\\.\\d+)?(?:px|%|vh|vw|em|rem)$/i', $value ) ? strtolower( $value ) : '' );
    }

}
// Settings
$oum_enable_scrollwheel_zoom_map = ( get_option( 'oum_enable_scrollwheel_zoom_map' ) === 'on' ? 'true' : 'false' );
$oum_enable_regions = get_option( 'oum_enable_regions' );
$oum_regions_layout_style = get_option( 'oum_regions_layout_style', 'layout-1' );
$oum_enable_cluster = ( get_option( 'oum_enable_cluster', 'on' ) === 'on' ? 'true' : 'false' );
$oum_enable_fullscreen = ( get_option( 'oum_enable_fullscreen', 'on' ) === 'on' ? 'true' : 'false' );
$oum_hide_location_popup = ( get_option( 'oum_hide_location_popup', '' ) === 'on' ? 'true' : 'false' );
$oum_enable_gmaps_link = get_option( 'oum_enable_gmaps_link', 'on' );
$oum_enable_address_autofill = get_option( 'oum_enable_address_autofill' );
$oum_max_image_filesize = ( !empty( get_option( 'oum_max_image_filesize' ) ) ? get_option( 'oum_max_image_filesize' ) : 10 );
$map_style = ( get_option( 'oum_map_style' ) ? get_option( 'oum_map_style' ) : 'OpenFreeMap.bright' );
$oum_tile_provider_mapbox_key = get_option( 'oum_tile_provider_mapbox_key', '' );
$oum_tile_provider_carto_key = get_option( 'oum_tile_provider_carto_key', '' );
$marker_icon = ( get_option( 'oum_marker_icon' ) ? get_option( 'oum_marker_icon' ) : 'default' );
$marker_user_icon = get_option( 'oum_marker_user_icon' );
$map_size = get_option( 'oum_map_size' );
$oum_map_height = get_option( 'oum_map_height' );
$oum_map_height_mobile = get_option( 'oum_map_height_mobile' );
$oum_action_after_submit = get_option( 'oum_action_after_submit' );
$thankyou_headline = get_option( 'oum_thankyou_headline' );
$thankyou_text = get_option( 'oum_thankyou_text' );
$thankyou_redirect = get_option( 'oum_thankyou_redirect' );
$oum_enable_add_location = get_option( 'oum_enable_add_location', 'on' );
$oum_enable_user_notification = get_option( 'oum_enable_user_notification' );
$text_notify_me_on_publish_label = ( get_option( 'oum_user_notification_label' ) ? get_option( 'oum_user_notification_label' ) : $this->oum_get_default_label( 'user_notification' ) );
$text_notify_me_on_publish_name = __( 'Your name', 'open-user-map' );
$text_notify_me_on_publish_email = __( 'Your email', 'open-user-map' );
$oum_enable_currentlocation = ( get_option( 'oum_enable_currentlocation' ) ? 'true' : 'false' );
$oum_disable_oum_attribution = get_option( 'oum_disable_oum_attribution' );
$oum_collapse_filter = ( get_option( 'oum_collapse_filter' ) ? 'use-collapse' : 'active' );
$oum_enable_toggle_all_categories = get_option( 'oum_enable_toggle_all_categories', 'off' );
$oum_ui_color = ( get_option( 'oum_ui_color' ) ? get_option( 'oum_ui_color' ) : $this->oum_ui_color_default );
$oum_plus_button_label = ( get_option( 'oum_plus_button_label' ) ? get_option( 'oum_plus_button_label' ) : __( 'Add location', 'open-user-map' ) );
$oum_marker_types_label = ( get_option( 'oum_marker_types_label' ) ? get_option( 'oum_marker_types_label' ) : $this->oum_get_default_label( 'marker_types' ) );
$oum_title_label = ( get_option( 'oum_title_label' ) ? get_option( 'oum_title_label' ) : $this->oum_get_default_label( 'title' ) );
$oum_map_label = ( get_option( 'oum_map_label' ) ? get_option( 'oum_map_label' ) : $this->oum_get_default_label( 'map' ) );
$oum_address_label = ( get_option( 'oum_address_label' ) ? get_option( 'oum_address_label' ) : $this->oum_get_default_label( 'address' ) );
$oum_enable_address = get_option( 'oum_enable_address', 'on' );
$oum_description_label = ( get_option( 'oum_description_label' ) ? get_option( 'oum_description_label' ) : $this->oum_get_default_label( 'description' ) );
$oum_upload_media_label = ( get_option( 'oum_upload_media_label' ) ? get_option( 'oum_upload_media_label' ) : $this->oum_get_default_label( 'upload_media' ) );
$oum_enable_fixed_map_bounds = get_option( 'oum_enable_fixed_map_bounds' );
$oum_enable_multiple_marker_types = ( get_option( 'oum_enable_multiple_marker_types', false ) ? 'true' : 'false' );
$oum_enable_searchbar = ( get_option( 'oum_enable_searchbar', '' ) === 'on' ? 'true' : 'false' );
$oum_searchbar_type = ( get_option( 'oum_searchbar_type' ) ? get_option( 'oum_searchbar_type' ) : 'address' );
$oum_enable_searchmarkers_button = ( get_option( 'oum_enable_searchmarkers_button', 'on' ) === 'on' ? 'true' : 'false' );
$oum_searchmarkers_label = ( get_option( 'oum_searchmarkers_label' ) ? get_option( 'oum_searchmarkers_label' ) : $this->oum_get_default_label( 'searchmarkers' ) );
$oum_searchmarkers_zoom = ( get_option( 'oum_searchmarkers_zoom' ) ? get_option( 'oum_searchmarkers_zoom' ) : $this->oum_searchmarkers_zoom_default );
$oum_geosearch_provider = ( get_option( 'oum_geosearch_provider' ) ? get_option( 'oum_geosearch_provider' ) : 'osm' );
$oum_geosearch_provider_geoapify_key = get_option( 'oum_geosearch_provider_geoapify_key', '' );
$oum_geosearch_provider_here_key = get_option( 'oum_geosearch_provider_here_key', '' );
$oum_geosearch_provider_mapbox_key = get_option( 'oum_geosearch_provider_mapbox_key', '' );
$oum_enable_searchaddress_button = ( get_option( 'oum_enable_searchaddress_button', 'on' ) === 'on' ? 'true' : 'false' );
$oum_searchaddress_label = ( get_option( 'oum_searchaddress_label' ) ? get_option( 'oum_searchaddress_label' ) : $this->oum_get_default_label( 'searchaddress' ) );
$oum_custom_js = get_option( 'oum_custom_js' );
$oum_location_date_type = get_option( 'oum_location_date_type', 'modified' );
// Advanced Filter Interface setting
$oum_enable_advanced_filter = get_option( 'oum_enable_advanced_filter' );
$oum_advanced_filter_layout = get_option( 'oum_advanced_filter_layout', 'left' );
$oum_advanced_filter_sections = get_option( 'oum_advanced_filter_sections', array() );
if ( !is_array( $oum_advanced_filter_sections ) ) {
    $oum_advanced_filter_sections = array();
}
// True when a Marker Categories or Searchbar section is configured in Advanced Filter.
$oum_has_marker_categories_filter_section = false;
$oum_has_searchbar_filter_section = false;
foreach ( $oum_advanced_filter_sections as $oum_af_section ) {
    if ( !isset( $oum_af_section['type'] ) ) {
        continue;
    }
    if ( $oum_af_section['type'] === 'marker_categories' ) {
        $oum_has_marker_categories_filter_section = true;
    }
    if ( $oum_af_section['type'] === 'searchbar' ) {
        $oum_has_searchbar_filter_section = true;
    }
}
// Filled after types / shortcodes are resolved; used to hide the on-map originals.
$show_marker_categories_in_advanced_filter = false;
$show_searchbar_in_advanced_filter = false;
// Custom Image settings
$oum_custom_image_url = get_option( 'oum_custom_image_url', '' );
$oum_custom_image_bounds_raw = get_option( 'oum_custom_image_bounds', '' );
$oum_custom_image_hide_tiles = get_option( 'oum_custom_image_hide_tiles', '' );
$oum_custom_image_clickable = get_option( 'oum_custom_image_clickable', '' );
$oum_custom_image_background_color = get_option( 'oum_custom_image_background_color', '#ffffff' );
// Process bounds data for JavaScript
$oum_custom_image_bounds = '{}';
if ( !empty( $oum_custom_image_bounds_raw ) ) {
    $bounds_array = maybe_unserialize( $oum_custom_image_bounds_raw );
    if ( is_array( $bounds_array ) ) {
        $oum_custom_image_bounds = json_encode( $bounds_array );
    }
}
$map_style = oum_sanitize_map_style_value( $map_style );
$oum_map_height = oum_sanitize_map_height_value( $oum_map_height );
$oum_map_height_mobile = oum_sanitize_map_height_value( $oum_map_height_mobile );
// Custom Attribute: Map Size
if ( isset( $block_attributes['size'] ) && $block_attributes['size'] != '' ) {
    $map_size = $block_attributes['size'];
}
// Custom Attribute: Map Style
if ( isset( $block_attributes['map_style'] ) && $block_attributes['map_style'] != '' ) {
    $map_style = oum_sanitize_map_style_value( $block_attributes['map_style'], $map_style );
}
// Custom Attribute: Height
if ( isset( $block_attributes['height'] ) && $block_attributes['height'] != '' ) {
    $oum_map_height = oum_sanitize_map_height_value( $block_attributes['height'] );
}
// Custom Attribute: Height (Mobile)
if ( isset( $block_attributes['height_mobile'] ) && $block_attributes['height_mobile'] != '' ) {
    $oum_map_height_mobile = oum_sanitize_map_height_value( $block_attributes['height_mobile'] );
}
// Custom Attribute: Clustering (true|false)
if ( isset( $block_attributes['enable_cluster'] ) && $block_attributes['enable_cluster'] != '' ) {
    $oum_enable_cluster = ( $block_attributes['enable_cluster'] === 'true' ? 'true' : 'false' );
}
// Custom Attribute: Map Type (interactive|simple)
if ( isset( $block_attributes['map_type'] ) && $block_attributes['map_type'] != '' ) {
    $oum_enable_add_location = ( $block_attributes['map_type'] == 'interactive' ? 'on' : '' );
}
// Custom Attribute: Fullscreen (true|false)
if ( isset( $block_attributes['enable_fullscreen'] ) && $block_attributes['enable_fullscreen'] != '' ) {
    $oum_enable_fullscreen = ( $block_attributes['enable_fullscreen'] === 'true' ? 'true' : 'false' );
}
// Custom Attribute: Hide location popup on marker click (true|false)
if ( isset( $block_attributes['hide_location_popup'] ) && $block_attributes['hide_location_popup'] != '' ) {
    $oum_hide_location_popup = ( $block_attributes['hide_location_popup'] === 'true' ? 'true' : 'false' );
}
// Custom Attribute: Searchbar (true|false)
if ( isset( $block_attributes['enable_searchbar'] ) && $block_attributes['enable_searchbar'] != '' ) {
    $oum_enable_searchbar = ( $block_attributes['enable_searchbar'] === 'true' ? 'true' : 'false' );
}
// Custom Attribute: Search Address Button (true|false)
if ( isset( $block_attributes['enable_searchaddress_button'] ) && $block_attributes['enable_searchaddress_button'] != '' ) {
    $oum_enable_searchaddress_button = ( $block_attributes['enable_searchaddress_button'] === 'true' ? 'true' : 'false' );
}
// Custom Attribute: Search Markers Button (true|false)
if ( isset( $block_attributes['enable_searchmarkers_button'] ) && $block_attributes['enable_searchmarkers_button'] != '' ) {
    $oum_enable_searchmarkers_button = ( $block_attributes['enable_searchmarkers_button'] === 'true' ? 'true' : 'false' );
}
// Custom Attribute: Current Location Button (true|false)
if ( isset( $block_attributes['enable_currentlocation'] ) && $block_attributes['enable_currentlocation'] != '' ) {
    $oum_enable_currentlocation = ( $block_attributes['enable_currentlocation'] === 'true' ? 'true' : 'false' );
}
// Custom Attribute: Disable Regions (true|false)
if ( isset( $block_attributes['disable_regions'] ) && $block_attributes['disable_regions'] != '' ) {
    $oum_enable_regions = ( $block_attributes['disable_regions'] == 'true' ? '' : $oum_enable_regions );
}
// Custom Attribute: Hide Filterbox (true|false)
if ( isset( $block_attributes['hide_filterbox'] ) && $block_attributes['hide_filterbox'] != '' ) {
    $oum_hide_filterbox = ( $block_attributes['hide_filterbox'] == 'true' ? 'true' : 'false' );
} else {
    $oum_hide_filterbox = 'false';
}
$oum_location_type_shortcode_context = $this->oum_location_type_shortcode_context( $block_attributes ?? array() );
$oum_location_type_marker_enabled = $this->oum_location_type_enabled_in_context( 'point', $oum_location_type_shortcode_context );
$oum_location_type_polyline_enabled = $this->oum_location_type_enabled_in_context( 'polyline', $oum_location_type_shortcode_context );
$oum_location_type_polygon_enabled = $this->oum_location_type_enabled_in_context( 'polygon', $oum_location_type_shortcode_context );
$oum_default_location_type = $this->oum_default_location_type_in_context( $oum_location_type_shortcode_context );
$GLOBALS['oum_location_type_shortcode_context'] = $oum_location_type_shortcode_context;
// Custom Attribute: Enable Advanced Filter (true|false)
if ( isset( $block_attributes['enable_advanced_filter'] ) && $block_attributes['enable_advanced_filter'] != '' ) {
    $oum_enable_advanced_filter = ( $block_attributes['enable_advanced_filter'] === 'true' ? true : false );
}
// Custom Attribute: Advanced Filter Layout (left|right|button|panel)
if ( isset( $block_attributes['advanced_filter_layout'] ) && $block_attributes['advanced_filter_layout'] != '' ) {
    $valid_layouts = array(
        'left',
        'right',
        'button',
        'panel'
    );
    $layout_value = strtolower( trim( $block_attributes['advanced_filter_layout'] ) );
    if ( in_array( $layout_value, $valid_layouts ) ) {
        $oum_advanced_filter_layout = $layout_value;
    }
}
if ( $oum_enable_regions == 'on' ) {
    // Taxonomy: Regions
    $regions = get_terms( array(
        'taxonomy'   => 'oum-region',
        'hide_empty' => false,
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key'     => 'oum_lat',
                'compare' => 'EXISTS',
            ),
            array(
                'key'     => 'oum_lng',
                'compare' => 'EXISTS',
            ),
            array(
                'key'     => 'oum_zoom',
                'compare' => 'EXISTS',
            ),
        ),
    ) );
}
// Taxonomy: Types (Marker Categories)
$types = get_terms( array(
    'taxonomy'   => 'oum-type',
    'hide_empty' => false,
) );
if ( is_wp_error( $types ) || empty( $types ) ) {
    $types = false;
} else {
    $types = \OpenUserMapPlugin\Base\BaseController::oum_sort_marker_categories_by_type( $types );
    $types = array_values( array_filter( $types, function ( $type ) use($oum_location_type_shortcode_context) {
        $category_type = \OpenUserMapPlugin\Base\BaseController::oum_marker_category_type( $type->term_id );
        return $this->oum_location_type_enabled_in_context( $category_type, $oum_location_type_shortcode_context );
    } ) );
    $types = ( empty( $types ) ? false : $types );
}
$query = array(
    'post_type'        => 'oum-location',
    'posts_per_page'   => -1,
    'suppress_filters' => false,
);
// Custom Attribute: Filter for types
if ( isset( $block_attributes['types'] ) && $block_attributes['types'] != '' ) {
    $selected_types_slugs = explode( '|', $block_attributes['types'] );
    // Check for attribute 'types-relation' and set relation accordingly
    $types_relation = ( isset( $block_attributes['types-relation'] ) && strtoupper( $block_attributes['types-relation'] ) === 'AND' ? 'AND' : 'OR' );
    if ( $types_relation === 'AND' ) {
        // Build tax_query with relation AND (all types must match)
        $tax_query = array(
            'relation' => 'AND',
        );
        foreach ( $selected_types_slugs as $slug ) {
            $tax_query[] = array(
                'taxonomy' => 'oum-type',
                'field'    => 'slug',
                'terms'    => $slug,
            );
        }
        $query['tax_query'] = $tax_query;
    } else {
        // Default: OR (any of the types)
        $query['tax_query'] = array(array(
            'taxonomy' => 'oum-type',
            'field'    => 'slug',
            'terms'    => $selected_types_slugs,
        ));
    }
    //overwrite types with filtered types
    $types = [];
    foreach ( $selected_types_slugs as $slug ) {
        $types[] = get_term_by( 'slug', $slug, 'oum-type' );
    }
    $types = \OpenUserMapPlugin\Base\BaseController::oum_sort_marker_categories_by_type( array_filter( $types ) );
}
// Custom Attribute: Filter for ids
if ( isset( $block_attributes['ids'] ) && $block_attributes['ids'] != '' ) {
    $selected_ids = explode( '|', $block_attributes['ids'] );
    $query['post__in'] = $selected_ids;
}
// Custom Attribute: Filter by date using keywords (e.g., "after:2025-08-15;before:2025-11-03")
// Accept both dash and underscore attribute names
$date_filter_attr = '';
if ( isset( $block_attributes['date-filter'] ) && $block_attributes['date-filter'] !== '' ) {
    $date_filter_attr = $block_attributes['date-filter'];
} elseif ( isset( $block_attributes['date_filter'] ) && $block_attributes['date_filter'] !== '' ) {
    $date_filter_attr = $block_attributes['date_filter'];
}
if ( $date_filter_attr !== '' ) {
    $date_filter_input = html_entity_decode( trim( $date_filter_attr ), ENT_QUOTES, 'UTF-8' );
    $tokens = array_map( 'trim', explode( ';', $date_filter_input ) );
    $date_query = array(
        'relation' => 'AND',
    );
    $date_column = ( $oum_location_date_type == 'created' ? 'post_date' : 'post_modified' );
    foreach ( $tokens as $token ) {
        if ( $token === '' ) {
            continue;
        }
        if ( preg_match( '/^(after|before):\\s*(\\d{4}-\\d{2}-\\d{2})$/i', $token, $m ) ) {
            $kw = strtolower( $m[1] );
            $date = $m[2];
            if ( $kw === 'after' ) {
                $date_query[] = array(
                    'column'    => $date_column,
                    'after'     => $date . ' 23:59:59',
                    'inclusive' => false,
                );
            } elseif ( $kw === 'before' ) {
                $date_query[] = array(
                    'column'    => $date_column,
                    'before'    => $date . ' 00:00:00',
                    'inclusive' => false,
                );
            }
        } elseif ( preg_match( '/^(\\d{4}-\\d{2}-\\d{2})$/', $token, $m ) ) {
            // Exact day without keyword: include that day
            $date = $m[1];
            $date_query[] = array(
                'column'    => $date_column,
                'after'     => $date . ' 00:00:00',
                'before'    => $date . ' 23:59:59',
                'inclusive' => true,
            );
        }
    }
    if ( count( $date_query ) > 1 ) {
        $query['date_query'] = $date_query;
    }
}
// Custom Attribute: Pre-select region
if ( isset( $regions ) && isset( $block_attributes['region'] ) && $block_attributes['region'] != '' ) {
    $oum_start_region_name = $block_attributes['region'];
    $regions_filtered = array_filter( $regions, function ( $obj ) use($oum_start_region_name) {
        return $obj->name == $oum_start_region_name;
    } );
    if ( !empty( $regions_filtered ) ) {
        $oum_start_region = current( $regions_filtered );
    }
}
// Instead of get_posts(), use WP_Query to get all post data at once
$locations_query = new WP_Query($query);
$posts = $locations_query->posts;
// Date filtering is handled directly via date_query in WP_Query
// Get all post meta in a single query
$post_ids = wp_list_pluck( $posts, 'ID' );
$all_meta = array();
if ( !empty( $post_ids ) ) {
    global $wpdb;
    $all_meta = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key, meta_value \n      FROM {$wpdb->postmeta} \n      WHERE post_id IN (" . implode( ',', array_fill( 0, count( $post_ids ), '%d' ) ) . ")\n      AND meta_key IN ('_oum_location_key', '_oum_location_image', '_oum_location_audio')", $post_ids ) );
}
// Index meta values by post_id and meta_key for faster lookup
$indexed_meta = array();
foreach ( $all_meta as $meta ) {
    if ( !isset( $indexed_meta[$meta->post_id] ) ) {
        $indexed_meta[$meta->post_id] = array();
    }
    $indexed_meta[$meta->post_id][$meta->meta_key] = $meta->meta_value;
}
/**
 * Preload all attachment IDs and their URLs at once (for media library images)
 *  */
global $wpdb;
// 1. Collect all image URLs from locations
$image_urls_needed = [];
foreach ( $posts as $post ) {
    $post_id = $post->ID;
    $image = ( isset( $indexed_meta[$post_id]['_oum_location_image'] ) ? $indexed_meta[$post_id]['_oum_location_image'] : '' );
    if ( $image ) {
        $image_urls_needed = array_merge( $image_urls_needed, explode( '|', $image ) );
    }
}
$image_urls_needed = array_unique( $image_urls_needed );
// 2. Filter out only media library images, ignore 'oum-useruploads'
$uploads_info = wp_upload_dir();
$uploads_baseurl = $uploads_info['baseurl'];
// Array of image URLs (input) — build the filenames we need
$filenames_needed = [];
foreach ( $image_urls_needed as $url ) {
    // Skip if it's a frontend user upload (not a media library attachment)
    if ( strpos( $url, 'oum-useruploads' ) !== false ) {
        continue;
    }
    // Check if the URL is a relative upload or an absolute upload from this site
    if ( strpos( $url, '/wp-content/uploads/' ) === 0 || strpos( $url, $uploads_baseurl ) === 0 ) {
        // Extract just the filename
        $filename = basename( $url );
        // Extract the filename without extension
        $filename_stem = pathinfo( $filename, PATHINFO_FILENAME );
        if ( $filename_stem ) {
            $filenames_needed[] = $filename_stem;
        }
    }
}
// If we have filenames to look for
if ( !empty( $filenames_needed ) ) {
    global $wpdb;
    // Build dynamic WHERE clause with safe prepared LIKE statements
    $likes = array_map( function ( $stem ) use($wpdb) {
        // Each LIKE: e.g., pm.meta_value LIKE '%683d9b1b1e7fd-1024x765%'
        return $wpdb->prepare( "pm.meta_value LIKE %s", '%' . $stem . '%' );
    }, $filenames_needed );
    // Build the SQL query
    $query = "\n        SELECT p.ID, pm.meta_value\n        FROM {$wpdb->posts} p\n        INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id)\n        WHERE p.post_type = 'attachment'\n        AND pm.meta_key = '_wp_attached_file'\n        AND (" . implode( ' OR ', $likes ) . ")\n    ";
    // Execute query and fetch results
    $attachments = $wpdb->get_results( $query );
    // Build lookup map: URL (relative and absolute) => attachment ID
    $image_url_to_id = [];
    foreach ( $attachments as $attachment ) {
        // Build relative URL (e.g., /wp-content/uploads/2025/06/file.jpg)
        $relative_url = '/wp-content/uploads/' . ltrim( $attachment->meta_value, '/' );
        // Build absolute URL (e.g., https://yoursite.com/wp-content/uploads/2025/06/file.jpg)
        $absolute_url = $uploads_baseurl . '/' . ltrim( $attachment->meta_value, '/' );
        // Map both relative and absolute URL to the attachment ID
        $image_url_to_id[$relative_url] = (int) $attachment->ID;
        $image_url_to_id[$absolute_url] = (int) $attachment->ID;
    }
}
// Get active custom fields once
$active_custom_fields = get_option( 'oum_custom_fields' );
$custom_fields_filter_config = array();
$custom_fields_filter_relation = 'AND';
/**
 * Parse custom-fields-filter attribute
 *
 * Format: LABEL:VALUE1|VALUE2:RELATION;LABEL2:VALUE3:RELATION
 * Example: "Select Level:One|Two:OR; Skill Level:Red"
 *
 * To include a colon in a value (e.g., URLs), escape it with a backslash: \:
 * Example: "Website:https\://example.com|http\://another.com"
 */
if ( !function_exists( 'oum_parse_custom_fields_filter' ) ) {
    function oum_parse_custom_fields_filter(  $filter_string  ) {
        if ( empty( $filter_string ) ) {
            return array();
        }
        $filter_config = array();
        $placeholder = '__OUM_ESCAPED_COLON__';
        $filter_groups = explode( ';', $filter_string );
        foreach ( $filter_groups as $group ) {
            $group = trim( $group );
            if ( empty( $group ) ) {
                continue;
            }
            $group_with_placeholder = str_replace( '\\:', $placeholder, $group );
            $parts = explode( ':', $group_with_placeholder );
            if ( count( $parts ) < 2 ) {
                continue;
            }
            $label = str_replace( $placeholder, ':', trim( $parts[0] ) );
            $relation = 'OR';
            $last_part = strtoupper( trim( end( $parts ) ) );
            if ( ($last_part === 'AND' || $last_part === 'OR') && count( $parts ) >= 3 ) {
                $relation = $last_part;
                $values_string = trim( implode( ':', array_slice( $parts, 1, -1 ) ) );
            } else {
                $values_string = trim( implode( ':', array_slice( $parts, 1 ) ) );
            }
            $values_string = str_replace( $placeholder, ':', $values_string );
            $values = array_map( 'trim', explode( '|', $values_string ) );
            $values = array_map( function ( $val ) use($placeholder) {
                return str_replace( $placeholder, ':', $val );
            }, $values );
            $filter_config[] = array(
                'label'    => $label,
                'values'   => $values,
                'relation' => $relation,
            );
        }
        return $filter_config;
    }

}
/**
 * Check if a location's meta matches the custom-fields filter
 */
if ( !function_exists( 'oum_location_matches_custom_field_filter' ) ) {
    function oum_location_matches_custom_field_filter(
        $location_meta,
        $filter_config,
        $active_custom_fields,
        $filter_groups_relation = 'AND'
    ) {
        if ( empty( $filter_config ) ) {
            return true;
        }
        $location_custom_fields = ( isset( $location_meta['custom_fields'] ) ? $location_meta['custom_fields'] : array() );
        $group_matches = array();
        foreach ( $filter_config as $filter_group ) {
            $label = $filter_group['label'];
            $values = $filter_group['values'];
            $relation = strtoupper( $filter_group['relation'] );
            $custom_field_index = null;
            if ( is_array( $active_custom_fields ) ) {
                foreach ( $active_custom_fields as $index => $custom_field ) {
                    if ( strtolower( trim( $custom_field['label'] ) ) === strtolower( trim( $label ) ) ) {
                        $custom_field_index = $index;
                        break;
                    }
                }
            }
            if ( $custom_field_index === null ) {
                $group_matches[] = false;
                continue;
            }
            $field_value = ( isset( $location_custom_fields[$custom_field_index] ) ? $location_custom_fields[$custom_field_index] : null );
            $field_values = array();
            if ( is_array( $field_value ) ) {
                $field_values = array_map( 'trim', $field_value );
            } elseif ( $field_value !== null && $field_value !== '' ) {
                if ( strpos( $field_value, '|' ) !== false ) {
                    $field_values = array_map( 'trim', explode( '|', $field_value ) );
                } else {
                    $field_values = array(trim( $field_value ));
                }
            }
            $filter_values = array_map( 'trim', $values );
            $matches = false;
            if ( $relation === 'AND' ) {
                $matches = count( array_intersect( $filter_values, $field_values ) ) === count( $filter_values );
            } else {
                $matches = count( array_intersect( $filter_values, $field_values ) ) > 0;
            }
            $group_matches[] = $matches;
        }
        if ( strtoupper( $filter_groups_relation ) === 'OR' ) {
            return in_array( true, $group_matches, true );
        }
        return !in_array( false, $group_matches, true );
    }

}
// Parse attributes for custom fields filter
if ( isset( $block_attributes['custom-fields-filter'] ) && $block_attributes['custom-fields-filter'] != '' ) {
    $custom_fields_filter_config = oum_parse_custom_fields_filter( $block_attributes['custom-fields-filter'] );
}
if ( isset( $block_attributes['custom-fields-filter-relation'] ) && $block_attributes['custom-fields-filter-relation'] != '' ) {
    $rel = strtoupper( trim( $block_attributes['custom-fields-filter-relation'] ) );
    if ( $rel === 'OR' || $rel === 'AND' ) {
        $custom_fields_filter_relation = $rel;
    }
}
$posts = array_values( $posts );
// ensure reindexing
if ( !empty( $custom_fields_filter_config ) && is_array( $posts ) && !empty( $posts ) ) {
    $filtered_posts = array();
    foreach ( $posts as $post ) {
        $pid = $post->ID;
        $lm = ( isset( $indexed_meta[$pid]['_oum_location_key'] ) ? maybe_unserialize( $indexed_meta[$pid]['_oum_location_key'] ) : array() );
        if ( oum_location_matches_custom_field_filter(
            $lm,
            $custom_fields_filter_config,
            $active_custom_fields,
            $custom_fields_filter_relation
        ) ) {
            $filtered_posts[] = $post;
        }
    }
    $posts = $filtered_posts;
}
$locations_list = array();
foreach ( $posts as $post ) {
    $post_id = $post->ID;
    // Get all meta values for this post from our indexed array
    $location_meta = ( isset( $indexed_meta[$post_id]['_oum_location_key'] ) ? maybe_unserialize( $indexed_meta[$post_id]['_oum_location_key'] ) : array() );
    if ( !isset( $location_meta['lat'] ) || !isset( $location_meta['lng'] ) ) {
        continue;
    }
    $name = str_replace( "'", "\\'", strip_tags( $post->post_title ) );
    $address = ( isset( $location_meta['address'] ) ? str_replace( "'", "\\'", preg_replace( '/\\r|\\n/', '', $location_meta['address'] ) ) : '' );
    $text = ( isset( $location_meta["text"] ) ? str_replace( "'", "\\'", str_replace( array("\r\n", "\r", "\n"), "<br>", $location_meta["text"] ) ) : '' );
    $video = ( isset( $location_meta["video"] ) ? $location_meta["video"] : '' );
    $category_color = '';
    $image = ( isset( $indexed_meta[$post_id]['_oum_location_image'] ) ? $indexed_meta[$post_id]['_oum_location_image'] : '' );
    $image_thumb = null;
    // Handle multiple images
    $images = array();
    if ( $image ) {
        $image_urls = explode( '|', $image );
        foreach ( $image_urls as $url ) {
            if ( stristr( $url, 'oum-useruploads' ) ) {
                // Handle user uploads - always use original image
                $images[] = $url;
            } else {
                // Handle media library images
                $image_id = ( isset( $image_url_to_id[$url] ) ? $image_url_to_id[$url] : 0 );
                if ( $image_id > 0 ) {
                    $thumb = wp_get_attachment_image_url( $image_id, 'medium_large' );
                    $images[] = ( $thumb ? $thumb : $url );
                } else {
                    $images[] = $url;
                }
            }
        }
    }
    // Convert to absolute URLs for JavaScript display
    $absolute_images = array_map( function ( $url ) {
        // Convert relative path to absolute URL if needed
        return ( strpos( $url, 'http' ) !== 0 ? site_url() . $url : $url );
    }, $images );
    $audio = ( isset( $indexed_meta[$post_id]['_oum_location_audio'] ) ? $indexed_meta[$post_id]['_oum_location_audio'] : '' );
    // Convert audio to absolute URL if needed
    $absolute_audio = ( isset( $audio ) && $audio != '' && strpos( $audio, 'http' ) !== 0 ? site_url() . $audio : $audio );
    // Optimized custom fields processing
    $custom_fields = [];
    $meta_custom_fields = ( isset( $location_meta['custom_fields'] ) ? $location_meta['custom_fields'] : false );
    // Private custom fields are hidden from public map data, but the edit form needs their stored values.
    // Expose private values only when the current user may edit this location (matches ajax_check_edit_permission in LocationController).
    $has_general_edit = current_user_can( 'edit_oum-locations' );
    $is_location_author = (int) get_current_user_id() === (int) get_post_field( 'post_author', $post_id );
    $can_edit_this_post = current_user_can( 'edit_post', $post_id );
    $include_private_custom_fields = $has_general_edit && ($is_location_author || $can_edit_this_post);
    if ( is_array( $meta_custom_fields ) && is_array( $active_custom_fields ) ) {
        // Iterate over active_custom_fields to maintain order
        foreach ( $active_custom_fields as $index => $custom_field ) {
            // Skip private fields for users who cannot edit (do not leak private data in page HTML/JSON).
            if ( isset( $custom_field['private'] ) && !$include_private_custom_fields ) {
                continue;
            }
            // Skip if no value exists for this field
            if ( !isset( $meta_custom_fields[$index] ) ) {
                continue;
            }
            $field_data = [
                'index'                => $index,
                'label'                => $custom_field['label'],
                'val'                  => $meta_custom_fields[$index],
                'fieldtype'            => ( isset( $custom_field['fieldtype'] ) ? $custom_field['fieldtype'] : 'text' ),
                'uselabelastextoption' => ( isset( $custom_field['uselabelastextoption'] ) ? $custom_field['uselabelastextoption'] : false ),
                'use12hour'            => ( isset( $custom_field['use12hour'] ) ? $custom_field['use12hour'] : false ),
                'private'              => !empty( $custom_field['private'] ),
            ];
            // Calculate open_now for opening_hours fields
            if ( $custom_field['fieldtype'] === 'opening_hours' ) {
                $opening_hours_data = json_decode( $meta_custom_fields[$index], true );
                // Use centralized helper function to calculate open_now
                $open_now = \OpenUserMapPlugin\Base\LocationController::calculate_open_now( $opening_hours_data );
                $field_data['open_now'] = $open_now;
            }
            $custom_fields[] = $field_data;
        }
    }
    // Determine marker icon based on number of categories (types)
    $multi_icon = ( get_option( 'oum_marker_multicategories_icon' ) ? get_option( 'oum_marker_multicategories_icon' ) : $this->oum_marker_multicategories_icon_default );
    // Helper function to get default icon based on settings
    $get_default_icon = function () use($marker_icon, $marker_user_icon) {
        if ( $marker_icon == 'user1' && $marker_user_icon ) {
            return esc_url( $marker_user_icon );
        } elseif ( $marker_icon ) {
            return esc_url( $this->plugin_url ) . 'src/leaflet/images/marker-icon_' . esc_attr( $marker_icon ) . '-2x.png';
        } else {
            return esc_url( $this->plugin_url ) . 'src/leaflet/images/marker-icon_default-2x.png';
        }
    };
    if ( isset( $location_types ) && is_array( $location_types ) ) {
        if ( count( $location_types ) > 1 ) {
            // Multiple categories: use multi-categories icon
            $icon = esc_url( $multi_icon );
        } elseif ( count( $location_types ) === 1 ) {
            // Single category: use that category's icon if set, else default
            $type = $location_types[0];
            $category_color = sanitize_hex_color( get_term_meta( $type->term_id, 'oum_marker_color', true ) );
            $cat_icon = get_term_meta( $type->term_id, 'oum_marker_icon', true );
            $cat_user_icon = get_term_meta( $type->term_id, 'oum_marker_user_icon', true );
            if ( $cat_icon == 'user1' && $cat_user_icon ) {
                $icon = esc_url( $cat_user_icon );
            } elseif ( $cat_icon ) {
                $icon = esc_url( $this->plugin_url ) . 'src/leaflet/images/marker-icon_' . esc_attr( $cat_icon ) . '-2x.png';
            } else {
                // Use default marker icon from settings
                $icon = $get_default_icon();
            }
        } else {
            // No category: use default marker icon from settings
            $icon = $get_default_icon();
        }
    } else {
        // No category: use default marker icon from settings
        $icon = $get_default_icon();
    }
    // Date: modified or published
    if ( $oum_location_date_type == 'created' ) {
        $date = get_the_date( '', $post_id );
    } else {
        $date = get_the_modified_date( '', $post_id );
    }
    $location_geometry_type = ( isset( $location_meta['geometry_type'] ) ? $location_meta['geometry_type'] : 'point' );
    $location_geometry = ( isset( $location_meta['geometry'] ) ? $location_meta['geometry'] : null );
    if ( !$this->oum_location_type_enabled_in_context( $location_geometry_type, $oum_location_type_shortcode_context ) ) {
        continue;
    }
    $show_location_measurement = $location_geometry_type === 'polyline' && get_option( 'oum_enable_location_type_polyline_distance', 'on' ) === 'on' || $location_geometry_type === 'polygon' && get_option( 'oum_enable_location_type_polygon_area', 'on' ) === 'on';
    // collect locations for JS use
    $location = array(
        'post_id'           => $post_id,
        'date'              => $date,
        'title'             => $name,
        'address'           => $address,
        'lat'               => $location_meta['lat'],
        'lng'               => $location_meta['lng'],
        'zoom'              => ( isset( $location_meta['zoom'] ) ? $location_meta['zoom'] : '12' ),
        'geometry_type'     => $location_geometry_type,
        'geometry'          => $location_geometry,
        'show_measurement'  => $show_location_measurement,
        'category_color'    => ( $category_color ? $category_color : (( get_option( 'oum_ui_color' ) ? get_option( 'oum_ui_color' ) : '#e82c71' )) ),
        'text'              => $text,
        'images'            => $absolute_images,
        'audio'             => $absolute_audio,
        'video'             => $video,
        'icon'              => $icon,
        'custom_fields'     => $custom_fields,
        'author_id'         => get_post_field( 'post_author', $post_id ),
        'votes'             => ( isset( $location_meta['votes'] ) ? intval( $location_meta['votes'] ) : 0 ),
        'star_rating_avg'   => ( isset( $location_meta['star_rating_avg'] ) ? floatval( $location_meta['star_rating_avg'] ) : 0 ),
        'star_rating_count' => ( isset( $location_meta['star_rating_count'] ) ? intval( $location_meta['star_rating_count'] ) : 0 ),
    );
    if ( isset( $location_types ) && is_array( $location_types ) && count( $location_types ) > 0 ) {
        foreach ( $location_types as $term ) {
            $location['types'][] = (string) $term->term_taxonomy_id;
        }
    }
    $locations_list[] = $location;
}
$oum_use_settings_start_location = 'false';
$normalize_start_attribute = static function ( $value, $min = null, $max = null ) {
    if ( !is_scalar( $value ) ) {
        return false;
    }
    // Shortcode attributes are rendered into JavaScript later, so only allow numeric map values.
    $value = trim( str_replace( ',', '.', sanitize_text_field( (string) $value ) ) );
    if ( $value === '' || !is_numeric( $value ) ) {
        return false;
    }
    $value = (float) $value;
    if ( $min !== null && $value < $min || $max !== null && $value > $max ) {
        return false;
    }
    return $value;
};
$has_valid_shortcode_start_location = false;
if ( isset( $block_attributes['lat'] ) && $block_attributes['lat'] != '' && isset( $block_attributes['long'] ) && $block_attributes['long'] != '' && isset( $block_attributes['zoom'] ) && $block_attributes['zoom'] != '' ) {
    $shortcode_start_lat = $normalize_start_attribute( $block_attributes['lat'], -90, 90 );
    $shortcode_start_lng = $normalize_start_attribute( $block_attributes['long'], -180, 180 );
    $shortcode_start_zoom = $normalize_start_attribute( $block_attributes['zoom'], 0 );
    $has_valid_shortcode_start_location = $shortcode_start_lat !== false && $shortcode_start_lng !== false && $shortcode_start_zoom !== false;
}
// Set focus for map init
if ( $has_valid_shortcode_start_location ) {
    //get validated lat, long, zoom from shortcode attributes
    $start_lat = $shortcode_start_lat;
    $start_lng = $shortcode_start_lng;
    $start_zoom = $shortcode_start_zoom;
} elseif ( isset( $oum_start_region ) && $oum_start_region != '' ) {
    //get region from shortcode attribute
    $start_lat = get_term_meta( $oum_start_region->term_id, 'oum_lat', true );
    $start_lng = get_term_meta( $oum_start_region->term_id, 'oum_lng', true );
    $start_zoom = get_term_meta( $oum_start_region->term_id, 'oum_zoom', true );
} elseif ( get_option( 'oum_start_lat' ) !== false && get_option( 'oum_start_lat' ) !== '' && (get_option( 'oum_start_lng' ) !== false && get_option( 'oum_start_lng' ) !== '') && (get_option( 'oum_start_zoom' ) !== false && get_option( 'oum_start_zoom' ) !== '') ) {
    //get from settings
    $oum_use_settings_start_location = 'true';
    $start_lat = get_option( 'oum_start_lat' );
    $start_lng = get_option( 'oum_start_lng' );
    $start_zoom = get_option( 'oum_start_zoom' );
} elseif ( count( $locations_list ) == 1 ) {
    //get from single location
    $start_lat = $locations_list[0]['lat'];
    $start_lng = $locations_list[0]['lng'];
    $start_zoom = $locations_list[0]['zoom'];
} else {
    //default worldview
    $start_lat = '26';
    $start_lng = '0';
    $start_zoom = '1';
}
$i = 0;
// BUGFIX: resolves issue with non-unique ids when caching inline js with 3rd party plugins
// todo: allow multiple maps/shortcodes on same site
//$unique_id = uniqid();
$unique_id = 20210929;