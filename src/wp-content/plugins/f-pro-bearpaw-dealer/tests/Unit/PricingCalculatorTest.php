<?php
/**
 * Unit tests for PricingCalculator.
 *
 * @package FPro\BearpawDealer\Tests
 */

namespace FPro\BearpawDealer\Tests;

use FPro\BearpawDealer\Domain\PricingCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Test PricingCalculator price calculation logic.
 */
class PricingCalculatorTest extends TestCase {

	/**
	 * @var PricingCalculator
	 */
	private PricingCalculator $calculator;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		// Default settings: 20% margin, 10% discount, 0.2 EUR threshold
		$this->calculator = new PricingCalculator(20.0, 10.0, 0.2);
	}

	/**
	 * Test basic price calculation with both B2B and retail prices.
	 */
	public function test_calculate_price_with_both_prices(): void {
		// B2B: 100 EUR, Retail: 150 EUR
		// Desired: 100 * 1.2 = 120
		// Max allowed: 150 * 0.9 = 135
		// Result: min(120, 135) = 120
		$price = $this->calculator->calculate_price(100.0, 150.0);
		$this->assertEquals(120.0, $price);
	}

	/**
	 * Test price calculation when retail caps the price.
	 */
	public function test_calculate_price_retail_caps(): void {
		// B2B: 100 EUR, Retail: 110 EUR
		// Desired: 100 * 1.2 = 120
		// Max allowed: 110 * 0.9 = 99
		// Result: min(120, 99) = 99
		$price = $this->calculator->calculate_price(100.0, 110.0);
		$this->assertEquals(99.0, $price);
	}

	/**
	 * Test price calculation with only B2B price (no retail).
	 */
	public function test_calculate_price_only_b2b(): void {
		// B2B: 100 EUR, Retail: 0 (not available)
		// Desired: 100 * 1.2 = 120
		// Max allowed: 0 (ignored)
		// Result: 120
		$price = $this->calculator->calculate_price(100.0, 0.0);
		$this->assertEquals(120.0, $price);
	}

	/**
	 * Test price calculation returns 0 for invalid B2B price.
	 */
	public function test_calculate_price_zero_b2b(): void {
		$price = $this->calculator->calculate_price(0.0, 150.0);
		$this->assertEquals(0.0, $price);

		$price = $this->calculator->calculate_price(-10.0, 150.0);
		$this->assertEquals(0.0, $price);
	}

	/**
	 * Test price rounding to 2 decimal places.
	 */
	public function test_calculate_price_rounding(): void {
		// B2B: 33.33, Retail: 50
		// Desired: 33.33 * 1.2 = 39.996
		// Max: 50 * 0.9 = 45
		// Result: 40.00
		$price = $this->calculator->calculate_price(33.33, 50.0);
		$this->assertEquals(40.0, $price);
	}

	/**
	 * Test should_update_price returns true when difference exceeds threshold.
	 */
	public function test_should_update_price_above_threshold(): void {
		$this->assertTrue($this->calculator->should_update_price(100.0, 100.21));
		$this->assertTrue($this->calculator->should_update_price(100.0, 99.79));
		$this->assertTrue($this->calculator->should_update_price(100.0, 101.0));
	}

	/**
	 * Test should_update_price returns false when difference is within threshold.
	 */
	public function test_should_update_price_below_threshold(): void {
		$this->assertFalse($this->calculator->should_update_price(100.0, 100.1));
		$this->assertFalse($this->calculator->should_update_price(100.0, 99.9));
		$this->assertFalse($this->calculator->should_update_price(100.0, 100.2)); // Exactly at threshold
	}

	/**
	 * Test should_update_price with zero prices.
	 */
	public function test_should_update_price_zero(): void {
		$this->assertFalse($this->calculator->should_update_price(0.0, 0.0));
		$this->assertTrue($this->calculator->should_update_price(0.0, 0.21));
	}

	/**
	 * Test custom margin and discount settings.
	 */
	public function test_custom_settings(): void {
		$calc = new PricingCalculator(30.0, 15.0, 0.5);
		// B2B: 100, Retail: 200
		// Desired: 100 * 1.3 = 130
		// Max: 200 * 0.85 = 170
		// Result: 130
		$price = $calc->calculate_price(100.0, 200.0);
		$this->assertEquals(130.0, $price);
	}

	/**
	 * Test from_settings factory method.
	 */
	public function test_from_settings(): void {
		$settings = [
			'margin_percent'         => 25.0,
			'discount_percent'       => 12.0,
			'price_change_threshold' => 0.5,
		];

		$calc = PricingCalculator::from_settings($settings);

		$this->assertEquals(25.0, $calc->get_margin_percent());
		$this->assertEquals(12.0, $calc->get_discount_percent());
		$this->assertEquals(0.5, $calc->get_price_change_threshold());
	}

	/**
	 * Test from_settings with missing values uses defaults.
	 */
	public function test_from_settings_defaults(): void {
		$settings = [];

		$calc = PricingCalculator::from_settings($settings);

		$this->assertEquals(20.0, $calc->get_margin_percent());
		$this->assertEquals(10.0, $calc->get_discount_percent());
		$this->assertEquals(0.2, $calc->get_price_change_threshold());
	}

	/**
	 * Test edge case: retail price equals B2B price.
	 */
	public function test_retail_equals_b2b(): void {
		// B2B: 100, Retail: 100
		// Desired: 120
		// Max: 100 * 0.9 = 90
		// Result: 90 (capped by retail)
		$price = $this->calculator->calculate_price(100.0, 100.0);
		$this->assertEquals(90.0, $price);
	}

	/**
	 * Test edge case: retail price lower than B2B (unusual but possible).
	 */
	public function test_retail_lower_than_b2b(): void {
		// B2B: 100, Retail: 80
		// Desired: 120
		// Max: 80 * 0.9 = 72
		// Result: 72
		$price = $this->calculator->calculate_price(100.0, 80.0);
		$this->assertEquals(72.0, $price);
	}
}