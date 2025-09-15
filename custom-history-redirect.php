<?php
/*
Plugin Name: XCOP Redirect
Plugin URI: https://xcoptech.com/xcop-redirect
Description: A powerful and customizable WordPress plugin that redirects users based on browser history length and referrer source. Perfect for tailoring user experiences, such as redirecting new tab openings from search engines. Features a modern and intuitive admin settings page for easy configuration.
Version: 1.0.0
Author: XCOP
Author URI: https://xcoptech.com/
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: xcop-redirect
Domain Path: /languages
*/

// Initialize plugin settings
function xcop_register_settings() {
    add_option('xcop_redirect_url', 'https://example.com');
    add_option('xcop_enable_referrer_check', '0');
    add_option('xcop_referrer_domain', 'google.com');
    register_setting('xcop_options_group', 'xcop_redirect_url', 'esc_url_raw');
    register_setting('xcop_options_group', 'xcop_enable_referrer_check', 'sanitize_text_field');
    register_setting('xcop_options_group', 'xcop_referrer_domain', 'sanitize_text_field');
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