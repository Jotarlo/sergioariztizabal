<?php
/**
 * Plugin Name: Gestión de Retos Sergio Aristizábal
 * Description: Retos reutilizables con inscripción pública, evaluación privada y clasificación.
 * Version: 1.3.6
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * License: GPL-2.0-or-later
 * Text Domain: gestion-retos-sergio
 */

defined( 'ABSPATH' ) || exit;

define( 'SARETOS_VERSION', '1.3.6' );
define( 'SARETOS_FILE', __FILE__ );

require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/includes/public.php';
require_once __DIR__ . '/includes/registration.php';
require_once __DIR__ . '/includes/evaluation.php';
require_once __DIR__ . '/includes/evaluators.php';

register_activation_hook( __FILE__, 'saretos_install' );
add_action( 'plugins_loaded', 'saretos_maybe_upgrade' );

add_action( 'admin_menu', 'saretos_admin_menu' );
add_action( 'admin_enqueue_scripts', 'saretos_admin_assets' );
add_action( 'admin_post_saretos_evaluator_create', 'saretos_create_evaluator' );
add_action( 'admin_post_saretos_save', 'saretos_save_challenge' );
add_action( 'admin_post_saretos_settings', 'saretos_save_settings' );
add_action( 'wp_ajax_saretos_grade', 'saretos_grade_ajax' );
add_action( 'wp_ajax_nopriv_saretos_grade', 'saretos_grade_forbidden' );
add_action( 'admin_post_saretos_grade', 'saretos_grade_submit' );
add_action( 'admin_post_saretos_csv', 'saretos_csv' );
add_action( 'admin_post_saretos_purge_demo', 'saretos_purge_demo' );
add_action( 'template_redirect', 'saretos_route_request', 1 );
add_action( 'wp_enqueue_scripts', 'saretos_public_assets' );
add_filter( 'elementor/frontend/the_content', 'saretos_elementor_band' );
add_shortcode( 'sa_retos_banda', 'saretos_band_shortcode' );
add_filter( 'the_content', 'saretos_page_content', 50 );
add_filter( 'astra_the_title_enabled', 'saretos_astra_title' );
add_filter( 'astra_get_content_layout', 'saretos_astra_layout' );
add_filter( 'astra_page_layout', 'saretos_astra_sidebar' );
add_filter( 'wp_robots', 'saretos_page_robots' );
add_action( 'pre_get_posts', 'saretos_hide_search' );
add_filter( 'wp_nav_menu_objects', 'saretos_nav_menu_items', 10, 2 );
add_filter( 'body_class', 'saretos_public_body_class' );
add_filter( 'wp_page_menu_args', 'saretos_page_menu_args' );
add_filter( 'wp_page_menu', 'saretos_page_menu_link', 10, 2 );
add_filter( 'wp_list_pages_excludes', 'saretos_hide_page_list' );
add_filter( 'wp_sitemaps_posts_query_args', 'saretos_hide_sitemap', 10, 2 );
add_filter( 'surerank_exclude_posts_from_sitemap', 'saretos_hide_page_list' );
add_filter( 'surerank_robots_meta_array', 'saretos_surerank_robots' );
