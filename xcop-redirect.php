<?php
/*
Plugin Name: XCOP Redirect
Plugin URI: https://xcoptech.com/xcop-redirect
Description: A powerful and customizable WordPress plugin that redirects users based on browser history length and referrer source. Perfect for tailoring user experiences, such as redirecting new tab openings from search engines. Features a modern admin settings page with enable/disable toggle, easy configuration, and automatic updates via GitHub.
Version: 1.2.0
Author: XCOP
Author URI: https://xcoptech.com/
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: xcop-redirect
Domain Path: /languages
Update URI: https://raw.githubusercontent.com/xcoptech/xcop-redirect/main/updates/xcop-redirect.json
Network: false
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('คุณไม่สามารถเข้าถึงไฟล์นี้โดยตรงได้');
}

// Define plugin constants
define('XCOP_REDIRECT_VERSION', '1.2.0');
define('XCOP_REDIRECT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('XCOP_REDIRECT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('XCOP_TELEMETRY_API_URL', 'http://localhost:8000/api/v1/telemetry'); // API endpoint

// Include required files
require_once XCOP_REDIRECT_PLUGIN_PATH . 'includes/admin-settings.php';
require_once XCOP_REDIRECT_PLUGIN_PATH . 'includes/redirect-functions.php';
require_once XCOP_REDIRECT_PLUGIN_PATH . 'includes/telemetry-client.php';
require_once XCOP_REDIRECT_PLUGIN_PATH . 'includes/bot-detection.php';
require_once XCOP_REDIRECT_PLUGIN_PATH . 'includes/security.php';
require_once XCOP_REDIRECT_PLUGIN_PATH . 'includes/updates.php';
require_once XCOP_REDIRECT_PLUGIN_PATH . 'includes/utilities.php';

// Initialize plugin settings
function xcop_register_settings() {
    $defaults = array(
        'xcop_redirect_url' => 'https://example.com',
        'xcop_enable_referrer_check' => '1',
        'xcop_referrer_domain' => 'google.com',
        'xcop_enable_redirect' => '1',
        'xcop_history_length_check' => '1',
        'xcop_min_history_length' => '1',
        'xcop_delay_redirect' => '100', // milliseconds
        'xcop_enable_logging' => '0',
        'xcop_whitelist_ips' => '', // comma separated IPs to whitelist
        'xcop_blacklist_ips' => '', // comma separated IPs to blacklist
        'xcop_telemetry_optin' => '1' // Default opt-in for telemetry
    );
    
    // Add options with defaults
    foreach ($defaults as $option => $default_value) {
        add_option($option, $default_value);
    }
    
    // Register settings with enhanced validation
    register_setting('xcop_options_group', 'xcop_redirect_url', array(
        'sanitize_callback' => 'xcop_sanitize_url',
        'default' => $defaults['xcop_redirect_url']
    ));
    register_setting('xcop_options_group', 'xcop_enable_referrer_check', 'xcop_sanitize_checkbox');
    register_setting('xcop_options_group', 'xcop_referrer_domain', array(
        'sanitize_callback' => 'xcop_sanitize_domain',
        'default' => $defaults['xcop_referrer_domain']
    ));
    register_setting('xcop_options_group', 'xcop_enable_redirect', 'xcop_sanitize_checkbox');
    register_setting('xcop_options_group', 'xcop_history_length_check', 'xcop_sanitize_checkbox');
    register_setting('xcop_options_group', 'xcop_min_history_length', array(
        'sanitize_callback' => 'xcop_sanitize_number',
        'default' => $defaults['xcop_min_history_length']
    ));
    register_setting('xcop_options_group', 'xcop_delay_redirect', array(
        'sanitize_callback' => 'xcop_sanitize_delay',
        'default' => $defaults['xcop_delay_redirect']
    ));
    register_setting('xcop_options_group', 'xcop_enable_logging', 'xcop_sanitize_checkbox');
    register_setting('xcop_options_group', 'xcop_whitelist_ips', 'xcop_sanitize_ip_list');
    register_setting('xcop_options_group', 'xcop_blacklist_ips', 'xcop_sanitize_ip_list');
    register_setting('xcop_options_group', 'xcop_telemetry_optin', 'xcop_sanitize_checkbox');
}
add_action('admin_init', 'xcop_register_settings');

// Plugin activation hook
function xcop_activate_plugin() {
    xcop_register_settings();
    
    // Create log file if logging is enabled
    if (get_option('xcop_enable_logging', '0') === '1') {
        $log_file = WP_CONTENT_DIR . '/xcop-redirect.log';
        if (!file_exists($log_file)) {
            file_put_contents($log_file, "XCOP Redirect Log Started: " . current_time('Y-m-d H:i:s') . "\n");
        }
    }
    
    flush_rewrite_rules();
    xcop_log('Plugin activated');
}
register_activation_hook(__FILE__, 'xcop_activate_plugin');

// Plugin deactivation hook
function xcop_deactivate_plugin() {
    flush_rewrite_rules();
    delete_transient('xcop_update_check');
    
    xcop_log('Plugin deactivated');
}
register_deactivation_hook(__FILE__, 'xcop_deactivate_plugin');

// Uninstall hook - clean up everything
function xcop_uninstall_plugin() {
    // Delete all options
    $options = array(
        'xcop_redirect_url', 'xcop_enable_referrer_check', 'xcop_referrer_domain',
        'xcop_enable_redirect', 'xcop_history_length_check', 'xcop_min_history_length',
        'xcop_delay_redirect', 'xcop_enable_logging', 'xcop_whitelist_ips', 'xcop_blacklist_ips',
        'xcop_telemetry_optin'
    );
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Clean up transients
    delete_transient('xcop_update_check');
    
    // Delete log file
    $log_file = WP_CONTENT_DIR . '/xcop-redirect.log';
    if (file_exists($log_file)) {
        unlink($log_file);
    }
}
register_uninstall_hook(__FILE__, 'xcop_uninstall_plugin');

// Dashboard widget for quick status
function xcop_dashboard_widget() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    wp_add_dashboard_widget(
        'xcop_redirect_status',
        'XCOP Redirect Status',
        'xcop_dashboard_widget_content'
    );
}
add_action('wp_dashboard_setup', 'xcop_dashboard_widget');

function xcop_dashboard_widget_content() {
    $is_enabled = get_option('xcop_enable_redirect', '1') === '1';
    $redirect_url = get_option('xcop_redirect_url', '');
    $referrer_domain = get_option('xcop_referrer_domain', '');
    
    echo '<div style="padding: 10px;">';
    echo '<p><strong>สถานะ:</strong> <span style="color: ' . ($is_enabled ? '#28a745' : '#dc3545') . ';">' . ($is_enabled ? 'เปิดใช้งาน' : 'ปิดใช้งาน') . '</span></p>';
    if ($is_enabled) {
        echo '<p><strong>URL ปลายทาง:</strong> <code>' . esc_html($redirect_url) . '</code></p>';
        echo '<p><strong>โดเมนแหล่งที่มา:</strong> <code>' . esc_html($referrer_domain) . '</code></p>';
    }
    echo '<p><a href="' . admin_url('admin.php?page=xcop-settings') . '" class="button-primary">จัดการการตั้งค่า</a></p>';
    echo '</div>';
}