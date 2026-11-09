<?php
/**
 * Product Matcher and Indexer
 *
 * Handles SKU-based matching between WooCommerce, B2B, and Retail products.
 * Provides efficient indexing and lookup for synchronization operations.
 */

namespace FPro\BearpawDealer\Domain;

/**
 * Interface for WooCommerce product objects.
 * Allows using test doubles in unit tests.
 */
interface WooCommerceProductInterface {
	public function get_id(): int;
	public function get_sku(): string;
	public function get_name(): string;
	public function get_regular_price(): string;
	public function get_price(): string;
	public function get_meta( string $key ): mixed;
}

/**
 * Value object representing a product variant from Bearpaw API.
 */
class ProductVariant {
	/** @var string */
	public string $sku;

	/** @var float */
	public float $price;

	/** @var int|string */
	public $variant_id;

	/** @var int|string */
	public $product_id;

	/** @var string */
	public string $product_title;

	/** @var string */
	public string $product_handle;

	/**
	 * Create from Bearpaw API variant data.
	 *
	 * @param array  $variant_data   Variant data from API.
	 * @param array  $product_data   Parent product data.
	 * @return self
	 */
	public static function from_api( array $variant_data, array $product_data ): self {
		$variant = new self();
		$variant->sku            = isset( $variant_data['sku'] ) ? trim( $variant_data['sku'] ) : '';
		$variant->price          = isset( $variant_data['price'] ) ? (float) $variant_data['price'] : 0.0;
		$variant->variant_id     = isset( $variant_data['id'] ) ? $variant_data['id'] : 0;
		$variant->product_id     = isset( $product_data['id'] ) ? $product_data['id'] : 0;
		$variant->product_title  = isset( $product_data['title'] ) ? $product_data['title'] : '';
		$variant->product_handle = isset( $product_data['handle'] ) ? $product_data['handle'] : '';
		return $variant;
	}

	/**
	 * Check if variant has valid SKU and price.
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		return $this->sku !== '' && $this->price > 0;
	}
}

/**
 * Value object representing a WooCommerce product with Bearpaw meta.
 */
class WooCommerceProduct {
	/** @var int */
	public int $id;

	/** @var string */
	public string $sku;

	/** @var string */
	public string $title;

	/** @var float */
	public float $current_price;

	/** @var int|string */
	public $dealer_product_id;

	/**
	 * Create from WC_Product object.
	 *
	 * @param WooCommerceProductInterface $product WooCommerce product.
	 * @return self
	 */
	public static function from_wc_product( WooCommerceProductInterface $product ): self {
		$wc_product = new self();
		$wc_product->id                 = $product->get_id();
		$wc_product->sku                = $product->get_sku() ? trim( $product->get_sku() ) : '';
		$wc_product->title              = $product->get_name();
		$wc_product->current_price      = (float) ( $product->get_regular_price() ?: $product->get_price() ?: 0 );
		$wc_product->dealer_product_id  = $product->get_meta( 'dealer_product_id' );
		return $wc_product;
	}

	/**
	 * Check if product has valid SKU.
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		return $this->sku !== '';
	}
}

/**
 * Indexed price data for a SKU.
 */
class SkuPriceIndex {
	/** @var float */
	public float $b2b_price = 0.0;

	/** @var float */
	public float $retail_price = 0.0;

	/** @var ProductVariant|null */
	public ?ProductVariant $b2b_variant = null;

	/** @var ProductVariant|null */
	public ?ProductVariant $retail_variant = null;

	/**
	 * Check if both prices are available.
	 *
	 * @return bool
	 */
	public function has_both_prices(): bool {
		return $this->b2b_price > 0 && $this->retail_price > 0;
	}

	/**
	 * Check if B2B price is available.
	 *
	 * @return bool
	 */
	public function has_b2b_price(): bool {
		return $this->b2b_price > 0;
	}

	/**
	 * Check if retail price is available.
	 *
	 * @return bool
	 */
	public function has_retail_price(): bool {
		return $this->retail_price > 0;
	}
}

/**
 * Product Matcher - builds and queries SKU indexes across sources.
 */
class ProductMatcher {
	/** @var array<string, SkuPriceIndex> */
	private array $sku_index = [];

	/** @var array<string, WooCommerceProduct> */
	private array $wc_products = [];

	/** @var array<string, ProductVariant> */
	private array $b2b_variants = [];

	/** @var array<string, ProductVariant> */
	private array $retail_variants = [];

	/**
	 * Build index from Bearpaw API products data.
	 *
	 * @param array $products_data Products array from API (with 'products' key).
	 * @param string $source Source name ('b2b' or 'retail').
	 * @return int Number of valid variants indexed.
	 */
	public function index_bearpaw_products( array $products_data, string $source ): int {
		$count = 0;

		if ( ! isset( $products_data['products'] ) || ! is_array( $products_data['products'] ) ) {
			return 0;
		}

		foreach ( $products_data['products'] as $product ) {
			if ( ! isset( $product['variants'] ) || ! is_array( $product['variants'] ) ) {
				continue;
			}

			foreach ( $product['variants'] as $variant_data ) {
				$variant = ProductVariant::from_api( $variant_data, $product );

				if ( ! $variant->is_valid() ) {
					continue;
				}

				$sku = $variant->sku;

				if ( $source === 'b2b' ) {
					// Keep minimum price for duplicate SKUs
					if ( ! isset( $this->b2b_variants[ $sku ] ) || $variant->price < $this->b2b_variants[ $sku ]->price ) {
						$this->b2b_variants[ $sku ] = $variant;
					}
				} elseif ( $source === 'retail' ) {
					if ( ! isset( $this->retail_variants[ $sku ] ) || $variant->price < $this->retail_variants[ $sku ]->price ) {
						$this->retail_variants[ $sku ] = $variant;
					}
				}

				$this->ensure_sku_index( $sku );
				$count++;
			}
		}

		// Sync prices to index
		$this->sync_index_prices();

		return $count;
	}

	/**
	 * Index WooCommerce products.
	 *
	 * @param array<WC_Product> $products WooCommerce products.
	 * @return int Number of products indexed.
	 */
	public function index_wc_products( array $products ): int {
		$count = 0;

		foreach ( $products as $product ) {
			$wc_product = WooCommerceProduct::from_wc_product( $product );

			if ( ! $wc_product->is_valid() ) {
				continue;
			}

			$this->wc_products[ $wc_product->sku ] = $wc_product;
			$this->ensure_sku_index( $wc_product->sku );
			$count++;
		}

		$this->sync_index_prices();

		return $count;
	}

	/**
	 * Ensure SKU exists in index.
	 *
	 * @param string $sku
	 */
	private function ensure_sku_index( string $sku ): void {
		if ( ! isset( $this->sku_index[ $sku ] ) ) {
			$this->sku_index[ $sku ] = new SkuPriceIndex();
		}
	}

	/**
	 * Sync variant prices to SKU index.
	 */
	private function sync_index_prices(): void {
		foreach ( $this->sku_index as $sku => $index ) {
			if ( isset( $this->b2b_variants[ $sku ] ) ) {
				$index->b2b_price   = $this->b2b_variants[ $sku ]->price;
				$index->b2b_variant = $this->b2b_variants[ $sku ];
			}
			if ( isset( $this->retail_variants[ $sku ] ) ) {
				$index->retail_price   = $this->retail_variants[ $sku ]->price;
				$index->retail_variant = $this->retail_variants[ $sku ];
			}
		}
	}

	/**
	 * Get price index for a SKU.
	 *
	 * @param string $sku
	 * @return SkuPriceIndex|null
	 */
	public function get_sku_index( string $sku ): ?SkuPriceIndex {
		return $this->sku_index[ $sku ] ?? null;
	}

	/**
	 * Get all SKU indexes.
	 *
	 * @return array<string, SkuPriceIndex>
	 */
	public function get_all_indexes(): array {
		return $this->sku_index;
	}

	/**
	 * Get WooCommerce product by SKU.
	 *
	 * @param string $sku
	 * @return WooCommerceProduct|null
	 */
	public function get_wc_product( string $sku ): ?WooCommerceProduct {
		return $this->wc_products[ $sku ] ?? null;
	}

	/**
	 * Get B2B variant by SKU.
	 *
	 * @param string $sku
	 * @return ProductVariant|null
	 */
	public function get_b2b_variant( string $sku ): ?ProductVariant {
		return $this->b2b_variants[ $sku ] ?? null;
	}

	/**
	 * Get Retail variant by SKU.
	 *
	 * @param string $sku
	 * @return ProductVariant|null
	 */
	public function get_retail_variant( string $sku ): ?ProductVariant {
		return $this->retail_variants[ $sku ] ?? null;
	}

	/**
	 * Get matching statistics.
	 *
	 * @return array Statistics array.
	 */
	public function get_statistics(): array {
		$total_skus     = count( $this->sku_index );
		$wc_count       = count( $this->wc_products );
		$b2b_count      = count( $this->b2b_variants );
		$retail_count   = count( $this->retail_variants );

		$wc_b2b_matches     = 0;
		$wc_retail_matches  = 0;
		$wc_both_matches    = 0;
		$wc_no_matches      = 0;
		$b2b_only           = 0;
		$retail_only        = 0;
		$both_only          = 0;

		foreach ( $this->sku_index as $sku => $index ) {
			$has_wc    = isset( $this->wc_products[ $sku ] );
			$has_b2b   = $index->has_b2b_price();
			$has_retail = $index->has_retail_price();

			if ( $has_wc ) {
				if ( $has_b2b ) {
					$wc_b2b_matches++;
				}
				if ( $has_retail ) {
					$wc_retail_matches++;
				}
				if ( $has_b2b && $has_retail ) {
					$wc_both_matches++;
				}
				if ( ! $has_b2b && ! $has_retail ) {
					$wc_no_matches++;
				}
			} else {
				if ( $has_b2b && $has_retail ) {
					$both_only++;
				} elseif ( $has_b2b ) {
					$b2b_only++;
				} elseif ( $has_retail ) {
					$retail_only++;
				}
			}
		}

		return [
			'total_skus'          => $total_skus,
			'wc_products'         => $wc_count,
			'b2b_variants'        => $b2b_count,
			'retail_variants'     => $retail_count,
			'wc_b2b_matches'      => $wc_b2b_matches,
			'wc_retail_matches'   => $wc_retail_matches,
			'wc_both_matches'     => $wc_both_matches,
			'wc_no_matches'       => $wc_no_matches,
			'b2b_only'            => $b2b_only,
			'retail_only'         => $retail_only,
			'both_only'           => $both_only,
		];
	}

	/**
	 * Get sample products for display.
	 *
	 * @param int $limit Maximum samples.
	 * @return array Sample product data.
	 */
	public function get_sample_products( int $limit = 10 ): array {
		$samples = [];

		foreach ( $this->sku_index as $sku => $index ) {
			if ( count( $samples ) >= $limit ) {
				break;
			}

			$wc_product = $this->get_wc_product( $sku );

			$samples[] = [
				'sku'            => $sku,
				'wc_product'     => $wc_product,
				'b2b_price'      => $index->has_b2b_price() ? $index->b2b_price : null,
				'retail_price'   => $index->has_retail_price() ? $index->retail_price : null,
				'has_b2b'        => $index->has_b2b_price(),
				'has_retail'     => $index->has_retail_price(),
				'b2b_variant'    => $index->b2b_variant,
				'retail_variant' => $index->retail_variant,
			];
		}

		return $samples;
	}

	/**
	 * Get products missing from WooCommerce (exist in B2B but not in WC).
	 *
	 * @return array Array of missing product data with calculated prices.
	 */
	public function get_missing_products( PricingCalculator $calculator ): array {
		$missing = [];

		foreach ( $this->b2b_variants as $sku => $variant ) {
			// Skip if already in WooCommerce
			if ( isset( $this->wc_products[ $sku ] ) ) {
				continue;
			}

			$retail_price = 0.0;
			if ( isset( $this->retail_variants[ $sku ] ) ) {
				$retail_price = $this->retail_variants[ $sku ]->price;
			}

			$calculated_price = $calculator->calculate_price( $variant->price, $retail_price );

			// Build B2B product URL
			$b2b_url = '';
			if ( $variant->product_handle ) {
				$b2b_url = 'https://www.b2bportal.bearpaw-products.de/products/' . $variant->product_handle;
			} elseif ( $sku ) {
				$b2b_url = 'https://www.b2bportal.bearpaw-products.de/search?q=' . urlencode( $sku );
			} elseif ( $variant->product_id ) {
				$b2b_url = 'https://www.b2bportal.bearpaw-products.de/products/' . $variant->product_id;
			}

			$missing[] = [
				'title'             => $variant->product_title,
				'sku'               => $sku,
				'price'             => $variant->price,
				'calculated_price'  => $calculated_price,
				'b2b_product_url'   => $b2b_url,
				'retail_price'      => $retail_price > 0 ? $retail_price : null,
				'product_id'        => $variant->product_id,
				'product_handle'    => $variant->product_handle,
			];
		}

		return $missing;
	}

	/**
	 * Get products eligible for price update (exist in WC, B2B, and Retail).
	 *
	 * @return array<string, array> Keyed by SKU with price data.
	 */
	public function get_updatable_products(): array {
		$updatable = [];

		foreach ( $this->wc_products as $sku => $wc_product ) {
			$index = $this->get_sku_index( $sku );

			if ( ! $index || ! $index->has_both_prices() ) {
				continue;
			}

			$updatable[ $sku ] = [
				'wc_product'    => $wc_product,
				'b2b_price'     => $index->b2b_price,
				'retail_price'  => $index->retail_price,
				'b2b_variant'   => $index->b2b_variant,
				'retail_variant'=> $index->retail_variant,
			];
		}

		return $updatable;
	}

	/**
	 * Clear all indexes.
	 */
	public function clear(): void {
		$this->sku_index       = [];
		$this->wc_products     = [];
		$this->b2b_variants    = [];
		$this->retail_variants = [];
	}
}