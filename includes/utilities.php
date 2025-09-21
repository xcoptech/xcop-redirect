<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit('คุณไม่สามารถเข้าถึงไฟล์นี้โดยตรงได้');
}

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

// Utility function to check if current request is from admin area
function xcop_is_admin_request() {
    return is_admin() || wp_doing_ajax() || (defined('DOING_CRON') && DOING_CRON);
}

// Utility function to get client browser information
function xcop_get_client_info() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    return array(
        'user_agent' => $user_agent,
        'ip' => xcop_get_user_ip(),
        'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'timestamp' => current_time('mysql'),
        'is_ssl' => is_ssl()
    );
}

// Utility function to validate redirect URL
function xcop_is_valid_redirect_url($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    $parsed_url = parse_url($url);
    if (!$parsed_url || !isset($parsed_url['scheme']) || !isset($parsed_url['host'])) {
        return false;
    }
    
    // Only allow http/https
    if (!in_array($parsed_url['scheme'], array('http', 'https'))) {
        return false;
    }
    
    // Don't allow redirects to localhost or internal IPs for security
    $host = $parsed_url['host'];
    if (in_array($host, array('localhost', '127.0.0.1', '::1')) || 
        preg_match('/^(10|172\.(1[6-9]|2[0-9]|3[01])|192\.168)\./', $host)) {
        return false;
    }
    
    return true;
}

// Utility function to clean log file if it gets too large
function xcop_maintain_log_file() {
    if (get_option('xcop_enable_logging', '0') !== '1') {
        return;
    }
    
    $log_file = WP_CONTENT_DIR . '/xcop-redirect.log';
    
    if (file_exists($log_file)) {
        $file_size = filesize($log_file);
        // If log file is larger than 5MB, truncate it
        if ($file_size > 5 * 1024 * 1024) {
            $lines = file($log_file, FILE_IGNORE_NEW_LINES);
            if ($lines !== false && count($lines) > 1000) {
                // Keep only last 500 lines
                $keep_lines = array_slice($lines, -500);
                file_put_contents($log_file, implode("\n", $keep_lines) . "\n");
                xcop_log('Log file truncated to maintain size');
            }
        }
    }
}

// Schedule log maintenance
if (!wp_next_scheduled('xcop_log_maintenance')) {
    wp_schedule_event(time(), 'daily', 'xcop_log_maintenance');
}
add_action('xcop_log_maintenance', 'xcop_maintain_log_file');