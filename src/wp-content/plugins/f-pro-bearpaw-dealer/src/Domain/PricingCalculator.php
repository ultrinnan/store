<?php
/**
 * Pricing Calculator for Bearpaw Dealer
 *
 * Handles all price calculation logic in a testable, isolated class.
 */

namespace FPro\BearpawDealer\Domain;

/**
 * Calculates optimal dealer prices based on B2B and Retail prices.
 */
class PricingCalculator {
	/**
	 * Margin percentage above B2B price (dealer margin).
	 *
	 * @var float
	 */
	private float $margin_percent;

	/**
	 * Discount percentage below retail price.
	 *
	 * @var float
	 */
	private float $discount_percent;

	/**
	 * Minimum price difference to trigger an update.
	 *
	 * @var float
	 */
	private float $price_change_threshold;

	/**
	 * Constructor.
	 *
	 * @param float $margin_percent        Margin above B2B price (default: 20.0).
	 * @param float $discount_percent      Discount below retail price (default: 10.0).
	 * @param float $price_change_threshold Minimum price change to update (default: 0.2).
	 */
	public function __construct(
		float $margin_percent = 20.0,
		float $discount_percent = 10.0,
		float $price_change_threshold = 0.2
	) {
		$this->margin_percent        = $margin_percent;
		$this->discount_percent      = $discount_percent;
		$this->price_change_threshold = $price_change_threshold;
	}

	/**
	 * Calculate the recommended price for a product.
	 *
	 * Formula:
	 * 1. Desired price = B2B Price × (1 + Margin%)
	 * 2. Maximum allowed price = Retail Price × (1 - Discount%)
	 * 3. Final price = min(Desired price, Maximum allowed price)
	 *
	 * @param float $b2b_price    B2B/dealer price.
	 * @param float $retail_price Retail price (0 if not available).
	 * @return float Calculated price rounded to 2 decimals.
	 */
	public function calculate_price(float $b2b_price, float $retail_price = 0.0): float {
		if ($b2b_price <= 0) {
			return 0.0;
		}

		// Desired price: B2B price + margin
		$desired_price = $b2b_price * (1 + $this->margin_percent / 100);

		// Maximum allowed price: retail price - discount
		$max_allowed_price = 0.0;
		if ($retail_price > 0) {
			$max_allowed_price = $retail_price * (1 - $this->discount_percent / 100);
		}

		// Use the lower of the two prices
		$calculated_price = $max_allowed_price > 0 && $desired_price > $max_allowed_price
			? $max_allowed_price
			: $desired_price;

		return round($calculated_price, 2);
	}

	/**
	 * Determine if a price should be updated based on the threshold.
	 *
	 * @param float $current_price Current price in WooCommerce.
	 * @param float $new_price     Calculated new price.
	 * @return bool True if price should be updated.
	 */
	public function should_update_price(float $current_price, float $new_price): bool {
		$diff = abs($current_price - $new_price);
		// Use a small epsilon to handle floating point precision issues
		return $diff > $this->price_change_threshold + 0.0001;
	}

	/**
	 * Get the margin percentage.
	 *
	 * @return float
	 */
	public function get_margin_percent(): float {
		return $this->margin_percent;
	}

	/**
	 * Get the discount percentage.
	 *
	 * @return float
	 */
	public function get_discount_percent(): float {
		return $this->discount_percent;
	}

	/**
	 * Get the price change threshold.
	 *
	 * @return float
	 */
	public function get_price_change_threshold(): float {
		return $this->price_change_threshold;
	}

	/**
	 * Create a calculator from plugin settings array.
	 *
	 * @param array $settings Plugin settings array.
	 * @return self
	 */
	public static function from_settings(array $settings): self {
		return new self(
			isset($settings['margin_percent']) ? (float) $settings['margin_percent'] : 20.0,
			isset($settings['discount_percent']) ? (float) $settings['discount_percent'] : 10.0,
			isset($settings['price_change_threshold']) ? (float) $settings['price_change_threshold'] : 0.2
		);
	}
}