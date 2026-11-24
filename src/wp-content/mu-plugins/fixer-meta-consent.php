<?php
/**
 * Plugin Name: Fixer – Meta (Facebook for WooCommerce) consent gate
 * Description: Holds Meta signals until the visitor gives marketing consent in Complianz.
 *              Before consent: no server-set _fbp/_fbc cookies, CAPI events suppressed,
 *              no CAPI param-builder (cdn.jsdelivr.net / unpkg.com), signals or pixel-events
 *              scripts, and no <noscript> facebook.com/tr pixel. After "Accept" the plugin
 *              runs normally (Complianz releases fbq on the same page; the rest from the next page).
 * Version: 1.0.0
 * Author: Fixer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Marketing consent given? Reads the Complianz / WP Consent API cookies directly
 * (wp_has_consent() returns true when no consent type is set, so it is not used here).
 */
function fixer_meta_has_marketing_consent() {
	foreach ( array( 'cmplz_marketing', 'wp_consent_marketing' ) as $name ) {
		if ( isset( $_COOKIE[ $name ] ) && 'allow' === $_COOKIE[ $name ] ) { // phpcs:ignore
			return true;
		}
	}
	return false;
}

/** Only gate visitor-facing requests; wp-admin screens and cron are left alone. */
function fixer_meta_should_gate() {
	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
		return false;
	}
	if ( is_admin() && ! wp_doing_ajax() ) {
		return false;
	}
	return ! fixer_meta_has_marketing_consent();
}

// 1) Server side: plugin's own "held" mode → no _fbp/_fbc Set-Cookie, CAPI events not sent.
add_filter(
	'facebook_signals_held',
	static function ( $held ) {
		return $held || fixer_meta_should_gate();
	},
	99
);

// 2) Client side: drop the Meta helper scripts (and their inline code) until consent.
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! fixer_meta_should_gate() ) {
			return;
		}
		foreach ( array( 'facebook-capi-param-builder', 'wc-facebook-signals', 'wc-facebook-pixel-events' ) as $handle ) {
			wp_dequeue_script( $handle );
		}
	},
	9999
);

// 3) Remove the <noscript> facebook.com/tr pixel until consent.
add_action(
	'wp_footer',
	static function () {
		if ( ! fixer_meta_should_gate() ) {
			return;
		}
		global $wp_filter;
		if ( empty( $wp_filter['wp_footer'] ) ) {
			return;
		}
		foreach ( $wp_filter['wp_footer']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				if ( is_array( $cb['function'] ) && isset( $cb['function'][1] ) && 'inject_base_pixel_noscript' === $cb['function'][1] ) {
					remove_action( 'wp_footer', $cb['function'], $priority );
				}
			}
		}
	},
	0
);
