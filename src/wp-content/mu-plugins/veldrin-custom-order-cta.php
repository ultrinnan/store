<?php
/**
 * Plugin Name: Veldrin – Custom order CTA
 * Description: Adds a short "Request a custom order" line to single product pages of Veldrin's own products (brand "veldrin", not synced from the Bearpaw dealer feed). Styles live in Additional CSS (FIXER-CTA block). Added by Fixer 2026-11-24.
 * Version: 1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Own product = has product_brand "veldrin" and no Bearpaw dealer_product_id meta.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function veldrin_is_own_product( $product_id ) {
	if ( '' !== (string) get_post_meta( $product_id, 'dealer_product_id', true ) ) {
		return false;
	}
	return taxonomy_exists( 'product_brand' ) && has_term( 'veldrin', 'product_brand', $product_id );
}

add_action(
	'woocommerce_single_product_summary',
	static function () {
		$product_id = get_the_ID();
		if ( ! $product_id || ! veldrin_is_own_product( $product_id ) ) {
			return;
		}
		$page = get_page_by_path( 'custom-order' );
		$url  = ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : '';
		if ( ! $url ) {
			return;
		}
		printf(
			'<p class="veldrin-custom-order-cta">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html__( 'Want it in your size or colours?', 'veldrin' ),
			esc_url( $url ),
			esc_html__( 'Request a custom order', 'veldrin' )
		);
	},
	35
);
