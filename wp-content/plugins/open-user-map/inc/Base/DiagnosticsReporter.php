<?php
/**
 * @package OpenUserMapPlugin
 */

namespace OpenUserMapPlugin\Base;

class DiagnosticsReporter
{
    /**
     * WP 6.9 template enhancement opt-out handler.
     *
     * @var OptOutFromTemplateEnhancement
     */
    private $opt_out_from_template_enhancement;

    /**
     * @param OptOutFromTemplateEnhancement $opt_out_from_template_enhancement Opt-out handler instance.
     */
    public function __construct(OptOutFromTemplateEnhancement $opt_out_from_template_enhancement)
    {
      $this->opt_out_from_template_enhancement = $opt_out_from_template_enhancement;
    }

    /**
     * Print frontend diagnostics comment.
     *
     * This intentionally exposes only non-sensitive, non-critical context.
     * It is always printed on frontend requests to simplify support/debugging.
     *
     * @return void
     */
    public function print_frontend_diagnostics_comment()
    {
      if (is_admin()) {
        return;
      }

      // Only print diagnostics when explicitly requested via URL parameter:
      // ?oum_debug=1
      if (!isset($_GET['oum_debug']) || $_GET['oum_debug'] !== '1') {
        return;
      }

      $diagnostics_data = $this->get_frontend_diagnostics_data();

      // Allow support/devs to append custom debug fields when needed.
      // Example:
      // add_filter('oum_frontend_diagnostics_data', function ($data) {
      //   $locations = get_posts(array(
      //     'post_type' => 'oum-location',
      //     'post_status' => 'publish',
      //     'numberposts' => 5,
      //     'fields' => 'ids',
      //   ));
      //   $data['locations'] = $locations;
      //   return $data;
      // });
      $diagnostics_data = apply_filters('oum_frontend_diagnostics_data', $diagnostics_data);

      if (!is_array($diagnostics_data)) {
        $diagnostics_data = array();
      }

      $lines = array();
      foreach ($diagnostics_data as $key => $value) {
        $lines[] = sprintf('%s: %s', esc_html((string) $key), esc_html($this->normalize_debug_value($value)));
      }

      echo "\n<!-- Open User Map Diagnostics\n";
      foreach ($lines as $line) {
        echo "  - " . $line . "\n";
      }
      echo "-->\n";
    }

    /**
     * Build default frontend diagnostics payload.
     *
     * @return array
     */
    private function get_frontend_diagnostics_data()
    {
      $opt_out_state = $this->opt_out_from_template_enhancement->get_diagnostics_state();

      return array(
        'oum_version' => $this->get_oum_plugin_version(),
        'oum_edition' => $this->get_oum_plugin_edition(),
        'buffer_enhancement_opt_out' => $opt_out_state['template_enhancement_opt_out'],
        'buffer_enhancement_opt_out_reason' => $opt_out_state['decision_reason'],
      );
    }

    /**
     * Normalize debug values for readable output in HTML comments.
     *
     * @param mixed $value
     * @return string
     */
    private function normalize_debug_value($value)
    {
      if (is_array($value) || is_object($value)) {
        $encoded = wp_json_encode($value);
        return $encoded !== false ? $encoded : 'unserializable';
      }

      if (is_bool($value)) {
        return $value ? 'true' : 'false';
      }

      if ($value === null) {
        return 'null';
      }

      return (string) $value;
    }

    /**
     * Read OUM plugin version from main plugin header.
     *
     * @return string
     */
    private function get_oum_plugin_version()
    {
      $plugin_main_file = dirname(dirname(dirname(__FILE__))) . '/open-user-map.php';

      if (!file_exists($plugin_main_file)) {
        return 'unknown';
      }

      $version_data = get_file_data($plugin_main_file, array('Version' => 'Version'));
      if (!isset($version_data['Version']) || $version_data['Version'] === '') {
        return 'unknown';
      }

      return $version_data['Version'];
    }

    /**
     * Detect whether current OUM package is Free or PRO.
     *
     * @return string
     */
    private function get_oum_plugin_edition()
    {
      if (function_exists('oum_fs')) {
        return oum_fs()->is__premium_only() ? 'PRO' : 'Free';
      }

      return 'unknown';
    }
}
