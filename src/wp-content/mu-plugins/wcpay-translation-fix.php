<?php
// Suppress the specific WooCommerce Payments early translation warning to avoid breaking headers.

if (!defined('ABSPATH')) {
    //just check
    exit;
}

// Do not load the textdomain before 'init' to comply with WP 6.7+. Let the plugin/Core handle it.

// Suppress the specific early JIT notice for this domain to prevent headers already sent.
add_filter('doing_it_wrong_trigger_error', function ($trigger, $function, $message) {
    echo 'test';
    $showErrors = defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY;
    if ($showErrors) {
        // In development, do not suppress; show the warning
        return $trigger;
    }
    if ($function === '_load_textdomain_just_in_time' && strpos($message, 'woocommerce-payments') !== false) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[wcpay-i18n] Suppressed early textdomain load notice: ' . $message);
        }
        return false;
    }
    return $trigger;
}, 10, 3);


