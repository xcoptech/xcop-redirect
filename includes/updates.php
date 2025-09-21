<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit('คุณไม่สามารถเข้าถึงไฟล์นี้โดยตรงได้');
}

// Check for plugin updates via GitHub with enhanced security
function xcop_check_for_updates($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = 'xcop-redirect';
    $plugin_file = $plugin_slug . '/xcop-redirect.php';
    $current_version = XCOP_REDIRECT_VERSION;
    $update_url = 'https://raw.githubusercontent.com/xcoptech/xcop-redirect/main/updates/xcop-redirect.json';

    // Skip if this plugin is not in the checked list
    if (!isset($transient->checked[$plugin_file])) {
        return $transient;
    }

    // Get cached response or fetch new data
    $remote = get_transient('xcop_update_check');
    if (false === $remote) {
        $remote = wp_remote_get($update_url, array(
            'timeout' => 15,
            'sslverify' => true,
            'headers' => array(
                'User-Agent' => 'XCOP-Redirect/' . $current_version . '; ' . home_url()
            )
        ));
        
        if (!is_wp_error($remote) && wp_remote_retrieve_response_code($remote) === 200) {
            set_transient('xcop_update_check', $remote, HOUR_IN_SECONDS * 6); // Cache for 6 hours
        }
    }

    if (!is_wp_error($remote) && wp_remote_retrieve_response_code($remote) === 200) {
        $body = wp_remote_retrieve_body($remote);
        $data = json_decode($body, true);
        
        if ($data && 
            isset($data['version']) && 
            version_compare($current_version, $data['version'], '<') &&
            filter_var($data['download_link'] ?? '', FILTER_VALIDATE_URL)) {
            
            $transient->response[$plugin_file] = array(
                'slug' => $plugin_slug,
                'plugin' => $plugin_file,
                'new_version' => sanitize_text_field($data['version']),
                'url' => esc_url_raw($data['homepage'] ?? ''),
                'package' => esc_url_raw($data['download_link']),
                'tested' => sanitize_text_field($data['tested'] ?? ''),
                'requires' => sanitize_text_field($data['requires'] ?? ''),
                'requires_php' => sanitize_text_field($data['requires_php'] ?? ''),
                'compatibility' => new stdClass()
            );
            
            xcop_log("Update available: {$data['version']}");
        }
    }

    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'xcop_check_for_updates');

// Clear transient cache when plugin is updated
function xcop_clear_update_transient($upgrader, $hook_extra) {
    if (isset($hook_extra['plugins'])) {
        foreach ($hook_extra['plugins'] as $plugin) {
            if (strpos($plugin, 'xcop-redirect') !== false) {
                delete_transient('xcop_update_check');
                xcop_log('Plugin updated, clearing update cache');
                break;
            }
        }
    }
}
add_action('upgrader_process_complete', 'xcop_clear_update_transient', 10, 2);

// Add update notice in plugins page
function xcop_update_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $current_screen = get_current_screen();
    if (!$current_screen || $current_screen->id !== 'plugins') {
        return;
    }
    
    $current_version = XCOP_REDIRECT_VERSION;
    $update_url = 'https://raw.githubusercontent.com/xcoptech/xcop-redirect/main/updates/xcop-redirect.json';

    $remote = get_transient('xcop_update_check');
    if (false === $remote) {
        $remote = wp_remote_get($update_url, array(
            'timeout' => 10,
            'sslverify' => true
        ));
        if (!is_wp_error($remote) && wp_remote_retrieve_response_code($remote) === 200) {
            set_transient('xcop_update_check', $remote, HOUR_IN_SECONDS * 6);
        }
    }

    if (!is_wp_error($remote) && wp_remote_retrieve_response_code($remote) === 200) {
        $body = wp_remote_retrieve_body($remote);
        $data = json_decode($body, true);
        
        if ($data && 
            isset($data['version']) && 
            version_compare($current_version, $data['version'], '<')) {
            
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>XCOP Redirect:</strong> ' . sprintf(
                'เวอร์ชัน %s พร้อมใช้งานแล้ว! (ปัจจุบัน: %s) <a href="%s" class="button-primary">อัปเดตเลย</a> หรือดู<a href="%s">การตั้งค่า</a>',
                esc_html($data['version']),
                esc_html($current_version),
                esc_url(admin_url('plugins.php')),
                esc_url(admin_url('admin.php?page=xcop-settings'))
            ) . '</p>';
            echo '</div>';
        }
    }
}
add_action('admin_notices', 'xcop_update_notice');

// Enhanced update information display
function xcop_plugin_update_info($response, $plugin_data, $plugin_file) {
    if (strpos($plugin_file, 'xcop-redirect') === false) {
        return $response;
    }
    
    $update_url = 'https://raw.githubusercontent.com/xcoptech/xcop-redirect/main/updates/xcop-redirect.json';
    $remote = wp_remote_get($update_url, array(
        'timeout' => 10,
        'sslverify' => true
    ));
    
    if (!is_wp_error($remote) && wp_remote_retrieve_response_code($remote) === 200) {
        $body = wp_remote_retrieve_body($remote);
        $data = json_decode($body, true);
        
        if ($data && isset($data['version'])) {
            $response = array(
                'slug' => 'xcop-redirect',
                'plugin' => $plugin_file,
                'new_version' => $data['version'],
                'url' => $data['homepage'] ?? '',
                'package' => $data['download_link'] ?? '',
                'tested' => $data['tested'] ?? '',
                'requires' => $data['requires'] ?? '',
                'requires_php' => $data['requires_php'] ?? '',
                'sections' => array(
                    'description' => $data['description'] ?? $plugin_data['Description'],
                    'changelog' => $data['changelog'] ?? 'ดูรายละเอียดการอัปเดตที่ GitHub',
                    'faq' => 'สำหรับคำถามที่พบบ่อย กรุณาติดต่อที่ support@xcoptech.com',
                ),
                'banners' => array(
                    'low' => $data['banner_low'] ?? '',
                    'high' => $data['banner_high'] ?? ''
                ),
                'icons' => array(
                    '1x' => $data['icon_1x'] ?? '',
                    '2x' => $data['icon_2x'] ?? ''
                )
            );
        }
    }
    
    return $response;
}
add_filter('plugins_api', 'xcop_plugin_update_info', 10, 3);

// Auto-update functionality (optional)
function xcop_auto_update($update, $item) {
    if ($item->slug === 'xcop-redirect') {
        return true; // Enable auto-update
    }
    return $update;
}

add_filter('auto_update_plugin', 'xcop_auto_update', 10, 2);
