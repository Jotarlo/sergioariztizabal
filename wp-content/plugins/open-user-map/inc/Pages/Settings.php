<?php

/**
 * @package OpenUserMapPlugin
 */
namespace OpenUserMapPlugin\Pages;

use OpenUserMapPlugin\Base\BaseController;
class Settings extends BaseController {
    public function register() {
        add_action( 'init', array($this, 'migrate_deprecated_settings') );
        add_action( 'admin_menu', array($this, 'add_admin_pages') );
        add_action( 'admin_init', array($this, 'add_plugin_settings') );
        add_action( 'admin_init', array($this, 'add_oum_wizard') );
        add_action( 'admin_notices', array($this, 'show_getting_started_notice') );
        add_action( 'wp_ajax_oum_dismiss_getting_started_notice', array($this, 'getting_started_dismiss_notice') );
        add_action( 'admin_notices', array($this, 'show_update_notice') );
        add_action( 'admin_notices', array($this, 'show_map_style_auto_switch_notice') );
        add_action( 'wp_ajax_oum_dismiss_update_notice', array($this, 'dismiss_update_notice') );
        add_action( 'wp_ajax_oum_csv_export', array($this, 'csv_export') );
        add_action( 'wp_ajax_oum_csv_import', array($this, 'csv_import') );
        // Hook to display "Settings Saved" message when settings are updated
        // Safety guards inside the method prevent fatals on non-admin requests
        add_action(
            'update_option',
            array($this, 'add_settings_updated_message'),
            10,
            3
        );
        // Rebuild rewrite rules when single-page mode changes (slug/rewrite can change).
        add_action(
            'update_option_oum_enable_single_page',
            array($this, 'flush_rewrite_rules_for_single_page_setting'),
            10,
            3
        );
        add_filter(
            'wp_redirect',
            array($this, 'preserve_active_tab_in_redirect'),
            10,
            1
        );
    }

    public function add_admin_pages() {
        add_submenu_page(
            'edit.php?post_type=oum-location',
            'Settings',
            'Settings',
            'manage_options',
            'open-user-map-settings',
            array($this, 'admin_index')
        );
    }

    public function add_plugin_settings() {
        register_setting( 'open-user-map-settings-getting-started-notice', 'oum_getting_started_notice_dismissed' );
        register_setting( 'open-user-map-settings-update-notice', 'oum_update_notice_dismissed_version' );
        register_setting( 'open-user-map-settings-group', 'oum_map_style', array(
            'sanitize_callback' => array($this, 'sanitize_map_style_setting'),
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_tile_provider_mapbox_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_tile_provider_carto_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_marker_icon', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_marker_user_icon', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_marker_multicategories_icon', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_category_icons_in_title', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_max_image_uploads', array(
            'sanitize_callback' => array($this, 'validate_max_image_uploads'),
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_popup_image_size', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_map_size', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_map_height', array(
            'sanitize_callback' => array($this, 'validate_size'),
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_map_height_mobile', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_start_lat', array(
            'sanitize_callback' => array($this, 'validate_geocoordinate'),
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_start_lng', array(
            'sanitize_callback' => array($this, 'validate_geocoordinate'),
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_start_zoom', array(
            'sanitize_callback' => array($this, 'validate_zoom'),
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_fixed_map_bounds', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_title', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_title_required', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_title_maxlength', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_title_label', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_map_label', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_hide_address', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_address', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_address_autofill', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_geosearch_provider', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_geosearch_provider_geoapify_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_geosearch_provider_here_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_geosearch_provider_mapbox_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_searchbar', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_searchbar_type', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_searchaddress_button', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_searchaddress_label', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_searchmarkers_button', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_searchmarkers_label', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_searchmarkers_zoom', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_gmaps_link', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_address_label', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_description', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_description_required', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_description_label', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_upload_media_label', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_image', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_image_required', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_audio', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_audio_required', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_video', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_video_required', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_custom_fields', array(
            'sanitize_callback' => array($this, 'validate_array'),
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_scrollwheel_zoom_map', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_cluster', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_fullscreen', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_hide_location_popup', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_currentlocation', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_disable_oum_attribution', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_max_image_filesize', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_max_audio_filesize', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_action_after_submit', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_thankyou_redirect', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_thankyou_headline', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_thankyou_text', array(
            'sanitize_callback' => 'wp_kses_post',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_thankyou_buttontext', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_plus_button_label', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_submit_button_label', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_form_headline', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_user_notification', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_user_notification_label', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_user_notification_subject', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_admin_notification', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_admin_notification_email', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_admin_notification_subject', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_webhook_notification', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_webhook_notification_url', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_user_restriction', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_redirect_to_registration', array(
            'sanitize_callback' => array($this, 'sanitize_add_location_redirect_target'),
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_auto_publish', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_auto_publish_for_everyone', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_add_user_location', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_marker_types', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_empty_marker_type', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_multiple_marker_types', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_toggle_all_categories', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_collapse_filter', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_marker_types_label', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_location_type_marker', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_ui_color', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_add_location', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_single_page', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_location_date', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_location_date_type', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_regions', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_regions_layout_style', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_enable_vote_feature', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_vote_button_label', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_vote_cookie_type', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_vote_type', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_custom_js', array(
            'sanitize_callback' => 'wp_kses_post',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_custom_css', array(
            'sanitize_callback' => 'wp_kses_post',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_custom_image_url', array(
            'sanitize_callback' => 'esc_url_raw',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_custom_image_bounds', array(
            'sanitize_callback' => array($this, 'validate_image_bounds'),
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_custom_image_hide_tiles', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_custom_image_clickable', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_custom_image_background_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
        ) );
        // Advanced Filter Interface settings
        register_setting( 'open-user-map-settings-group', 'oum_enable_advanced_filter', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_advanced_filter_layout', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_advanced_filter_label', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_advanced_filter_reset_text', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_advanced_filter_sections', array(
            'sanitize_callback' => array($this, 'validate_advanced_filter_sections'),
        ) );
        register_setting( 'open-user-map-settings-group', 'oum_csv_import_publish_immediately', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group-wizard-1', 'oum_wizard_usecase', array(
            'sanitize_callback' => array($this, 'process_wizard_usecase'),
        ) );
        register_setting( 'open-user-map-settings-group-wizard-1', 'oum_wizard_usecase_done', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'open-user-map-settings-group-wizard-2', 'oum_wizard_finish_done', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
    }

    /**
     * Sanitize the redirect destination for logged-out users clicking "Add location".
     *
     * @param string $value Submitted redirect target.
     * @return string
     */
    public function sanitize_add_location_redirect_target( $value ) {
        $value = sanitize_key( $value );
        // Backward compatibility for the former checkbox value.
        if ( $value === 'on' ) {
            return 'registration';
        }
        return ( in_array( $value, array('none', 'registration', 'login'), true ) ? $value : 'none' );
    }

    public function migrate_deprecated_settings() {
        // Variant 1: invert old settings
        $options = array(
            'oum_disable_add_location'  => 'oum_enable_add_location',
            'oum_disable_title'         => 'oum_enable_title',
            'oum_disable_address'       => 'oum_enable_address',
            'oum_disable_gmaps_link'    => 'oum_enable_gmaps_link',
            'oum_disable_description'   => 'oum_enable_description',
            'oum_disable_image'         => 'oum_enable_image',
            'oum_disable_audio'         => 'oum_enable_audio',
            'oum_disable_cluster'       => 'oum_enable_cluster',
            'oum_disable_fullscreen'    => 'oum_enable_fullscreen',
            'oum_disable_searchaddress' => 'oum_enable_searchaddress_button',
        );
        foreach ( $options as $old_option => $new_option ) {
            $old_setting = get_option( $old_option );
            // do nothing if old option doesnt exist
            if ( $old_setting === false ) {
                //$this->safe_log('Open User Map: Deprecated option ' . $old_option . ' does not exist. Nothing to do.');
                continue;
            }
            if ( empty( $old_setting ) ) {
                $new_setting = 'on';
            } else {
                $new_setting = '';
            }
            //update (or create) new
            update_option( $new_option, $new_setting );
            $this->safe_log( 'Open User Map: Update new option ' . $new_option . ' from old option ' . $old_option . '. New Value: ' . $new_setting );
            //delete old
            delete_option( $old_option );
            $this->safe_log( 'Open User Map: Deleting old option ' . $new_option . '.' );
        }
        // Variant 2: rename settings (keep value)
        $options = array(
            'oum_enable_searchaddress' => 'oum_enable_searchbar',
        );
        foreach ( $options as $old_option => $new_option ) {
            $old_setting = get_option( $old_option );
            // do nothing if old option doesnt exist
            if ( $old_setting === false ) {
                //$this->safe_log('Open User Map: Deprecated option ' . $old_option . ' does not exist. Nothing to do.');
                continue;
            }
            //update (or create) new
            update_option( $new_option, $old_setting );
            $this->safe_log( 'Open User Map: Update new option ' . $new_option . ' from old option ' . $old_option . '. New Value: ' . $old_setting );
            //delete old
            delete_option( $old_option );
            $this->safe_log( 'Open User Map: Deleting old option ' . $new_option . '.' );
        }
        // Variant 3: change value of a setting
        if ( get_option( 'oum_map_style' ) == 'Stamen.TonerLite' ) {
            update_option( 'oum_map_style', 'CartoDB.Positron' );
        }
        if ( get_option( 'oum_map_style' ) == 'Stadia.StamenTonerLite' ) {
            update_option( 'oum_map_style', 'CartoDB.Positron' );
        }
        $this->switch_map_style_without_required_api_key();
    }

    public function sanitize_map_style_setting( $input ) {
        $map_style = sanitize_text_field( $input );
        if ( !preg_match( '/^[A-Za-z0-9._-]+$/', $map_style ) ) {
            return $this->get_default_map_style();
        }
        $submitted_keys = array();
        if ( isset( $_POST['oum_tile_provider_carto_key'] ) ) {
            $submitted_keys['carto'] = sanitize_text_field( wp_unslash( $_POST['oum_tile_provider_carto_key'] ) );
        }
        if ( isset( $_POST['oum_tile_provider_mapbox_key'] ) ) {
            $submitted_keys['mapbox'] = sanitize_text_field( wp_unslash( $_POST['oum_tile_provider_mapbox_key'] ) );
        }
        $replacement_style = $this->get_no_key_replacement_map_style( $map_style, $submitted_keys );
        if ( $replacement_style !== '' ) {
            $this->store_map_style_auto_switch_notice( $map_style, $replacement_style );
            return $replacement_style;
        }
        return $map_style;
    }

    public function switch_map_style_without_required_api_key() {
        $map_style = get_option( 'oum_map_style', '' );
        if ( $map_style === '' ) {
            return;
        }
        $replacement_style = $this->get_no_key_replacement_map_style( $map_style );
        if ( $replacement_style === '' || $replacement_style === $map_style ) {
            return;
        }
        update_option( 'oum_map_style', $replacement_style );
        $this->store_map_style_auto_switch_notice( $map_style, $replacement_style );
    }

    public function store_map_style_auto_switch_notice( $old_style, $new_style ) {
        $old_definition = $this->get_map_style_definition( $old_style );
        update_option( 'oum_map_style_auto_switch_notice', array(
            'old_style'        => $old_style,
            'new_style'        => $new_style,
            'required_api_key' => ( isset( $old_definition['requires_api_key'] ) ? $old_definition['requires_api_key'] : '' ),
        ) );
    }

    public function add_oum_wizard() {
        if ( get_option( 'oum_enable_add_location' ) !== 'on' && get_option( 'oum_enable_add_location' ) !== '' || get_option( 'oum_wizard_usecase_done' ) && !get_option( 'oum_wizard_finish_done' ) ) {
            add_action( 'admin_body_class', function ( $class ) {
                $class .= ' oum-settings-wizard';
                return $class;
            } );
        }
    }

    public function admin_index() {
        require_once oum_get_template( 'page-backend-settings.php' );
    }

    public static function validate_geocoordinate( $input ) {
        // Validation
        $geocoordinate_validated = floatval( str_replace( ',', '.', sanitize_text_field( $input ) ) );
        if ( !$geocoordinate_validated && $geocoordinate_validated != '0' ) {
            $geocoordinate_validated = '';
        }
        return $geocoordinate_validated;
    }

    public static function validate_zoom( $input ) {
        // Validation
        $zoom_validated = floatval( str_replace( ',', '.', sanitize_text_field( $input ) ) );
        if ( !$zoom_validated ) {
            $zoom_validated = '';
        }
        return $zoom_validated;
    }

    public static function validate_size( $input ) {
        // Add px if it's missing
        $size_validated = ( is_numeric( $input ) ? $input . 'px' : sanitize_text_field( $input ) );
        return $size_validated;
    }

    public function validate_array( $array ) {
        // if not an array
        if ( !is_array( $array ) ) {
            return '';
        }
        foreach ( $array as &$value ) {
            if ( !is_array( $value ) ) {
                // sanitize if value is not an array
                $value = sanitize_text_field( $value );
            } else {
                // go inside this function again
                $this->validate_array( $value );
            }
        }
        return $array;
    }

    public function validate_max_image_uploads( $input ) {
        // Convert to integer
        $value = intval( $input );
        // Validate range
        if ( $value < 1 ) {
            $value = 1;
        } elseif ( $value > 5 ) {
            $value = 5;
        }
        return $value;
    }

    /**
     * Validate image bounds input
     */
    public function validate_image_bounds( $input ) {
        // Allow empty input
        if ( empty( $input ) ) {
            return '';
        }
        // Parse JSON input from form
        $bounds = json_decode( $input, true );
        if ( !is_array( $bounds ) ) {
            add_settings_error( 'oum_custom_image_bounds', 'invalid_bounds', __( 'Invalid bounds format. Please use the form fields to set bounds.', 'open-user-map' ) );
            return '';
        }
        // Validate required bounds fields
        $required_fields = array(
            'north',
            'south',
            'east',
            'west'
        );
        foreach ( $required_fields as $field ) {
            if ( !isset( $bounds[$field] ) || !is_numeric( $bounds[$field] ) ) {
                add_settings_error( 'oum_custom_image_bounds', 'missing_bounds', __( 'All bounds fields (North, South, East, West) are required and must be numeric.', 'open-user-map' ) );
                return '';
            }
        }
        // Validate bounds logic
        if ( $bounds['north'] <= $bounds['south'] ) {
            add_settings_error( 'oum_custom_image_bounds', 'invalid_bounds_logic', __( 'North latitude must be greater than South latitude.', 'open-user-map' ) );
            return '';
        }
        if ( $bounds['east'] <= $bounds['west'] ) {
            add_settings_error( 'oum_custom_image_bounds', 'invalid_bounds_logic', __( 'East longitude must be greater than West longitude.', 'open-user-map' ) );
            return '';
        }
        // Validate coordinate ranges
        if ( $bounds['north'] > 90 || $bounds['south'] < -90 ) {
            add_settings_error( 'oum_custom_image_bounds', 'invalid_latitude', __( 'Latitude values must be between -90 and 90 degrees.', 'open-user-map' ) );
            return '';
        }
        if ( $bounds['east'] > 180 || $bounds['west'] < -180 ) {
            add_settings_error( 'oum_custom_image_bounds', 'invalid_longitude', __( 'Longitude values must be between -180 and 180 degrees.', 'open-user-map' ) );
            return '';
        }
        // Return as serialized array
        return maybe_serialize( $bounds );
    }

    public static function show_getting_started_notice() {
        // return if already dismissed
        if ( get_option( 'oum_getting_started_notice_dismissed' ) ) {
            return;
        }
        $screen = get_current_screen();
        //$this->safe_log(print_r($screen, true));
        // Only render this notice on a Open User Map page.
        if ( !$screen || 'edit.php?post_type=oum-location' !== $screen->parent_file ) {
            return;
        }
        // Get plugin icon URL
        $icon_url = plugins_url( 'assets/images/icon-256x256.png', dirname( dirname( __FILE__ ) ) );
        // Render the notice's HTML with icon and improved layout
        echo '<div class="notice oum-getting-started-notice notice-success is-dismissible">';
        echo '<div class="oum-getting-started-content">';
        echo '<div class="oum-getting-started-icon-wrapper">';
        echo '<img src="' . esc_url( $icon_url ) . '" alt="Open User Map" class="oum-getting-started-icon" />';
        echo '</div>';
        echo '<div class="oum-getting-started-text">';
        echo sprintf( __( '<h3>🚀 Get started with Open User Map</h3><ol><li>Use the WordPress block editor (or Elementor) to insert the <b>Open User Map</b> block onto a page. Alternatively, you can use the shortcode <input class="shortcode-display" type="text" readonly value=\'[open-user-map]\' />.</li><li>You can <a href="%s">Manage Markers</a> under <i>Open User Map > All Locations</i></li><li><a href="%s">Customize</a> map styles, enable features, or get help via <i>Open User Map > Settings</i></li></ol>', 'open-user-map' ), 'edit.php?post_type=oum-location', 'edit.php?post_type=oum-location&page=open-user-map-settings' );
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    public static function getting_started_dismiss_notice() {
        update_option( 'oum_getting_started_notice_dismissed', 1 );
    }

    /**
     * Show update notice when a new version is available
     * 
     * This notice only appears on OUM-related admin pages and checks WordPress's
     * update transient to detect if a new version is available without making
     * external requests.
     */
    public static function show_update_notice() {
        $screen = get_current_screen();
        // Only render this notice on Open User Map pages
        if ( !$screen || 'edit.php?post_type=oum-location' !== $screen->parent_file ) {
            return;
        }
        // Get plugin basename and current version
        $plugin_basename = plugin_basename( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/open-user-map.php';
        $plugin_data = get_file_data( dirname( dirname( dirname( __FILE__ ) ) ) . '/open-user-map.php', array(
            'Version' => 'Version',
        ) );
        $current_version = ( isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : '' );
        // Get update information from WordPress transient
        $update_plugins = get_site_transient( 'update_plugins' );
        // Check if update is available
        if ( !$update_plugins || empty( $update_plugins->response[$plugin_basename] ) ) {
            return;
        }
        $update_info = $update_plugins->response[$plugin_basename];
        $new_version = ( isset( $update_info->new_version ) ? $update_info->new_version : '' );
        // Only show notice if there's actually a newer version
        if ( empty( $new_version ) || version_compare( $current_version, $new_version, '>=' ) ) {
            return;
        }
        // Check if this specific version was already dismissed
        $dismissed_version = get_option( 'oum_update_notice_dismissed_version' );
        if ( $dismissed_version === $new_version ) {
            return;
        }
        // Get update URL (link to plugins page with update action)
        $update_url = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' . urlencode( $plugin_basename ) ), 'upgrade-plugin_' . $plugin_basename );
        // Render the notice's HTML
        echo '<div class="notice oum-update-notice notice-info is-dismissible" data-version="' . esc_attr( $new_version ) . '">';
        echo '<p><strong>' . esc_html__( 'A new version of Open User Map is available!', 'open-user-map' ) . '</strong></p>';
        echo '<p>';
        echo sprintf( esc_html__( 'You have version %1$s installed. Update to version %2$s.', 'open-user-map' ), '<strong>' . esc_html( $current_version ) . '</strong>', '<strong>' . esc_html( $new_version ) . '</strong>' );
        echo '</p>';
        echo '<p>';
        echo '<a href="' . esc_url( $update_url ) . '" class="button button-primary">' . esc_html__( 'Update Now', 'open-user-map' ) . '</a> ';
        echo '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '" class="button">' . esc_html__( 'View All Updates', 'open-user-map' ) . '</a>';
        echo '</p>';
        echo '</div>';
    }

    /**
     * Handle AJAX request to dismiss the update notice
     * 
     * Stores the dismissed version so the notice will show again for future updates
     */
    public static function dismiss_update_notice() {
        // Check user capabilities
        if ( !current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Insufficient permissions.', 'open-user-map' ),
            ) );
            return;
        }
        // Get the version from POST data
        $version = ( isset( $_POST['version'] ) ? sanitize_text_field( $_POST['version'] ) : '' );
        if ( empty( $version ) ) {
            wp_send_json_error( array(
                'message' => __( 'Version parameter missing.', 'open-user-map' ),
            ) );
            return;
        }
        // Store the dismissed version
        // update_option returns false only if the option has the same value, true if updated, or option_id if new
        $result = update_option( 'oum_update_notice_dismissed_version', $version, false );
        // Always send success if we got here (even if value didn't change, it's still "dismissed")
        wp_send_json_success( array(
            'message' => __( 'Update notice dismissed.', 'open-user-map' ),
            'version' => $version,
        ) );
    }

    public function show_map_style_auto_switch_notice() {
        if ( !current_user_can( 'manage_options' ) ) {
            return;
        }
        $notice = get_option( 'oum_map_style_auto_switch_notice' );
        if ( !is_array( $notice ) || empty( $notice['old_style'] ) || empty( $notice['new_style'] ) ) {
            return;
        }
        $old_definition = $this->get_map_style_definition( $notice['old_style'] );
        $new_definition = $this->get_map_style_definition( $notice['new_style'] );
        $old_label = ( $old_definition && !empty( $old_definition['label'] ) ? $old_definition['label'] : $notice['old_style'] );
        $new_label = ( $new_definition && !empty( $new_definition['label'] ) ? $new_definition['label'] : $notice['new_style'] );
        $api_key_labels = array(
            'carto'  => 'CARTO',
            'mapbox' => 'MapBox',
        );
        $required_api_key = ( !empty( $notice['required_api_key'] ) && isset( $api_key_labels[$notice['required_api_key']] ) ? $api_key_labels[$notice['required_api_key']] : __( 'required', 'open-user-map' ) );
        echo '<div class="notice notice-warning">';
        echo '<p><strong>' . esc_html__( 'Open User Map: Map style switched automatically', 'open-user-map' ) . '</strong></p>';
        echo '<p>' . sprintf(
            esc_html__( 'Open User Map switched your map style from %1$s to %2$s because no %3$s API Key is configured. Enter the missing API Key and select the original provider style again, or keep the new map style.', 'open-user-map' ),
            '<strong>' . esc_html( $old_label ) . '</strong>',
            '<strong>' . esc_html( $new_label ) . '</strong>',
            esc_html( $required_api_key )
        ) . ' ';
        echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=oum-location&page=open-user-map-settings' ) ) . '">' . esc_html__( 'Open settings', 'open-user-map' ) . '</a></p>';
        echo '</div>';
        delete_option( 'oum_map_style_auto_switch_notice' );
    }

    public function process_wizard_usecase( $input ) {
        // Adjust OUM settings based on the setup wizard choice.
        // Important: This is a one-time preset and should not affect later manual settings changes.
        if ( $input == 1 ) {
            // Enable visitor contributions.
            update_option( 'oum_enable_add_location', 'on' );
        } elseif ( $input == 2 ) {
            // Disable visitor contributions.
            update_option( 'oum_enable_add_location', '' );
            // Apply a minimal "business-style" preset for initial setup only.
            update_option( 'oum_enable_fullscreen', '' );
            update_option( 'oum_enable_searchbar', '' );
            update_option( 'oum_enable_searchaddress_button', '' );
            update_option( 'oum_enable_searchmarkers_button', '' );
            update_option( 'oum_enable_currentlocation', '' );
            update_option( 'oum_enable_location_date', '' );
            update_option( 'oum_enable_marker_types', '' );
            update_option( 'oum_enable_advanced_filter', '' );
        }
        return $input;
    }

    public function csv_export() {
        if ( isset( $_POST['action'] ) && $_POST['action'] == 'oum_csv_export' ) {
            // Initialize error handling
            $error = new \WP_Error();
            // Security: Check user capabilities
            if ( !current_user_can( 'manage_options' ) ) {
                $error->add( '003', 'Insufficient permissions. Only administrators can export CSV files.' );
            }
            // Security: Require nonce
            if ( !isset( $_POST['oum_location_nonce'] ) ) {
                $error->add( '002', 'Not allowed' );
            } else {
                $nonce = sanitize_text_field( $_POST['oum_location_nonce'] );
                if ( !wp_verify_nonce( $nonce, 'oum_location' ) ) {
                    $error->add( '002', 'Not allowed' );
                }
            }
            if ( $error->has_errors() ) {
                // Return errors
                wp_send_json_error( $error );
            } else {
                // Chunked export (prevents memory spikes on large datasets)
                $page = ( isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1 );
                $per_page = ( isset( $_POST['per_page'] ) ? intval( $_POST['per_page'] ) : 200 );
                $per_page = max( 50, min( 500, $per_page ) );
                $offset = ($page - 1) * $per_page;
                $published_count = wp_count_posts( 'oum-location' );
                $total_locations = ( isset( $published_count->publish ) ? intval( $published_count->publish ) : 0 );
                $all_oum_locations = get_posts( array(
                    'post_type'      => 'oum-location',
                    'post_status'    => 'publish',
                    'posts_per_page' => $per_page,
                    'offset'         => $offset,
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                    'fields'         => 'ids',
                ) );
                $locations_list = array();
                $available_custom_fields = get_option( 'oum_custom_fields', array() );
                // all available custom fields
                foreach ( $all_oum_locations as $post_id ) {
                    $location_meta = get_post_meta( $post_id, '_oum_location_key', true );
                    $geometry_type = ( is_array( $location_meta ) && isset( $location_meta['geometry_type'] ) ? $location_meta['geometry_type'] : 'point' );
                    $geometry_type = ( in_array( $geometry_type, array('polyline', 'polygon'), true ) ? $geometry_type : 'point' );
                    $lat = oum_get_location_value( 'lat', $post_id );
                    $lng = oum_get_location_value( 'lng', $post_id );
                    $geometry = '';
                    if ( $geometry_type === 'point' && $lat !== '' && $lng !== '' ) {
                        $geometry = wp_json_encode( array(
                            'type'        => 'Point',
                            'coordinates' => array(floatval( str_replace( ',', '.', $lng ) ), floatval( str_replace( ',', '.', $lat ) )),
                        ) );
                    } elseif ( is_array( $location_meta ) && isset( $location_meta['geometry'] ) && is_array( $location_meta['geometry'] ) ) {
                        $geometry = wp_json_encode( $location_meta['geometry'] );
                    }
                    // get fields
                    $location = array(
                        'post_id'           => $post_id,
                        'wp_author_id'      => oum_get_location_value( 'wp_author_id', $post_id ),
                        'title'             => oum_get_location_value( 'title', $post_id ),
                        'image'             => oum_get_location_value( 'image', $post_id, true ),
                        'video'             => oum_get_location_value( 'video', $post_id, true ),
                        'audio'             => oum_get_location_value( 'audio', $post_id, true ),
                        'type'              => oum_get_location_value( 'type', $post_id ),
                        'subtitle'          => oum_get_location_value( 'subtitle', $post_id ),
                        'lat'               => $lat,
                        'lng'               => $lng,
                        'zoom'              => oum_get_location_value( 'zoom', $post_id ),
                        'geometry_type'     => $geometry_type,
                        'geometry'          => $geometry,
                        'text'              => oum_get_location_value( 'text', $post_id ),
                        'notification'      => oum_get_location_value( 'notification', $post_id ),
                        'author_name'       => oum_get_location_value( 'author_name', $post_id ),
                        'author_email'      => oum_get_location_value( 'author_email', $post_id ),
                        'votes'             => oum_get_location_value( 'votes', $post_id, true ),
                        'star_rating_avg'   => oum_get_location_value( 'star_rating_avg', $post_id ),
                        'star_rating_count' => oum_get_location_value( 'star_rating_count', $post_id ),
                    );
                    //get custom fields
                    $location_customfields = array();
                    foreach ( $available_custom_fields as $custom_field_id => $custom_field ) {
                        $value = oum_get_location_value( $custom_field['label'], $post_id, true );
                        if ( isset( $custom_field['fieldtype'] ) && $custom_field['fieldtype'] === 'opening_hours' ) {
                            // Export opening hours in the same text format the CSV importer can parse.
                            $use12hour = isset( $custom_field['use12hour'] ) && $custom_field['use12hour'];
                            $value = \OpenUserMapPlugin\Base\LocationController::convert_opening_hours_json_to_input( $value, $use12hour );
                        }
                        // transform array to pipe-separated string (also empty array)
                        if ( is_array( $value ) ) {
                            $value = implode( '|', $value );
                        }
                        $location_customfields['CUSTOMFIELD_' . $custom_field_id . '_' . $custom_field['label']] = $value;
                    }
                    $location_data = array_merge( $location, $location_customfields );
                    $locations_list[] = $location_data;
                }
                $datetime = date( 'Y-m-d_His' );
                // Format: YYYY-MM-DD_HHMMSS
                $exported = min( $offset + count( $locations_list ), $total_locations );
                $response = array(
                    'locations' => $locations_list,
                    'datetime'  => $datetime,
                    'page'      => $page,
                    'per_page'  => $per_page,
                    'total'     => $total_locations,
                    'exported'  => $exported,
                    'has_more'  => $exported < $total_locations,
                );
                wp_send_json_success( $response );
            }
        }
    }

    public function detectDelimiter( $csvFile ) {
        $delimiters = array(
            ';'  => 0,
            ','  => 0,
            "\t" => 0,
        );
        $handle = fopen( $csvFile, "r" );
        if ( $handle === false ) {
            return false;
        }
        $firstLine = fgets( $handle );
        fclose( $handle );
        if ( $firstLine === false ) {
            return false;
        }
        foreach ( $delimiters as $delimiter => &$count ) {
            $count = count( str_getcsv( $firstLine, $delimiter ) );
        }
        return array_search( max( $delimiters ), $delimiters );
    }

    /**
     * Parse a CSV file into location rows keyed by the header columns.
     *
     * Empty and malformed rows are skipped. Returns a WP_Error when the file
     * cannot be read or has no valid header.
     */
    private function parse_csv_locations( $csv_file, $delimiter ) {
        $file_to_read = fopen( $csv_file, 'r' );
        if ( $file_to_read === false ) {
            return new \WP_Error('009', 'CSV file could not be opened for reading.');
        }
        $rows = array();
        while ( ($row = fgetcsv( $file_to_read, 99999, $delimiter )) !== false ) {
            $rows[] = $row;
        }
        fclose( $file_to_read );
        if ( empty( $rows ) || !is_array( $rows[0] ) ) {
            return new \WP_Error('010', 'CSV file is empty or has an invalid header row.');
        }
        $header = $rows[0];
        // Excel/Sheets often prefix the first column with a UTF-8 BOM
        // and may add whitespace, which would hide the post_id key.
        $header = array_map( function ( $column ) {
            $column = (string) $column;
            $column = preg_replace( '/^\\xEF\\xBB\\xBF/', '', $column );
            return trim( $column );
        }, $header );
        $locations = array();
        foreach ( array_slice( $rows, 1 ) as $row ) {
            $row_has_content = is_array( $row ) && !empty( array_filter( $row, function ( $value ) {
                if ( $value === null ) {
                    return false;
                }
                if ( is_string( $value ) ) {
                    return $value !== '';
                }
                return true;
            } ) );
            if ( !$row_has_content ) {
                $this->safe_log( 'Open User Map: an empty line or a row not of type array detected and skipped' );
                continue;
            }
            if ( count( $header ) !== count( $row ) ) {
                $this->safe_log( 'Open User Map: malformed CSV row with mismatched column count detected and skipped' );
                continue;
            }
            $location = array_combine( $header, $row );
            if ( $location === false ) {
                $this->safe_log( 'Open User Map: malformed CSV row could not be mapped to header and was skipped' );
                continue;
            }
            $locations[] = $location;
        }
        return $locations;
    }

    /**
     * Convert pipe-separated category names to term IDs, creating missing terms.
     * $term_id_cache is reused across rows in the same request.
     */
    private function get_csv_import_type_ids( $types, &$term_id_cache ) {
        $type_ids = array();
        $types = explode( '|', (string) $types );
        foreach ( $types as $type_name ) {
            $type_name = trim( $type_name );
            if ( $type_name === '' ) {
                continue;
            }
            if ( isset( $term_id_cache[$type_name] ) ) {
                $type_ids[] = $term_id_cache[$type_name];
                continue;
            }
            $term = get_term_by( 'name', $type_name, 'oum-type' );
            if ( $term && !is_wp_error( $term ) ) {
                $term_id_cache[$type_name] = $term->term_id;
                $type_ids[] = $term->term_id;
            } else {
                $new_term = wp_insert_term( $type_name, 'oum-type' );
                if ( !is_wp_error( $new_term ) ) {
                    $term_id_cache[$type_name] = $new_term['term_id'];
                    $type_ids[] = $new_term['term_id'];
                }
            }
        }
        return $type_ids;
    }

    /**
     * Build a post excerpt from location text so CSV import can set it
     * on the first wp_insert_post and skip a second wp_update_post.
     */
    private function get_csv_import_excerpt( $text ) {
        $max_length = 400;
        $text = wp_strip_all_tags( (string) $text );
        if ( $text === '' ) {
            return '';
        }
        if ( strlen( $text ) > $max_length ) {
            $text = substr( $text, 0, $max_length );
            $last_space = strrpos( $text, ' ' );
            if ( $last_space !== false ) {
                $text = substr( $text, 0, $last_space );
            }
            $text .= '...';
        }
        return sanitize_text_field( $text );
    }

    /**
     * Reduce WordPress overhead while a CSV import chunk is running.
     */
    private function begin_csv_import_boost() {
        if ( !defined( 'OUM_DOING_CSV_IMPORT' ) ) {
            define( 'OUM_DOING_CSV_IMPORT', true );
        }
        wp_defer_term_counting( true );
        wp_defer_comment_counting( true );
        wp_suspend_cache_invalidation( true );
        add_filter(
            'wp_revisions_to_keep',
            array($this, 'disable_revisions_during_csv_import'),
            10,
            2
        );
    }

    /**
     * Restore normal WordPress write behavior after a CSV import chunk.
     */
    private function end_csv_import_boost() {
        wp_suspend_cache_invalidation( false );
        wp_defer_comment_counting( false );
        wp_defer_term_counting( false );
        remove_filter( 'wp_revisions_to_keep', array($this, 'disable_revisions_during_csv_import'), 10 );
    }

    /**
     * Skip location revisions during CSV import (called via wp_revisions_to_keep).
     */
    public function disable_revisions_during_csv_import( $num, $post ) {
        if ( $post instanceof \WP_Post && $post->post_type === 'oum-location' ) {
            return 0;
        }
        return $num;
    }

    public function csv_import() {
        if ( isset( $_POST['action'] ) && $_POST['action'] == 'oum_csv_import' ) {
            // Initialize error handling
            $error = new \WP_Error();
            // Security: Check user capabilities - only administrators can import CSV
            if ( !current_user_can( 'manage_options' ) ) {
                $error->add( '003', 'Insufficient permissions. Only administrators can import CSV files.' );
            }
            // Dont save without nonce
            if ( !isset( $_POST['oum_location_nonce'] ) ) {
                $error->add( '002', 'Not allowed' );
            }
            // Dont save if nonce is incorrect
            $nonce = $_POST['oum_location_nonce'];
            if ( !wp_verify_nonce( $nonce, 'oum_location' ) ) {
                $error->add( '002', 'Not allowed' );
            }
            // Exit if no file
            if ( !isset( $_POST['url'] ) ) {
                $error->add( '001', 'File upload failed.' );
            }
            if ( $error->has_errors() ) {
                // Return errors
                wp_send_json_error( $error );
            } else {
                // Security: Sanitize and validate the file path to prevent path traversal attacks
                $upload_url = sanitize_text_field( $_POST['url'] );
                // Get uploads directory information
                $upload_dir = wp_get_upload_dir();
                if ( !empty( $upload_dir['error'] ) || empty( $upload_dir['basedir'] ) || empty( $upload_dir['baseurl'] ) ) {
                    $error->add( '004', 'Upload directory is not available. Please check your WordPress uploads configuration.' );
                    wp_send_json_error( $error );
                    return;
                }
                $upload_basedir = $upload_dir['basedir'];
                $upload_baseurl = $upload_dir['baseurl'];
                // Verify that the URL is within the uploads directory
                if ( strpos( $upload_url, $upload_baseurl ) !== 0 ) {
                    $error->add( '004', 'Invalid file path. File must be within the uploads directory.' );
                    wp_send_json_error( $error );
                    return;
                }
                // Extract the relative path from the uploads directory
                $relative_path = str_replace( $upload_baseurl, '', $upload_url );
                // Remove leading slash if present
                $relative_path = ltrim( $relative_path, '/' );
                // Security: Prevent path traversal attacks by removing any '../' sequences
                $relative_path = str_replace( '..', '', $relative_path );
                // Handle paths for both single and multisite installations
                if ( is_multisite() ) {
                    // For multisite, remove the duplicate sites/[blog_id] from path
                    // as it's already included in wp_get_upload_dir()['basedir']
                    $blog_id = get_current_blog_id();
                    $relative_path = preg_replace( "#^sites/{$blog_id}/#", '', $relative_path );
                }
                // Construct the full file path
                $csv_file = $upload_basedir . '/' . $relative_path;
                // Security: Resolve the real path and verify it's still within uploads directory
                $real_csv_file = realpath( $csv_file );
                $real_upload_basedir = realpath( $upload_basedir );
                // Verify file exists and is readable
                if ( $real_csv_file === false || !is_readable( $real_csv_file ) ) {
                    $error->add( '005', 'File not found or not readable.' );
                    wp_send_json_error( $error );
                    return;
                }
                // Security: Ensure the resolved path is within the uploads directory (prevents path traversal)
                if ( $real_upload_basedir === false || strpos( $real_csv_file, $real_upload_basedir ) !== 0 ) {
                    $error->add( '006', 'Invalid file path. File must be within the uploads directory.' );
                    wp_send_json_error( $error );
                    return;
                }
                // Security: Verify file extension is .csv
                $file_extension = strtolower( pathinfo( $real_csv_file, PATHINFO_EXTENSION ) );
                if ( $file_extension !== 'csv' ) {
                    $error->add( '007', 'Invalid file type. Only CSV files are allowed.' );
                    wp_send_json_error( $error );
                    return;
                }
                // Use the resolved real path for file operations
                $csv_file = $real_csv_file;
                $delimiter = $this->detectDelimiter( $csv_file );
                if ( $delimiter === false ) {
                    $error->add( '008', 'CSV file could not be read.' );
                    wp_send_json_error( $error );
                    return;
                }
                $locations = $this->parse_csv_locations( $csv_file, $delimiter );
                if ( is_wp_error( $locations ) ) {
                    wp_send_json_error( $locations );
                    return;
                }
                // Chunked import (same pattern as CSV export) to stay under PHP timeouts.
                $total_locations = count( $locations );
                $page = ( isset( $_POST['page'] ) ? max( 1, intval( $_POST['page'] ) ) : 1 );
                if ( isset( $_POST['per_page'] ) ) {
                    $per_page = max( 10, min( 100, intval( $_POST['per_page'] ) ) );
                } else {
                    // Backward compatible: process the whole file in one request.
                    $per_page = max( 1, $total_locations );
                }
                $offset = ($page - 1) * $per_page;
                $chunk = array_slice( $locations, $offset, $per_page );
                if ( function_exists( 'set_time_limit' ) ) {
                    @set_time_limit( 120 );
                }
                // Create or Update the posts
                // Determine post status based on POST data (from checkbox)
                // Read directly from POST to use immediate value without saving first
                // If checkbox is checked in POST, publish immediately; otherwise use draft (default)
                $publish_immediately = isset( $_POST['oum_csv_import_publish_immediately'] ) && $_POST['oum_csv_import_publish_immediately'] === 'on';
                $post_status = ( $publish_immediately ? 'publish' : 'draft' );
                $cnt_imported_locations = 0;
                $term_id_cache = array();
                $available_custom_fields = get_option( 'oum_custom_fields', array() );
                $this->begin_csv_import_boost();
                try {
                    foreach ( $chunk as $location ) {
                        // Marker categories
                        $types = ( isset( $location['type'] ) ? (string) $location['type'] : '' );
                        if ( $types ) {
                            // Convert term names to term IDs for hierarchical taxonomy compatibility
                            // WordPress requires term IDs (not names) for hierarchical taxonomies in tax_input
                            $types = $this->get_csv_import_type_ids( $types, $term_id_cache );
                        }
                        $location_title = ( isset( $location['title'] ) ? $location['title'] : '' );
                        $location_text = ( isset( $location['text'] ) ? $location['text'] : '' );
                        // author
                        $wp_author_id = ( isset( $location['wp_author_id'] ) && $location['wp_author_id'] != '' ? $location['wp_author_id'] : get_current_user_id() );
                        // Update only when post_id points to an existing location.
                        // Otherwise insert a new location (empty/invalid IDs must not
                        // be passed to wp_insert_post as ID, or WordPress creates a duplicate).
                        $location_post_id = ( isset( $location['post_id'] ) ? intval( trim( (string) $location['post_id'] ) ) : 0 );
                        $is_existing_location = $location_post_id > 0 && get_post_type( $location_post_id ) === 'oum-location';
                        $post_data = array(
                            'post_author' => $wp_author_id,
                            'post_type'   => 'oum-location',
                            'post_title'  => $location_title,
                            'post_name'   => sanitize_title( $location_title ),
                            'post_status' => $post_status,
                            'tax_input'   => array(
                                'oum-type' => $types,
                            ),
                        );
                        // Set excerpt on insert so save_fields does not need a second wp_update_post.
                        if ( !$is_existing_location ) {
                            $post_data['post_excerpt'] = $this->get_csv_import_excerpt( $location_text );
                        }
                        if ( $is_existing_location ) {
                            $post_data['ID'] = $location_post_id;
                            $insert_post = wp_update_post( $post_data );
                        } else {
                            $insert_post = wp_insert_post( $post_data );
                        }
                        if ( $insert_post ) {
                            // Add fields
                            $subtitle_value = '';
                            if ( isset( $location['subtitle'] ) && $location['subtitle'] !== '' ) {
                                $subtitle_value = $location['subtitle'];
                            } elseif ( isset( $location['address'] ) ) {
                                $subtitle_value = $location['address'];
                            }
                            // Support both "lng" and "long" for longitude (CSV export/Sheets often use "long")
                            $lat_value = ( isset( $location['lat'] ) ? $location['lat'] : '' );
                            $lng_value = ( isset( $location['lng'] ) ? $location['lng'] : (( isset( $location['long'] ) ? $location['long'] : '' )) );
                            // Normalize decimal separator: comma to period (e.g. European "51,5706508" -> "51.5706508")
                            if ( $lat_value !== '' ) {
                                $lat_value = str_replace( ',', '.', trim( $lat_value ) );
                            }
                            if ( $lng_value !== '' ) {
                                $lng_value = str_replace( ',', '.', trim( $lng_value ) );
                            }
                            $has_geometry_columns = array_key_exists( 'geometry_type', $location ) || array_key_exists( 'geometry', $location );
                            $geometry_type = ( isset( $location['geometry_type'] ) ? sanitize_key( $location['geometry_type'] ) : 'point' );
                            if ( !in_array( $geometry_type, array('point', 'polyline', 'polygon'), true ) ) {
                                $geometry_type = 'point';
                            }
                            $geometry_value = ( isset( $location['geometry'] ) ? trim( (string) $location['geometry'] ) : '' );
                            if ( !isset( $location['geometry_type'] ) && $geometry_value !== '' ) {
                                $decoded_geometry = json_decode( $geometry_value, true );
                                if ( is_array( $decoded_geometry ) && isset( $decoded_geometry['type'] ) ) {
                                    if ( $decoded_geometry['type'] === 'LineString' ) {
                                        $geometry_type = 'polyline';
                                    } elseif ( $decoded_geometry['type'] === 'Polygon' ) {
                                        $geometry_type = 'polygon';
                                    }
                                }
                            }
                            // Older CSV exports did not contain geometry columns. When updating existing
                            // locations from such files, keep stored line/area geometry intact.
                            if ( !$has_geometry_columns ) {
                                $existing_location_data = get_post_meta( $insert_post, '_oum_location_key', true );
                                if ( is_array( $existing_location_data ) && isset( $existing_location_data['geometry_type'] ) ) {
                                    $geometry_type = ( in_array( $existing_location_data['geometry_type'], array('polyline', 'polygon'), true ) ? $existing_location_data['geometry_type'] : 'point' );
                                    $geometry_value = ( isset( $existing_location_data['geometry'] ) && is_array( $existing_location_data['geometry'] ) ? wp_json_encode( $existing_location_data['geometry'] ) : '' );
                                }
                            }
                            $fields = array(
                                'oum_location_nonce'             => $nonce,
                                'oum_location_image'             => ( isset( $location['image'] ) ? $location['image'] : '' ),
                                'oum_location_video'             => ( isset( $location['video'] ) ? $location['video'] : '' ),
                                'oum_location_audio'             => ( isset( $location['audio'] ) ? $location['audio'] : '' ),
                                'oum_location_address'           => $subtitle_value,
                                'oum_location_lat'               => $lat_value,
                                'oum_location_lng'               => $lng_value,
                                'oum_location_zoom'              => ( isset( $location['zoom'] ) ? $location['zoom'] : '' ),
                                'oum_geometry_type'              => $geometry_type,
                                'oum_location_geometry'          => $geometry_value,
                                'oum_location_text'              => $location_text,
                                'oum_location_notification'      => ( isset( $location['notification'] ) ? $location['notification'] : '' ),
                                'oum_location_author_name'       => ( isset( $location['author_name'] ) ? $location['author_name'] : '' ),
                                'oum_location_author_email'      => ( isset( $location['author_email'] ) ? $location['author_email'] : '' ),
                                'oum_location_votes'             => ( isset( $location['votes'] ) ? $location['votes'] : '' ),
                                'oum_location_star_rating_avg'   => ( isset( $location['star_rating_avg'] ) ? $location['star_rating_avg'] : '' ),
                                'oum_location_star_rating_count' => ( isset( $location['star_rating_count'] ) ? $location['star_rating_count'] : '' ),
                            );
                            // Add custom fields
                            $customfields = array_filter( $location, function ( $val, $key ) {
                                return strpos( $key, 'CUSTOMFIELD' ) === 0;
                            }, ARRAY_FILTER_USE_BOTH );
                            foreach ( $customfields as $key => $val ) {
                                $key_parts = explode( '_', $key );
                                if ( !isset( $key_parts[1] ) || $key_parts[1] === '' ) {
                                    continue;
                                }
                                $id = $key_parts[1];
                                $fieldtype = ( isset( $available_custom_fields[$id]['fieldtype'] ) ? $available_custom_fields[$id]['fieldtype'] : 'text' );
                                // Opening hours use pipes to separate time blocks and must stay a string for parsing.
                                if ( $fieldtype !== 'opening_hours' && $val && strpos( $val, '|' ) !== false ) {
                                    $val = explode( '|', $val );
                                }
                                $fields['oum_location_custom_fields'][$id] = $val;
                            }
                            // Validate and Save
                            \OpenUserMapPlugin\Base\LocationController::save_fields( $insert_post, $fields );
                            $cnt_imported_locations++;
                        }
                    }
                } finally {
                    $this->end_csv_import_boost();
                }
                $processed = $offset + count( $chunk );
                $has_more = $processed < $total_locations;
                wp_send_json_success( array(
                    'imported'  => $cnt_imported_locations,
                    'processed' => $processed,
                    'total'     => $total_locations,
                    'page'      => $page,
                    'per_page'  => $per_page,
                    'has_more'  => $has_more,
                    'message'   => $cnt_imported_locations . ' Locations have been imported successfully.',
                ) );
            }
        }
    }

    /**
     * Add settings updated message
     * 
     * This function is hooked to 'update_option' and adds a success message when settings are saved.
     * Guards prevent fatal errors on non-admin requests (wp-login.php, frontend, AJAX, cron, etc.)
     * where add_settings_error() may not be loaded.
     */
    public function add_settings_updated_message( $option, $old_value, $value ) {
        // Prevent fatals on wp-login.php, frontend, cron, CLI, etc.
        // add_settings_error() is only available in admin context (loaded from wp-admin/includes/options.php)
        if ( !is_admin() || !function_exists( 'add_settings_error' ) ) {
            return;
        }
        // Only when our settings form is being saved (options.php POST)
        if ( empty( $_POST['option_page'] ) ) {
            return;
        }
        $allowed_groups = array(
            'open-user-map-settings-group',
            'open-user-map-settings-group-wizard-1',
            'open-user-map-settings-group-wizard-2',
            'open-user-map-settings-getting-started-notice',
            'open-user-map-settings-update-notice'
        );
        if ( !in_array( $_POST['option_page'], $allowed_groups, true ) ) {
            return;
        }
        // Only add message for our plugin settings
        if ( strpos( $option, 'oum_' ) !== 0 ) {
            return;
        }
        global $wp_settings_errors;
        // Check if we already added our message
        if ( !empty( $wp_settings_errors ) ) {
            foreach ( $wp_settings_errors as $error ) {
                if ( $error['setting'] === 'oum_messages' && $error['code'] === 'oum_message' ) {
                    return;
                    // Message already exists, don't add another one
                }
            }
        }
        add_settings_error(
            'oum_messages',
            'oum_message',
            __( 'Settings Saved', 'open-user-map' ),
            'updated'
        );
    }

    /**
     * Flush rewrite rules when the single-page setting changes.
     *
     * This ensures location permalinks start/stop working immediately after
     * enabling or disabling single pages.
     *
     * @param mixed  $old_value Previous option value.
     * @param mixed  $value     New option value.
     * @param string $option    Option name.
     */
    public function flush_rewrite_rules_for_single_page_setting( $old_value, $value, $option ) {
        // Only flush when the value actually changed.
        if ( $old_value === $value ) {
            return;
        }
        flush_rewrite_rules();
    }

    /**
     * Validate advanced filter sections
     */
    public function validate_advanced_filter_sections( $input ) {
        if ( !is_array( $input ) ) {
            return array();
        }
        $validated_sections = array();
        $has_marker_categories_section = false;
        $has_searchbar_section = false;
        foreach ( $input as $index => $section ) {
            if ( !is_array( $section ) ) {
                continue;
            }
            $validated_section = array();
            // Validate type
            if ( isset( $section['type'] ) && in_array( $section['type'], array(
                'custom_field',
                'html',
                'marker_categories',
                'searchbar'
            ) ) ) {
                $validated_section['type'] = sanitize_text_field( $section['type'] );
            } else {
                continue;
                // Skip invalid sections
            }
            // Only allow one Marker Categories section
            if ( $validated_section['type'] === 'marker_categories' ) {
                if ( $has_marker_categories_section ) {
                    continue;
                }
                $has_marker_categories_section = true;
            }
            // Only allow one Searchbar section
            if ( $validated_section['type'] === 'searchbar' ) {
                if ( $has_searchbar_section ) {
                    continue;
                }
                $has_searchbar_section = true;
            }
            // Validate custom field ID if type is custom_field
            if ( $validated_section['type'] === 'custom_field' && isset( $section['custom_field_id'] ) ) {
                $validated_section['custom_field_id'] = sanitize_text_field( $section['custom_field_id'] );
                // Validate checkbox relation if set (only for checkbox fields)
                if ( isset( $section['checkbox_relation'] ) && in_array( $section['checkbox_relation'], array('OR', 'AND') ) ) {
                    $validated_section['checkbox_relation'] = sanitize_text_field( $section['checkbox_relation'] );
                }
            }
            // Validate HTML content if type is html
            if ( $validated_section['type'] === 'html' && isset( $section['html_content'] ) ) {
                $validated_section['html_content'] = wp_kses_post( $section['html_content'] );
            }
            $validated_sections[] = $validated_section;
        }
        return $validated_sections;
    }

    /**
     * Preserve active tab in redirect URL after form submission
     * 
     * This function hooks into WordPress redirects to add the active tab parameter
     * so users stay on the same tab after saving settings.
     */
    public function preserve_active_tab_in_redirect( $location ) {
        // Only modify redirects for our settings page
        if ( strpos( $location, 'open-user-map-settings' ) === false ) {
            return $location;
        }
        // Get the active tab from POST data (form submission)
        if ( isset( $_POST['oum_active_tab'] ) && !empty( $_POST['oum_active_tab'] ) ) {
            $active_tab = sanitize_text_field( $_POST['oum_active_tab'] );
            // Add tab parameter to redirect URL
            $separator = ( strpos( $location, '?' ) !== false ? '&' : '?' );
            $location = $location . $separator . 'tab=' . urlencode( $active_tab );
        }
        return $location;
    }

}
