<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit('คุณไม่สามารถเข้าถึงไฟล์นี้โดยตรงได้');
}

// Client-side telemetry script - replaces server-side telemetry
function xcop_add_telemetry_script() {
    if (get_option('xcop_telemetry_optin', '1') !== '1') {
        return;
    }
    
    $telemetry_api_url = XCOP_TELEMETRY_API_URL;
    $plugin_version = XCOP_REDIRECT_VERSION;
    $site_url = get_site_url();
    $wordpress_version = get_bloginfo('version');
    $enable_redirect = get_option('xcop_enable_redirect', '1');
    $referrer_domain = get_option('xcop_referrer_domain', '');
    
    ?>
    <script type="text/javascript">
    (function() {
        'use strict';
        
        // Global telemetry function for manual triggering
        window.xcopSendTelemetry = function(activeStatus, manualTrigger) {
            return new Promise(function(resolve, reject) {
                if (!navigator.sendBeacon && !window.fetch) {
                    reject(new Error('ไม่รองรับ API ที่จำเป็น'));
                    return;
                }
                
                var telemetryData = {
                    domain: '<?php echo esc_js($site_url); ?>',
                    active_status: activeStatus,
                    plugin_version: '<?php echo esc_js($plugin_version); ?>',
                    wordpress_version: '<?php echo esc_js($wordpress_version); ?>',
                    php_version: '<?php echo esc_js(phpversion()); ?>',
                    timestamp: new Date().toISOString(),
                    user_agent: navigator.userAgent,
                    screen_resolution: screen.width + 'x' + screen.height,
                    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
                    language: navigator.language,
                    referrer: document.referrer || '',
                    url: window.location.href,
                    manual_trigger: manualTrigger || false,
                    meta: {
                        referrer_domain: '<?php echo esc_js($referrer_domain); ?>',
                        enable_redirect: '<?php echo esc_js($enable_redirect); ?>',
                        page_load_time: Date.now(),
                        viewport: window.innerWidth + 'x' + window.innerHeight,
                        color_depth: screen.colorDepth,
                        pixel_ratio: window.devicePixelRatio || 1,
                        connection_type: navigator.connection ? navigator.connection.effectiveType : 'unknown',
                        online_status: navigator.onLine
                    }
                };
                
                var apiUrl = '<?php echo esc_js($telemetry_api_url); ?>';
                
                // Try fetch first with timeout
                if (window.fetch) {
                    var controller = new AbortController();
                    var timeoutId = setTimeout(function() {
                        controller.abort();
                    }, 10000); // 10 second timeout
                    
                    fetch(apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(telemetryData),
                        keepalive: true,
                        signal: controller.signal
                    }).then(function(response) {
                        clearTimeout(timeoutId);
                        if (response.ok) {
                            console.log('XCOP Redirect: Telemetry sent successfully via fetch');
                            resolve({method: 'fetch', status: 'success'});
                        } else {
                            throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                        }
                    }).catch(function(error) {
                        clearTimeout(timeoutId);
                        console.log('XCOP Redirect: Telemetry fetch failed, trying sendBeacon:', error.message);
                        
                        // Fallback to sendBeacon
                        if (navigator.sendBeacon) {
                            try {
                                var success = navigator.sendBeacon(apiUrl, JSON.stringify(telemetryData));
                                if (success) {
                                    console.log('XCOP Redirect: Telemetry sent successfully via sendBeacon');
                                    resolve({method: 'sendBeacon', status: 'success'});
                                } else {
                                    reject(new Error('sendBeacon failed'));
                                }
                            } catch (beaconError) {
                                reject(new Error('sendBeacon error: ' + beaconError.message));
                            }
                        } else {
                            reject(error);
                        }
                    });
                } else if (navigator.sendBeacon) {
                    // Direct sendBeacon if fetch not available
                    try {
                        var success = navigator.sendBeacon(apiUrl, JSON.stringify(telemetryData));
                        if (success) {
                            console.log('XCOP Redirect: Telemetry sent successfully via sendBeacon');
                            resolve({method: 'sendBeacon', status: 'success'});
                        } else {
                            reject(new Error('sendBeacon failed'));
                        }
                    } catch (beaconError) {
                        reject(new Error('sendBeacon error: ' + beaconError.message));
                    }
                } else {
                    reject(new Error('ไม่มี API ที่รองรับการส่งข้อมูล'));
                }
            });
        };
        
        // Send initial telemetry on page load
        function sendInitialTelemetry() {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        window.xcopSendTelemetry(true, false).catch(function(error) {
                            console.log('XCOP Redirect: Initial telemetry failed:', error.message);
                        });
                    }, 2000); // 2 second delay to ensure page is fully loaded
                });
            } else {
                setTimeout(function() {
                    window.xcopSendTelemetry(true, false).catch(function(error) {
                        console.log('XCOP Redirect: Initial telemetry failed:', error.message);
                    });
                }, 2000);
            }
        }
        
        // Periodic telemetry (every 5 minutes for active users)
        var telemetryInterval = setInterval(function() {
            if (!document.hidden && navigator.onLine) {
                window.xcopSendTelemetry(true, false).catch(function(error) {
                    console.log('XCOP Redirect: Periodic telemetry failed:', error.message);
                });
            }
        }, 300000); // 5 minutes
        
        // Send telemetry before page unload
        window.addEventListener('beforeunload', function() {
            clearInterval(telemetryInterval);
            // Use sendBeacon for unload events as it's more reliable
            if (navigator.sendBeacon) {
                try {
                    var unloadData = {
                        domain: '<?php echo esc_js($site_url); ?>',
                        active_status: false,
                        plugin_version: '<?php echo esc_js($plugin_version); ?>',
                        timestamp: new Date().toISOString(),
                        event_type: 'page_unload',
                        session_duration: Date.now() - window.xcopSessionStart
                    };
                    navigator.sendBeacon('<?php echo esc_js($telemetry_api_url); ?>', JSON.stringify(unloadData));
                } catch (e) {
                    // Silent fail for unload events
                }
            }
        });
        
        // Track session start time
        window.xcopSessionStart = Date.now();
        
        // Send telemetry on visibility change
        if (typeof document.visibilityState !== 'undefined') {
            document.addEventListener('visibilitychange', function() {
                if (document.visibilityState === 'hidden') {
                    // Page became hidden - send inactive status
                    window.xcopSendTelemetry(false, false).catch(function(error) {
                        console.log('XCOP Redirect: Visibility hidden telemetry failed:', error.message);
                    });
                } else if (document.visibilityState === 'visible') {
                    // Page became visible - send active status
                    setTimeout(function() {
                        window.xcopSendTelemetry(true, false).catch(function(error) {
                            console.log('XCOP Redirect: Visibility visible telemetry failed:', error.message);
                        });
                    }, 1000);
                }
            });
        }
        
        // Network status change tracking
        if ('connection' in navigator) {
            navigator.connection.addEventListener('change', function() {
                setTimeout(function() {
                    window.xcopSendTelemetry(true, false).catch(function(error) {
                        console.log('XCOP Redirect: Connection change telemetry failed:', error.message);
                    });
                }, 2000);
            });
        }
        
        // Send initial telemetry
        sendInitialTelemetry();
        
    })();
    </script>
    <?php
}

// Add telemetry script to all pages when opt-in is enabled
function xcop_enqueue_telemetry_script() {
    if (get_option('xcop_telemetry_optin', '1') === '1') {
        add_action('wp_head', 'xcop_add_telemetry_script', 999);
    }
}
add_action('wp_enqueue_scripts', 'xcop_enqueue_telemetry_script');

// Also add to admin pages for settings page functionality
function xcop_enqueue_admin_telemetry_script() {
    if (get_option('xcop_telemetry_optin', '1') === '1') {
        $current_screen = get_current_screen();
        if ($current_screen && $current_screen->id === 'toplevel_page_xcop-settings') {
            add_action('admin_head', 'xcop_add_telemetry_script', 999);
        }
    }
}
add_action('admin_enqueue_scripts', 'xcop_enqueue_admin_telemetry_script');

// AJAX endpoint for manual telemetry testing from admin
function xcop_ajax_test_telemetry() {
    // Verify nonce and permissions
    if (!check_ajax_referer('xcop_telemetry_nonce', 'nonce', false)) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    // Return API endpoint and configuration for client-side testing
    wp_send_json_success(array(
        'api_url' => XCOP_TELEMETRY_API_URL,
        'site_url' => get_site_url(),
        'plugin_version' => XCOP_REDIRECT_VERSION,
        'wordpress_version' => get_bloginfo('version'),
        'php_version' => phpversion(),
        'message' => 'Use client-side xcopSendTelemetry() function'
    ));
}
add_action('wp_ajax_xcop_test_telemetry', 'xcop_ajax_test_telemetry');

// Add telemetry debug info to admin dashboard
function xcop_add_telemetry_debug_widget() {
    if (!current_user_can('manage_options') || get_option('xcop_enable_logging', '0') !== '1') {
        return;
    }
    
    wp_add_dashboard_widget(
        'xcop_telemetry_debug',
        'XCOP Telemetry Debug Info',
        'xcop_telemetry_debug_content'
    );
}
add_action('wp_dashboard_setup', 'xcop_add_telemetry_debug_widget');

function xcop_telemetry_debug_content() {
    $telemetry_enabled = get_option('xcop_telemetry_optin', '1') === '1';
    $api_url = XCOP_TELEMETRY_API_URL;
    
    echo '<div style="padding: 10px;">';
    echo '<p><strong>Telemetry Status:</strong> <span style="color: ' . ($telemetry_enabled ? '#28a745' : '#dc3545') . ';">' . ($telemetry_enabled ? 'Enabled' : 'Disabled') . '</span></p>';
    
    if ($telemetry_enabled) {
        echo '<p><strong>API Endpoint:</strong> <code>' . esc_html($api_url) . '</code></p>';
        echo '<p><strong>Method:</strong> Client-side JavaScript</p>';
        echo '<p><strong>Test Function:</strong> <code>window.xcopSendTelemetry(true, true)</code></p>';
        
        echo '<button type="button" onclick="testTelemetry()" class="button button-small">Test Telemetry</button>';
        echo '<div id="telemetry-test-result" style="margin-top: 10px;"></div>';
        
        ?>
        <script>
        function testTelemetry() {
            var resultDiv = document.getElementById('telemetry-test-result');
            resultDiv.innerHTML = '<em style="color: #0073aa;">Testing...</em>';
            
            if (typeof window.xcopSendTelemetry === 'function') {
                window.xcopSendTelemetry(true, true).then(function(result) {
                    resultDiv.innerHTML = '<span style="color: #28a745;">✓ Success: ' + result.method + '</span>';
                }).catch(function(error) {
                    resultDiv.innerHTML = '<span style="color: #dc3545;">✗ Failed: ' + error.message + '</span>';
                });
            } else {
                resultDiv.innerHTML = '<span style="color: #dc3545;">✗ Function not available</span>';
            }
        }
        </script>
        <?php
    }
    
    echo '</div>';
}