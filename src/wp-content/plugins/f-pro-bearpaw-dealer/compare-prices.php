<?php
/**
 * Compare prices between dealer (B2B) and retail Bearpaw stores
 */

// Load WordPress
require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

// Get 10 products with dealer_product_id
$args = array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'posts_per_page' => 10,
	'orderby'        => 'ID',
	'order'          => 'ASC',
	'meta_query'     => array(
		array(
			'key'     => 'dealer_product_id',
			'compare' => 'EXISTS',
		),
	),
);

$products_query = new WP_Query( $args );

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Price Comparison</title>";
echo "<style>
	body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
	.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
	h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
	table { width: 100%; border-collapse: collapse; margin: 20px 0; }
	th, td { padding: 12px; text-align: left; border: 1px solid #ddd; }
	th { background: #0073aa; color: white; font-weight: bold; }
	tr:nth-child(even) { background: #f9f9f9; }
	.sku { font-family: monospace; font-weight: bold; color: #2271b1; }
	.price { font-weight: bold; }
	.dealer-price { color: #00a32a; }
	.retail-price { color: #d63638; }
	.difference { font-weight: bold; }
	.positive { color: #00a32a; }
	.negative { color: #d63638; }
	.status { padding: 5px 10px; border-radius: 3px; font-size: 12px; }
	.status-found { background: #d4edda; color: #155724; }
	.status-not-found { background: #f8d7da; color: #721c24; }
	.status-error { background: #fff3cd; color: #856404; }
</style></head><body>";
echo "<div class='container'>";

echo "<h1>💰 Price Comparison: Dealer vs Retail</h1>";
echo "<p>Comparing prices for products with dealer_product_id between dealer (B2B) and retail stores</p>";

if ( ! $products_query->have_posts() ) {
	echo "<p>No products found with dealer_product_id.</p>";
	echo "</div></body></html>";
	exit;
}

echo "<table>";
echo "<tr>";
echo "<th>#</th>";
echo "<th>WP ID</th>";
echo "<th>SKU</th>";
echo "<th>Product Title</th>";
echo "<th>Dealer Price<br/>(Your Store)</th>";
echo "<th>Retail Price<br/>(bearpaw-products.com)</th>";
echo "<th>Difference</th>";
echo "<th>Status</th>";
echo "</tr>";

$row_num = 0;

while ( $products_query->have_posts() ) {
	$products_query->the_post();
	$product_id = get_the_ID();
	
	$dealer_id = get_post_meta( $product_id, 'dealer_product_id', true );
	$sku = get_post_meta( $product_id, '_sku', true );
	$title = get_the_title();
	
	// Get dealer price from WooCommerce
	$product = wc_get_product( $product_id );
	$dealer_price = $product ? $product->get_regular_price() : 0;
	if ( ! $dealer_price && $product ) {
		$dealer_price = $product->get_price();
	}
	
	$row_num++;
	
	echo "<tr>";
	echo "<td>{$row_num}</td>";
	echo "<td>{$product_id}</td>";
	echo "<td class='sku'>" . ( $sku ? esc_html( $sku ) : 'N/A' ) . "</td>";
	echo "<td>" . esc_html( $title ) . "</td>";
	echo "<td class='price dealer-price'>" . ( $dealer_price ? number_format( $dealer_price, 2, '.', '' ) . ' EUR' : 'N/A' ) . "</td>";
	
	// Generate search URL for manual checking
	$search_url = $sku ? "https://www.bearpaw-products.com/search?q=" . urlencode( $sku ) : '';
	
	echo "<td class='price retail-price'>";
	if ( $search_url ) {
		echo "<a href='" . esc_url( $search_url ) . "' target='_blank' style='color: #2271b1; text-decoration: none;'>";
		echo "Check on Bearpaw →";
		echo "</a>";
	} else {
		echo "N/A";
	}
	echo "</td>";
	
	// Show note about manual check
	echo "<td style='color: #666; font-size: 12px;'>";
	echo "Click link to check";
	echo "</td>";
	
	echo "<td>";
	if ( $search_url ) {
		echo "<span class='status status-found'>Search link</span>";
	} else {
		echo "<span class='status status-error'>No SKU</span>";
	}
	echo "</td>";
	echo "</tr>";
}

wp_reset_postdata();

echo "</table>";

echo "<div style='margin-top: 30px; padding: 20px; background: #f0f6fc; border-left: 4px solid #2271b1;'>";
echo "<h3 style='margin-top: 0;'>Notes</h3>";
echo "<ul>";
echo "<li><strong>Dealer Price:</strong> Price set in your WooCommerce store</li>";
echo "<li><strong>Retail Price:</strong> Click the link to search for the product on bearpaw-products.com by SKU</li>";
echo "<li><strong>Note:</strong> Bearpaw website uses JavaScript for loading content, so automatic price extraction is not possible. Please check prices manually using the provided links.</li>";
echo "<li>Compare your dealer prices with retail prices to ensure you're offering 10% discount as planned.</li>";
echo "</ul>";
echo "</div>";

echo "</div></body></html>";

