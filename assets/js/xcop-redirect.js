(function() {
    'use strict';

    // Check for params object
    if (typeof xcop_redirect_params === 'undefined') {
        console.log('XCOP Redirect: Params not found.');
        return;
    }

    // Enhanced bot detection on client side
    function isBot() {
        if (window.xcopBotDetectionResult && window.xcopBotDetectionResult.isBot) {
            return true;
        }
        var ua = navigator.userAgent.toLowerCase();
        var botPatterns = ['bot', 'crawler', 'spider', 'scraper', 'fetcher', 'headless', 'phantom', 'selenium', 'puppeteer'];
        for (var i = 0; i < botPatterns.length; i++) {
            if (ua.indexOf(botPatterns[i]) !== -1) {
                return true;
            }
        }
        if (!window.screen || !window.screen.width || !window.screen.height) {
            return true;
        }
        if (navigator.webdriver || window.callPhantom || window._phantom) {
            return true;
        }
        return false;
    }

    if (isBot()) {
        console.log('XCOP Redirect: Bot detected, skipping redirect');
        return;
    }

    var referrerValid = false;
    var historyValid = false;

    // Check referrer if enabled
    if (xcop_redirect_params.enable_referrer_check) {
        if (document.referrer) {
            var referrerDomain = xcop_redirect_params.referrer_domain;
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
            } catch (e) {
                console.log('XCOP Redirect: Error parsing referrer URL');
                referrerValid = false;
            }
        } else {
            console.log('XCOP Redirect: No referrer found');
        }
    } else {
        referrerValid = true; // Skip referrer check
        console.log('XCOP Redirect: Referrer check disabled');
    }

    // Check history length if enabled
    if (xcop_redirect_params.enable_history_check) {
        var minHistoryLength = parseInt(xcop_redirect_params.min_history_length, 10);
        historyValid = window.history.length > minHistoryLength;
        console.log('XCOP Redirect: History length check - ' + window.history.length + ' > ' + minHistoryLength + ' = ' + historyValid);
    } else {
        historyValid = true; // Skip history check
        console.log('XCOP Redirect: History check disabled');
    }

    var hasMouseMoved = false;
    var hasKeyPressed = false;
    var hasScrolled = false;
    document.addEventListener('mousemove', function() { hasMouseMoved = true; }, { once: true });
    document.addEventListener('keydown', function() { hasKeyPressed = true; }, { once: true });
    document.addEventListener('scroll', function() { hasScrolled = true; }, { once: true });

    var screenValid = window.screen.width > 100 && window.screen.height > 100 &&
        window.screen.width < 10000 && window.screen.height < 10000;

    if (!screenValid) {
        console.log('XCOP Redirect: Invalid screen dimensions detected');
        return;
    }

    function performSecurityChecks() {
        var checks = {
            hasLocalStorage: typeof(Storage) !== "undefined",
            hasSessionStorage: typeof(Storage) !== "undefined" && window.sessionStorage,
            hasIndexedDB: window.indexedDB !== undefined,
            hasWebGL: (function() {
                try {
                    var canvas = document.createElement('canvas');
                    return !!(canvas.getContext('webgl') || canvas.getContext('experimental-webgl'));
                } catch (e) {
                    return false;
                }
            })(),
            hasGeolocation: navigator.geolocation !== undefined,
            hasTouchEvents: 'ontouchstart' in window || navigator.maxTouchPoints > 0,
            deviceMemory: navigator.deviceMemory || 'unknown',
            hardwareConcurrency: navigator.hardwareConcurrency || 'unknown'
        };
        console.log('XCOP Redirect: Security checks:', checks);
        var featureCount = Object.keys(checks).filter(function(key) { return checks[key] === true; }).length;
        return featureCount >= 3;
    }

    function performRedirect(url) {
        try {
            if (window.location.replace) {
                window.location.replace(url);
            } else if (window.location.href) {
                window.location.href = url;
            } else {
                window.location = url;
            }
        } catch (e) {
            console.error('XCOP Redirect: Failed to redirect', e);
        }
    }

    if (referrerValid && historyValid && performSecurityChecks()) {
        var delay = parseInt(xcop_redirect_params.delay, 10);
        console.log('XCOP Redirect: All conditions met, redirecting in ' + delay + 'ms');

        var redirectTimer = setTimeout(function() {
            var redirectUrl = xcop_redirect_params.redirect_url;
            console.log('XCOP Redirect: Executing redirect to ' + redirectUrl);

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

        var cancelRedirect = function() {
            if (hasMouseMoved || hasKeyPressed || hasScrolled) {
                console.log('XCOP Redirect: User interaction detected, may cancel redirect');
            }
        };
        setTimeout(cancelRedirect, Math.min(delay, 500));

    } else {
        console.log('XCOP Redirect: Conditions not met - referrer: ' + referrerValid + ', history: ' + historyValid);
    }
})();
