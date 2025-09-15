<?php
/*
Plugin Name: XCOP Redirect
Plugin URI: https://xcoptech.com/xcop-redirect
Description: A powerful and customizable WordPress plugin that redirects users based on browser history length and referrer source. Perfect for tailoring user experiences, such as redirecting new tab openings from search engines. Features a modern admin settings page with enable/disable toggle, easy configuration, and automatic updates via GitHub.
Version: 1.1.1
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
*/

// Initialize plugin settings
function xcop_register_settings() {
    add_option('xcop_redirect_url', 'https://example.com');
    add_option('xcop_enable_referrer_check', '0');
    add_option('xcop_referrer_domain', 'google.com');
    add_option('xcop_enable_redirect', '1'); // Default to enabled
    register_setting('xcop_options_group', 'xcop_redirect_url', 'esc_url_raw');
    register_setting('xcop_options_group', 'xcop_enable_referrer_check', 'sanitize_text_field');
    register_setting('xcop_options_group', 'xcop_referrer_domain', 'sanitize_text_field');
    register_setting('xcop_options_group', 'xcop_enable_redirect', 'sanitize_text_field');
}
add_action('admin_init', 'xcop_register_settings');

// Add top-level menu for settings
function xcop_options_page() {
    add_menu_page(
        'XCOP Redirect Settings',      // Page title
        'XCOP Redirect',              // Menu title
        'manage_options',             // Capability
        'xcop-settings',              // Menu slug
        'xcop_options_page_html',     // Callback function
        'dashicons-admin-links',      // Icon
        80                            // Position
    );
}
add_action('admin_menu', 'xcop_options_page');

// Render settings page with enhanced UI
function xcop_options_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap xcop-settings-wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <div class="xcop-settings-container">
            <form action="options.php" method="post">
                <?php
                settings_fields('xcop_options_group');
                do_settings_sections('xcop-settings');
                ?>
                <h2>Redirect Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="xcop_enable_redirect">Enable Redirect</label>
                            <span class="description">Toggle to enable or disable the redirect functionality.</span>
                        </th>
                        <td>
                            <input type="checkbox" id="xcop_enable_redirect" name="xcop_enable_redirect" value="1" <?php checked(get_option('xcop_enable_redirect', '1'), '1'); ?> />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="xcop_redirect_url">Redirect URL</label>
                            <span class="description">Enter the URL to redirect users when conditions are met.</span>
                        </th>
                        <td>
                            <input type="url" id="xcop_redirect_url" name="xcop_redirect_url" value="<?php echo esc_attr(get_option('xcop_redirect_url')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="xcop_enable_referrer_check">Enable Referrer Check</label>
                            <span class="description">Check if the user comes from a specific referrer (e.g., Google).</span>
                        </th>
                        <td>
                            <input type="checkbox" id="xcop_enable_referrer_check" name="xcop_enable_referrer_check" value="1" <?php checked(get_option('xcop_enable_referrer_check', '0'), '1'); ?> />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="xcop_referrer_domain">Referrer Domain</label>
                            <span class="description">Specify the referrer domain to check (e.g., google.com).</span>
                        </th>
                        <td>
                            <input type="text" id="xcop_referrer_domain" name="xcop_referrer_domain" value="<?php echo esc_attr(get_option('xcop_referrer_domain', 'google.com')); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings', 'primary', 'submit', true); ?>
            </form>
        </div>
    </div>
    <style>
        .xcop-settings-wrap {
            max-width: 800px;
            margin: 0 auto;
        }
        .xcop-settings-container {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .xcop-settings-container h2 {
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .form-table th .description {
            display: block;
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }
        .form-table input[type="url"],
        .form-table input[type="text"] {
            width: 100%;
            max-width: 400px;
        }
        .form-table input[type="checkbox"] {
            margin-right: 10px;
        }
        .button-primary {
            background: #0073aa;
            border-color: #006799;
        }
        .button-primary:hover {
            background: #006799;
        }
    </style>
    <?php
}

// Enqueue JavaScript for redirect logic
function xcop_enqueue_scripts() {
    $enable_redirect = get_option('xcop_enable_redirect', '1');
    if ($enable_redirect !== '1') {
        return; // Skip if redirect is disabled
    }

    $redirect_url = get_option('xcop_redirect_url', 'https://example.com');
    $enable_referrer_check = get_option('xcop_enable_referrer_check', '0');
    $referrer_domain = get_option('xcop_referrer_domain', 'google.com');
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const referrer = document.referrer || '';
            const shouldCheckReferrer = <?php echo json_encode($enable_referrer_check === '1'); ?>;
            const referrerDomain = <?php echo json_encode($referrer_domain); ?>;
            const shouldRedirect = window.history.length === 1 && 
                (!shouldCheckReferrer || referrer.includes(referrerDomain));

            if (shouldRedirect) {
                window.location.href = '<?php echo esc_url($redirect_url); ?>';
            }
        });
    </script>
    <?php
}
add_action('wp_head', 'xcop_enqueue_scripts');

// Check for plugin updates via GitHub
function xcop_check_for_updates($transient) {
    if (empty($transient->checked)) {
        return $transient;
    }

    $plugin_slug = 'xcop-redirect';
    $current_version = '1.1.1'; // Must match Version in plugin header
    $update_url = 'https://raw.githubusercontent.com/xcoptech/xcop-redirect/main/updates/xcop-redirect.json';

    // Get cached response or fetch new data
    $remote = get_transient('xcop_update_check');
    if (false === $remote) {
        $remote = wp_remote_get($update_url, array('timeout' => 10));
        set_transient('xcop_update_check', $remote, HOUR_IN_SECONDS * 12); // Cache for 12 hours
    }

    if (!is_wp_error($remote) && isset($remote['response']['code']) && $remote['response']['code'] == 200) {
        $data = json_decode(wp_remote_retrieve_body($remote));
        if ($data && version_compare($current_version, $data->version, '<')) {
            $transient->response[$plugin_slug . '/xcop-redirect.php'] = array(
                'slug' => $plugin_slug,
                'new_version' => $data->version,
                'url' => $data->homepage,
                'package' => $data->download_link,
                'tested' => $data->tested,
                'requires' => $data->requires,
                'requires_php' => $data->requires_php
            );
        }
    }

    return $transient;
}
add_filter('site_transient_update_plugins', 'xcop_check_for_updates');

// Clear transient cache when plugin is updated
function xcop_clear_update_transient() {
    delete_transient('xcop_update_check');
}
add_action('upgrader_process_complete', 'xcop_clear_update_transient', 10, 2);

// Add update notice in plugins page
function xcop_update_notice() {
    $plugin_data = get_plugin_data(__FILE__);
    $current_version = $plugin_data['Version'];
    $update_url = 'https://raw.githubusercontent.com/xcoptech/xcop-redirect/main/updates/xcop-redirect.json';

    $remote = get_transient('xcop_update_check');
    if (false === $remote) {
        $remote = wp_remote_get($update_url, array('timeout' => 10));
        set_transient('xcop_update_check', $remote, HOUR_IN_SECONDS * 12);
    }

    if (!is_wp_error($remote) && isset($remote['response']['code']) && $remote['response']['code'] == 200) {
        $data = json_decode(wp_remote_retrieve_body($remote));
        if ($data && version_compare($current_version, $data->version, '<')) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p>' . sprintf(
                __('XCOP Redirect %s is available! <a href="%s">Update now</a> or visit the <a href="%s">plugin settings</a> for more details.', 'xcop-redirect'),
                esc_html($data->version),
                esc_url(admin_url('plugins.php')),
                esc_url(admin_url('admin.php?page=xcop-settings'))
            ) . '</p>';
            echo '</div>';
        }
    }
}
add_action('admin_notices', 'xcop_update_notice');