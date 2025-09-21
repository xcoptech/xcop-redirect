<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit('คุณไม่สามารถเข้าถึงไฟล์นี้โดยตรงได้');
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
        'msnbot', 'yahoo', 'ask jeeves', 'teoma', 'gigabot',
        
        // Social media bots
        'facebookexternalhit', 'twitterbot', 'linkedinbot', 'whatsapp',
        'telegrambot', 'skypeuripreview', 'vkshare', 'pinterest',
        'slack', 'discord', 'line', 'kakaotalk',
        
        // SEO and monitoring bots
        'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot', 'rogerbot',
        'screaming frog', 'siteimprove', 'gtmetrix', 'pingdom',
        'majestic', 'spyfu', 'opensiteexplorer', 'seokicks',
        
        // Security and malware bots
        'nmap', 'masscan', 'zmap', 'sqlmap', 'nikto',
        'w3af', 'skipfish', 'grabber', 'wpscan',
        
        // Generic patterns
        'bot', 'crawler', 'spider', 'scraper', 'fetcher', 'checker',
        'monitor', 'test', 'validator', 'analyzer', 'audit',
        'indexer', 'harvester', 'archiver',
        
        // Headless browsers and automation
        'headlesschrome', 'phantomjs', 'selenium', 'playwright',
        'puppeteer', 'chrome-lighthouse', 'pagespeed',
        'browserless', 'splash',
        
        // HTTP libraries and tools
        'curl', 'wget', 'python-requests', 'python-urllib', 'go-http-client',
        'ruby', 'perl', 'java', 'node.js', 'axios', 'httpclient',
        'okhttp', 'apache-httpclient', 'libwww',
        
        // Suspicious or automated patterns
        'postman', 'insomnia', 'httpie', 'rest-client',
        'api-client', 'test-client'
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
        preg_match('/\+https?:\/\//', $user_agent) || // Contains links
        preg_match('/^[a-z]+ [\d\.]+$/i', $user_agent) || // Pattern like "Mozilla 5.0" only
        !preg_match('/[a-z]/i', $user_agent)) { // No letters (suspicious)
        xcop_log("Suspicious user agent detected: {$user_agent}");
        return true;
    }
    
    // Check for missing or suspicious HTTP headers
    $headers = getallheaders();
    if (is_array($headers)) {
        $headers = array_change_key_case($headers, CASE_LOWER);
        
        // Real browsers usually have these headers
        $expected_headers = array('accept', 'accept-language');
        $missing_headers = 0;
        
        foreach ($expected_headers as $header) {
            if (!isset($headers[$header]) || empty($headers[$header])) {
                $missing_headers++;
            }
        }
        
        if ($missing_headers >= count($expected_headers)) {
            xcop_log("Bot detected: Missing essential browser headers");
            return true;
        }
        
        // Check for suspicious accept headers
        if (isset($headers['accept'])) {
            $accept = strtolower($headers['accept']);
            // Bots often have very simple accept headers
            if ($accept === '*/*' || $accept === 'text/html') {
                xcop_log("Bot detected: Suspicious accept header: {$accept}");
                return true;
            }
        }
    }
    
    // Check request method - only GET should reach the redirect logic
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if ($method !== 'GET') {
        xcop_log("Bot detected: Non-GET request method: {$method}");
        return true;
    }
    
    return false;
}

// Advanced bot detection using multiple signals
function xcop_is_sophisticated_bot() {
    if (xcop_is_bot()) {
        return true;
    }
    
    $score = 0;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $client_info = xcop_get_client_info();
    
    // Check for automation indicators
    
    // 1. User agent analysis
    if (preg_match('/automation|webdriver|selenium|phantom/i', $user_agent)) {
        $score += 50;
    }
    
    // 2. No referrer but direct access patterns
    if (empty($client_info['referrer']) && !is_admin()) {
        $score += 10;
    }
    
    // 3. Unusual request patterns
    $request_uri = $client_info['request_uri'];
    if (preg_match('/\.php$|\/wp-|\/admin|\/api\//', $request_uri)) {
        $score += 15;
    }
    
    // 4. Check for headless browser indicators in JS (would be handled client-side)
    // This is a server-side check for known headless patterns
    if (preg_match('/headless|phantom|nightmare/i', $user_agent)) {
        $score += 30;
    }
    
    // 5. Time-based detection (very fast sequential requests)
    $ip_hash = md5($client_info['ip']);
    $last_request_time = get_transient('xcop_last_request_' . $ip_hash);
    $current_time = microtime(true);
    
    if ($last_request_time !== false) {
        $time_diff = $current_time - $last_request_time;
        if ($time_diff < 0.5) { // Less than 500ms between requests
            $score += 25;
        }
    }
    
    set_transient('xcop_last_request_' . $ip_hash, $current_time, 60);
    
    // 6. Check for common bot IP ranges (this is basic - in production you'd use more comprehensive lists)
    $ip = $client_info['ip'];
    if (preg_match('/^(66\.249\.|207\.46\.|65\.52\.|72\.14\.)/', $ip)) {
        $score += 40; // Known search engine IP ranges
    }
    
    $threshold = 50; // Adjust based on your needs
    $is_bot = $score >= $threshold;
    
    if ($is_bot) {
        xcop_log("Sophisticated bot detected with score: {$score}");
    }
    
    return $is_bot;
}

// Client-side bot detection script
function xcop_add_bot_detection_script() {
    ?>
    <script type="text/javascript">
    (function() {
        'use strict';
        
        // Client-side bot detection
        window.xcopClientBotDetection = function() {
            var botScore = 0;
            var indicators = [];
            
            // Check for webdriver
            if (navigator.webdriver) {
                botScore += 50;
                indicators.push('webdriver');
            }
            
            // Check for missing properties
            if (!window.chrome && !window.safari && navigator.userAgent.indexOf('Chrome') > -1) {
                botScore += 30;
                indicators.push('missing_chrome_object');
            }
            
            // Check for automation frameworks
            if (window.callPhantom || window._phantom || window.phantom) {
                botScore += 50;
                indicators.push('phantom');
            }
            
            if (window.Buffer) {
                botScore += 30;
                indicators.push('nodejs_buffer');
            }
            
            // Check for headless indicators
            if (navigator.plugins.length === 0) {
                botScore += 20;
                indicators.push('no_plugins');
            }
            
            if (navigator.languages && navigator.languages.length === 0) {
                botScore += 20;
                indicators.push('no_languages');
            }
            
            // Check screen properties
            if (screen.width === 0 || screen.height === 0) {
                botScore += 40;
                indicators.push('zero_screen');
            }
            
            // Check for unusual screen sizes
            if (screen.width < 100 || screen.height < 100 || 
                screen.width > 5000 || screen.height > 5000) {
                botScore += 25;
                indicators.push('unusual_screen');
            }
            
            // Check for missing mouse/touch events capability
            if (!('onmouseenter' in document.documentElement)) {
                botScore += 15;
                indicators.push('no_mouse_events');
            }
            
            // Check for permission API (bots often don't have this)
            if (!navigator.permissions) {
                botScore += 10;
                indicators.push('no_permissions_api');
            }
            
            // Check for battery API (most bots don't have this)
            if (!navigator.getBattery && !navigator.battery) {
                botScore += 5;
                indicators.push('no_battery_api');
            }
            
            var isBot = botScore >= 50;
            
            return {
                isBot: isBot,
                score: botScore,
                indicators: indicators,
                userAgent: navigator.userAgent
            };
        };
        
        // Store detection result for use by other scripts
        window.xcopBotDetectionResult = window.xcopClientBotDetection();
        
        if (window.xcopBotDetectionResult.isBot) {
            console.log('XCOP Bot Detection: Client-side bot detected', window.xcopBotDetectionResult);
        }
        
    })();
    </script>
    <?php
}

// Add bot detection to both frontend and admin
add_action('wp_head', 'xcop_add_bot_detection_script', 5);
add_action('admin_head', 'xcop_add_bot_detection_script', 5);

// Whitelist legitimate bots that should not be redirected
function xcop_is_whitelisted_bot() {
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
    
    $whitelisted_bots = array(
        'googlebot',
        'bingbot',
        'slurp', // Yahoo
        'duckduckbot',
        'baiduspider',
        'yandexbot',
        'facebookexternalhit',
        'twitterbot',
        'linkedinbot',
        'whatsapp',
        'telegram',
        'pinterest'
    );
    
    foreach ($whitelisted_bots as $bot) {
        if (strpos($user_agent, $bot) !== false) {
            xcop_log("Whitelisted bot detected: {$bot}");
            return true;
        }
    }
    
    return false;
}