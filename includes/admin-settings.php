<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit(__('คุณไม่สามารถเข้าถึงไฟล์นี้โดยตรงได้', 'xcop-redirect'));
}

// Add top-level menu for settings
function xcop_options_page() {
    add_menu_page(
        __('XCOP Redirect Settings', 'xcop-redirect'),
        'XCOP Redirect',
        'manage_options',
        'xcop-settings',
        'xcop_options_page_html',
        'dashicons-admin-links',
        80
    );
}
add_action('admin_menu', 'xcop_options_page');

// Handle AJAX request for manual telemetry send
function xcop_handle_manual_telemetry() {
    // Verify nonce
    if (!check_ajax_referer('xcop_telemetry_nonce', 'nonce', false)) {
        wp_die(__('Security check failed', 'xcop-redirect'));
    }
    
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'xcop-redirect'));
    }
    
    // Return success response - actual sending will be handled by JavaScript
    wp_send_json_success(array('message' => __('Telemetry will be sent via client-side', 'xcop-redirect')));
}
add_action('wp_ajax_xcop_send_telemetry', 'xcop_handle_manual_telemetry');

// Render enhanced settings page
function xcop_options_page_html() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to access this page.', 'xcop-redirect'));
    }
    
    // Get plugin info
    $plugin_data = get_plugin_data(XCOP_REDIRECT_PLUGIN_PATH . 'xcop-redirect.php');
    $current_version = $plugin_data['Version'];
    
    // Handle form submission
    $settings_saved = false;
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'xcop_options_group-options')) {
        $settings_saved = true;
    }
    ?>
    <div class="wrap xcop-settings-wrap">
        <div class="xcop-header">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="xcop-version"><?php esc_html_e( 'Version', 'xcop-redirect' ); ?> <?php echo esc_html($current_version); ?></div>
        </div>
        <?php settings_errors(); ?>
        
        <div class="xcop-settings-container">
            <form action="options.php" method="post" id="xcop-settings-form">
                <?php
                settings_fields('xcop_options_group');
                do_settings_sections('xcop-settings');
                ?>
                
                <!-- Main Settings -->
                <div class="xcop-section">
                    <h2><?php esc_html_e( 'Main Settings', 'xcop-redirect' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="xcop_enable_redirect"><?php esc_html_e( 'Enable Redirect', 'xcop-redirect' ); ?></label>
                                <span class="description"><?php esc_html_e( 'Enable/disable the redirect system.', 'xcop-redirect' ); ?></span>
                            </th>
                            <td>
                                <label class="xcop-toggle">
                                    <input type="checkbox" id="xcop_enable_redirect" name="xcop_enable_redirect" value="1" <?php checked(get_option('xcop_enable_redirect', '1'), '1'); ?> />
                                    <span class="xcop-toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="xcop_redirect_url"><?php esc_html_e( 'Redirect URL', 'xcop-redirect' ); ?></label>
                                <span class="description"><?php esc_html_e( 'Enter the URL to redirect users to (must be https).', 'xcop-redirect' ); ?></span>
                            </th>
                            <td>
                                <input type="url" id="xcop_redirect_url" name="xcop_redirect_url" value="<?php echo esc_attr(get_option('xcop_redirect_url')); ?>" class="regular-text" required />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="xcop_delay_redirect"><?php esc_html_e( 'Delay (milliseconds)', 'xcop-redirect' ); ?></label>
                                <span class="description"><?php esc_html_e( 'Time to wait before redirecting (0-10000 ms).', 'xcop-redirect' ); ?></span>
                            </th>
                            <td>
                                <input type="number" id="xcop_delay_redirect" name="xcop_delay_redirect" value="<?php echo esc_attr(get_option('xcop_delay_redirect', '100')); ?>" min="0" max="10000" />
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Referrer Settings -->
                <div class="xcop-section">
                    <h2><?php esc_html_e( 'Referrer Settings', 'xcop-redirect' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="xcop_enable_referrer_check"><?php esc_html_e( 'Check Referrer', 'xcop-redirect' ); ?></label>
                                <span class="description"><?php esc_html_e( 'Check if the user comes from a specific referrer.', 'xcop-redirect' ); ?></span>
                            </th>
                            <td>
                                <label class="xcop-toggle">
                                    <input type="checkbox" id="xcop_enable_referrer_check" name="xcop_enable_referrer_check" value="1" <?php checked(get_option('xcop_enable_referrer_check', '1'), '1'); ?> />
                                    <span class="xcop-toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="xcop_referrer_domain"><?php esc_html_e( 'Referrer Domain', 'xcop-redirect' ); ?></label>
                                <span class="description"><?php esc_html_e( 'Specify the domain to check (e.g., google.com, bing.com).', 'xcop-redirect' ); ?></span>
                            </th>
                            <td>
                                <input type="text" id="xcop_referrer_domain" name="xcop_referrer_domain" value="<?php echo esc_attr(get_option('xcop_referrer_domain', 'google.com')); ?>" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- History Settings -->
                <div class="xcop-section">
                    <h2><?php esc_html_e( 'Browser History Settings', 'xcop-redirect' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="xcop_history_length_check"><?php esc_html_e( 'Check Browser History', 'xcop-redirect' ); ?></label>
                                <span class="description"><?php esc_html_e( 'Check history length to distinguish direct access from new tabs.', 'xcop-redirect' ); ?></span>
                            </th>
                            <td>
                                <label class="xcop-toggle">
                                    <input type="checkbox" id="xcop_history_length_check" name="xcop_history_length_check" value="1" <?php checked(get_option('xcop_history_length_check', '1'), '1'); ?> />
                                    <span class="xcop-toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="xcop_min_history_length"><?php esc_html_e( 'Minimum History Length', 'xcop-redirect' ); ?></label>
                                <span class="description"><?php esc_html_e( 'Redirect only when history is greater than this value (1 = new tab, 0 = all).', 'xcop-redirect' ); ?></span>
                            </th>
                            <td>
                                <input type="number" id="xcop_min_history_length" name="xcop_min_history_length" value="<?php echo esc_attr(get_option('xcop_min_history_length', '1')); ?>" min="0" max="100" />
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Advanced Settings -->
                <div class="xcop-section">
                    <h2><?php esc_html_e( 'Advanced Settings', 'xcop-redirect' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="xcop_enable_logging"><?php esc_html_e( 'Enable Logging', 'xcop-redirect' ); ?></label>
                                <span class="description"><?php esc_html_e( 'Log plugin activity (for troubleshooting).', 'xcop-redirect' ); ?></span>
                            </th>
                            <td>
                                <label class="xcop-toggle">
                                    <input type="checkbox" id="xcop_enable_logging" name="xcop_enable_logging" value="1" <?php checked(get_option('xcop_enable_logging', '0'), '1'); ?> />
                                    <span class="xcop-toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="xcop_whitelist_ips"><?php esc_html_e( 'Whitelisted IPs', 'xcop-redirect' ); ?></label>
                                <span class="description"><?php esc_html_e( 'IPs that can access the site without being redirected (comma-separated).', 'xcop-redirect' ); ?></span>
                            </th>
                            <td>
                                <textarea id="xcop_whitelist_ips" name="xcop_whitelist_ips" class="large-text" rows="3" placeholder="192.168.1.1, 10.0.0.1"><?php echo esc_textarea(get_option('xcop_whitelist_ips', '')); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="xcop_blacklist_ips"><?php esc_html_e( 'Blacklisted IPs', 'xcop-redirect' ); ?></label>
                                <span class="description"><?php esc_html_e( 'IPs that are blocked from accessing the site (comma-separated).', 'xcop-redirect' ); ?></span>
                            </th>
                            <td>
                                <textarea id="xcop_blacklist_ips" name="xcop_blacklist_ips" class="large-text" rows="3" placeholder="203.0.113.1, 198.51.100.1"><?php echo esc_textarea(get_option('xcop_blacklist_ips', '')); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="xcop_telemetry_optin"><?php esc_html_e( 'Allow Telemetry', 'xcop-redirect' ); ?></label>
                                <span class="description"><?php esc_html_e( 'Send usage data like domain, plugin version, and active status to help improve the plugin (no personal data is collected).', 'xcop-redirect' ); ?></span>
                            </th>
                            <td>
                                <label class="xcop-toggle">
                                    <input type="checkbox" id="xcop_telemetry_optin" name="xcop_telemetry_optin" value="1" <?php checked(get_option('xcop_telemetry_optin', '1'), '1'); ?> />
                                    <span class="xcop-toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Button Section -->
                <div class="xcop-buttons-section">
                    <div class="button-group">
                        <?php submit_button(__('Save Settings', 'xcop-redirect'), 'primary large', 'submit', false, array('id' => 'xcop-save-settings')); ?>
                        
                        <?php if (get_option('xcop_telemetry_optin', '1') === '1'): ?>
                        <button type="button" id="xcop-send-telemetry" class="button button-secondary large">
                            <span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 5px;"></span>
                            <?php esc_html_e('Send Telemetry Now', 'xcop-redirect'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    
                    <div id="xcop-telemetry-status" class="telemetry-status" style="display: none;">
                        <span class="status-message"></span>
                    </div>
                </div>
            </form>
            
            <!-- Status & Info Box -->
            <div class="xcop-info-box">
                <h3><?php esc_html_e( 'Status & How it Works', 'xcop-redirect' ); ?></h3>
                <div class="xcop-status">
                    <div class="status-item">
                        <span class="status-label"><?php esc_html_e( 'Status:', 'xcop-redirect' ); ?></span>
                        <span class="status-value <?php echo get_option('xcop_enable_redirect', '1') === '1' ? 'active' : 'inactive'; ?>">
                            <?php echo get_option('xcop_enable_redirect', '1') === '1' ? esc_html__( 'Enabled', 'xcop-redirect' ) : esc_html__( 'Disabled', 'xcop-redirect' ); ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label"><?php esc_html_e( 'Redirect URL:', 'xcop-redirect' ); ?></span>
                        <span class="status-value"><?php echo esc_html(get_option('xcop_redirect_url', __( 'Not set', 'xcop-redirect' ))); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label"><?php esc_html_e( 'Referrer Domain:', 'xcop-redirect' ); ?></span>
                        <span class="status-value"><?php echo esc_html(get_option('xcop_referrer_domain', __( 'Not set', 'xcop-redirect' ))); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label"><?php esc_html_e( 'Telemetry:', 'xcop-redirect' ); ?></span>
                        <span class="status-value <?php echo get_option('xcop_telemetry_optin', '1') === '1' ? 'active' : 'inactive'; ?>">
                            <?php echo get_option('xcop_telemetry_optin', '1') === '1' ? esc_html__( 'Enabled', 'xcop-redirect' ) : esc_html__( 'Disabled', 'xcop-redirect' ); ?>
                        </span>
                    </div>
                </div>
                
                <h4><?php esc_html_e( 'How it Works', 'xcop-redirect' ); ?></h4>
                <ul>
                    <li><strong><?php esc_html_e( 'Clicks from search results:', 'xcop-redirect' ); ?></strong> <?php esc_html_e( 'Users clicking from a search engine will be redirected.', 'xcop-redirect' ); ?></li>
                    <li><strong><?php esc_html_e( 'New tab detection:', 'xcop-redirect' ); ?></strong> <?php esc_html_e( 'Opening a new tab from a search result will trigger the redirect.', 'xcop-redirect' ); ?></li>
                    <li><strong><?php esc_html_e( 'Direct access prevention:', 'xcop-redirect' ); ?></strong> <?php esc_html_e( 'Pasting the URL directly will not trigger a redirect.', 'xcop-redirect' ); ?></li>
                    <li><strong><?php esc_html_e( 'Bot prevention:', 'xcop-redirect' ); ?></strong> <?php esc_html_e( 'Bots and crawlers are automatically excluded.', 'xcop-redirect' ); ?></li>
                    <li><strong><?php esc_html_e( 'Telemetry:', 'xcop-redirect' ); ?></strong> <?php esc_html_e( 'Usage data is sent from the client-side to help improve the plugin.', 'xcop-redirect' ); ?></li>
                </ul>
            </div>
        </div>
    </div>
    
    <style>
        .xcop-settings-wrap {
            max-width: 1000px;
            margin: 20px auto;
        }
        
        .xcop-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .xcop-header h1 {
            margin: 0;
            color: #0073aa;
        }
        
        .xcop-version {
            background: #0073aa;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .xcop-settings-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .xcop-section {
            padding: 30px;
            border-bottom: 1px solid #eee;
        }
        
        .xcop-section:last-of-type {
            border-bottom: none;
        }
        
        .xcop-section h2 {
            margin: 0 0 20px 0;
            color: #0073aa;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        
        .form-table th .description {
            display: block;
            color: #666;
            font-size: 12px;
            margin-top: 5px;
            font-weight: normal;
        }
        
        .form-table input[type="url"],
        .form-table input[type="text"],
        .form-table input[type="number"],
        .form-table textarea {
            width: 100%;
            max-width: 500px;
        }
        
        /* Toggle Switch */
        .xcop-toggle {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .xcop-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .xcop-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .xcop-toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        .xcop-toggle input:checked + .xcop-toggle-slider {
            background-color: #0073aa;
        }
        
        .xcop-toggle input:checked + .xcop-toggle-slider:before {
            transform: translateX(26px);
        }
        
        /* Button Styling */
        .xcop-buttons-section {
            padding: 30px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
        }
        
        .button-group {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .button.large {
            height: auto;
            line-height: 1;
            padding: 15px 25px;
            font-size: 16px;
            border-radius: 5px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }
        
        .button-primary.large {
            background: linear-gradient(135deg, #0073aa, #005177);
            border: none;
            color: white;
        }
        
        .button-primary.large:hover {
            background: linear-gradient(135deg, #005177, #0073aa);
            transform: translateY(-1px);
            color: white;
        }
        
        .button-secondary.large {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: 1px solid #6c757d;
            color: white;
        }
        
        .button-secondary.large:hover {
            background: linear-gradient(135deg, #495057, #6c757d);
            transform: translateY(-1px);
            color: white;
        }
        
        .telemetry-status {
            margin-top: 15px;
            padding: 12px;
            border-radius: 5px;
            font-weight: 500;
        }
        
        .telemetry-status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .telemetry-status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .telemetry-status.loading {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .xcop-info-box {
            margin: 30px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 25px;
        }
        
        .xcop-info-box h3 {
            margin: 0 0 20px 0;
            color: #0073aa;
        }
        
        .xcop-info-box h4 {
            margin: 20px 0 10px 0;
            color: #495057;
        }
        
        .xcop-status {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .status-item {
            background: white;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #0073aa;
        }
        
        .status-label {
            font-weight: bold;
            color: #495057;
        }
        
        .status-value {
            display: block;
            margin-top: 5px;
            font-size: 14px;
        }
        
        .status-value.active {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-value.inactive {
            color: #dc3545;
            font-weight: bold;
        }
        
        .xcop-info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .xcop-info-box li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        @media (max-width: 768px) {
            .xcop-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .xcop-status {
                grid-template-columns: 1fr;
            }
            
            .xcop-section {
                padding: 20px;
            }
            
            .button-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .button.large {
                justify-content: center;
            }
        }
    </style>
    
    <!-- JavaScript for enhanced functionality -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Handle manual telemetry send
        $('#xcop-send-telemetry').on('click', function() {
            var $button = $(this);
            var $status = $('#xcop-telemetry-status');
            var $message = $status.find('.status-message');
            
            // Show loading state
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update-alt" style="vertical-align: middle; margin-right: 5px; animation: rotation 1s infinite linear;"></span>กำลังส่งข้อมูล...');
            $status.removeClass('success error').addClass('loading').show();
            $message.text('กำลังเตรียมส่งข้อมูล telemetry...');
            
            // Call telemetry function directly from client
            if (typeof window.xcopSendTelemetry === 'function') {
                try {
                    window.xcopSendTelemetry(true).then(function(result) {
                        // Success
                        $status.removeClass('loading error').addClass('success');
                        $message.text('ส่งข้อมูล telemetry สำเร็จแล้ว!');
                        $button.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="vertical-align: middle; margin-right: 5px;"></span>ส่งสำเร็จ!');
                        
                        setTimeout(function() {
                            $button.html('<span class="dashicons dashicons-upload" style="vertical-align: middle; margin-right: 5px;"></span>ส่งข้อมูล Telemetry ทันที');
                            $status.fadeOut();
                        }, 3000);
                    }).catch(function(error) {
                        // Error
                        $status.removeClass('loading success').addClass('error');
                        $message.text('เกิดข้อผิดพลาดในการส่งข้อมูล: ' + (error.message || 'Unknown error'));
                        $button.prop('disabled', false).html('<span class="dashicons dashicons-warning" style="vertical-align: middle; margin-right: 5px;"></span>ลองใหม่');
                    });
                } catch (error) {
                    // Fallback error handling
                    $status.removeClass('loading success').addClass('error');
                    $message.text('ไม่สามารถส่งข้อมูล telemetry ได้: ' + error.message);
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-warning" style="vertical-align: middle; margin-right: 5px;"></span>ลองใหม่');
                }
            } else {
                // Fallback - telemetry function not available
                $status.removeClass('loading success').addClass('error');
                $message.text('ฟังก์ชัน telemetry ไม่พร้อมใช้งาน กรุณารีเฟรชหน้าเว็บแล้วลองใหม่');
                $button.prop('disabled', false).html('<span class="dashicons dashicons-warning" style="vertical-align: middle; margin-right: 5px;"></span>ลองใหม่');
            }
        });
        
        // Handle form submission with telemetry
        $('#xcop-settings-form').on('submit', function(e) {
            var telemetryEnabled = $('#xcop_telemetry_optin').is(':checked');
            
            if (telemetryEnabled && typeof window.xcopSendTelemetry === 'function') {
                // Send telemetry after successful form submission
                setTimeout(function() {
                    window.xcopSendTelemetry(true).then(function() {
                        console.log('XCOP Redirect: Telemetry sent after settings save');
                    }).catch(function(error) {
                        console.error('XCOP Redirect: Failed to send telemetry after settings save:', error);
                    });
                }, 1000);
            }
        });
        
        // Toggle telemetry button visibility based on opt-in checkbox
        $('#xcop_telemetry_optin').on('change', function() {
            var $telemetryButton = $('#xcop-send-telemetry');
            if ($(this).is(':checked')) {
                $telemetryButton.show();
            } else {
                $telemetryButton.hide();
            }
        });
    });
    
    // CSS animation for loading spinner
    document.addEventListener('DOMContentLoaded', function() {
        var style = document.createElement('style');
        style.textContent = `
            @keyframes rotation {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    });
    </script>
    <?php
}

// Add admin footer text
function xcop_admin_footer($text) {
    $current_screen = get_current_screen();
    if ($current_screen && $current_screen->id === 'toplevel_page_xcop-settings') {
        $thank_you_text = sprintf(
            /* translators: 1: Plugin name, 2: Plugin version, 3: Author link */
            __( 'Thank you for using %1$s version %2$s | Developed by %3$s', 'xcop-redirect' ),
            '<strong>XCOP Redirect</strong>',
            XCOP_REDIRECT_VERSION,
            '<a href="https://xcoptech.com" target="_blank">XCOP</a>'
        );
        return wp_kses_post( $thank_you_text );
    }
    return $text;
}
add_filter('admin_footer_text', 'xcop_admin_footer');