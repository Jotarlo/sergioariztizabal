<?php

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
// Load settings
$oum_enable_gmaps_link = get_option( 'oum_enable_gmaps_link', 'on' );
$oum_location_date_type = get_option( 'oum_location_date_type', 'modified' );
// Build query
$count = max( 1, (int) get_option( 'posts_per_page', 10 ) );
// Custom Attribute: Override the default WordPress posts per page setting. Use -1 to show all locations.
if ( isset( $block_attributes['posts_per_page'] ) && trim( $block_attributes['posts_per_page'] ) !== '' ) {
    $posts_per_page_attribute = (int) trim( $block_attributes['posts_per_page'] );
    if ( $posts_per_page_attribute === -1 || $posts_per_page_attribute > 0 ) {
        $count = $posts_per_page_attribute;
    }
}
$paged = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
$query = array(
    'post_type'      => 'oum-location',
    'fields'         => 'ids',
    'posts_per_page' => $count,
    'paged'          => $paged,
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
/**
 * Helper function to check if a location matches custom field filter criteria
 * 
 * @param array $location_meta The location meta data
 * @param array $filter_config The parsed filter configuration
 * @param array $active_custom_fields All active custom fields configuration
 * @param string $filter_groups_relation Relation between filter groups (AND or OR)
 * @return bool True if location matches filter, false otherwise
*/
if ( !function_exists( 'oum_location_matches_custom_field_filter' ) ) {
    function oum_location_matches_custom_field_filter(
        $location_meta,
        $filter_config,
        $active_custom_fields,
        $filter_groups_relation = 'AND'
    ) {
        // If no filters configured, always match
        if ( empty( $filter_config ) ) {
            return true;
        }
        // Get custom fields from location meta
        $location_custom_fields = ( isset( $location_meta['custom_fields'] ) ? $location_meta['custom_fields'] : array() );
        // Track matches for each filter group
        $group_matches = array();
        // Check each filter group
        foreach ( $filter_config as $filter_group ) {
            $label = $filter_group['label'];
            $values = $filter_group['values'];
            $relation = strtoupper( $filter_group['relation'] );
            // OR or AND (within group)
            // Find custom field index by label
            $custom_field_index = null;
            foreach ( $active_custom_fields as $index => $custom_field ) {
                if ( strtolower( trim( $custom_field['label'] ) ) === strtolower( trim( $label ) ) ) {
                    $custom_field_index = $index;
                    break;
                }
            }
            // If custom field not found, consider it as not matching
            if ( $custom_field_index === null ) {
                $group_matches[] = false;
                continue;
            }
            // Get the location's custom field value
            $field_value = ( isset( $location_custom_fields[$custom_field_index] ) ? $location_custom_fields[$custom_field_index] : null );
            // Normalize field value to array for comparison
            $field_values = array();
            if ( is_array( $field_value ) ) {
                $field_values = array_map( 'trim', $field_value );
            } elseif ( $field_value !== null && $field_value !== '' ) {
                // Handle pipe-separated values (for fields that store multiple values as string)
                if ( strpos( $field_value, '|' ) !== false ) {
                    $field_values = array_map( 'trim', explode( '|', $field_value ) );
                } else {
                    $field_values = array(trim( $field_value ));
                }
            }
            // Normalize filter values
            $filter_values = array_map( 'trim', $values );
            // Check if any or all values match (depending on relation within group)
            $matches = false;
            if ( $relation === 'AND' ) {
                // All filter values must be in field values
                $matches = count( array_intersect( $filter_values, $field_values ) ) === count( $filter_values );
            } else {
                // OR: At least one filter value must match
                $matches = count( array_intersect( $filter_values, $field_values ) ) > 0;
            }
            $group_matches[] = $matches;
        }
        // Apply relation between filter groups
        if ( strtoupper( $filter_groups_relation ) === 'OR' ) {
            // At least one group must match
            return in_array( true, $group_matches, true );
        } else {
            // AND: All groups must match
            return !in_array( false, $group_matches, true );
        }
    }

}
/**
 * Parse custom-fields-filter attribute
 * 
 * Format: LABEL:VALUE1|VALUE2:RELATION;LABEL2:VALUE3:RELATION
 * Example: "Select Level:One|Two:OR;Skill Level:Red"
 * 
 * To include a colon in a value (e.g., URLs), escape it with a backslash: \:
 * Example: "Website:https\://example.com|http\://another.com"
 * 
 * @param string $filter_string The filter string from attribute
 * @return array Parsed filter configuration
*/
if ( !function_exists( 'oum_parse_custom_fields_filter' ) ) {
    function oum_parse_custom_fields_filter(  $filter_string  ) {
        if ( empty( $filter_string ) ) {
            return array();
        }
        $filter_config = array();
        // Temporary placeholder for escaped colons (unlikely to appear in user input)
        $placeholder = '__OUM_ESCAPED_COLON__';
        // Split by semicolon to get individual filter groups
        $filter_groups = explode( ';', $filter_string );
        foreach ( $filter_groups as $group ) {
            $group = trim( $group );
            if ( empty( $group ) ) {
                continue;
            }
            // Replace escaped colons (\:) with placeholder before splitting
            // This allows values to contain colons (e.g., URLs like https://example.com)
            $group_with_placeholder = str_replace( '\\:', $placeholder, $group );
            // Parse format: LABEL:VALUES:RELATION
            // RELATION is optional, defaults to OR
            $parts = explode( ':', $group_with_placeholder );
            if ( count( $parts ) < 2 ) {
                continue;
                // Invalid format, skip
            }
            // Restore escaped colons in label
            $label = str_replace( $placeholder, ':', trim( $parts[0] ) );
            $relation = 'OR';
            // Default relation
            // Determine if the last part is a relation token; values are everything between label and (optional) relation
            $last_part = strtoupper( trim( end( $parts ) ) );
            if ( ($last_part === 'AND' || $last_part === 'OR') && count( $parts ) >= 3 ) {
                $relation = $last_part;
                $values_string = trim( implode( ':', array_slice( $parts, 1, -1 ) ) );
            } else {
                $values_string = trim( implode( ':', array_slice( $parts, 1 ) ) );
            }
            // Restore escaped colons in values string
            $values_string = str_replace( $placeholder, ':', $values_string );
            // Split values by pipe
            $values = array_map( 'trim', explode( '|', $values_string ) );
            // Restore escaped colons in each individual value
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
 * Helper function to normalize custom field values for sorting.
 *
 * @param mixed $field_value The raw custom field value from location meta.
 * @return string Normalized value used in comparison.
 */
if ( !function_exists( 'oum_normalize_custom_field_sort_value' ) ) {
    function oum_normalize_custom_field_sort_value(  $field_value  ) {
        if ( is_array( $field_value ) ) {
            $field_value = implode( '|', array_map( 'strval', $field_value ) );
        }
        if ( $field_value === null ) {
            return '';
        }
        return trim( wp_strip_all_tags( (string) $field_value ) );
    }

}
/**
 * Compare two normalized sort values with numeric fallback support.
 *
 * @param string $a First normalized value.
 * @param string $b Second normalized value.
 * @param string $direction ASC or DESC.
 * @return int Comparison result for usort.
 */
if ( !function_exists( 'oum_compare_sort_values' ) ) {
    function oum_compare_sort_values(  $a, $b, $direction = 'ASC'  ) {
        if ( is_numeric( $a ) && is_numeric( $b ) ) {
            $result = (float) $a <=> (float) $b;
        } else {
            $result = strnatcasecmp( (string) $a, (string) $b );
        }
        if ( $result === 0 ) {
            return 0;
        }
        return ( $direction === 'DESC' ? -$result : $result );
    }

}
// Get active custom fields once (needed for filtering and custom-field sorting)
$active_custom_fields = get_option( 'oum_custom_fields', array() );
// Custom Attribute: Sort list view by title/date/custom field label (e.g. "Title:DESC")
$sort_field_label = '';
$sort_direction = 'ASC';
$sort_type = '';
$sort_custom_field_index = null;
if ( isset( $block_attributes['sort'] ) && trim( (string) $block_attributes['sort'] ) !== '' ) {
    $sort_attr = html_entity_decode( trim( (string) $block_attributes['sort'] ), ENT_QUOTES, 'UTF-8' );
    $separator_pos = strrpos( $sort_attr, ':' );
    if ( $separator_pos !== false ) {
        $sort_field_label = trim( substr( $sort_attr, 0, $separator_pos ) );
        $sort_direction_candidate = strtoupper( trim( substr( $sort_attr, $separator_pos + 1 ) ) );
    } else {
        $sort_field_label = trim( $sort_attr );
        $sort_direction_candidate = 'ASC';
    }
    if ( $sort_direction_candidate === 'DESC' || $sort_direction_candidate === 'ASC' ) {
        $sort_direction = $sort_direction_candidate;
    }
    if ( $sort_field_label !== '' ) {
        $normalized_sort_field = strtolower( $sort_field_label );
        if ( $normalized_sort_field === 'title' ) {
            $sort_type = 'native_title';
            $query['orderby'] = 'title';
            $query['order'] = $sort_direction;
        } elseif ( $normalized_sort_field === 'date' ) {
            $sort_type = 'native_date';
            $query['orderby'] = ( $oum_location_date_type === 'created' ? 'date' : 'modified' );
            $query['order'] = $sort_direction;
        } else {
            // Try to resolve the custom field by label (case-insensitive)
            foreach ( $active_custom_fields as $index => $custom_field ) {
                if ( !isset( $custom_field['label'] ) ) {
                    continue;
                }
                if ( strtolower( trim( (string) $custom_field['label'] ) ) === $normalized_sort_field ) {
                    $sort_type = 'custom_field';
                    $sort_custom_field_index = (int) $index;
                    break;
                }
            }
        }
    }
}
// Parse custom fields filter attributes
$custom_fields_filter_config = array();
$custom_fields_filter_relation = 'AND';
// Default relation between filter groups
if ( isset( $block_attributes['custom-fields-filter'] ) && $block_attributes['custom-fields-filter'] != '' ) {
    $custom_fields_filter_config = oum_parse_custom_fields_filter( $block_attributes['custom-fields-filter'] );
}
if ( isset( $block_attributes['custom-fields-filter-relation'] ) && $block_attributes['custom-fields-filter-relation'] != '' ) {
    $relation = strtoupper( trim( $block_attributes['custom-fields-filter-relation'] ) );
    if ( $relation === 'OR' || $relation === 'AND' ) {
        $custom_fields_filter_relation = $relation;
    }
}
// Init WP_Query (with custom-fields pagination handling)
$pagination_total_pages = 0;
// fallback to real query pages when 0
if ( !empty( $custom_fields_filter_config ) || $sort_type === 'custom_field' && $sort_custom_field_index !== null ) {
    // We need accurate pagination after PHP-side filtering/sorting.
    // Strategy: fetch all candidate IDs, apply optional filter + optional custom-field sorting,
    // then slice IDs for current page and run a second query on the slice.
    $all_query = $query;
    $all_query['posts_per_page'] = -1;
    unset($all_query['paged']);
    $all_locations_query = new WP_Query($all_query);
    $matched_ids = array();
    if ( $all_locations_query->have_posts() ) {
        foreach ( $all_locations_query->posts as $post_id ) {
            $post_id = (int) $post_id;
            $location_meta_for_match = get_post_meta( $post_id, '_oum_location_key', true );
            if ( !empty( $custom_fields_filter_config ) ) {
                if ( !oum_location_matches_custom_field_filter(
                    $location_meta_for_match,
                    $custom_fields_filter_config,
                    $active_custom_fields,
                    $custom_fields_filter_relation
                ) ) {
                    continue;
                }
            }
            $matched_ids[] = $post_id;
        }
    }
    wp_reset_postdata();
    // Sort by custom field after filtering so pagination stays correct.
    if ( $sort_type === 'custom_field' && $sort_custom_field_index !== null && !empty( $matched_ids ) ) {
        $sort_value_cache = array();
        usort( $matched_ids, function ( $a, $b ) use(&$sort_value_cache, $sort_custom_field_index, $sort_direction) {
            if ( !array_key_exists( $a, $sort_value_cache ) ) {
                $meta_a = get_post_meta( $a, '_oum_location_key', true );
                $raw_value_a = ( is_array( $meta_a ) && isset( $meta_a['custom_fields'][$sort_custom_field_index] ) ? $meta_a['custom_fields'][$sort_custom_field_index] : '' );
                $sort_value_cache[$a] = oum_normalize_custom_field_sort_value( $raw_value_a );
            }
            if ( !array_key_exists( $b, $sort_value_cache ) ) {
                $meta_b = get_post_meta( $b, '_oum_location_key', true );
                $raw_value_b = ( is_array( $meta_b ) && isset( $meta_b['custom_fields'][$sort_custom_field_index] ) ? $meta_b['custom_fields'][$sort_custom_field_index] : '' );
                $sort_value_cache[$b] = oum_normalize_custom_field_sort_value( $raw_value_b );
            }
            $result = oum_compare_sort_values( $sort_value_cache[$a], $sort_value_cache[$b], $sort_direction );
            if ( $result === 0 ) {
                return $a <=> $b;
                // stable fallback for equal values
            }
            return $result;
        } );
    }
    // Compute pagination based on matched IDs
    $per_page = $count;
    $current_page = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
    $total_matched = count( $matched_ids );
    $pagination_total_pages = ( $per_page === -1 || $per_page <= 0 ? 1 : (int) ceil( $total_matched / $per_page ) );
    // Slice IDs for current page
    if ( $per_page === -1 ) {
        $page_ids = $matched_ids;
    } else {
        $offset = max( 0, ($current_page - 1) * $per_page );
        $page_ids = array_slice( $matched_ids, $offset, $per_page );
    }
    // Build a focused query for just these IDs (preserve ordering)
    $query['post__in'] = ( $page_ids ?: array(0) );
    // ensure no results if empty
    $query['orderby'] = 'post__in';
    $query['posts_per_page'] = ( $per_page === -1 ? -1 : count( $page_ids ) );
    $query['paged'] = 1;
    // paging handled by slicing
}
$locations_query = new WP_Query($query);
$locations_list = array();
if ( $locations_query->have_posts() ) {
    while ( $locations_query->have_posts() ) {
        $locations_query->the_post();
        $post_id = get_the_ID();
        // Prepare data
        $location_meta = get_post_meta( $post_id, '_oum_location_key', true );
        // Apply custom field filter if configured
        if ( !empty( $custom_fields_filter_config ) ) {
            if ( !oum_location_matches_custom_field_filter(
                $location_meta,
                $custom_fields_filter_config,
                $active_custom_fields,
                $custom_fields_filter_relation
            ) ) {
                continue;
                // Skip this location if it doesn't match the filter
            }
        }
        $name = str_replace( "'", "\\'", strip_tags( get_the_title( $post_id ) ) );
        $address = ( isset( $location_meta['address'] ) ? str_replace( "'", "\\'", preg_replace( '/\\r|\\n/', '', $location_meta['address'] ) ) : '' );
        $text = ( isset( $location_meta["text"] ) ? str_replace( "'", "\\'", str_replace( array("\r\n", "\r", "\n"), "<br>", $location_meta["text"] ) ) : '' );
        $video = ( isset( $location_meta["video"] ) ? $location_meta["video"] : '' );
        $image = get_post_meta( $post_id, '_oum_location_image', true );
        $image_thumb = null;
        if ( stristr( $image, 'oum-useruploads' ) ) {
            //image uploaded from frontend - always use original image
            $image_thumb = $image;
        } else {
            //image uploaded from backend
            $image_id = attachment_url_to_postid( $image );
            if ( $image_id > 0 ) {
                $image_thumb = wp_get_attachment_image_url( $image_id, 'medium' );
            }
        }
        if ( isset( $image_thumb ) && $image_thumb != '' ) {
            //use thumbnail if available
            $image = $image_thumb;
        }
        $audio = get_post_meta( $post_id, '_oum_location_audio', true );
        // custom fields
        $custom_fields = [];
        $meta_custom_fields = ( isset( $location_meta['custom_fields'] ) ? $location_meta['custom_fields'] : false );
        if ( is_array( $meta_custom_fields ) && is_array( $active_custom_fields ) ) {
            foreach ( $active_custom_fields as $index => $active_custom_field ) {
                //don't add if private
                if ( isset( $active_custom_field['private'] ) ) {
                    continue;
                }
                if ( isset( $meta_custom_fields[$index] ) ) {
                    $field_data = array(
                        'label'                => $active_custom_field['label'],
                        'val'                  => $meta_custom_fields[$index],
                        'fieldtype'            => $active_custom_field['fieldtype'],
                        'uselabelastextoption' => ( isset( $active_custom_field['uselabelastextoption'] ) ? $active_custom_field['uselabelastextoption'] : false ),
                    );
                    // Add opening hours specific data
                    if ( $active_custom_field['fieldtype'] === 'opening_hours' ) {
                        $opening_hours_data = json_decode( $meta_custom_fields[$index], true );
                        // Use centralized helper function to calculate open_now
                        $open_now = \OpenUserMapPlugin\Base\LocationController::calculate_open_now( $opening_hours_data );
                        $field_data['use12hour'] = ( isset( $active_custom_field['use12hour'] ) ? $active_custom_field['use12hour'] : false );
                        $field_data['open_now'] = $open_now;
                    }
                    array_push( $custom_fields, $field_data );
                }
            }
        }
        if ( !isset( $location_meta['lat'] ) && !isset( $location_meta['lng'] ) ) {
            continue;
        }
        $geolocation = array(
            'lat' => $location_meta['lat'],
            'lng' => $location_meta['lng'],
        );
        if ( isset( $location_types ) && is_array( $location_types ) && count( $location_types ) == 1 && !get_option( 'oum_enable_multiple_marker_types' ) ) {
            //get current location icon from oum-type taxonomy
            $type = $location_types[0];
            $current_marker_icon = ( get_term_meta( $type->term_id, 'oum_marker_icon', true ) ? get_term_meta( $type->term_id, 'oum_marker_icon', true ) : 'default' );
            $current_marker_user_icon = get_term_meta( $type->term_id, 'oum_marker_user_icon', true );
        } else {
            //get current location icon from settings
            $current_marker_icon = ( get_option( 'oum_marker_icon' ) ? get_option( 'oum_marker_icon' ) : 'default' );
            $current_marker_user_icon = get_option( 'oum_marker_user_icon' );
        }
        if ( $current_marker_icon == 'user1' && $current_marker_user_icon ) {
            $icon = esc_url( $current_marker_user_icon );
        } else {
            $icon = esc_url( $this->plugin_url ) . 'src/leaflet/images/marker-icon_' . esc_attr( $current_marker_icon ) . '-2x.png';
        }
        // Date: modified or published
        if ( $oum_location_date_type == 'created' ) {
            $date = get_the_date( '', $post_id );
        } else {
            $date = get_the_modified_date( '', $post_id );
        }
        // collect locations for JS use
        $location = array(
            'post_id'           => $post_id,
            'date'              => $date,
            'title'             => $name,
            'address'           => $address,
            'lat'               => $geolocation['lat'],
            'lng'               => $geolocation['lng'],
            'text'              => $text,
            'image'             => $image,
            'audio'             => $audio,
            'video'             => $video,
            'icon'              => $icon,
            'custom_fields'     => $custom_fields,
            'votes'             => ( isset( $location_meta['votes'] ) ? intval( $location_meta['votes'] ) : 0 ),
            'star_rating_avg'   => ( isset( $location_meta['star_rating_avg'] ) ? floatval( $location_meta['star_rating_avg'] ) : 0 ),
            'star_rating_count' => ( isset( $location_meta['star_rating_count'] ) ? intval( $location_meta['star_rating_count'] ) : 0 ),
        );
        if ( isset( $location_types ) && is_array( $location_types ) && count( $location_types ) > 0 ) {
            foreach ( $location_types as $term ) {
                $location['types'][] = (string) $term->term_taxonomy_id;
            }
        }
        // HOOK: modify location data before rendering to DOM (list view)
        // This allows developers to customize marker icons, add custom data, etc.
        $location = apply_filters( 'oum_location_data', $location, $post_id );
        $locations_list[] = $location;
    }
}
// Clean UTF-8 encoding for location data (Repair if needed)
$locations_list_clean = clean_utf8( $locations_list );
?>

<div class="open-user-map-locations-list">

  <div class="oum-locations-list-items">
    <?php 
foreach ( $locations_list_clean as $location ) {
    ?>

      <?php 
    if ( get_option( 'oum_enable_location_date' ) === 'on' ) {
        $date_tag = '<div class="oum_location_date">' . wp_kses_post( $location['date'] ) . '</div>';
    } else {
        $date_tag = '';
    }
    // Get and display assigned categories as icons inline with title
    $name_tag = '';
    if ( get_option( 'oum_enable_title', 'on' ) == 'on' ) {
        $title_wrapper_content = '';
        // Add the title
        $title_wrapper_content .= '<h3 class="oum_location_name">' . esc_attr( $location['title'] ) . '</h3>';
        // Add category icons after the title if setting is enabled
        if ( get_option( 'oum_enable_category_icons_in_title', 'on' ) === 'on' && isset( $location['post_id'] ) && $location['post_id'] ) {
            $category_icons = oum_get_location_value( 'type_icons', $location['post_id'] );
            if ( $category_icons ) {
                $title_wrapper_content .= $category_icons;
            }
        }
        $name_tag = '<div class="oum_location_title">' . $title_wrapper_content . '</div>';
    }
    $media_tag = '';
    if ( $location['image'] ) {
        // Split image URLs if multiple images exist
        $images = explode( '|', $location['image'] );
        if ( count( $images ) > 1 ) {
            // Multiple images - use carousel
            $media_tag = '<div class="oum-carousel">';
            $media_tag .= '<div class="oum-carousel-inner">';
            foreach ( $images as $index => $image_url ) {
                if ( !empty( $image_url ) ) {
                    // Convert relative path to absolute URL if needed
                    $absolute_image_url = ( strpos( $image_url, 'http' ) !== 0 ? site_url() . $image_url : $image_url );
                    $active_class = ( $index === 0 ? ' active' : '' );
                    $media_tag .= '<div class="oum-carousel-item' . $active_class . '">';
                    $media_tag .= '<img class="skip-lazy" src="' . esc_url_raw( $absolute_image_url ) . '" alt="' . esc_attr( $location['title'] ) . '">';
                    $media_tag .= '</div>';
                }
            }
            $media_tag .= '</div>';
            $media_tag .= '</div>';
        } else {
            // Single image - use regular image display
            // Convert relative path to absolute URL if needed
            $absolute_image_url = ( strpos( $location['image'], 'http' ) !== 0 ? site_url() . $location['image'] : $location['image'] );
            $media_tag = '<div class="oum_location_image"><img class="skip-lazy" src="' . esc_url_raw( $absolute_image_url ) . '"></div>';
        }
    }
    //HOOK: modify location image
    $media_tag = apply_filters( 'oum_location_bubble_image', $media_tag, $location );
    // Convert relative audio path to absolute URL if needed
    $audio_url = ( $location['audio'] && strpos( $location['audio'], 'http' ) !== 0 ? site_url() . $location['audio'] : $location['audio'] );
    $audio_tag = ( $audio_url ? '<audio controls="controls" style="width:100%"><source type="audio/mp4" src="' . esc_attr( $audio_url ) . '"><source type="audio/mpeg" src="' . esc_attr( $audio_url ) . '"><source type="audio/wav" src="' . esc_attr( $audio_url ) . '"></audio>' : '' );
    $address_tag = '';
    if ( get_option( 'oum_enable_address', 'on' ) === 'on' ) {
        $address_tag = ( $location['address'] && !get_option( 'oum_hide_address' ) ? esc_attr( $location['address'] ) : '' );
        if ( $oum_enable_gmaps_link === 'on' && $address_tag ) {
            $address_tag = '<a title="' . __( 'go to Google Maps', 'open-user-map' ) . '" href="https://www.google.com/maps/search/?api=1&amp;query=' . esc_attr( $location['lat'] ) . '%2C' . esc_attr( $location['lng'] ) . '" target="_blank">' . $address_tag . '</a>';
        }
    }
    $address_tag = ( $address_tag != '' ? '<div class="oum_location_address">' . $address_tag . '</div>' : '' );
    if ( get_option( 'oum_enable_description', 'on' ) === 'on' ) {
        $description_tag = '<div class="oum_location_description">' . wp_kses_post( $location['text'] ) . '</div>';
    } else {
        $description_tag = '';
    }
    $custom_fields = '';
    if ( isset( $location['custom_fields'] ) && is_array( $location['custom_fields'] ) ) {
        $custom_fields .= '<div class="oum_location_custom_fields">';
        foreach ( $location['custom_fields'] as $custom_field ) {
            if ( !$custom_field['val'] || $custom_field['val'] == '' ) {
                continue;
            }
            // Handle opening hours field type
            if ( $custom_field['fieldtype'] == 'opening_hours' ) {
                // Use centralized formatting method
                $custom_fields .= \OpenUserMapPlugin\Base\LocationController::format_opening_hours_for_display( $custom_field['val'], $custom_field, $this->plugin_url );
            } elseif ( is_array( $custom_field['val'] ) ) {
                array_walk( $custom_field['val'], function ( &$x ) {
                    $x = '<span data-value="' . $x . '">' . $x . '</span>';
                } );
                $custom_fields .= '<div data-custom-field-label="' . esc_attr( $custom_field['label'] ) . '" class="oum_custom_field oum_custom_field_type_' . esc_attr( $custom_field['fieldtype'] ) . '"><strong>' . $custom_field['label'] . ':</strong> ' . implode( '', $custom_field['val'] ) . '</div>';
            } else {
                if ( stristr( $custom_field['val'], '|' ) ) {
                    //multiple entries separated with | symbol
                    $custom_fields .= '<div data-custom-field-label="' . esc_attr( $custom_field['label'] ) . '" class="oum_custom_field oum_custom_field_type_' . esc_attr( $custom_field['fieldtype'] ) . '"><strong>' . $custom_field['label'] . ':</strong> ';
                    foreach ( explode( '|', $custom_field['val'] ) as $entry ) {
                        $entry = trim( $entry );
                        if ( wp_http_validate_url( $entry ) ) {
                            //URL
                            $custom_fields .= '<a target="_blank" href="' . $entry . '">' . $entry . '</a> ';
                        } elseif ( is_email( $entry ) && $custom_field['fieldtype'] == 'email' ) {
                            //Email
                            $custom_fields .= '<a target="_blank" href="mailto:' . $entry . '">' . $entry . '</a> ';
                        } else {
                            //Text
                            $custom_fields .= '<span data-value="' . $entry . '">' . $entry . '</span>';
                        }
                    }
                    $custom_fields .= '</div>';
                } else {
                    //single entry
                    if ( wp_http_validate_url( $custom_field['val'] ) ) {
                        //URL
                        if ( isset( $custom_field['uselabelastextoption'] ) && $custom_field['uselabelastextoption'] ) {
                            // Use label as link text
                            $custom_fields .= '<div data-custom-field-label="' . esc_attr( $custom_field['label'] ) . '" class="oum_custom_field oum_custom_field_type_' . esc_attr( $custom_field['fieldtype'] ) . '"><a target="_blank" href="' . $custom_field['val'] . '">' . $custom_field['label'] . '</a></div>';
                        } else {
                            // Show label and use URL as link text
                            $custom_fields .= '<div data-custom-field-label="' . esc_attr( $custom_field['label'] ) . '" class="oum_custom_field oum_custom_field_type_' . esc_attr( $custom_field['fieldtype'] ) . '"><strong>' . $custom_field['label'] . ':</strong> <a target="_blank" href="' . $custom_field['val'] . '">' . $custom_field['val'] . '</a></div>';
                        }
                    } elseif ( is_email( $custom_field['val'] ) && $custom_field['fieldtype'] == 'email' ) {
                        //Email
                        $custom_fields .= '<div data-custom-field-label="' . esc_attr( $custom_field['label'] ) . '" class="oum_custom_field oum_custom_field_type_' . esc_attr( $custom_field['fieldtype'] ) . '"><strong>' . $custom_field['label'] . ':</strong> <a target="_blank" href="mailto:' . $custom_field['val'] . '">' . $custom_field['val'] . '</a></div>';
                    } else {
                        //Text
                        $custom_fields .= '<div data-custom-field-label="' . esc_attr( $custom_field['label'] ) . '" class="oum_custom_field oum_custom_field_type_' . esc_attr( $custom_field['fieldtype'] ) . '"><strong>' . $custom_field['label'] . ':</strong> <span data-value="' . $custom_field['val'] . '">' . $custom_field['val'] . '</span></div>';
                    }
                }
            }
        }
        $custom_fields .= '</div>';
    }
    if ( get_option( 'oum_enable_single_page' ) ) {
        $link_tag = '<div class="oum_read_more"><a href="' . get_the_permalink( $location['post_id'] ) . '">' . __( 'Read more', 'open-user-map' ) . '</a></div>';
    } else {
        $link_tag = '';
    }
    // Add vote button or star rating if feature is enabled
    $vote_button = '';
    if ( get_option( 'oum_enable_vote_feature' ) === 'on' ) {
        // Get vote type setting (default: upvote)
        $vote_type = get_option( 'oum_vote_type', 'upvote' );
        if ( $vote_type === 'star_rating' ) {
            // Star rating type
            $star_rating_avg = ( isset( $location['star_rating_avg'] ) ? floatval( $location['star_rating_avg'] ) : 0 );
            $star_rating_count = ( isset( $location['star_rating_count'] ) ? intval( $location['star_rating_count'] ) : 0 );
            $vote_button = '<div class="oum_star_rating_wrap">';
            $vote_button .= '<div class="oum_star_rating" data-post-id="' . esc_attr( $location['post_id'] ) . '" data-average="' . esc_attr( $star_rating_avg ) . '" data-count="' . esc_attr( $star_rating_count ) . '">';
            $vote_button .= '<div class="oum_stars">';
            // Create 5 stars
            for ($i = 1; $i <= 5; $i++) {
                $vote_button .= '<span class="oum_star" data-rating="' . esc_attr( $i ) . '" aria-label="' . esc_attr( sprintf( __( 'Rate %d stars', 'open-user-map' ), $i ) ) . '">★</span>';
            }
            $vote_button .= '</div>';
            if ( $star_rating_count > 0 ) {
                $vote_button .= '<span class="oum_star_rating_count">(' . esc_html( $star_rating_count ) . ')</span>';
            } else {
                $vote_button .= '<span class="oum_star_rating_count" style="display: none;">(0)</span>';
            }
            $vote_button .= '</div>';
            $vote_button .= '</div>';
        } else {
            // Upvote type (default)
            $votes = ( isset( $location['votes'] ) ? intval( $location['votes'] ) : 0 );
            $vote_label = get_option( 'oum_vote_button_label', __( '👍', 'open-user-map' ) );
            // Handle empty values with fallbacks
            $display_vote_label = ( !empty( trim( $vote_label ) ) ? $vote_label : __( '👍', 'open-user-map' ) );
            $vote_button = '<div class="oum_vote_button_wrap">';
            $vote_button .= '<button class="oum_vote_button" data-post-id="' . esc_attr( $location['post_id'] ) . '" data-votes="' . esc_attr( $votes ) . '" data-label="' . esc_attr( $display_vote_label ) . '">';
            $vote_button .= '<span class="oum_vote_text">' . esc_html( $display_vote_label ) . '</span>';
            if ( $votes > 0 ) {
                $vote_button .= '<span class="oum_vote_count">' . esc_html( $votes ) . '</span>';
            }
            $vote_button .= '</button>';
            $vote_button .= '</div>';
        }
    }
    // building bubble block content
    $content = '<div class="oum_location_media">' . $media_tag . '</div>';
    $content .= '<div class="oum_location_text">';
    $content .= $date_tag;
    $content .= $address_tag;
    $content .= $name_tag;
    $content .= $custom_fields;
    $content .= $description_tag;
    $content .= $audio_tag;
    $content .= '<div class="oum_location_text_bottom">' . $vote_button . $link_tag . '</div>';
    $content .= '</div>';
    // removing backslash escape
    $content = str_replace( "\\", "", $content );
    //HOOK: modify location list item content
    $content = apply_filters( 'oum_location_list_item_content', $content, $location );
    // set location
    $oum_location = [
        'title'   => html_entity_decode( esc_attr( $location['title'] ) ),
        'lat'     => esc_attr( $location["lat"] ),
        'lng'     => esc_attr( $location["lng"] ),
        'content' => $content,
        'icon'    => esc_attr( $location["icon"] ),
        'types'   => ( isset( $location["types"] ) ? $location["types"] : [] ),
        'post_id' => esc_attr( $location["post_id"] ),
        'votes'   => ( isset( $location['votes'] ) ? intval( $location['votes'] ) : 0 ),
    ];
    ?>

      <div class="oum-locations-list-item">
        <?php 
    echo $oum_location['content'];
    ?>
      </div>

    <?php 
}
?>
  </div>

  <?php 
// Determine total pages (use filtered total if available)
$total_pages_for_pagination = ( $pagination_total_pages && $pagination_total_pages > 0 ? $pagination_total_pages : $locations_query->max_num_pages );
?>
  <?php 
if ( $total_pages_for_pagination > 1 ) {
    ?>
    <nav class="pagination oum-locations-list-pagination">
      <?php 
    echo paginate_links( array(
        'current'   => max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) ),
        'total'     => $total_pages_for_pagination,
        'prev_text' => __( '&laquo; Prev' ),
        'next_text' => __( 'Next &raquo;' ),
    ) );
    ?>
    </nav>
  <?php 
}
?>

  <?php 
wp_reset_postdata();
?>

</div>

<script>
// Initialize opening hours toggle functionality for list view
(function() {
  function initOpeningHours() {
    if (typeof OUMOpeningHours !== 'undefined' && typeof OUMOpeningHours.init === 'function') {
      OUMOpeningHours.init();
    } else {
      // Wait for script to load
      setTimeout(initOpeningHours, 100);
    }
  }
  
  // Start initialization when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOpeningHours);
  } else {
    initOpeningHours();
  }
})();
</script>