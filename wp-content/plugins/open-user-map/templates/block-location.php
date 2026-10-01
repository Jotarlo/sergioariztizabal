<?php 

/**
 * Shortcode Example: [open-user-map-location value="Favorite color" post_id="12345"(optional) ]
 * 
 */

$format = isset($block_attributes['format']) ? strtolower($block_attributes['format']) : 'value';

$post_id = isset($block_attributes['post_id']) ? $block_attributes['post_id'] : get_the_ID();
$requested_value = isset($block_attributes['value']) ? $block_attributes['value'] : '';

// When format="object" the shortcode should return JSON for custom data-attributes etc.
if ($format === 'object') {
    // Pick a single value if requested, otherwise expose the stored meta payload.
    if ($requested_value !== '') {
        $location_object = array(
            $requested_value => oum_get_location_value($requested_value, $post_id, true),
        );
    } else {
        $location_object = get_post_meta($post_id, '_oum_location_key', true);

        if (!is_array($location_object)) {
            $location_object = array();
        }

        // Provide some useful defaults that are stored outside of _oum_location_key.
        $location_object['title'] = get_the_title($post_id);
        $location_object['images'] = oum_get_location_value('images', $post_id, true);
        $location_object['audio'] = oum_get_location_value('audio', $post_id, true);
        $location_object['video'] = oum_get_location_value('video', $post_id, true);
        $location_object['marker_categories'] = oum_get_location_value('type', $post_id, true);
        $location_object['votes'] = oum_get_location_value('votes', $post_id, true);
        $location_object['star_rating_avg'] = oum_get_location_value('star_rating_avg', $post_id, true);
        $location_object['star_rating_count'] = oum_get_location_value('star_rating_count', $post_id, true);
        $location_object['wp_author_id'] = oum_get_location_value('wp_author_id', $post_id, true);
        $location_object['post_id'] = $post_id;

        // Rename "address" to "subtitle" if it exists
        if (isset($location_object['address'])) {
            $location_object['subtitle'] = $location_object['address'];
            unset($location_object['address']);
        }

        // Transform custom_fields from numeric IDs to readable labels
        if (isset($location_object['custom_fields']) && is_array($location_object['custom_fields'])) {
            $active_custom_fields = get_option('oum_custom_fields', array());
            $transformed_custom_fields = array();
            $meta_custom_fields = $location_object['custom_fields'];

            if (is_array($active_custom_fields) && is_array($meta_custom_fields)) {
                // Iterate over active_custom_fields to maintain order and get labels
                foreach ($active_custom_fields as $index => $custom_field) {
                    // Skip if field is marked as private
                    if (isset($custom_field['private']) && $custom_field['private']) {
                        continue;
                    }

                    // Skip if no value exists for this field
                    if (!isset($meta_custom_fields[$index])) {
                        continue;
                    }

                    // Use label as key instead of numeric ID
                    $label = isset($custom_field['label']) ? $custom_field['label'] : $index;
                    $transformed_custom_fields[$label] = $meta_custom_fields[$index];
                }
            }

            // Replace the numeric-keyed custom_fields with label-keyed version
            $location_object['custom_fields'] = $transformed_custom_fields;
        }
    }

    $encoded_location = wp_json_encode($location_object);

    if ($encoded_location === false) {
        return '';
    }

    echo esc_html($encoded_location);

    return;
}

if ($requested_value === '') {
    return null; // no value attribute for the default value format
}

$value = oum_get_location_value($requested_value, $post_id);

// Escape only plain-text user fields to prevent XSS. HTML-producing values (e.g. map, image) are output as-is.
$plain_text_attrs = array('notification', 'author_name', 'author_email', 'address', 'subtitle', 'title');
if (in_array($requested_value, $plain_text_attrs, true)) {
    $value_safe = esc_html($value);
} elseif ($requested_value === 'text') {
    $value_safe = wp_kses_post($value);
} else {
    $value_safe = $value;
}

?>

<div class="oum-location-value" data-value="<?php echo esc_attr($block_attributes['value']); ?>"><?php echo $value_safe; ?></div>

<?php
// Initialize opening hours toggle if opening hours are present
if (strpos($value, 'oum-opening-hours-header') !== false) {
?>
<script>
(function() {
  if (typeof OUMOpeningHours !== 'undefined' && typeof OUMOpeningHours.init === 'function') {
    OUMOpeningHours.init();
  } else {
    // Wait for script to load, then initialize
    (function check() {
      if (typeof OUMOpeningHours !== 'undefined' && typeof OUMOpeningHours.init === 'function') {
        OUMOpeningHours.init();
      } else {
        setTimeout(check, 100);
      }
    })();
  }
})();
</script>
<?php
}

// Initialize star ratings if star rating is present
if (strpos($value, 'oum_star_rating') !== false) {
?>
<script>
(function() {
  if (typeof window.OUMVoteHandler !== 'undefined' && typeof window.OUMVoteHandler.initializeStarRatings === 'function') {
    window.OUMVoteHandler.initializeStarRatings();
  } else {
    // Wait for script to load, then initialize
    (function check() {
      if (typeof window.OUMVoteHandler !== 'undefined' && typeof window.OUMVoteHandler.initializeStarRatings === 'function') {
        window.OUMVoteHandler.initializeStarRatings();
      } else {
        setTimeout(check, 100);
      }
    })();
  }
})();
</script>
<?php
}
?>