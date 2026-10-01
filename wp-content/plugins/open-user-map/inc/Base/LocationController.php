<?php

/**
 * @package OpenUserMapPlugin
 */
namespace OpenUserMapPlugin\Base;

use OpenUserMapPlugin\Base\BaseController;
class LocationController extends BaseController {
    public $settings;

    public function register() {
        // CPT: Location
        add_action( 'init', array($this, 'location_cpt') );
        add_action( 'admin_init', array($this, 'oum_capabilities') );
        add_action( 'add_meta_boxes', array($this, 'add_meta_box') );
        add_action( 'save_post', array($this, 'save_fields') );
        add_action( 'manage_oum-location_posts_columns', array($this, 'set_custom_location_columns') );
        add_action(
            'manage_oum-location_posts_custom_column',
            array($this, 'set_custom_location_columns_data'),
            10,
            2
        );
        // this method has 2 attributes
        add_filter( 'manage_oum-location_posts_sortable_columns', array($this, 'set_sortable_columns') );
        add_action( 'pre_get_posts', array($this, 'custom_search_oum_location') );
        add_action( 'admin_menu', array($this, 'add_pending_counter_to_menu') );
        add_filter(
            'post_thumbnail_html',
            array($this, 'default_location_header'),
            10,
            5
        );
        add_filter( 'the_content', array($this, 'default_location_content') );
        // AJAX: Check if current user can edit a specific location
        add_action( 'wp_ajax_oum_check_edit_permission', array($this, 'ajax_check_edit_permission') );
        add_action( 'wp_ajax_nopriv_oum_check_edit_permission', array($this, 'ajax_check_edit_permission') );
    }

    /**
     * CPT: Location
     */
    public static function location_cpt() {
        $labels = array(
            'name'               => __( 'Locations', 'open-user-map' ),
            'singular_name'      => __( 'Location', 'open-user-map' ),
            'add_new'            => __( 'Add new Location', 'open-user-map' ),
            'add_new_item'       => __( 'Add new Location', 'open-user-map' ),
            'edit_item'          => __( 'Edit Location', 'open-user-map' ),
            'new_item'           => __( 'New Location', 'open-user-map' ),
            'all_items'          => __( 'All Locations', 'open-user-map' ),
            'view_item'          => __( 'View Location', 'open-user-map' ),
            'search_items'       => __( 'Search Locations', 'open-user-map' ),
            'not_found'          => __( 'No Locations found', 'open-user-map' ),
            'not_found_in_trash' => __( 'No Location in trash', 'open-user-map' ),
            'parent_item_colon'  => '',
            'menu_name'          => __( 'Open User Map', 'open-user-map' ),
        );
        $args = array(
            'labels'              => $labels,
            'capability_type'     => 'oum-location',
            'map_meta_cap'        => true,
            'description'         => __( 'Location', 'open-user-map' ),
            'show_ui'             => true,
            'menu_position'       => 20,
            'menu_icon'           => 'dashicons-location-alt',
            'supports'            => array(
                'title',
                'author',
                'thumbnail',
                'excerpt',
                'revisions',
                'trash'
            ),
            'public'              => true,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_in_rest'        => true,
            'show_in_nav_menus'   => true,
            'has_archive'         => false,
            'rewrite'             => false,
        );
        register_post_type( 'oum-location', $args );
    }

    /**
     * Assign default capabilities to default user roles (same as 'post')
     */
    public function oum_capabilities() {
        // Administrator, Editor
        $roles = array('editor', 'administrator');
        foreach ( $roles as $the_role ) {
            $role = get_role( $the_role );
            if ( !is_null( $role ) ) {
                $role->add_cap( 'read_oum-location' );
                $role->add_cap( 'read_private_oum-locations' );
                $role->add_cap( 'edit_oum-location' );
                $role->add_cap( 'edit_oum-locations' );
                $role->add_cap( 'edit_others_oum-locations' );
                $role->add_cap( 'edit_published_oum-locations' );
                $role->add_cap( 'edit_private_oum-locations' );
                $role->add_cap( 'publish_oum-locations' );
                $role->add_cap( 'delete_oum-locations' );
                $role->add_cap( 'delete_others_oum-locations' );
                $role->add_cap( 'delete_private_oum-locations' );
                $role->add_cap( 'delete_published_oum-locations' );
            }
        }
        // Author
        $role = get_role( 'author' );
        if ( !is_null( $role ) ) {
            $role->add_cap( 'edit_oum-locations' );
            $role->add_cap( 'edit_published_oum-locations' );
            $role->add_cap( 'publish_oum-locations' );
            $role->add_cap( 'delete_oum-locations' );
            $role->add_cap( 'delete_published_oum-locations' );
        }
        // Contributor
        $role = get_role( 'contributor' );
        if ( !is_null( $role ) ) {
            $role->add_cap( 'edit_oum-locations' );
            $role->add_cap( 'delete_oum-locations' );
        }
        // Subscriber
        $role = get_role( 'subscriber' );
        if ( !is_null( $role ) ) {
            $role->add_cap( 'edit_oum-locations' );
            $role->add_cap( 'delete_oum-locations' );
        }
    }

    public function add_meta_box() {
        add_meta_box(
            'location_customfields',
            __( 'Open User Map Location Settings', 'open-user-map' ),
            array($this, 'render_customfields_box'),
            'oum-location',
            'normal',
            'high'
        );
    }

    public function render_customfields_box( $post ) {
        wp_nonce_field( 'oum_location', 'oum_location_nonce' );
        $data = get_post_meta( $post->ID, '_oum_location_key', true );
        //$this->safe_log(print_r($data, true));
        $address = ( isset( $data['address'] ) ? $data['address'] : '' );
        $lat = ( isset( $data['lat'] ) ? $data['lat'] : '' );
        $lng = ( isset( $data['lng'] ) ? $data['lng'] : '' );
        $zoom = ( isset( $data['zoom'] ) ? $data['zoom'] : '12' );
        $geometry_type = ( isset( $data['geometry_type'] ) && in_array( $data['geometry_type'], array('polyline', 'polygon'), true ) ? $data['geometry_type'] : 'point' );
        $geometry = ( isset( $data['geometry'] ) && is_array( $data['geometry'] ) ? wp_json_encode( $data['geometry'] ) : '' );
        $text = ( isset( $data['text'] ) ? $data['text'] : '' );
        $video = ( isset( $data['video'] ) ? $data['video'] : '' );
        $has_video = ( isset( $video ) && $video != '' ? 'has-video' : '' );
        $video_tag = ( $has_video ? apply_filters( 'the_content', esc_attr( $video ) ) : '' );
        $votes = ( isset( $data['votes'] ) ? intval( $data['votes'] ) : 0 );
        $star_rating_avg = ( isset( $data['star_rating_avg'] ) ? floatval( $data['star_rating_avg'] ) : 0 );
        $star_rating_count = ( isset( $data['star_rating_count'] ) ? intval( $data['star_rating_count'] ) : 0 );
        $show_vote_settings = get_option( 'oum_enable_vote_feature' ) === 'on';
        $image = get_post_meta( $post->ID, '_oum_location_image', true );
        // Convert relative paths to absolute URLs for preview
        if ( $image ) {
            $image_urls = explode( '|', $image );
            $absolute_urls = array();
            foreach ( $image_urls as $url ) {
                if ( !empty( $url ) ) {
                    // Convert relative path to absolute URL if needed
                    if ( strpos( $url, 'http' ) !== 0 ) {
                        // Get the site path from site_url
                        $site_path = parse_url( site_url(), PHP_URL_PATH );
                        // Check if the URL already starts with the site path
                        if ( $site_path && strpos( $url, $site_path ) === 0 ) {
                            // URL already has the site path, remove it to avoid duplication
                            $url = substr( $url, strlen( $site_path ) );
                        }
                        $absolute_urls[] = site_url() . $url;
                    } else {
                        $absolute_urls[] = $url;
                    }
                }
            }
            $image = implode( '|', $absolute_urls );
        }
        $audio = get_post_meta( $post->ID, '_oum_location_audio', true );
        $has_audio = ( isset( $audio ) && $audio != '' ? 'has-audio' : '' );
        // Convert relative audio path to absolute URL if needed
        if ( $audio && strpos( $audio, 'http' ) !== 0 ) {
            // Get the site path from site_url
            $site_path = parse_url( site_url(), PHP_URL_PATH );
            // Check if the URL already starts with the site path
            if ( $site_path && strpos( $audio, $site_path ) === 0 ) {
                // URL already has the site path, remove it to avoid duplication
                $audio = substr( $audio, strlen( $site_path ) );
            }
            $audio_url = site_url() . $audio;
            $audio_tag = ( $has_audio ? '<audio controls="controls" style="width:100%"><source type="audio/mp4" src="' . esc_attr( $audio_url ) . '"><source type="audio/mpeg" src="' . esc_attr( $audio_url ) . '"><source type="audio/wav" src="' . esc_attr( $audio_url ) . '"></audio>' : '' );
            $audio = $audio_url;
            // Update audio variable with the absolute URL
        } else {
            $audio_tag = ( $has_audio ? '<audio controls="controls" style="width:100%"><source type="audio/mp4" src="' . esc_attr( $audio ) . '"><source type="audio/mpeg" src="' . esc_attr( $audio ) . '"><source type="audio/wav" src="' . esc_attr( $audio ) . '"></audio>' : '' );
        }
        $notification = ( isset( $data['notification'] ) ? $data['notification'] : '' );
        $author_name = ( isset( $data['author_name'] ) ? $data['author_name'] : '' );
        $author_email = ( isset( $data['author_email'] ) ? $data['author_email'] : '' );
        $text_notify_me_on_publish_label = ( get_option( 'oum_user_notification_label' ) ? get_option( 'oum_user_notification_label' ) : $this->oum_get_default_label( 'user_notification' ) );
        $text_notify_me_on_publish_name = __( 'Your name', 'open-user-map' );
        $text_notify_me_on_publish_email = __( 'Your email', 'open-user-map' );
        $notified = get_post_meta( $post->ID, '_oum_location_notified', true );
        $notified_tag = ( isset( $notified ) && $notified != '' ? '<p>User has been notified on ' . date( "Y-m-d H:i:s", $notified ) . '</p>' : '' );
        // Set map style
        $map_style = ( get_option( 'oum_map_style' ) ? get_option( 'oum_map_style' ) : $this->get_default_map_style() );
        $oum_tile_provider_mapbox_key = get_option( 'oum_tile_provider_mapbox_key', '' );
        $oum_tile_provider_carto_key = get_option( 'oum_tile_provider_carto_key', '' );
        $marker_icon = ( get_option( 'oum_marker_icon' ) ? get_option( 'oum_marker_icon' ) : 'default' );
        $marker_user_icon = get_option( 'oum_marker_user_icon' );
        $meta_custom_fields = ( isset( $data['custom_fields'] ) ? $data['custom_fields'] : false );
        $active_custom_fields = get_option( 'oum_custom_fields' );
        // render view
        require_once oum_get_template( 'page-backend-location.php' );
    }

    /**
     * Sanitize a submitted GeoJSON LineString or Polygon geometry.
     *
     * @param string|array $geometry_json Raw JSON string or decoded geometry.
     * @return array|false Sanitized GeoJSON geometry, or false when invalid.
     */
    public static function sanitize_location_geometry( $geometry_json ) {
        $geometry = ( is_array( $geometry_json ) ? $geometry_json : json_decode( (string) $geometry_json, true ) );
        if ( !is_array( $geometry ) || !isset( $geometry['type'], $geometry['coordinates'] ) || !is_array( $geometry['coordinates'] ) ) {
            return false;
        }
        $geometry_type = $geometry['type'];
        if ( $geometry_type !== 'LineString' && $geometry_type !== 'Polygon' ) {
            return false;
        }
        $raw_coordinates = ( $geometry_type === 'Polygon' ? ( isset( $geometry['coordinates'][0] ) && is_array( $geometry['coordinates'][0] ) ? $geometry['coordinates'][0] : array() ) : $geometry['coordinates'] );
        $coordinates = array();
        $max_points = (int) apply_filters( 'oum_polyline_max_points', 500 );
        foreach ( $raw_coordinates as $coordinate ) {
            if ( !is_array( $coordinate ) || count( $coordinate ) < 2 ) {
                return false;
            }
            $lng = (float) str_replace( ',', '.', (string) $coordinate[0] );
            $lat = (float) str_replace( ',', '.', (string) $coordinate[1] );
            if ( !is_finite( $lat ) || !is_finite( $lng ) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) {
                return false;
            }
            $coordinates[] = array($lng, $lat);
            if ( count( $coordinates ) > $max_points ) {
                return false;
            }
        }
        if ( count( $coordinates ) < 2 ) {
            return false;
        }
        if ( $geometry_type === 'Polygon' ) {
            if ( count( $coordinates ) < 3 ) {
                return false;
            }
            // GeoJSON polygons should be closed. Accept imports that omit the
            // closing coordinate by closing the outer ring server-side.
            if ( $coordinates[0] !== $coordinates[count( $coordinates ) - 1] ) {
                $coordinates[] = $coordinates[0];
            }
            return array(
                'type'        => 'Polygon',
                'coordinates' => array($coordinates),
            );
        }
        return array(
            'type'        => 'LineString',
            'coordinates' => $coordinates,
        );
    }

    /**
     * Derive a stable center point from a sanitized LineString.
     *
     * @param array $geometry Sanitized GeoJSON LineString.
     * @return array{lat: float, lng: float}|false
     */
    public static function get_location_geometry_center( $geometry ) {
        if ( !is_array( $geometry ) || empty( $geometry['coordinates'] ) || !is_array( $geometry['coordinates'] ) ) {
            return false;
        }
        $min_lat = 90;
        $max_lat = -90;
        $min_lng = 180;
        $max_lng = -180;
        $coordinates = ( isset( $geometry['type'] ) && $geometry['type'] === 'Polygon' && isset( $geometry['coordinates'][0] ) && is_array( $geometry['coordinates'][0] ) ? $geometry['coordinates'][0] : $geometry['coordinates'] );
        foreach ( $coordinates as $coordinate ) {
            if ( !is_array( $coordinate ) || count( $coordinate ) < 2 ) {
                return false;
            }
            $lng = (float) $coordinate[0];
            $lat = (float) $coordinate[1];
            $min_lat = min( $min_lat, $lat );
            $max_lat = max( $max_lat, $lat );
            $min_lng = min( $min_lng, $lng );
            $max_lng = max( $max_lng, $lng );
        }
        return array(
            'lat' => ($min_lat + $max_lat) / 2,
            'lng' => ($min_lng + $max_lng) / 2,
        );
    }

    /**
     * Allowed location image file extensions.
     *
     * @return array
     */
    public static function get_allowed_location_image_extensions() {
        return array(
            'jpeg',
            'jpg',
            'png',
            'webp',
            'gif'
        );
    }

    /**
     * True when a value contains characters that must never appear in a stored image URL.
     *
     * Quotes, angle brackets, whitespace and control characters can break out of HTML attributes.
     *
     * @param string $value
     * @return bool
     */
    public static function location_image_url_has_unsafe_characters( $value ) {
        if ( $value === '' ) {
            return false;
        }
        if ( preg_match( '/["\'<>\\\\\\s]|[\\x00-\\x1F\\x7F]/', $value ) ) {
            return true;
        }
        $decoded = rawurldecode( $value );
        if ( $decoded !== $value && preg_match( '/["\'<>\\\\\\s]|[\\x00-\\x1F\\x7F]/', $decoded ) ) {
            return true;
        }
        return false;
    }

    /**
     * Normalize a location image value to a comparable same-site path or absolute URL.
     *
     * @param string $url
     * @return string Empty string when the value cannot be used as an image URL.
     */
    public static function normalize_location_image_url( $url ) {
        $url = trim( (string) $url );
        if ( $url === '' || self::location_image_url_has_unsafe_characters( $url ) ) {
            return '';
        }
        $is_absolute = (bool) filter_var( $url, FILTER_VALIDATE_URL );
        $path = $url;
        if ( $is_absolute ) {
            $parts = wp_parse_url( $url );
            if ( !is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['path'] ) ) {
                return '';
            }
            $scheme = strtolower( $parts['scheme'] );
            if ( !in_array( $scheme, array('http', 'https'), true ) ) {
                return '';
            }
            $host = ( isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '' );
            $path = $parts['path'];
            $site_host = strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) );
            $site_path = wp_parse_url( site_url(), PHP_URL_PATH );
            if ( self::location_image_url_has_unsafe_characters( $path ) || strpos( $path, '..' ) !== false ) {
                return '';
            }
            // Same-site absolute URLs are compared as paths so frontend pathnames match stored values.
            if ( $host !== '' && $host === $site_host ) {
                if ( $site_path && strpos( $path, $site_path ) === 0 ) {
                    $path = substr( $path, strlen( $site_path ) );
                }
                if ( $path === '' || $path[0] !== '/' ) {
                    $path = '/' . ltrim( $path, '/' );
                }
                return $path;
            }
            return esc_url_raw( $url );
        }
        $site_path = wp_parse_url( site_url(), PHP_URL_PATH );
        if ( $site_path && strpos( $path, $site_path ) === 0 ) {
            $path = substr( $path, strlen( $site_path ) );
        }
        if ( $path === '' || $path[0] !== '/' || strpos( $path, '..' ) !== false || self::location_image_url_has_unsafe_characters( $path ) ) {
            return '';
        }
        return $path;
    }

    /**
     * Sanitize a location image URL before it is stored.
     *
     * @param string $url
     * @return string Sanitized relative path or URL, or empty string when invalid.
     */
    public static function sanitize_location_image_url( $url ) {
        $normalized = self::normalize_location_image_url( $url );
        if ( $normalized === '' ) {
            return '';
        }
        $path_for_ext = $normalized;
        if ( filter_var( $normalized, FILTER_VALIDATE_URL ) ) {
            $path_for_ext = (string) wp_parse_url( $normalized, PHP_URL_PATH );
        }
        $ext = strtolower( pathinfo( $path_for_ext, PATHINFO_EXTENSION ) );
        if ( !in_array( $ext, self::get_allowed_location_image_extensions(), true ) ) {
            return '';
        }
        if ( filter_var( $normalized, FILTER_VALIDATE_URL ) ) {
            return esc_url_raw( $normalized );
        }
        return $normalized;
    }

    /**
     * Compare two image values, treating WordPress resized filenames as the same image.
     *
     * @param string $url_a
     * @param string $url_b
     * @return bool
     */
    public static function location_image_urls_match( $url_a, $url_b ) {
        $a = self::normalize_location_image_url( $url_a );
        $b = self::normalize_location_image_url( $url_b );
        if ( $a === '' || $b === '' ) {
            return false;
        }
        if ( $a === $b ) {
            return true;
        }
        $path_a = ( filter_var( $a, FILTER_VALIDATE_URL ) ? (string) wp_parse_url( $a, PHP_URL_PATH ) : $a );
        $path_b = ( filter_var( $b, FILTER_VALIDATE_URL ) ? (string) wp_parse_url( $b, PHP_URL_PATH ) : $b );
        $strip_size = function ( $filename ) {
            return preg_replace( '/-(?:scaled|rotated|[0-9]+x[0-9]+)$/', '', $filename );
        };
        return dirname( $path_a ) === dirname( $path_b ) && $strip_size( pathinfo( $path_a, PATHINFO_FILENAME ) ) === $strip_size( pathinfo( $path_b, PATHINFO_FILENAME ) ) && strtolower( pathinfo( $path_a, PATHINFO_EXTENSION ) ) === strtolower( pathinfo( $path_b, PATHINFO_EXTENSION ) );
    }

    /**
     * Sanitized image URLs already stored for a location.
     *
     * @param int $post_id
     * @return array
     */
    public static function get_allowed_existing_location_images( $post_id ) {
        $allowed = array();
        $stored = get_post_meta( $post_id, '_oum_location_image', true );
        if ( !is_string( $stored ) || $stored === '' ) {
            return $allowed;
        }
        foreach ( explode( '|', $stored ) as $image_url ) {
            $sanitized = self::sanitize_location_image_url( $image_url );
            if ( $sanitized !== '' ) {
                $allowed[] = $sanitized;
            }
        }
        return $allowed;
    }

    /**
     * Resolve a submitted existing image to the stored URL for that location.
     *
     * @param string $identifier
     * @param array  $allowed_images
     * @return string
     */
    public static function resolve_existing_location_image( $identifier, $allowed_images ) {
        if ( !is_array( $allowed_images ) || empty( $allowed_images ) ) {
            return '';
        }
        foreach ( $allowed_images as $stored_url ) {
            if ( self::location_image_urls_match( $identifier, $stored_url ) ) {
                return $stored_url;
            }
        }
        return '';
    }

    /**
     * Save Location Fields (Backend)
     */
    public static function save_fields( $post_id, $fields = array() ) {
        $location_data = $_REQUEST;
        // Set data source ($_REQUEST or $fields)
        if ( !empty( $fields ) ) {
            $location_data = $fields;
            $location_data['post_type'] = 'oum-location';
        }
        // Dont save if not a location
        if ( !isset( $location_data['post_type'] ) || $location_data['post_type'] != 'oum-location' ) {
            return $post_id;
        }
        // Dont save if wordpress just auto-saves
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }
        // WordPress creates revisions during admin saves. Updating meta for a
        // revision redirects to the parent post and can overwrite location data.
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || get_post_type( $post_id ) !== 'oum-location' ) {
            return $post_id;
        }
        // Dont save if user is not allowed to do
        $has_general_permission = current_user_can( 'edit_oum-locations' );
        $is_author = get_current_user_id() == get_post_field( 'post_author', $post_id );
        $can_edit_specific_post = current_user_can( 'edit_post', $post_id );
        $allow_edit = ( $has_general_permission && ($is_author || $can_edit_specific_post) ? true : false );
        if ( !$allow_edit ) {
            return $post_id;
        }
        // Check if this is a Quick Edit operation
        $is_quick_edit = isset( $location_data['action'] ) && in_array( $location_data['action'], array('edit', 'inline-save') );
        // For regular save operations, verify nonce
        if ( !$is_quick_edit ) {
            if ( !isset( $location_data['oum_location_nonce'] ) ) {
                return $post_id;
            }
            $nonce = $location_data['oum_location_nonce'];
            if ( !wp_verify_nonce( $nonce, 'oum_location' ) ) {
                return $post_id;
            }
        }
        // Handle image uploads and updates (for regular save)
        if ( isset( $location_data['oum_location_image'] ) ) {
            $images = explode( '|', $location_data['oum_location_image'] );
            // Validate image URLs and convert to relative paths
            $valid_images = array();
            foreach ( $images as $image_url ) {
                $sanitized_image = self::sanitize_location_image_url( $image_url );
                if ( $sanitized_image !== '' && !in_array( $sanitized_image, $valid_images, true ) ) {
                    $valid_images[] = $sanitized_image;
                }
            }
            // Store images as pipe-separated string
            update_post_meta( $post_id, '_oum_location_image', implode( '|', $valid_images ) );
            // Set first image as featured image (needs full URL)
            if ( !empty( $valid_images[0] ) ) {
                if ( strpos( $valid_images[0], '/wp-content' ) === 0 ) {
                    // Convert relative path to full URL for featured image
                    self::set_featured_image( $post_id, site_url() . $valid_images[0] );
                } else {
                    // Use as is if it's already a full URL
                    self::set_featured_image( $post_id, $valid_images[0] );
                }
            }
        } elseif ( $is_quick_edit ) {
            $old_images = get_post_meta( $post_id, '_oum_location_image', true );
            if ( !empty( $old_images ) ) {
                $images = explode( '|', $old_images );
                if ( !empty( $images[0] ) ) {
                    if ( strpos( $images[0], '/wp-content' ) === 0 ) {
                        // Convert relative path to full URL for featured image
                        self::set_featured_image( $post_id, site_url() . $images[0] );
                    } else {
                        // Use as is if it's already a full URL
                        self::set_featured_image( $post_id, $images[0] );
                    }
                }
            }
        }
        // Quick Edit does not write location text, so set the excerpt from
        // the description that is already stored for this post.
        if ( $is_quick_edit ) {
            if ( get_the_excerpt( $post_id ) == '' ) {
                self::set_excerpt( $post_id );
            }
            return $post_id;
        }
        // Continue with regular save operation
        $lat_validated = ( isset( $location_data['oum_location_lat'] ) ? floatval( str_replace( ',', '.', sanitize_text_field( $location_data['oum_location_lat'] ) ) ) : '' );
        $lng_validated = ( isset( $location_data['oum_location_lng'] ) ? floatval( str_replace( ',', '.', sanitize_text_field( $location_data['oum_location_lng'] ) ) ) : '' );
        $zoom_validated = ( isset( $location_data['oum_location_zoom'] ) ? intval( sanitize_text_field( $location_data['oum_location_zoom'] ) ) : 12 );
        // Get existing location data before geometry validation so invalid
        // imports/updates cannot accidentally downgrade stored lines or areas.
        $existing_data = get_post_meta( $post_id, '_oum_location_key', true );
        if ( !is_array( $existing_data ) ) {
            $existing_data = array();
        }
        $submitted_geometry_type = ( isset( $location_data['oum_geometry_type'] ) ? sanitize_key( $location_data['oum_geometry_type'] ) : 'point' );
        $geometry_type = ( self::oum_location_type_enabled( $submitted_geometry_type ) ? $submitted_geometry_type : self::oum_default_location_type() );
        $geometry = false;
        if ( $submitted_geometry_type !== 'point' && $geometry_type !== $submitted_geometry_type && isset( $existing_data['geometry_type'], $existing_data['geometry'] ) && $existing_data['geometry_type'] === $submitted_geometry_type && is_array( $existing_data['geometry'] ) ) {
            $geometry_type = $submitted_geometry_type;
            $geometry = $existing_data['geometry'];
        }
        if ( $geometry_type !== 'point' ) {
            if ( !$geometry ) {
                $geometry = ( isset( $location_data['oum_location_geometry'] ) ? self::sanitize_location_geometry( wp_unslash( $location_data['oum_location_geometry'] ) ) : false );
            }
            if ( $geometry && ($geometry_type === 'polygon') === ($geometry['type'] === 'Polygon') ) {
                $center = self::get_location_geometry_center( $geometry );
                if ( $center ) {
                    $lat_validated = $center['lat'];
                    $lng_validated = $center['lng'];
                }
            } else {
                $existing_geometry_type = ( isset( $existing_data['geometry_type'] ) && in_array( $existing_data['geometry_type'], array('polyline', 'polygon'), true ) ? $existing_data['geometry_type'] : 'point' );
                if ( $submitted_geometry_type === $existing_geometry_type && isset( $existing_data['geometry'] ) && is_array( $existing_data['geometry'] ) ) {
                    // Preserve existing geometry when an update/import submits
                    // invalid geometry for the same non-point type.
                    $geometry_type = $existing_geometry_type;
                    $geometry = $existing_data['geometry'];
                } else {
                    // Invalid new backend geometry falls back to the established point behavior.
                    $geometry_type = 'point';
                }
            }
        }
        $data = array(
            'address'       => ( isset( $location_data['oum_location_address'] ) ? sanitize_text_field( $location_data['oum_location_address'] ) : '' ),
            'lat'           => $lat_validated,
            'lng'           => $lng_validated,
            'zoom'          => $zoom_validated,
            'text'          => ( isset( $location_data['oum_location_text'] ) ? wp_kses_post( $location_data['oum_location_text'] ) : '' ),
            'author_name'   => ( isset( $location_data['oum_location_author_name'] ) ? sanitize_text_field( $location_data['oum_location_author_name'] ) : '' ),
            'author_email'  => ( isset( $location_data['oum_location_author_email'] ) ? sanitize_text_field( $location_data['oum_location_author_email'] ) : '' ),
            'video'         => ( isset( $location_data['oum_location_video'] ) ? sanitize_text_field( $location_data['oum_location_video'] ) : '' ),
            'geometry_type' => $geometry_type,
        );
        if ( $geometry_type !== 'point' && $geometry ) {
            $data['geometry'] = $geometry;
        }
        // Handle votes - use imported value if provided, otherwise preserve existing
        if ( isset( $location_data['oum_location_votes'] ) && $location_data['oum_location_votes'] !== '' ) {
            $data['votes'] = intval( $location_data['oum_location_votes'] );
        } elseif ( isset( $existing_data['votes'] ) ) {
            $data['votes'] = $existing_data['votes'];
        }
        // Handle star rating - use imported values if provided, otherwise preserve existing
        if ( isset( $location_data['oum_location_star_rating_avg'] ) && $location_data['oum_location_star_rating_avg'] !== '' ) {
            $data['star_rating_avg'] = floatval( $location_data['oum_location_star_rating_avg'] );
        } elseif ( isset( $existing_data['star_rating_avg'] ) ) {
            $data['star_rating_avg'] = $existing_data['star_rating_avg'];
        }
        if ( isset( $location_data['oum_location_star_rating_count'] ) && $location_data['oum_location_star_rating_count'] !== '' ) {
            $data['star_rating_count'] = intval( $location_data['oum_location_star_rating_count'] );
        } elseif ( isset( $existing_data['star_rating_count'] ) ) {
            $data['star_rating_count'] = $existing_data['star_rating_count'];
        }
        if ( isset( $location_data['oum_location_notification'] ) ) {
            $data['notification'] = sanitize_text_field( $location_data['oum_location_notification'] );
        }
        if ( isset( $location_data['oum_location_custom_fields'] ) && is_array( $location_data['oum_location_custom_fields'] ) ) {
            $available_custom_fields = get_option( 'oum_custom_fields', array() );
            $processed_custom_fields = array();
            foreach ( $location_data['oum_location_custom_fields'] as $index => $val ) {
                $fieldtype = ( isset( $available_custom_fields[$index]['fieldtype'] ) ? $available_custom_fields[$index]['fieldtype'] : 'text' );
                // Check if this is an opening hours field
                if ( $fieldtype === 'opening_hours' ) {
                    // Handle array format [hours] or string format
                    $hours_input = '';
                    if ( is_array( $val ) ) {
                        $hours_input = ( isset( $val['hours'] ) ? sanitize_text_field( $val['hours'] ) : '' );
                    } else {
                        $hours_input = sanitize_text_field( $val );
                    }
                    if ( $hours_input !== '' ) {
                        // Convert input format to JSON (using WordPress timezone)
                        $parsed = self::convert_opening_hours_input_to_json( $hours_input );
                        if ( $parsed ) {
                            $processed_custom_fields[$index] = json_encode( $parsed );
                        } else {
                            // Invalid format, skip or store as-is
                            $processed_custom_fields[$index] = '';
                        }
                    } else {
                        $processed_custom_fields[$index] = '';
                    }
                } elseif ( is_array( $val ) ) {
                    // Regular array field (like checkbox)
                    $processed_custom_fields[$index] = $val;
                } else {
                    // Regular single value or already processed JSON
                    $processed_custom_fields[$index] = $val;
                }
            }
            $data['custom_fields'] = $processed_custom_fields;
        }
        update_post_meta( $post_id, '_oum_location_key', $data );
        if ( isset( $location_data['oum_location_audio'] ) ) {
            // validate & store audio seperately (to avoid serialized URLs [bad for search & replace due to domain change])
            $audio_url = esc_url_raw( $location_data['oum_location_audio'] );
            // Convert absolute URLs to relative paths
            $data_audio = str_replace( site_url(), '', $audio_url );
            update_post_meta( $post_id, '_oum_location_audio', $data_audio );
        }
        // Set excerpt after the description is saved so first-time admin
        // saves and CSV imports get an excerpt on the first write.
        // CSV import already writes the excerpt on insert to avoid a second wp_update_post.
        if ( !(defined( 'OUM_DOING_CSV_IMPORT' ) && OUM_DOING_CSV_IMPORT) && get_the_excerpt( $post_id ) == '' ) {
            self::set_excerpt( $post_id );
        }
    }

    /**
     * Helper function to set the featured image
     */
    public static function set_featured_image( $post_id, $image_url ) {
        // Get current featured image filename
        $current_thumbnail_id = get_post_thumbnail_id( $post_id );
        if ( $current_thumbnail_id ) {
            // Get the original filename from attachment metadata
            $current_thumbnail_meta = wp_get_attachment_metadata( $current_thumbnail_id );
            if ( isset( $current_thumbnail_meta['original_image'] ) ) {
                $current_thumbnail_filename = pathinfo( $current_thumbnail_meta['original_image'], PATHINFO_FILENAME );
            } else {
                $current_thumbnail_filename = pathinfo( $current_thumbnail_meta['file'], PATHINFO_FILENAME );
            }
            // Remove any suffixes
            $current_thumbnail_filename = preg_replace( '/-(?:scaled|[0-9]+x[0-9]+|[0-9]+)$/', '', $current_thumbnail_filename );
        }
        // Get new image filename, handling both relative and absolute paths
        $new_image_filename = '';
        if ( strpos( $image_url, '/wp-content' ) === 0 || strpos( $image_url, '/uploads' ) === 0 ) {
            // Relative path
            $new_image_filename = pathinfo( $image_url, PATHINFO_FILENAME );
        } else {
            // Absolute path or external URL
            $new_image_filename = pathinfo( parse_url( $image_url, PHP_URL_PATH ), PATHINFO_FILENAME );
        }
        // Remove size suffixes like -scaled, -1024x587, -1, etc.
        $base_image_filename = preg_replace( '/-(?:scaled|[0-9]+x[0-9]+|[0-9]+)$/', '', $new_image_filename );
        // Compare base filenames
        if ( empty( $current_thumbnail_id ) || $base_image_filename !== $current_thumbnail_filename ) {
            // Convert relative URL to absolute URL if needed
            $absolute_url = ( strpos( $image_url, 'http' ) !== 0 ? site_url() . $image_url : $image_url );
            global $wpdb;
            $attachment_id = false;
            // Get uploads dir
            $upload_dir = wp_upload_dir();
            $file_path = false;
            if ( strpos( $absolute_url, $upload_dir['baseurl'] ) === 0 ) {
                $file_path = ltrim( str_replace( $upload_dir['baseurl'], '', $absolute_url ), '/' );
            } else {
                if ( strpos( $image_url, '/wp-content/uploads/' ) === 0 ) {
                    $file_path = ltrim( str_replace( '/wp-content/uploads/', '', $image_url ), '/' );
                }
            }
            $posts_table = $wpdb->posts;
            $postmeta_table = $wpdb->postmeta;
            // --- 1. Prefer exact _wp_attached_file match (image attachments) ---
            if ( $file_path ) {
                $query = $wpdb->prepare( "SELECT p.ID FROM {$posts_table} p INNER JOIN {$postmeta_table} pm ON p.ID = pm.post_id WHERE pm.meta_key = '_wp_attached_file' AND pm.meta_value = %s AND p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%' LIMIT 1", $file_path );
                $attachment_id = $wpdb->get_var( $query );
            }
            // --- 2. Fallback: Search for attachment by base filename (image attachments) ---
            if ( !$attachment_id && $base_image_filename ) {
                $like_pattern = '%' . $wpdb->esc_like( $base_image_filename ) . '%';
                $query = $wpdb->prepare( "SELECT p.ID, pm.meta_value FROM {$posts_table} p INNER JOIN {$postmeta_table} pm ON p.ID = pm.post_id WHERE pm.meta_key = '_wp_attached_file' AND pm.meta_value LIKE %s AND p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'", $like_pattern );
                $results = $wpdb->get_results( $query );
                if ( $results ) {
                    // Try to find the best match: prefer the original file (no size suffix)
                    foreach ( $results as $row ) {
                        $filename = pathinfo( $row->meta_value, PATHINFO_FILENAME );
                        $filename_base = preg_replace( '/-(?:scaled|[0-9]+x[0-9]+|[0-9]+)$/', '', $filename );
                        if ( $filename_base === $base_image_filename ) {
                            $attachment_id = $row->ID;
                            break;
                        }
                    }
                    // If no perfect match, just use the first found
                    if ( !$attachment_id ) {
                        $attachment_id = $results[0]->ID;
                    }
                }
            }
            // --- 3. Fallback: search by guid (full URL, image attachments) ---
            if ( !$attachment_id ) {
                $query = $wpdb->prepare( "SELECT ID FROM {$posts_table} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' AND guid = %s LIMIT 1", $absolute_url );
                $attachment_id = $wpdb->get_var( $query );
            }
            if ( $attachment_id ) {
                // Attachment found, set as featured image
                set_post_thumbnail( $post_id, $attachment_id );
            } else {
                // Not found, sideload image as before
                $upload = media_sideload_image(
                    $absolute_url,
                    $post_id,
                    null,
                    'src'
                );
                if ( !is_wp_error( $upload ) ) {
                    $attachment_id = attachment_url_to_postid( $upload );
                    if ( $attachment_id ) {
                        set_post_thumbnail( $post_id, $attachment_id );
                    }
                }
            }
        }
    }

    /**
     * Helper function to set the excerpt (if not set)
     */
    public static function set_excerpt( $post_id ) {
        $max_length = 400;
        $post_text = oum_get_location_value( 'text', $post_id, true );
        $text = wp_strip_all_tags( $post_text );
        if ( $text ) {
            if ( strlen( $text ) > $max_length ) {
                $text = substr( $text, 0, $max_length );
                $last_space = strrpos( $text, ' ' );
                if ( $last_space !== false ) {
                    $text = substr( $text, 0, $last_space );
                }
                $text .= '...';
            }
            $post = array(
                'ID'           => $post_id,
                'post_excerpt' => sanitize_text_field( $text ),
            );
            wp_update_post( $post );
        }
    }

    public function set_custom_location_columns( $columns ) {
        // Get all default columns we want to preserve
        $cb = ( isset( $columns['cb'] ) ? $columns['cb'] : '' );
        $title = ( isset( $columns['title'] ) ? $columns['title'] : '' );
        $author = ( isset( $columns['author'] ) ? $columns['author'] : '' );
        $categories = ( isset( $columns['taxonomy-oum-type'] ) ? $columns['taxonomy-oum-type'] : '' );
        $comments = ( isset( $columns['comments'] ) ? $columns['comments'] : '' );
        $date = ( isset( $columns['date'] ) ? $columns['date'] : '' );
        // Remove all columns
        $columns = array();
        // Add columns in desired order
        if ( $cb ) {
            $columns['cb'] = $cb;
        }
        $columns['post_id'] = 'ID';
        $columns['title'] = $title;
        $columns['address'] = __( 'Subtitle', 'open-user-map' );
        if ( $categories ) {
            $columns['taxonomy-oum-type'] = $categories;
        }
        $columns['text'] = __( 'Text', 'open-user-map' );
        $columns['geocoordinates'] = __( 'Coordinates', 'open-user-map' );
        // Add votes/star rating column only if vote feature is enabled
        if ( get_option( 'oum_enable_vote_feature' ) === 'on' ) {
            // Get vote type setting (default: upvote)
            $vote_type = get_option( 'oum_vote_type', 'upvote' );
            if ( $vote_type === 'star_rating' ) {
                $columns['votes'] = __( 'Star Rating', 'open-user-map' );
            } else {
                $columns['votes'] = __( 'Votes', 'open-user-map' );
            }
        }
        if ( $comments ) {
            $columns['comments'] = $comments;
        }
        if ( $author ) {
            $columns['author'] = $author;
        }
        if ( $date ) {
            $columns['date'] = $date;
        }
        return $columns;
    }

    public function set_custom_location_columns_data( $column, $post_id ) {
        $data = get_post_meta( $post_id, '_oum_location_key', true );
        $text = ( isset( $data['text'] ) ? $data['text'] : '' );
        $address = ( isset( $data['address'] ) ? $data['address'] : '' );
        $lat = ( isset( $data['lat'] ) ? $data['lat'] : '' );
        $lng = ( isset( $data['lng'] ) ? $data['lng'] : '' );
        $votes = ( isset( $data['votes'] ) ? intval( $data['votes'] ) : 0 );
        // Get star rating data
        $star_rating_avg = ( isset( $data['star_rating_avg'] ) ? floatval( $data['star_rating_avg'] ) : 0 );
        $star_rating_count = ( isset( $data['star_rating_count'] ) ? intval( $data['star_rating_count'] ) : 0 );
        switch ( $column ) {
            case 'post_id':
                echo esc_html( $post_id );
                break;
            case 'text':
                echo esc_html( $text );
                break;
            case 'address':
                echo esc_html( $address );
                break;
            case 'geocoordinates':
                echo esc_attr( $lat ) . ', ' . esc_attr( $lng );
                break;
            case 'votes':
                // Get vote type setting (default: upvote)
                $vote_type = get_option( 'oum_vote_type', 'upvote' );
                if ( $vote_type === 'star_rating' ) {
                    // Display star rating: show rounded average and count
                    $rounded_avg = round( $star_rating_avg );
                    if ( $star_rating_count > 0 ) {
                        // Display stars (★) for the rounded average
                        $stars = str_repeat( '★', $rounded_avg );
                        $empty_stars = str_repeat( '☆', 5 - $rounded_avg );
                        echo esc_html( $stars . $empty_stars . ' (' . $star_rating_count . ')' );
                    } else {
                        echo esc_html( '☆☆☆☆☆ (0)' );
                    }
                } else {
                    // Display upvote count
                    echo esc_attr( $votes );
                }
                break;
            default:
                break;
        }
    }

    /**
     * Set sortable columns for the admin list
     */
    public function set_sortable_columns( $columns ) {
        // Add votes column as sortable only if vote feature is enabled
        if ( get_option( 'oum_enable_vote_feature' ) === 'on' ) {
            $columns['votes'] = 'votes';
        }
        return $columns;
    }

    /**
     * Custom search for locations (including meta and author)
     */
    public function custom_search_oum_location( $query ) {
        // Ensure we're in the WordPress admin, it's a search query, and the right post type
        if ( $query->is_search() && is_admin() && $query->is_main_query() && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'oum-location' ) {
            // Get the search term
            $search_term = $query->query_vars['s'];
            // Clear the default search query
            $query->set( 's', '' );
            // Join wp_users and wp_postmeta tables for author and meta field searches
            add_filter( 'posts_join', function ( $join ) {
                global $wpdb;
                // Join wp_users for author search
                if ( strpos( $join, "{$wpdb->users}" ) === false ) {
                    $join .= " LEFT JOIN {$wpdb->users} AS u ON {$wpdb->posts}.post_author = u.ID ";
                }
                // Join wp_postmeta for meta field search
                if ( strpos( $join, "{$wpdb->postmeta}" ) === false ) {
                    $join .= " LEFT JOIN {$wpdb->postmeta} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id ";
                }
                return $join;
            } );
            // Modify the search query to include user_login, user_email, post_title, and post_content search
            add_filter(
                'posts_search',
                function ( $search, $query ) use($search_term) {
                    global $wpdb;
                    if ( $search_term ) {
                        $like_term = '%' . $wpdb->esc_like( $search_term ) . '%';
                        // Combine search conditions for post title, content, and author fields.
                        // Use prepared placeholders to avoid SQL injection via search input.
                        $search .= $wpdb->prepare(
                            " AND ({$wpdb->posts}.post_title LIKE %s\n                                    OR {$wpdb->posts}.post_content LIKE %s\n                                    OR u.user_login LIKE %s\n                                    OR u.user_email LIKE %s) ",
                            $like_term,
                            $like_term,
                            $like_term,
                            $like_term
                        );
                    }
                    return $search;
                },
                10,
                2
            );
            // Modify the WHERE clause to include the meta query for '_oum_location_key'
            add_filter( 'posts_where', function ( $where ) use($search_term) {
                global $wpdb;
                // Search in the _oum_location_key meta field
                $escaped_meta_value = '%' . $wpdb->esc_like( $search_term ) . '%';
                $where .= $wpdb->prepare( " OR ({$wpdb->postmeta}.meta_key = '_oum_location_key' AND {$wpdb->postmeta}.meta_value LIKE %s)", $escaped_meta_value );
                return $where;
            } );
            // Group results by post ID to avoid duplicates from multiple meta entries
            add_filter( 'posts_groupby', function ( $groupby ) {
                global $wpdb;
                if ( !$groupby ) {
                    $groupby = "{$wpdb->posts}.ID";
                    // Group by post ID to ensure unique results
                }
                return $groupby;
            } );
        }
    }

    public function add_pending_counter_to_menu() {
        global $menu;
        $count = count( get_posts( array(
            'post_type'      => 'oum-location',
            'post_status'    => 'pending',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) ) );
        $menu_item = wp_list_filter( $menu, array(
            2 => 'edit.php?post_type=oum-location',
        ) );
        if ( !empty( $menu_item ) && $count >= 1 ) {
            $menu_item_position = key( $menu_item );
            // get the array key (position) of the element
            $menu[$menu_item_position][0] .= ' <span class="awaiting-mod">' . $count . '</span>';
        }
    }

    /**
     * Get a value from a location
     */
    public function get_location_value( $attr, $post_id, $raw = false ) {
        $location = get_post_meta( $post_id, '_oum_location_key', true );
        // Early return if no valid location data
        if ( !is_array( $location ) ) {
            return '';
        }
        $custom_field_ids = get_option( 'oum_custom_fields', array() );
        // get all available custom fields
        $types = get_terms( array(
            'taxonomy'   => 'oum-type',
            'hide_empty' => false,
        ) );
        // get all available types
        $value = '';
        // Normalize attribute: "subtitle" now represents the legacy "address" field.
        if ( is_string( $attr ) ) {
            if ( strtolower( $attr ) === 'subtitle' ) {
                $attr = 'address';
            }
        }
        if ( $attr == 'title' ) {
            // GET TITLE
            $value = get_the_title( $post_id );
        } elseif ( $attr == 'image' || $attr == 'images' ) {
            // GET IMAGES
            $image = get_post_meta( $post_id, '_oum_location_image', true );
            $has_image = ( isset( $image ) && $image != '' ? 'has-image' : '' );
            if ( $has_image ) {
                $images = explode( '|', $image );
                if ( count( $images ) > 1 && !$raw ) {
                    // Enqueue carousel script and styles
                    // Enqueue frontend CSS with custom inline CSS
                    $this->enqueue_frontend_css();
                    wp_enqueue_script(
                        'oum_frontend_carousel_js',
                        plugin_dir_url( dirname( dirname( __FILE__ ) ) ) . 'src/js/frontend-carousel.js',
                        array(),
                        $this->plugin_version,
                        array(
                            'strategy'  => 'defer',
                            'in_footer' => true,
                        )
                    );
                    // Multiple images - use carousel
                    $value = '<div class="oum-carousel">';
                    $value .= '<div class="oum-carousel-inner">';
                    foreach ( $images as $index => $image_url ) {
                        if ( !empty( $image_url ) ) {
                            // Convert relative path to absolute URL if needed
                            $absolute_image_url = ( strpos( $image_url, 'http' ) !== 0 ? site_url() . $image_url : $image_url );
                            $active_class = ( $index === 0 ? ' active' : '' );
                            $value .= '<div class="oum-carousel-item' . $active_class . '">';
                            $value .= '<img class="skip-lazy" src="' . esc_url_raw( $absolute_image_url ) . '">';
                            $value .= '</div>';
                        }
                    }
                    $value .= '</div>';
                    $value .= '</div>';
                } else {
                    // Single image or raw output
                    if ( !$raw ) {
                        // Convert relative path to absolute URL if needed
                        $absolute_image_url = ( strpos( $images[0], 'http' ) !== 0 ? site_url() . $images[0] : $images[0] );
                        $value = '<img src="' . esc_attr( $absolute_image_url ) . '">';
                    } else {
                        // For raw output (like CSV export), ensure all URLs are relative
                        $relative_urls = array();
                        foreach ( $images as $url ) {
                            if ( !empty( $url ) ) {
                                // If it's an absolute URL from this site, convert to relative
                                if ( strpos( $url, 'http' ) === 0 ) {
                                    // Convert absolute URL to relative path
                                    $site_url = site_url();
                                    if ( strpos( $url, $site_url ) === 0 ) {
                                        $url = str_replace( $site_url, '', $url );
                                    }
                                }
                                $relative_urls[] = $url;
                            }
                        }
                        $value = implode( '|', $relative_urls );
                    }
                }
            } else {
                $value = '';
            }
        } elseif ( $attr == 'audio' ) {
            // GET AUDIO
            $audio = get_post_meta( $post_id, '_oum_location_audio', true );
            $has_audio = ( isset( $audio ) && $audio != '' ? 'has-audio' : '' );
            if ( !$raw ) {
                // Convert relative path to absolute URL if needed for display
                $audio_url = ( isset( $audio ) && $audio != '' && strpos( $audio, 'http' ) !== 0 ? site_url() . $audio : $audio );
                $value = ( $has_audio ? '<audio controls="controls" style="width:100%"><source type="audio/mp4" src="' . esc_attr( $audio_url ) . '"><source type="audio/mpeg" src="' . esc_attr( $audio_url ) . '"><source type="audio/wav" src="' . esc_attr( $audio_url ) . '"></audio>' : '' );
            } else {
                // For raw output (like CSV export), ensure the URL is relative
                if ( $has_audio ) {
                    // If it's an absolute URL from this site, convert to relative
                    if ( strpos( $audio, 'http' ) === 0 ) {
                        // Convert absolute URL to relative path
                        $site_url = site_url();
                        if ( strpos( $audio, $site_url ) === 0 ) {
                            $audio = str_replace( $site_url, '', $audio );
                        }
                    }
                    $value = esc_attr( $audio );
                } else {
                    $value = '';
                }
            }
        } elseif ( $attr == 'video' ) {
            // GET VIDEO
            $video = $location['video'];
            $has_video = ( isset( $video ) && $video != '' ? 'has-video' : '' );
            $video_tag = ( $has_video ? apply_filters( 'the_content', esc_attr( $video ) ) : '' );
            if ( !$raw ) {
                $value = ( $has_video ? $video_tag : '' );
            } else {
                $value = ( $has_video ? esc_attr( $video ) : '' );
            }
        } elseif ( $attr == 'votes' ) {
            // Get vote type setting (default: upvote)
            $vote_type = get_option( 'oum_vote_type', 'upvote' );
            $votes = ( isset( $location['votes'] ) ? intval( $location['votes'] ) : 0 );
            if ( $vote_type === 'star_rating' ) {
                // GET STAR RATING
                $star_rating_avg = ( isset( $location['star_rating_avg'] ) ? floatval( $location['star_rating_avg'] ) : 0 );
                $star_rating_count = ( isset( $location['star_rating_count'] ) ? intval( $location['star_rating_count'] ) : 0 );
                if ( !$raw ) {
                    // Render star rating HTML (same as in popup and list views)
                    $rounded_avg = round( $star_rating_avg );
                    $value = '<div class="oum_star_rating_wrap">';
                    $value .= '<div class="oum_star_rating" data-post-id="' . esc_attr( $post_id ) . '" data-average="' . esc_attr( $star_rating_avg ) . '" data-count="' . esc_attr( $star_rating_count ) . '">';
                    $value .= '<div class="oum_stars">';
                    // Create 5 stars
                    for ($i = 1; $i <= 5; $i++) {
                        $value .= '<span class="oum_star" data-rating="' . esc_attr( $i ) . '" aria-label="' . esc_attr( sprintf( __( 'Rate %d stars', 'open-user-map' ), $i ) ) . '">★</span>';
                    }
                    $value .= '</div>';
                    if ( $star_rating_count > 0 ) {
                        $value .= '<span class="oum_star_rating_count">(' . esc_html( $star_rating_count ) . ')</span>';
                    } else {
                        $value .= '<span class="oum_star_rating_count" style="display: none;">(0)</span>';
                    }
                    $value .= '</div>';
                    $value .= '</div>';
                } else {
                    // Raw output should always return the stored DB value for votes.
                    // This keeps exports/imports consistent and avoids synthesized values.
                    $value = $votes;
                }
            } else {
                // GET VOTES (upvote type)
                $value = $votes;
            }
        } elseif ( $attr == 'star_rating_avg' ) {
            // GET STAR RATING AVERAGE
            $star_rating_avg = ( isset( $location['star_rating_avg'] ) ? floatval( $location['star_rating_avg'] ) : 0 );
            $value = $star_rating_avg;
        } elseif ( $attr == 'star_rating_count' ) {
            // GET STAR RATING COUNT
            $star_rating_count = ( isset( $location['star_rating_count'] ) ? intval( $location['star_rating_count'] ) : 0 );
            $value = $star_rating_count;
        } elseif ( $attr == 'geometry_type' ) {
            // GET GEOMETRY TYPE
            $value = ( isset( $location['geometry_type'] ) && in_array( $location['geometry_type'], array('polyline', 'polygon'), true ) ? $location['geometry_type'] : 'point' );
        } elseif ( $attr == 'geometry' ) {
            // GET GEOJSON GEOMETRY
            $value = ( isset( $location['geometry'] ) && is_array( $location['geometry'] ) ? wp_json_encode( $location['geometry'] ) : '' );
        } elseif ( $attr == 'type' ) {
            // GET TYPE
            $location_types = ( get_the_terms( $post_id, 'oum-type' ) && !is_wp_error( get_the_terms( $post_id, 'oum-type' ) ) ? get_the_terms( $post_id, 'oum-type' ) : false );
            if ( isset( $location_types ) && is_array( $location_types ) && count( $location_types ) == 1 && !get_option( 'oum_enable_multiple_marker_types' ) ) {
                $value = $location_types[0]->name;
            } else {
                $value = '';
                if ( isset( $location_types ) && is_array( $location_types ) ) {
                    $value = implode( '|', wp_list_pluck( $location_types, 'name' ) );
                }
            }
        } elseif ( $attr == 'type_icons' ) {
            // GET TYPE ICONS
            $location_types = ( get_the_terms( $post_id, 'oum-type' ) && !is_wp_error( get_the_terms( $post_id, 'oum-type' ) ) ? get_the_terms( $post_id, 'oum-type' ) : false );
            if ( isset( $location_types ) && is_array( $location_types ) && !empty( $location_types ) ) {
                $plugin_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
                $plugin_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) );
                $route_icon_path = $plugin_path . 'assets/images/ico_route.svg';
                $area_icon_path = $plugin_path . 'assets/images/ico_area.svg';
                $route_icon_svg = ( is_readable( $route_icon_path ) ? file_get_contents( $route_icon_path ) : '' );
                $area_icon_svg = ( is_readable( $area_icon_path ) ? file_get_contents( $area_icon_path ) : '' );
                $category_icons_content = '';
                foreach ( $location_types as $category ) {
                    $category_type = self::oum_marker_category_type( $category->term_id );
                    $category_color = sanitize_hex_color( get_term_meta( $category->term_id, 'oum_marker_color', true ) );
                    if ( !$category_color ) {
                        $category_color = ( get_option( 'oum_ui_color' ) ? get_option( 'oum_ui_color' ) : '#e82c71' );
                    }
                    if ( $category_type === 'polyline' ) {
                        $colored_route_icon_svg = str_replace( 'currentColor', esc_attr( $category_color ), $route_icon_svg );
                        $category_icons_content .= '<span class="oum_category_icon oum_category_shape_icon" title="' . esc_attr( $category->name ) . '">' . $colored_route_icon_svg . '</span>';
                        continue;
                    }
                    if ( $category_type === 'polygon' ) {
                        $colored_area_icon_svg = str_replace( 'currentColor', esc_attr( $category_color ), $area_icon_svg );
                        $category_icons_content .= '<span class="oum_category_icon oum_category_shape_icon" title="' . esc_attr( $category->name ) . '">' . $colored_area_icon_svg . '</span>';
                        continue;
                    }
                    // Get category icon
                    $cat_icon = get_term_meta( $category->term_id, 'oum_marker_icon', true );
                    $cat_user_icon = get_term_meta( $category->term_id, 'oum_marker_user_icon', true );
                    // Determine icon URL
                    if ( $cat_icon == 'user1' && $cat_user_icon ) {
                        $icon_url = esc_url( $cat_user_icon );
                    } elseif ( $cat_icon ) {
                        $icon_url = esc_url( $plugin_url ) . 'src/leaflet/images/marker-icon_' . esc_attr( $cat_icon ) . '-2x.png';
                    } else {
                        // Use default marker icon from settings
                        $marker_icon = ( get_option( 'oum_marker_icon' ) ? get_option( 'oum_marker_icon' ) : 'default' );
                        $marker_user_icon = get_option( 'oum_marker_user_icon' );
                        if ( $marker_icon == 'user1' && $marker_user_icon ) {
                            $icon_url = esc_url( $marker_user_icon );
                        } else {
                            $icon_url = esc_url( $plugin_url ) . 'src/leaflet/images/marker-icon_' . esc_attr( $marker_icon ) . '-2x.png';
                        }
                    }
                    $category_icons_content .= '<img class="oum_category_icon" src="' . $icon_url . '" alt="' . esc_attr( $category->name ) . '" title="' . esc_attr( $category->name ) . '">';
                }
                $value = '<div class="oum_location_category_icons">' . $category_icons_content . '</div>';
            } else {
                $value = '';
            }
        } elseif ( $attr == 'map' ) {
            // GET MAP
            $plugin_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
            $map_style = ( get_option( 'oum_map_style' ) ? get_option( 'oum_map_style' ) : $this->get_default_map_style() );
            $oum_tile_provider_mapbox_key = get_option( 'oum_tile_provider_mapbox_key', '' );
            $oum_tile_provider_carto_key = get_option( 'oum_tile_provider_carto_key', '' );
            $lat = $location['lat'];
            $lng = $location['lng'];
            $zoom = ( isset( $location['zoom'] ) ? $location['zoom'] : '12' );
            $geometry_type = ( isset( $location['geometry_type'] ) && in_array( $location['geometry_type'], array('polyline', 'polygon'), true ) ? $location['geometry_type'] : 'point' );
            $geometry = ( isset( $location['geometry'] ) && is_array( $location['geometry'] ) ? wp_json_encode( $location['geometry'] ) : '' );
            $category_color = ( get_option( 'oum_ui_color' ) ? get_option( 'oum_ui_color' ) : $this->oum_ui_color_default );
            // Get location types
            $location_types = ( get_the_terms( $post_id, 'oum-type' ) && !is_wp_error( get_the_terms( $post_id, 'oum-type' ) ) ? get_the_terms( $post_id, 'oum-type' ) : false );
            if ( $geometry_type !== 'point' && isset( $location_types ) && is_array( $location_types ) ) {
                foreach ( $location_types as $type ) {
                    if ( self::oum_marker_category_type( $type->term_id ) !== $geometry_type ) {
                        continue;
                    }
                    $type_color = sanitize_hex_color( get_term_meta( $type->term_id, 'oum_marker_color', true ) );
                    if ( $type_color ) {
                        $category_color = $type_color;
                    }
                    break;
                }
            }
            // Determine marker icon based on location type or settings
            if ( isset( $location_types ) && is_array( $location_types ) && count( $location_types ) == 1 && !get_option( 'oum_enable_multiple_marker_types' ) ) {
                $type = $location_types[0];
                if ( $type->term_id && get_term_meta( $type->term_id, 'oum_marker_icon', true ) ) {
                    // Get marker icon from location type
                    $marker_icon = get_term_meta( $type->term_id, 'oum_marker_icon', true );
                    $marker_user_icon = get_term_meta( $type->term_id, 'oum_marker_user_icon', true );
                } else {
                    // Get marker icon from settings
                    $marker_icon = ( get_option( 'oum_marker_icon' ) ? get_option( 'oum_marker_icon' ) : 'default' );
                    $marker_user_icon = get_option( 'oum_marker_user_icon' );
                }
            } else {
                // Get marker icon from settings
                $marker_icon = ( get_option( 'oum_marker_icon' ) ? get_option( 'oum_marker_icon' ) : 'default' );
                $marker_user_icon = get_option( 'oum_marker_user_icon' );
            }
            // Set marker icon URL
            $marker_icon_url = ( $marker_icon == 'user1' && $marker_user_icon ? esc_url( $marker_user_icon ) : esc_url( $plugin_url ) . 'src/leaflet/images/marker-icon_' . esc_attr( $marker_icon ) . '-2x.png' );
            $marker_shadow_url = esc_url( $plugin_url ) . 'src/leaflet/images/marker-shadow.png';
            $value = '<div id="mapRenderLocation" data-lat="' . esc_attr( $lat ) . '" data-lng="' . esc_attr( $lng ) . '" data-zoom="' . esc_attr( $zoom ) . '" data-mapstyle="' . esc_attr( $map_style ) . '" data-tile_provider_mapbox_key="' . esc_attr( $oum_tile_provider_mapbox_key ) . '" data-tile_provider_carto_key="' . esc_attr( $oum_tile_provider_carto_key ) . '" data-marker_icon_url="' . esc_url( $marker_icon_url ) . '" data-marker_shadow_url="' . esc_url( $marker_shadow_url ) . '" data-geometry-type="' . esc_attr( $geometry_type ) . '" data-geometry="' . esc_attr( $geometry ) . '" data-category-color="' . esc_attr( $category_color ) . '" class="open-user-map-location-map leaflet-map map-style_' . esc_attr( $map_style ) . '"' . $this->get_tile_provider_data_attribute( $map_style, 'container' ) . '></div>';
        } elseif ( $attr == 'route' ) {
            // GET GOOGLE ROUTE LINK
            $lat = esc_attr( $location['lat'] );
            $lng = esc_attr( $location['lng'] );
            $text = ( $location['address'] ? $location['address'] : __( 'Route on Google Maps', 'open-user-map' ) );
            $value = '<a title="' . __( 'go to Google Maps', 'open-user-map' ) . '" href="https://www.google.com/maps/search/?api=1&amp;query=' . $lat . '%2C' . $lng . '" target="_blank">' . $text . '</a>';
        } elseif ( $attr == 'wp_author_id' ) {
            // GET AUTHOR ID
            $value = get_post_field( 'post_author', $post_id );
        } elseif ( isset( $location[$attr] ) ) {
            // GET DEFAULT FIELD
            $value = $location[$attr];
        } else {
            // GET CUSTOM FIELD
            foreach ( $custom_field_ids as $custom_field_id => $custom_field ) {
                if ( strtolower( $custom_field['label'] ) == strtolower( $attr ) && isset( $location['custom_fields'][$custom_field_id] ) ) {
                    $value = $location['custom_fields'][$custom_field_id];
                    // Special handling for opening hours field type
                    if ( !$raw && isset( $custom_field['fieldtype'] ) && $custom_field['fieldtype'] === 'opening_hours' ) {
                        $value = self::format_opening_hours_for_display( $value, $custom_field, $this->plugin_url );
                    }
                    break;
                }
            }
        }
        if ( !$raw ) {
            //change array to list
            if ( is_array( $value ) ) {
                $value = implode( ', ', $value );
            }
        }
        return $value;
    }

    // Add a custom header to the location single page
    public function default_location_header(
        $featured_image_html,
        $post_id,
        $post_thumbnail_id,
        $size,
        $attr
    ) {
        if ( is_singular( 'oum-location' ) && in_the_loop() && is_main_query() ) {
            $location = get_post_meta( $post_id, '_oum_location_key', true );
            if ( isset( $location['video'] ) && $location['video'] != '' ) {
                $featured_image_html = '<div class="open-user-map-single-default-template-media has-video">' . apply_filters( 'the_content', esc_attr( $location['video'] ) ) . '</div>';
            } else {
                $featured_image_html = '<div class="open-user-map-single-default-template-media">' . $featured_image_html . '</div>';
            }
        }
        return $featured_image_html;
    }

    // Add custom content to the location single page
    public function default_location_content( $content ) {
        // Check if we're inside the main loop in a single Post of type 'custom_post_type'.
        if ( is_singular( 'oum-location' ) && in_the_loop() && is_main_query() ) {
            // Check if the content is empty
            if ( empty( trim( $content ) ) ) {
                // Custom content to display if the original content is empty
                $custom_content = '
                <!-- wp:group {"className":"open-user-map-single-default-template","layout":{"type":"default"}} -->
                <div class="wp-block-group open-user-map-single-default-template">
                
                    <!-- wp:columns -->
                    <div class="wp-block-columns">
                    
                        <!-- wp:column {"width":"66.66%"} -->
                        <div class="wp-block-column" style="flex-basis:66.66%">

                            <!-- wp:shortcode -->
                            [open-user-map-location value="image"]
                            <!-- /wp:shortcode -->

                            <!-- wp:shortcode -->
                            [open-user-map-location value="text"]
                            <!-- /wp:shortcode -->
                        
                        </div>

                        <!-- /wp:column -->

                        <!-- wp:column {"width":"33.33%"} -->
                        <div class="wp-block-column" style="flex-basis:33.33%">

                            <!-- wp:shortcode -->
                            [open-user-map-location value="map"]
                            <!-- /wp:shortcode -->

                            <!-- wp:shortcode -->
                            [open-user-map-location value="route"]
                            <!-- /wp:shortcode -->

                            <!-- wp:shortcode -->
                            [open-user-map-location value="type"]
                            <!-- /wp:shortcode -->

                        </div>
                        <!-- /wp:column -->
                    </div>
                    <!-- /wp:columns -->
                </div>
                <!-- /wp:group -->
                ';
                // Apply filter to allow users to override the custom content
                $filtered_content = apply_filters( 'oum_default_location_content', $custom_content, get_the_ID() );
                // Return filtered content if not empty, otherwise return original content
                return ( !empty( trim( $filtered_content ) ) ? $filtered_content : $content );
            }
        }
        // Return the original content if it's not empty or if the conditions are not met
        return $content;
    }

    /**
     * Get WordPress timezone as DateTimeZone object
     * @return \DateTimeZone - WordPress timezone or UTC as fallback
     */
    private static function get_wordpress_timezone() {
        $timezone_string = get_option( 'timezone_string' );
        if ( $timezone_string ) {
            return new \DateTimeZone($timezone_string);
        } else {
            // Fallback: use GMT offset
            $gmt_offset = get_option( 'gmt_offset' );
            if ( $gmt_offset !== false ) {
                $offset_hours = intval( $gmt_offset );
                $inverted_offset = -$offset_hours;
                // Invert for Etc/GMT
                return new \DateTimeZone('Etc/GMT' . (( $inverted_offset >= 0 ? '+' : '' )) . $inverted_offset);
            } else {
                return new \DateTimeZone('UTC');
            }
        }
    }

    /**
     * Calculate if location is currently open based on opening hours
     * @param array $opening_hours_data - Decoded opening hours JSON data
     * @return bool - True if currently open, false otherwise
     */
    public static function calculate_open_now( $opening_hours_data ) {
        if ( !$opening_hours_data || !is_array( $opening_hours_data ) || !isset( $opening_hours_data['week'] ) ) {
            return false;
        }
        try {
            $timezone = self::get_wordpress_timezone();
            $now = new \DateTime('now', $timezone);
            $current_day = strtolower( $now->format( 'D' ) );
            $current_time = $now->format( 'H:i' );
            // Map day abbreviation to key
            $day_map = array(
                'mon' => 'mo',
                'tue' => 'tu',
                'wed' => 'we',
                'thu' => 'th',
                'fri' => 'fr',
                'sat' => 'sa',
                'sun' => 'su',
            );
            if ( isset( $day_map[$current_day] ) ) {
                $day_key = $day_map[$current_day];
                if ( isset( $opening_hours_data['week'][$day_key] ) && is_array( $opening_hours_data['week'][$day_key] ) ) {
                    foreach ( $opening_hours_data['week'][$day_key] as $block ) {
                        if ( isset( $block['start'] ) && isset( $block['end'] ) ) {
                            if ( $current_time >= $block['start'] && $current_time <= $block['end'] ) {
                                return true;
                            }
                        }
                    }
                }
            }
        } catch ( \Exception $e ) {
            // Timezone error, return false
        }
        return false;
    }

    /**
     * Convert 24-hour format time to 12-hour format
     * @param string $time24 - Time in 24-hour format (HH:MM)
     * @return string - Time in 12-hour format (H:MM AM/PM)
     */
    private static function convert24To12( $time24 ) {
        if ( !preg_match( '/^(\\d{2}):(\\d{2})$/', $time24, $matches ) ) {
            return $time24;
            // Return as-is if invalid format
        }
        $hour = intval( $matches[1] );
        $minute = $matches[2];
        $period = 'AM';
        if ( $hour == 0 ) {
            $hour = 12;
            // 00:00 = 12:00 AM
        } elseif ( $hour == 12 ) {
            $period = 'PM';
            // 12:00 = 12:00 PM
        } elseif ( $hour > 12 ) {
            $hour -= 12;
            $period = 'PM';
        }
        return $hour . ':' . $minute . ' ' . $period;
    }

    /**
     * Canonical English weekday abbreviations used internally and in CSV export.
     *
     * @return array<string,string> Day key => English abbreviation (Mo, Tu, ...)
     */
    public static function get_opening_hours_english_day_abbrs() {
        return array(
            'mo' => 'Mo',
            'tu' => 'Tu',
            'we' => 'We',
            'th' => 'Th',
            'fr' => 'Fr',
            'sa' => 'Sa',
            'su' => 'Su',
        );
    }

    /**
     * WordPress weekday index for each internal day key (0 = Sunday).
     *
     * @return array<string,int>
     */
    private static function get_opening_hours_day_indexes() {
        return array(
            'mo' => 1,
            'tu' => 2,
            'we' => 3,
            'th' => 4,
            'fr' => 5,
            'sa' => 6,
            'su' => 0,
        );
    }

    /**
     * Translated weekday display names for the current WordPress locale.
     *
     * @return array<string,string> Day key => translated full name (e.g. Monday / Montag)
     */
    public static function get_opening_hours_day_names() {
        global $wp_locale;
        $fallback = array(
            'mo' => 'Monday',
            'tu' => 'Tuesday',
            'we' => 'Wednesday',
            'th' => 'Thursday',
            'fr' => 'Friday',
            'sa' => 'Saturday',
            'su' => 'Sunday',
        );
        $names = array();
        foreach ( self::get_opening_hours_day_indexes() as $key => $index ) {
            if ( $wp_locale instanceof \WP_Locale ) {
                $names[$key] = $wp_locale->get_weekday( $index );
            } else {
                $names[$key] = $fallback[$key];
            }
        }
        return $names;
    }

    /**
     * Normalize a weekday alias for matching (trim, strip trailing dots, lowercase, remove accents).
     *
     * @param string $alias
     * @return string
     */
    public static function normalize_opening_hours_day_alias( $alias ) {
        $alias = rtrim( trim( (string) $alias ), '.' );
        if ( function_exists( 'remove_accents' ) ) {
            $alias = remove_accents( $alias );
        }
        if ( function_exists( 'mb_strtolower' ) ) {
            return mb_strtolower( $alias, 'UTF-8' );
        }
        return strtolower( $alias );
    }

    /**
     * Build a short weekday alias from a translated weekday name (first two letters).
     *
     * @param string $weekday_name
     * @return string Normalized alias (e.g. "do" from "Donnerstag")
     */
    private static function opening_hours_abbr_from_name( $weekday_name ) {
        // Normalize first so accented names stay 2 ASCII letters (e.g. Sábado → sa)
        $normalized = self::normalize_opening_hours_day_alias( $weekday_name );
        if ( $normalized === '' ) {
            return '';
        }
        if ( function_exists( 'mb_substr' ) ) {
            return mb_substr(
                $normalized,
                0,
                2,
                'UTF-8'
            );
        }
        return substr( $normalized, 0, 2 );
    }

    /**
     * Format a normalized alias for display in the input field (e.g. "do" → "Do").
     *
     * @param string $alias
     * @return string
     */
    private static function format_opening_hours_day_abbr_for_display( $alias ) {
        $alias = self::normalize_opening_hours_day_alias( $alias );
        if ( $alias === '' ) {
            return '';
        }
        if ( function_exists( 'mb_strtoupper' ) && function_exists( 'mb_substr' ) && function_exists( 'mb_strlen' ) ) {
            $first = mb_strtoupper( mb_substr(
                $alias,
                0,
                1,
                'UTF-8'
            ), 'UTF-8' );
            $rest_length = mb_strlen( $alias, 'UTF-8' ) - 1;
            $rest = ( $rest_length > 0 ? mb_substr(
                $alias,
                1,
                $rest_length,
                'UTF-8'
            ) : '' );
            return $first . $rest;
        }
        return ucfirst( $alias );
    }

    /**
     * Add a weekday alias to the map without overwriting an existing (usually English) mapping.
     *
     * @param array<string,string> $map
     * @param string $alias
     * @param string $day_key
     * @return void
     */
    private static function add_opening_hours_day_alias( &$map, $alias, $day_key ) {
        $normalized = self::normalize_opening_hours_day_alias( $alias );
        if ( $normalized === '' || isset( $map[$normalized] ) ) {
            return;
        }
        $map[$normalized] = $day_key;
    }

    /**
     * Localized weekday abbreviations for the current WordPress locale (used in forms).
     * Example (de_DE): Mo, Di, Mi, Do, Fr, Sa, So
     *
     * @return array<string,string> Day key => localized abbreviation
     */
    public static function get_opening_hours_localized_day_abbrs() {
        $english = self::get_opening_hours_english_day_abbrs();
        $names = self::get_opening_hours_day_names();
        $abbrs = array();
        foreach ( $english as $key => $english_abbr ) {
            $from_name = ( isset( $names[$key] ) ? self::opening_hours_abbr_from_name( $names[$key] ) : '' );
            $abbrs[$key] = ( $from_name !== '' ? self::format_opening_hours_day_abbr_for_display( $from_name ) : $english_abbr );
        }
        return $abbrs;
    }

    /**
     * Map of accepted input aliases (normalized) to internal day keys.
     * Always includes English abbreviations; also includes current-locale variants.
     * English aliases are registered first and never overwritten.
     *
     * @return array<string,string> Normalized alias => day key (mo, tu, ...)
     */
    public static function get_opening_hours_day_alias_map() {
        $map = array();
        $english = self::get_opening_hours_english_day_abbrs();
        // Canonical English 2-letter codes (always accepted for backward compatibility)
        foreach ( $english as $key => $abbr ) {
            $map[self::normalize_opening_hours_day_alias( $abbr )] = $key;
        }
        global $wp_locale;
        if ( !$wp_locale instanceof \WP_Locale ) {
            return $map;
        }
        // Add current-locale abbreviations, WP weekday abbrevs, and full weekday names
        foreach ( self::get_opening_hours_day_indexes() as $key => $index ) {
            $name = $wp_locale->get_weekday( $index );
            self::add_opening_hours_day_alias( $map, $name, $key );
            self::add_opening_hours_day_alias( $map, self::opening_hours_abbr_from_name( $name ), $key );
            if ( isset( $wp_locale->weekday_abbrev[$name] ) ) {
                self::add_opening_hours_day_alias( $map, $wp_locale->get_weekday_abbrev( $name ), $key );
            }
        }
        return $map;
    }

    /**
     * Example input string for placeholders (localized for forms).
     *
     * @param bool $use12hour
     * @param bool $localized Whether to use locale abbreviations (true for forms, false for CSV)
     * @return string
     */
    public static function get_opening_hours_input_example( $use12hour = false, $localized = true ) {
        $abbrs = ( $localized ? self::get_opening_hours_localized_day_abbrs() : self::get_opening_hours_english_day_abbrs() );
        if ( $use12hour ) {
            return $abbrs['mo'] . ' 9:00 AM-5:00 PM | ' . $abbrs['tu'] . ' 9:00 AM-11:00 AM | ' . $abbrs['tu'] . ' 1:00 PM-5:00 PM';
        }
        return $abbrs['mo'] . ' 09:00-18:00 | ' . $abbrs['tu'] . ' 09:00-11:00 | ' . $abbrs['tu'] . ' 13:00-18:00';
    }

    /**
     * Translated format hint for opening hours input fields.
     *
     * @param bool $use12hour
     * @return string
     */
    public static function get_opening_hours_format_hint( $use12hour = false ) {
        $abbrs = self::get_opening_hours_localized_day_abbrs();
        $day_range = $abbrs['mo'] . '–' . $abbrs['su'];
        if ( $use12hour ) {
            return sprintf( 
                /* translators: %s is the weekday abbreviation range, e.g. Mo–Su or Mo–So */
                __( 'Enter the day (%s) and opening hours in 12-hour format with AM/PM (e.g. 9:00 AM–5:00 PM). Use | to separate multiple time blocks.', 'open-user-map' ),
                $day_range
             );
        }
        return sprintf( 
            /* translators: %s is the weekday abbreviation range, e.g. Mo–Su or Mo–So */
            __( 'Enter the day (%s) and opening hours in 24-hour format (e.g. 09:00–18:00). Use | to separate multiple time blocks.', 'open-user-map' ),
            $day_range
         );
    }

    /**
     * Data passed to frontend JS for validation and form formatting.
     *
     * @return array<string,mixed>
     */
    public static function get_opening_hours_js_data() {
        $abbrs = self::get_opening_hours_localized_day_abbrs();
        $example_24 = self::get_opening_hours_input_example( false, true );
        $example_12 = $abbrs['mo'] . ' 9:00 AM-5:00 PM';
        return array(
            'day_aliases'    => self::get_opening_hours_day_alias_map(),
            'day_abbrs'      => $abbrs,
            'invalid_format' => sprintf( 
                /* translators: 1: example in 24-hour format, 2: example in 12-hour format */
                __( 'Invalid opening hours format. Expected format: %1$s or %2$s', 'open-user-map' ),
                $example_24,
                $example_12
             ),
        );
    }

    /**
     * Format opening hours JSON for display (same format as popup)
     * @param string $json_value - JSON string containing opening hours data
     * @param array $custom_field - Custom field configuration array (must include 'label' and optionally 'use12hour' and 'open_now')
     * @param string $plugin_url - Plugin URL for icon paths
     * @return string - Formatted HTML output
     */
    public static function format_opening_hours_for_display( $json_value, $custom_field, $plugin_url = '' ) {
        // Decode JSON
        $opening_hours_data = json_decode( $json_value, true );
        if ( !$opening_hours_data || !is_array( $opening_hours_data ) || !isset( $opening_hours_data['week'] ) ) {
            // Invalid JSON, return raw value
            return '<div data-custom-field-label="' . esc_attr( $custom_field['label'] ) . '" class="oum_custom_field oum_custom_field_type_opening_hours"><strong>' . esc_html( $custom_field['label'] ) . ':</strong> <span data-value="' . esc_attr( $json_value ) . '">' . esc_html( $json_value ) . '</span></div>';
        }
        // Use WordPress locale weekday names (e.g. Monday / Montag)
        $day_names = self::get_opening_hours_day_names();
        $days = array(
            'mo',
            'tu',
            'we',
            'th',
            'fr',
            'sa',
            'su'
        );
        $formatted_lines = array();
        // Calculate open_now status using centralized helper
        $open_now = self::calculate_open_now( $opening_hours_data );
        // Check if 12-hour format is enabled for this field
        $use12hour = isset( $custom_field['use12hour'] ) && $custom_field['use12hour'];
        // Format each day with day on left, times on right
        // Each time block gets its own row
        foreach ( $days as $day ) {
            $day_name = $day_names[$day];
            $blocks = ( isset( $opening_hours_data['week'][$day] ) ? $opening_hours_data['week'][$day] : array() );
            if ( empty( $blocks ) ) {
                $formatted_lines[] = sprintf( '<div class="oum-opening-hours-row"><span class="oum-opening-hours-day-name">%s</span><span class="oum-opening-hours-status closed">%s</span></div>', esc_html( $day_name ), esc_html__( 'closed', 'open-user-map' ) );
            } else {
                // Create a separate row for each time block
                foreach ( $blocks as $index => $block ) {
                    if ( isset( $block['start'] ) && isset( $block['end'] ) ) {
                        // Convert to 12-hour format if enabled
                        if ( $use12hour ) {
                            $start_time = self::convert24To12( $block['start'] );
                            $end_time = self::convert24To12( $block['end'] );
                            $time_range = esc_html( $start_time . '–' . $end_time );
                        } else {
                            $time_range = esc_html( $block['start'] . '–' . $block['end'] );
                        }
                        // Show day name only on first row for this day
                        $day_display = ( $index === 0 ? esc_html( $day_name ) : '' );
                        $formatted_lines[] = sprintf( '<div class="oum-opening-hours-row"><span class="oum-opening-hours-day-name">%s</span><span class="oum-opening-hours-times">%s</span></div>', $day_display, $time_range );
                    }
                }
            }
        }
        // Build the output HTML (same structure as popup)
        // Use pre-calculated open_now if provided, otherwise use calculated value
        $open_now = ( isset( $custom_field['open_now'] ) ? $custom_field['open_now'] : $open_now );
        $status_class = ( $open_now ? 'open' : 'closed' );
        $status_text = ( $open_now ? __( 'Open now', 'open-user-map' ) : __( 'Closed now', 'open-user-map' ) );
        // Get plugin URL if not provided
        if ( empty( $plugin_url ) ) {
            $plugin_url = plugin_dir_url( dirname( dirname( __FILE__ ) ) );
        }
        $arrow_icon_url = esc_url( $plugin_url ) . 'assets/images/ico_arrow_down.png';
        $output = '<div data-custom-field-label="' . esc_attr( $custom_field['label'] ) . '" class="oum_custom_field oum_custom_field_type_opening_hours">';
        $output .= '<div class="oum-opening-hours-header" role="button" tabindex="0">';
        $output .= '<strong class="oum-opening-hours-label">' . esc_html( $custom_field['label'] ) . ': </strong>';
        $output .= '<span class="oum-opening-hours-status-indicator oum-opening-hours-status-' . $status_class . '">' . esc_html( $status_text ) . '</span>';
        $output .= '<img src="' . $arrow_icon_url . '" class="oum-opening-hours-toggle-icon" alt="" />';
        $output .= '</div>';
        $output .= '<div class="oum-opening-hours-wrapper" style="display: none;">';
        $output .= implode( '', $formatted_lines );
        $output .= '</div>';
        $output .= '</div>';
        return $output;
    }

    /**
     * Convert opening hours input string to JSON structure
     * @param string $input - Input string in format "Mo 09:00-18:00 | Tu 09:00-11:00"
     * @return array|null - Parsed JSON structure or null if invalid
     */
    public static function convert_opening_hours_input_to_json( $input ) {
        if ( empty( $input ) || !is_string( $input ) ) {
            return null;
        }
        $trimmed = trim( $input );
        if ( $trimmed === '' ) {
            return null;
        }
        // Initialize week structure
        $week = array(
            'mo' => array(),
            'tu' => array(),
            'we' => array(),
            'th' => array(),
            'fr' => array(),
            'sa' => array(),
            'su' => array(),
        );
        // English + current-locale weekday aliases (e.g. Th and Do both map to Thursday)
        $day_map = self::get_opening_hours_day_alias_map();
        /**
         * Convert 12-hour format time to 24-hour format
         * @param string $time12 - Time in 12-hour format (e.g., "9:00 AM" or "11:30 PM")
         * @return string|null - Time in 24-hour format (HH:MM) or null if invalid
         */
        $convert12To24 = function ( $time12 ) {
            // Pattern: H:MM or HH:MM followed by AM/PM (case insensitive)
            if ( preg_match( '/^(\\d{1,2}):(\\d{2})\\s*(AM|PM)$/i', trim( $time12 ), $matches ) ) {
                $hour = intval( $matches[1] );
                $minute = intval( $matches[2] );
                $period = strtoupper( $matches[3] );
                // Validate minute
                if ( $minute < 0 || $minute > 59 ) {
                    return null;
                }
                // Convert to 24-hour format
                if ( $period === 'AM' ) {
                    if ( $hour === 12 ) {
                        $hour = 0;
                        // 12:00 AM = 00:00
                    }
                } else {
                    // PM
                    if ( $hour !== 12 ) {
                        $hour += 12;
                        // 1:00 PM = 13:00, but 12:00 PM = 12:00
                    }
                }
                // Validate hour
                if ( $hour < 0 || $hour > 23 ) {
                    return null;
                }
                return str_pad(
                    $hour,
                    2,
                    '0',
                    STR_PAD_LEFT
                ) . ':' . str_pad(
                    $minute,
                    2,
                    '0',
                    STR_PAD_LEFT
                );
            }
            return null;
        };
        // Split by pipe and process each block
        $blocks = explode( '|', $trimmed );
        // Day token is language-agnostic; aliases are resolved via $day_map
        $pattern24 = '/^(\\S+)\\s+(\\d{1,2}):(\\d{2})-(\\d{1,2}):(\\d{2})$/u';
        $pattern12 = '/^(\\S+)\\s+(\\d{1,2}):(\\d{2})\\s*(AM|PM)-(\\d{1,2}):(\\d{2})\\s*(AM|PM)$/iu';
        foreach ( $blocks as $block ) {
            $block = trim( $block );
            if ( $block === '' ) {
                continue;
            }
            $start_time = null;
            $end_time = null;
            $day_abbr = null;
            // Try 24-hour format first
            if ( preg_match( $pattern24, $block, $matches ) ) {
                $day_abbr = self::normalize_opening_hours_day_alias( $matches[1] );
                $start_hour = intval( $matches[2] );
                $start_min = intval( $matches[3] );
                $end_hour = intval( $matches[4] );
                $end_min = intval( $matches[5] );
                // Validate time ranges
                if ( $start_hour >= 0 && $start_hour <= 23 && $start_min >= 0 && $start_min <= 59 && $end_hour >= 0 && $end_hour <= 23 && $end_min >= 0 && $end_min <= 59 ) {
                    $start_time = str_pad(
                        $start_hour,
                        2,
                        '0',
                        STR_PAD_LEFT
                    ) . ':' . str_pad(
                        $start_min,
                        2,
                        '0',
                        STR_PAD_LEFT
                    );
                    $end_time = str_pad(
                        $end_hour,
                        2,
                        '0',
                        STR_PAD_LEFT
                    ) . ':' . str_pad(
                        $end_min,
                        2,
                        '0',
                        STR_PAD_LEFT
                    );
                }
            } elseif ( preg_match( $pattern12, $block, $matches ) ) {
                // Try 12-hour format
                $day_abbr = self::normalize_opening_hours_day_alias( $matches[1] );
                $start_time12 = $matches[2] . ':' . $matches[3] . ' ' . $matches[4];
                $end_time12 = $matches[5] . ':' . $matches[6] . ' ' . $matches[7];
                $start_time = $convert12To24( $start_time12 );
                $end_time = $convert12To24( $end_time12 );
            }
            if ( $start_time && $end_time && $day_abbr ) {
                $day_key = ( isset( $day_map[$day_abbr] ) ? $day_map[$day_abbr] : null );
                if ( $day_key && isset( $week[$day_key] ) ) {
                    $week[$day_key][] = array(
                        'start' => $start_time,
                        'end'   => $end_time,
                    );
                }
            }
        }
        // Return structure without timezone - timezone will be loaded from WordPress settings when needed
        return array(
            'week' => $week,
        );
    }

    /**
     * Convert opening hours JSON to input format string
     * @param string $json_value - JSON string containing opening hours data
     * @param bool $use12hour - Whether to use 12-hour format
     * @param bool $localized - Use locale weekday abbreviations (forms) or English (CSV export)
     * @return string - Input format string (e.g., "Mo 09:00-18:00 | Tu 09:00-11:00")
     */
    public static function convert_opening_hours_json_to_input( $json_value, $use12hour = false, $localized = false ) {
        if ( empty( $json_value ) ) {
            return '';
        }
        $decoded = json_decode( $json_value, true );
        if ( !$decoded || !is_array( $decoded ) || !isset( $decoded['week'] ) ) {
            // Not JSON, return as-is
            return $json_value;
        }
        $day_abbrs = ( $localized ? self::get_opening_hours_localized_day_abbrs() : self::get_opening_hours_english_day_abbrs() );
        $blocks = array();
        foreach ( $decoded['week'] as $day => $time_blocks ) {
            if ( !isset( $day_abbrs[$day] ) || !is_array( $time_blocks ) ) {
                continue;
            }
            foreach ( $time_blocks as $block ) {
                if ( isset( $block['start'] ) && isset( $block['end'] ) ) {
                    if ( $use12hour ) {
                        $start_time = self::convert24To12( $block['start'] );
                        $end_time = self::convert24To12( $block['end'] );
                        $blocks[] = $day_abbrs[$day] . ' ' . $start_time . '-' . $end_time;
                    } else {
                        $blocks[] = $day_abbrs[$day] . ' ' . $block['start'] . '-' . $block['end'];
                    }
                }
            }
        }
        return implode( ' | ', $blocks );
    }

    /**
     * AJAX handler to check if current user can edit a specific location
     * Returns JSON response with can_edit boolean
     * 
     * This is used to dynamically show/hide edit buttons in cached pages
     * 
     * @return void (outputs JSON)
     */
    public function ajax_check_edit_permission() {
        // Get post_id from request
        $post_id = ( isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0 );
        // Validate post_id
        if ( !$post_id || get_post_type( $post_id ) !== 'oum-location' ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid location ID', 'open-user-map' ),
            ) );
            return;
        }
        // Check if location exists
        if ( get_post_status( $post_id ) === false ) {
            wp_send_json_error( array(
                'message' => __( 'Location not found', 'open-user-map' ),
            ) );
            return;
        }
        // Get location author ID
        $author_id = get_post_field( 'post_author', $post_id );
        // Check permissions (same logic as in partial-map-render.php)
        $has_general_permission = current_user_can( 'edit_oum-locations' );
        $is_author = get_current_user_id() == $author_id;
        $can_edit_specific_post = current_user_can( 'edit_post', $post_id );
        $can_edit = ( $has_general_permission && ($is_author || $can_edit_specific_post) ? true : false );
        // Return result
        wp_send_json_success( array(
            'can_edit' => $can_edit,
        ) );
    }

}
