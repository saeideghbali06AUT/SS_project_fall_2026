<?php
/**
 * Plugin Name: Faculty Opportunities Portal
 * Description: سامانه فارسی فرصت‌های دانشکده (راویان) با ظاهر اپل‌گونه، پروفایل اساتید، ثبت و تأیید آگهی، درخواست دانشجویان و کارت دسترسی اساتید.
 * Version: 2.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Faculty Portal Project
 * Text Domain: faculty-opportunities-portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FCP_VERSION', '2.1.0' );
define( 'FCP_DB_VERSION', '3' );
define( 'FCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'FCP_URL', plugin_dir_url( __FILE__ ) );

require_once FCP_DIR . 'includes/core.php';
require_once FCP_DIR . 'includes/db.php';
require_once FCP_DIR . 'includes/applications.php';
require_once FCP_DIR . 'includes/advice.php';
require_once FCP_DIR . 'includes/auth.php';
require_once FCP_DIR . 'includes/forms.php';
require_once FCP_DIR . 'includes/rest.php';
require_once FCP_DIR . 'includes/admin.php';
require_once FCP_DIR . 'includes/views.php';
require_once FCP_DIR . 'includes/views-account.php';
require_once FCP_DIR . 'includes/views-advice.php';

register_activation_hook( __FILE__, 'fcp_activate' );
register_deactivation_hook( __FILE__, 'fcp_deactivate' );

add_action( 'after_setup_theme', 'fcp_after_setup_theme' );
add_action( 'init', 'fcp_register_content' );
add_action( 'init', 'fcp_maybe_upgrade', 20 );
add_filter( 'template_include', 'fcp_template_include', 99 );
add_action( 'wp_enqueue_scripts', 'fcp_enqueue_assets' );
add_action( 'template_redirect', 'fcp_track_view' );
add_action( 'rest_api_init', 'fcp_register_rest' );
add_action( 'rest_api_init', 'fcp_register_advice_rest' );

// Professor forms (logged-in).
add_action( 'admin_post_fcp_save_opportunity', 'fcp_handle_save_opportunity' );
add_action( 'admin_post_fcp_save_profile', 'fcp_handle_save_profile' );
add_action( 'admin_post_fcp_change_password', 'fcp_handle_change_password' );
add_action( 'admin_post_fcp_opportunity_action', 'fcp_handle_opportunity_action' );
add_action( 'admin_post_fcp_update_application', 'fcp_handle_update_application' );
add_action( 'admin_post_fcp_save_advice', 'fcp_handle_save_advice' );
add_action( 'admin_post_fcp_advice_action', 'fcp_handle_advice_action' );
add_action( 'admin_post_fcp_download_resume', 'fcp_handle_download_resume' );

// Student application (public).
add_action( 'admin_post_nopriv_fcp_submit_application', 'fcp_handle_submit_application' );
add_action( 'admin_post_fcp_submit_application', 'fcp_handle_submit_application' );

// Login experience & protection.
add_action( 'wp_login_failed', 'fcp_login_failed' );
add_filter( 'authenticate', 'fcp_login_redirect_empty', 1 );
add_filter( 'authenticate', 'fcp_authenticate_throttle', 30, 2 );
add_action( 'wp_login', 'fcp_clear_failed_login' );
add_filter( 'show_admin_bar', 'fcp_hide_admin_bar' );
add_action( 'admin_init', 'fcp_block_admin_for_professors' );
add_filter( 'login_redirect', 'fcp_login_redirect', 10, 3 );

// Admin tools.
add_action( 'admin_menu', 'fcp_admin_menu' );
add_action( 'admin_enqueue_scripts', 'fcp_admin_assets' );
add_action( 'admin_post_fcp_save_settings', 'fcp_handle_save_settings' );
add_action( 'admin_post_fcp_create_cards', 'fcp_handle_create_cards' );
add_action( 'admin_post_fcp_reset_card', 'fcp_handle_reset_card' );
add_filter( 'manage_fcp_opportunity_posts_columns', 'fcp_admin_columns' );
add_action( 'manage_fcp_opportunity_posts_custom_column', 'fcp_admin_column_value', 10, 2 );
add_action( 'add_meta_boxes_fcp_opportunity', 'fcp_add_admin_meta_box' );
add_action( 'save_post_fcp_opportunity', 'fcp_save_admin_meta_box' );

add_action( 'add_meta_boxes_fcp_advice', 'fcp_add_advice_meta_box' );
add_action( 'save_post_fcp_advice', 'fcp_save_advice_meta_box' );
add_filter( 'manage_fcp_advice_posts_columns', 'fcp_advice_admin_columns' );
add_action( 'manage_fcp_advice_posts_custom_column', 'fcp_advice_admin_column_value', 10, 2 );
add_action( 'before_delete_post', 'fcp_advice_cleanup_votes' );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'fcp_plugin_action_links' );
