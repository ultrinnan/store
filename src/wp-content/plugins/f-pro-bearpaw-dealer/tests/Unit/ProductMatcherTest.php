<?php
/**
 * Unit tests for ProductMatcher.
 *
 * @package FPro\BearpawDealer\Tests
 */

namespace FPro\BearpawDealer\Tests;

use FPro\BearpawDealer\Domain\ProductMatcher;
use FPro\BearpawDealer\Domain\ProductVariant;
use FPro\BearpawDealer\Domain\WooCommerceProduct;
use FPro\BearpawDealer\Domain\WooCommerceProductInterface;
use FPro\BearpawDealer\Domain\PricingCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Test ProductMatcher functionality.
 */
class ProductMatcherTest extends TestCase {

	/** @var ProductMatcher */
	private ProductMatcher $matcher;

	/** @var PricingCalculator */
	private PricingCalculator $calculator;

	protected function setUp(): void {
		$this->matcher   = new ProductMatcher();
		$this->calculator = new PricingCalculator( 20.0, 10.0, 0.2 );
	}

	/**
	 * Create mock Bearpaw product data.
	 */
	private function create_bearpaw_product( string $id, string $title, string $handle, array $variants ): array {
		return [
			'id'      => $id,
			'title'   => $title,
			'handle'  => $handle,
			'variants' => $variants,
		];
	}

	/**
	 * Create mock variant data.
	 */
	private function create_variant( string $id, string $sku, float $price ): array {
		return [
			'id'    => $id,
			'sku'   => $sku,
			'price' => $price,
		];
	}

	/**
	 * Test indexing B2B products.
	 */
	public function test_index_b2b_products(): void {
		$b2b_data = [
			'products' => [
				$this->create_bearpaw_product( '1', 'Product A', 'product-a', [
					$this->create_variant( 'v1', 'SKU001', 100.0 ),
					$this->create_variant( 'v2', 'SKU002', 150.0 ),
				] ),
				$this->create_bearpaw_product( '2', 'Product B', 'product-b', [
					$this->create_variant( 'v3', 'SKU003', 200.0 ),
				] ),
			],
		];

		$count = $this->matcher->index_bearpaw_products( $b2b_data, 'b2b' );

		$this->assertEquals( 3, $count );
		$this->assertEquals( 100.0, $this->matcher->get_b2b_variant( 'SKU001' )->price );
		$this->assertEquals( 150.0, $this->matcher->get_b2b_variant( 'SKU002' )->price );
		$this->assertEquals( 200.0, $this->matcher->get_b2b_variant( 'SKU003' )->price );
	}

	/**
	 * Test indexing Retail products.
	 */
	public function test_index_retail_products(): void {
		$retail_data = [
			'products' => [
				$this->create_bearpaw_product( '1', 'Product A', 'product-a', [
					$this->create_variant( 'v1', 'SKU001', 120.0 ),
					$this->create_variant( 'v2', 'SKU002', 180.0 ),
				] ),
			],
		];

		$count = $this->matcher->index_bearpaw_products( $retail_data, 'retail' );

		$this->assertEquals( 2, $count );
		$this->assertEquals( 120.0, $this->matcher->get_retail_variant( 'SKU001' )->price );
		$this->assertEquals( 180.0, $this->matcher->get_retail_variant( 'SKU002' )->price );
	}

	/**
	 * Test duplicate SKU handling (keeps minimum price).
	 */
	public function test_duplicate_sku_keeps_min_price(): void {
		$b2b_data = [
			'products' => [
				$this->create_bearpaw_product( '1', 'Product A', 'product-a', [
					$this->create_variant( 'v1', 'SKU001', 100.0 ),
				] ),
				$this->create_bearpaw_product( '2', 'Product B', 'product-b', [
					$this->create_variant( 'v2', 'SKU001', 80.0 ), // Same SKU, lower price
				] ),
			],
		];

		$this->matcher->index_bearpaw_products( $b2b_data, 'b2b' );

		$this->assertEquals( 80.0, $this->matcher->get_b2b_variant( 'SKU001' )->price );
		$this->assertEquals( '2', $this->matcher->get_b2b_variant( 'SKU001' )->product_id );
	}

	/**
	 * Test SKU index has combined prices.
	 */
	public function test_sku_index_combines_prices(): void {
		$b2b_data = [
			'products' => [
				$this->create_bearpaw_product( '1', 'Product A', 'product-a', [
					$this->create_variant( 'v1', 'SKU001', 100.0 ),
				] ),
			],
		];

		$retail_data = [
			'products' => [
				$this->create_bearpaw_product( '1', 'Product A', 'product-a', [
					$this->create_variant( 'v1', 'SKU001', 150.0 ),
				] ),
			],
		];

		$this->matcher->index_bearpaw_products( $b2b_data, 'b2b' );
		$this->matcher->index_bearpaw_products( $retail_data, 'retail' );

		$index = $this->matcher->get_sku_index( 'SKU001' );
		$this->assertNotNull( $index );
		$this->assertEquals( 100.0, $index->b2b_price );
		$this->assertEquals( 150.0, $index->retail_price );
		$this->assertTrue( $index->has_both_prices() );
	}

	/**
	 * Test statistics calculation.
	 */
	public function test_statistics(): void {
		$b2b_data = [
			'products' => [
				$this->create_bearpaw_product( '1', 'Product A', 'product-a', [
					$this->create_variant( 'v1', 'SKU001', 100.0 ),
					$this->create_variant( 'v2', 'SKU002', 150.0 ),
				] ),
			],
		];

		$retail_data = [
			'products' => [
				$this->create_bearpaw_product( '1', 'Product A', 'product-a', [
					$this->create_variant( 'v1', 'SKU001', 150.0 ),
				] ),
			],
		];

		$this->matcher->index_bearpaw_products( $b2b_data, 'b2b' );
		$this->matcher->index_bearpaw_products( $retail_data, 'retail' );

		// Add WC product matching SKU001
		$wc_product = $this->create_test_wc_product( 1, 'SKU001', 'WC Product A', 120.0 );
		$this->matcher->index_wc_products( [ $wc_product ] );

		$stats = $this->matcher->get_statistics();

		$this->assertEquals( 2, $stats['total_skus'] );
		$this->assertEquals( 1, $stats['wc_products'] );
		$this->assertEquals( 2, $stats['b2b_variants'] );
		$this->assertEquals( 1, $stats['retail_variants'] );
		$this->assertEquals( 1, $stats['wc_b2b_matches'] );
		$this->assertEquals( 1, $stats['wc_retail_matches'] );
		$this->assertEquals( 1, $stats['wc_both_matches'] );
		$this->assertEquals( 0, $stats['wc_no_matches'] );
	}

	/**
	 * Test missing products detection.
	 */
	public function test_get_missing_products(): void {
		$b2b_data = [
			'products' => [
				$this->create_bearpaw_product( '1', 'Product A', 'product-a', [
					$this->create_variant( 'v1', 'SKU001', 100.0 ),
				] ),
				$this->create_bearpaw_product( '2', 'Product B', 'product-b', [
					$this->create_variant( 'v2', 'SKU002', 150.0 ),
				] ),
			],
		];

		$retail_data = [
			'products' => [
				$this->create_bearpaw_product( '1', 'Product A', 'product-a', [
					$this->create_variant( 'v1', 'SKU001', 150.0 ),
				] ),
			],
		];

		$this->matcher->index_bearpaw_products( $b2b_data, 'b2b' );
		$this->matcher->index_bearpaw_products( $retail_data, 'retail' );

		// Add WC product for SKU001 (should NOT be missing)
		$wc_product = $this->create_test_wc_product( 1, 'SKU001', 'WC Product A', 120.0 );
		$this->matcher->index_wc_products( [ $wc_product ] );

		$missing = $this->matcher->get_missing_products( $this->calculator );

		// Only SKU002 should be missing
		$this->assertCount( 1, $missing );
		$this->assertEquals( 'SKU002', $missing[0]['sku'] );
		$this->assertEquals( 150.0, $missing[0]['price'] );
		// Calculated price: min(150 * 1.2, 150 * 0.9) = min(180, 135) = 135... wait, retail not available for SKU002
		// So calculated = 150 * 1.2 = 180
		$this->assertEquals( 180.0, $missing[0]['calculated_price'] );
		$this->assertStringContainsString( 'product-b', $missing[0]['b2b_product_url'] );
	}

	/**
	 * Test updatable products detection.
	 */
	public function test_get_updatable_products(): void {
		$b2b_data = [
			'products' => [
				$this->create_bearpaw_product( '1', 'Product A', 'product-a', [
					$this->create_variant( 'v1', 'SKU001', 100.0 ),
					$this->create_variant( 'v2', 'SKU002', 150.0 ),
				] ),
			],
		];

		$retail_data = [
			'products' => [
				$this->create_bearpaw_product( '1', 'Product A', 'product-a', [
					$this->create_variant( 'v1', 'SKU001', 150.0 ),
				] ),
			],
		];

		$this->matcher->index_bearpaw_products( $b2b_data, 'b2b' );
		$this->matcher->index_bearpaw_products( $retail_data, 'retail' );

		// Add WC products
		$wc_product1 = $this->create_test_wc_product( 1, 'SKU001', 'WC Product A', 120.0 );
		$wc_product2 = $this->create_test_wc_product( 2, 'SKU002', 'WC Product B', 160.0 ); // No retail match
		$this->matcher->index_wc_products( [ $wc_product1, $wc_product2 ] );

		$updatable = $this->matcher->get_updatable_products();

		// Only SKU001 has both B2B and retail prices
		$this->assertCount( 1, $updatable );
		$this->assertArrayHasKey( 'SKU001', $updatable );
		$this->assertEquals( 1, $updatable['SKU001']['wc_product']->id );
		$this->assertEquals( 100.0, $updatable['SKU001']['b2b_price'] );
		$this->assertEquals( 150.0, $updatable['SKU001']['retail_price'] );
	}

	/**
	 * Test sample products.
	 */
	public function test_get_sample_products(): void {
		$b2b_data = [
			'products' => [
				$this->create_bearpaw_product( '1', 'Product A', 'product-a', [
					$this->create_variant( 'v1', 'SKU001', 100.0 ),
				] ),
			],
		];

		$this->matcher->index_bearpaw_products( $b2b_data, 'b2b' );

		$wc_product = $this->create_test_wc_product( 1, 'SKU001', 'WC Product A', 120.0 );
		$this->matcher->index_wc_products( [ $wc_product ] );

		$samples = $this->matcher->get_sample_products( 5 );

		$this->assertCount( 1, $samples );
		$this->assertEquals( 'SKU001', $samples[0]['sku'] );
		$this->assertEquals( 100.0, $samples[0]['b2b_price'] );
		$this->assertNull( $samples[0]['retail_price'] );
		$this->assertNotNull( $samples[0]['wc_product'] );
	}

	/**
	 * Test clear resets all indexes.
	 */
	public function test_clear(): void {
		$b2b_data = [
			'products' => [
				$this->create_bearpaw_product( '1', 'Product A', 'product-a', [
					$this->create_variant( 'v1', 'SKU001', 100.0 ),
				] ),
			],
		];

		$this->matcher->index_bearpaw_products( $b2b_data, 'b2b' );
		$this->assertEquals( 1, count( $this->matcher->get_all_indexes() ) );

		$this->matcher->clear();
		$this->assertEquals( 0, count( $this->matcher->get_all_indexes() ) );
	}

	/**
	 * Create test double for WC_Product.
	 */
	private function create_test_wc_product( int $id, string $sku, string $title, float $price ): WooCommerceProductInterface {
		return new class( $id, $sku, $title, $price ) implements \FPro\BearpawDealer\Domain\WooCommerceProductInterface {
			private int $id;
			private string $sku;
			private string $title;
			private float $price;

			public function __construct( int $id, string $sku, string $title, float $price ) {
				$this->id    = $id;
				$this->sku   = $sku;
				$this->title = $title;
				$this->price = $price;
			}

			public function get_id(): int { return $this->id; }
			public function get_sku(): string { return $this->sku; }
			public function get_name(): string { return $this->title; }
			public function get_regular_price(): string { return (string) $this->price; }
			public function get_price(): string { return (string) $this->price; }
			public function get_meta( string $key ): mixed {
				return $key === 'dealer_product_id' ? 'shopify_' . $this->id : null;
			}
		};
	}
}