<?php
// Prevent the WooCommerce Payments early translation warning across pages.

if (!defined('ABSPATH')) {
    exit;
}

// 1) Preload the textdomain at the earliest possible time (file load),
//    so JIT won't attempt to load it before 'init'.
$__wcpay_locale = function_exists('determine_locale') ? determine_locale() : get_locale();
$__wcpay_paths = array(
    WP_LANG_DIR . '/plugins/woocommerce-payments-' . $__wcpay_locale . '.mo',
    WP_PLUGIN_DIR . '/woocommerce-payments/languages/woocommerce-payments-' . $__wcpay_locale . '.mo',
    WP_PLUGIN_DIR . '/woocommerce-payments/languages/woocommerce-payments.mo',
);
foreach ($__wcpay_paths as $__wcpay_mo) {
    if (file_exists($__wcpay_mo)) {
        load_textdomain('woocommerce-payments', $__wcpay_mo);
        break;
    }
}
unset($__wcpay_locale, $__wcpay_paths, $__wcpay_mo);

// 2) If still not loaded, mark domain as loaded to avoid JIT doing_it_wrong pre-init.
if (! function_exists('is_textdomain_loaded') || ! is_textdomain_loaded('woocommerce-payments')) {
    if (! isset($GLOBALS['l10n'])) {
        $GLOBALS['l10n'] = array();
    }
    if (! isset($GLOBALS['l10n']['woocommerce-payments'])) {
        $GLOBALS['l10n']['woocommerce-payments'] = class_exists('NOOP_Translations') ? new NOOP_Translations() : new MO();
    }
}

// 3) Suppress the specific doing_it_wrong for this domain (always), but log it.
add_filter('doing_it_wrong_trigger_error', function ($trigger, $function, $message) {
    if ($function === '_load_textdomain_just_in_time' && strpos($message, 'woocommerce-payments') !== false) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[wcpay-i18n] Suppressed early textdomain load notice: ' . $message);
        }
        return false;
    }
    return $trigger;
}, 10, 3);


