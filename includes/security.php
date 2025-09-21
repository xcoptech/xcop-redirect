<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit('คุณไม่สามารถเข้าถึงไฟล์นี้โดยตรงได้');
}

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
        
        // Additional security headers
        header('X-Robots-Tag: noindex, nofollow', true);
        header('X-Permitted-Cross-Domain-Policies: none');
    }
}
add_action('send_headers', 'xcop_add_security_headers');

// Prevent direct file access to plugin files
function xcop_prevent_direct_access() {
    if (defined('ABSPATH')) {
        return;
    }
    
    http_response_code(403);
    exit('Direct access forbidden');
}

// Enhanced input validation and sanitization
function xcop_validate_and_sanitize_input($input, $type = 'text') {
    switch ($type) {
        case 'url':
            $sanitized = esc_url_raw($input);
            if (!filter_var($sanitized, FILTER_VALIDATE_URL)) {
                return false;
            }
            return $sanitized;
            
        case 'domain':
            $sanitized = sanitize_text_field($input);
            $sanitized = strtolower(trim($sanitized));
            $sanitized = preg_replace('/^https?:\/\//', '', $sanitized);
            $sanitized = preg_replace('/^www\./', '', $sanitized);
            $sanitized = rtrim($sanitized, '/');
            
            if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $sanitized)) {
                return false;
            }
            return $sanitized;
            
        case 'ip_list':
            $ips = array_map('trim', explode(',', $input));
            $valid_ips = array();
            
            foreach ($ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $valid_ips[] = $ip;
                }
            }
            
            return implode(', ', $valid_ips);
            
        case 'number':
            $number = absint($input);
            return ($number >= 0) ? $number : 0;
            
        case 'checkbox':
            return ($input === '1' || $input === 1 || $input === true) ? '1' : '0';
            
        default:
            return sanitize_text_field($input);
    }
}

// SQL injection prevention
function xcop_prevent_sql_injection() {
    $suspicious_patterns = array(
        'union', 'select', 'insert', 'update', 'delete', 'drop',
        'create', 'alter', 'exec', 'execute', 'script', 'javascript',
        'vbscript', 'onload', 'onerror', 'onclick'
    );
    
    $request_uri = strtolower($_SERVER['REQUEST_URI'] ?? '');
    $query_string = strtolower($_SERVER['QUERY_STRING'] ?? '');
    
    foreach ($suspicious_patterns as $pattern) {
        if (strpos($request_uri, $pattern) !== false || 
            strpos($query_string, $pattern) !== false) {
            xcop_log("SQL injection attempt detected: {$pattern}");
            wp_die('Suspicious activity detected', 'Security Alert', array('response' => 403));
        }
    }
}
add_action('init', 'xcop_prevent_sql_injection', 1);

// XSS prevention
function xcop_prevent_xss() {
    $xss_patterns = array(
        '<script', '</script>', 'javascript:', 'vbscript:',
        'onload=', 'onerror=', 'onclick=', 'onmouseover=',
        'onfocus=', 'onblur=', 'onchange=', 'onsubmit='
    );
    
    $inputs_to_check = array_merge($_GET, $_POST, $_COOKIE);
    
    foreach ($inputs_to_check as $key => $value) {
        if (is_string($value)) {
            $value_lower = strtolower($value);
            foreach ($xss_patterns as $pattern) {
                if (strpos($value_lower, $pattern) !== false) {
                    xcop_log("XSS attempt detected in {$key}: {$pattern}");
                    wp_die('Suspicious activity detected', 'Security Alert', array('response' => 403));
                }
            }
        }
    }
}
add_action('init', 'xcop_prevent_xss', 2);

// File inclusion attack prevention
function xcop_prevent_file_inclusion() {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    
    $dangerous_patterns = array(
        '../', '..\\', './', '.\\',
        'php://input', 'php://filter', 'data://',
        'file://', 'ftp://', 'sftp://',
        'expect://', 'zip://', 'compress.zlib://'
    );
    
    foreach ($dangerous_patterns as $pattern) {
        if (stripos($request_uri, $pattern) !== false) {
            xcop_log("File inclusion attempt detected: {$pattern}");
            wp_die('Suspicious activity detected', 'Security Alert', array('response' => 403));
        }
    }
}
add_action('init', 'xcop_prevent_file_inclusion', 3);

// Brute force protection
function xcop_brute_force_protection() {
    $ip = xcop_get_user_ip();
    $transient_key = 'xcop_failed_attempts_' . md5($ip);
    
    $failed_attempts = get_transient($transient_key);
    
    if ($failed_attempts !== false && $failed_attempts >= 5) {
        xcop_log("IP blocked due to brute force: {$ip}");
        wp_die('Too many failed attempts. Please try again later.', 'Access Blocked', array('response' => 429));
    }
    
    // Track failed login attempts
    if (isset($_POST['log']) && isset($_POST['pwd'])) {
        add_action('wp_login_failed', function($username) use ($transient_key) {
            $attempts = get_transient($transient_key) ?: 0;
            set_transient($transient_key, $attempts + 1, HOUR_IN_SECONDS);
            xcop_log("Failed login attempt for user: {$username}");
        });
    }
}
add_action('init', 'xcop_brute_force_protection', 4);

// Directory traversal protection
function xcop_prevent_directory_traversal() {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // Decode URL encoding
    $decoded_uri = urldecode($request_uri);
    
    $traversal_patterns = array(
        '../', '..\\', '..%2f', '..%5c',
        '%2e%2e%2f', '%2e%2e%5c',
        '..%252f', '..%255c'
    );
    
    foreach ($traversal_patterns as $pattern) {
        if (stripos($decoded_uri, $pattern) !== false) {
            xcop_log("Directory traversal attempt detected: {$pattern}");
            wp_die('Suspicious activity detected', 'Security Alert', array('response' => 403));
        }
    }
}
add_action('init', 'xcop_prevent_directory_traversal', 5);

// Command injection protection
function xcop_prevent_command_injection() {
    $dangerous_commands = array(
        'system', 'exec', 'shell_exec', 'passthru',
        'eval', 'base64_decode', 'gzinflate',
        'str_rot13', 'file_get_contents', 'curl_exec'
    );
    
    $inputs_to_check = array_merge($_GET, $_POST);
    
    foreach ($inputs_to_check as $key => $value) {
        if (is_string($value)) {
            $value_lower = strtolower($value);
            foreach ($dangerous_commands as $command) {
                if (strpos($value_lower, $command) !== false) {
                    xcop_log("Command injection attempt detected in {$key}: {$command}");
                    wp_die('Suspicious activity detected', 'Security Alert', array('response' => 403));
                }
            }
        }
    }
}
add_action('init', 'xcop_prevent_command_injection', 6);

// Rate limiting for specific actions
function xcop_rate_limit_actions() {
    $ip = xcop_get_user_ip();
    $current_time = time();
    
    // Different rate limits for different actions
    $actions = array(
        'settings_save' => array('limit' => 10, 'window' => 600), // 10 saves per 10 minutes
        'telemetry_send' => array('limit' => 100, 'window' => 3600), // 100 sends per hour
        'redirect_test' => array('limit' => 20, 'window' => 300) // 20 tests per 5 minutes
    );
    
    foreach ($actions as $action => $config) {
        $transient_key = "xcop_rate_limit_{$action}_" . md5($ip);
        $requests = get_transient($transient_key) ?: array();
        
        // Clean old requests
        $requests = array_filter($requests, function($timestamp) use ($current_time, $config) {
            return ($current_time - $timestamp) < $config['window'];
        });
        
        // Check rate limit
        if (count($requests) >= $config['limit']) {
            xcop_log("Rate limit exceeded for {$action} from IP: {$ip}");
            wp_die('Rate limit exceeded. Please try again later.', 'Rate Limited', array('response' => 429));
        }
        
        // Add current request
        $requests[] = $current_time;
        set_transient($transient_key, $requests, $config['window']);
    }
}

// Security scan for uploaded files (if any)
function xcop_scan_uploaded_files($file) {
    if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
        return $file;
    }
    
    $content = file_get_contents($file['tmp_name']);
    
    $malicious_patterns = array(
        '<?php', '<?=', '<script', 'eval(', 'base64_decode(',
        'system(', 'exec(', 'shell_exec(', 'passthru(',
        'file_get_contents(', 'curl_exec(', 'fopen(',
        'javascript:', 'vbscript:'
    );
    
    foreach ($malicious_patterns as $pattern) {
        if (stripos($content, $pattern) !== false) {
            xcop_log("Malicious file upload attempt detected: {$pattern}");
            wp_die('Malicious file detected', 'Security Alert', array('response' => 403));
        }
    }
    
    return $file;
}
add_filter('wp_handle_upload_prefilter', 'xcop_scan_uploaded_files');

// Honeypot field for form submissions
function xcop_add_honeypot_field() {
    echo '<input type="text" name="xcop_honeypot" value="" style="display:none !important;" tabindex="-1" autocomplete="off">';
}

function xcop_check_honeypot() {
    if (isset($_POST['xcop_honeypot']) && !empty($_POST['xcop_honeypot'])) {
        xcop_log("Honeypot triggered from IP: " . xcop_get_user_ip());
        wp_die('Spam detected', 'Security Alert', array('response' => 403));
    }
}

// Security monitoring and alerting
function xcop_security_monitor() {
    $security_events = get_option('xcop_security_events', array());
    $current_time = time();
    
    // Clean old events (keep only last 24 hours)
    $security_events = array_filter($security_events, function($event) use ($current_time) {
        return ($current_time - $event['timestamp']) < DAY_IN_SECONDS;
    });
    
    // Count events by type
    $event_counts = array();
    foreach ($security_events as $event) {
        $type = $event['type'];
        $event_counts[$type] = ($event_counts[$type] ?? 0) + 1;
    }
    
    // Alert thresholds
    $thresholds = array(
        'sql_injection' => 3,
        'xss_attempt' => 5,
        'brute_force' => 10,
        'file_inclusion' => 2
    );
    
    foreach ($thresholds as $type => $threshold) {
        if (($event_counts[$type] ?? 0) >= $threshold) {
            xcop_send_security_alert($type, $event_counts[$type]);
        }
    }
    
    update_option('xcop_security_events', array_values($security_events));
}

function xcop_log_security_event($type, $details = '') {
    $security_events = get_option('xcop_security_events', array());
    
    $security_events[] = array(
        'type' => $type,
        'timestamp' => time(),
        'ip' => xcop_get_user_ip(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'details' => $details
    );
    
    update_option('xcop_security_events', $security_events);
    xcop_log("Security event logged: {$type} - {$details}");
}

function xcop_send_security_alert($type, $count) {
    $admin_email = get_option('admin_email');
    $site_name = get_bloginfo('name');
    $site_url = get_site_url();
    
    $subject = "[Security Alert] {$site_name} - Multiple {$type} attempts detected";
    $message = "Security Alert for {$site_name} ({$site_url})\n\n";
    $message .= "Event Type: {$type}\n";
    $message .= "Count in last 24 hours: {$count}\n";
    $message .= "Time: " . current_time('Y-m-d H:i:s') . "\n\n";
    $message .= "Please check your site's security logs and consider taking additional security measures.\n\n";
    $message .= "This alert was generated by XCOP Redirect Plugin.";
    
    wp_mail($admin_email, $subject, $message);
    xcop_log("Security alert sent for {$type}: {$count} events");
}

// Schedule security monitoring
if (!wp_next_scheduled('xcop_security_monitor')) {
    wp_schedule_event(time(), 'hourly', 'xcop_security_monitor');
}
add_action('xcop_security_monitor', 'xcop_security_monitor');