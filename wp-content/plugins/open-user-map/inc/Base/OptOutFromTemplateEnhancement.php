<?php
/**
 * @package OpenUserMapPlugin
 */

namespace OpenUserMapPlugin\Base;

/**
 * Handles Open User Map's WP 6.9 template enhancement opt-out logic.
 */
class OptOutFromTemplateEnhancement
{
    /**
     * Tracks whether WP 6.9 template enhancement buffer was opted out.
     *
     * @var bool
     */
    private $wp69_opt_out_active = false;

    /**
     * Stores a short reason for current WP 6.9 decision.
     *
     * @var string
     */
    private $wp69_opt_out_reason = 'not-evaluated';

    /**
     * Conditionally opt out of WP 6.9 template enhancement output buffer.
     *
     * Must run before template inclusion so WordPress can skip creating
     * the template enhancement buffer for this request.
     *
     * @return void
     */
    public function maybe_disable_wp69_template_enhancement_buffer()
    {
      if (is_admin()) {
        $this->wp69_opt_out_reason = 'admin';
        return;
      }

      // Safe on WP < 6.9 where this feature does not exist.
      if (!function_exists('wp_should_output_buffer_template_for_enhancement')) {
        $this->wp69_opt_out_reason = 'wp<6.9';
        return;
      }

      $should_disable = $this->is_oum_likely_to_render_on_current_request();

      // Extension point for advanced integrations (templates/builders/custom render paths).
      $should_disable = apply_filters('oum_should_disable_wp69_template_enhancement_buffer', $should_disable);

      if ($should_disable) {
        add_filter('wp_should_output_buffer_template_for_enhancement', '__return_false', PHP_INT_MAX);
        $this->wp69_opt_out_active = true;
        $this->wp69_opt_out_reason = 'predicted-oum';
      } else {
        $this->wp69_opt_out_reason = 'not-predicted';
      }
    }

    /**
     * Determine whether the current request is likely to render OUM output.
     *
     * @return bool
     */
    private function is_oum_likely_to_render_on_current_request()
    {
      // 1) Fast-path: queried object (most accurate signal for current request).
      $queried_object = get_queried_object();
      if ($queried_object instanceof \WP_Post && $this->post_likely_renders_oum($queried_object)) {
        return true;
      }

      // 2) Fast-path: active widgets (common for global footer/sidebar shortcodes).
      if ($this->active_widgets_likely_render_oum()) {
        return true;
      }

      // 3) Non-singular fallback: inspect main query post content/blocks only.
      // Keep this lightweight and avoid expensive per-post meta scans.
      global $wp_query;
      if (isset($wp_query->posts) && is_array($wp_query->posts)) {
        foreach ($wp_query->posts as $post) {
          if ($post instanceof \WP_Post && $this->post_content_or_block_likely_renders_oum($post)) {
            return true;
          }
        }
      }

      return false;
    }

    /**
     * Check string for any known OUM shortcodes.
     *
     * @param string $content Content to inspect.
     * @return bool
     */
    private function post_content_has_oum_shortcode($content)
    {
      if (!is_string($content) || $content === '') {
        return false;
      }

      $shortcodes = array(
        'open-user-map',
        'open-user-map-form',
        'open-user-map-gallery',
        'open-user-map-location',
        'open-user-map-list',
      );

      foreach ($shortcodes as $shortcode) {
        if (has_shortcode($content, $shortcode)) {
          return true;
        }
      }

      return false;
    }

    /**
     * Determine if a single post is likely to render OUM.
     *
     * Covers classic shortcodes, OUM block, builder metadata, and ACF/custom meta
     * values containing OUM shortcodes.
     *
     * @param \WP_Post $post Post object.
     * @return bool
     */
    private function post_likely_renders_oum($post)
    {
      // 1) Classic shortcode + Gutenberg block detection.
      if ($this->post_content_or_block_likely_renders_oum($post)) {
        return true;
      }

      // 2) Known page-builder data stores (singular/queried post).
      $builder_meta_keys = array(
        '_elementor_data',        // Elementor
        '_bricks_page_content_2', // Bricks
        '_bricks_data',           // Bricks (alternate key used in some setups)
        '_fl_builder_data',       // Beaver Builder (published)
        '_fl_builder_draft',      // Beaver Builder (draft)
      );

      foreach ($builder_meta_keys as $meta_key) {
        $meta_value = get_post_meta($post->ID, $meta_key, true);
        if (is_string($meta_value) && $meta_value !== '') {
          // Elementor widget instance id.
          if (strpos($meta_value, 'open_user_map_widget') !== false) {
            return true;
          }

          // Fallback for embedded OUM shortcode text in builder payloads.
          if ($this->post_content_has_oum_shortcode($meta_value)) {
            return true;
          }
        }
      }

      return false;
    }

    /**
     * Lightweight per-post detection: classic shortcode + Gutenberg block.
     *
     * @param \WP_Post $post Post object.
     * @return bool
     */
    private function post_content_or_block_likely_renders_oum($post)
    {
      if ($this->post_content_has_oum_shortcode($post->post_content)) {
        return true;
      }

      if (function_exists('has_block') && has_block('open-user-map/map', $post)) {
        return true;
      }

      return false;
    }

    /**
     * Check active text/block widgets for OUM shortcodes.
     *
     * @return bool
     */
    private function active_widgets_likely_render_oum()
    {
      // Legacy/Classic text widgets.
      $text_widgets = get_option('widget_text');
      if (is_array($text_widgets)) {
        foreach ($text_widgets as $widget_config) {
          if (is_array($widget_config) && isset($widget_config['text']) && is_string($widget_config['text'])) {
            if ($this->post_content_has_oum_shortcode($widget_config['text'])) {
              return true;
            }
          }
        }
      }

      // Block widgets (widget_block option stores block markup per widget instance).
      $block_widgets = get_option('widget_block');
      if (is_array($block_widgets)) {
        foreach ($block_widgets as $widget_config) {
          if (is_array($widget_config) && isset($widget_config['content']) && is_string($widget_config['content'])) {
            if ($this->post_content_has_oum_shortcode($widget_config['content'])) {
              return true;
            }

            if (function_exists('has_block') && has_block('open-user-map/map', $widget_config['content'])) {
              return true;
            }
          }
        }
      }

      return false;
    }

    /**
     * Get WP 6.9/OUM diagnostics state for reporting.
     *
     * @return array
     */
    public function get_diagnostics_state()
    {
      $status = $this->wp69_opt_out_active ? 'ON' : 'OFF';

      return array(
        'template_enhancement_opt_out' => $status,
        'decision_reason' => $this->wp69_opt_out_reason,
      );
    }
}
