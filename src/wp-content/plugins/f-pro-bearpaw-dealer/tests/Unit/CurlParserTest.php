<?php
/**
 * Unit tests for CurlParser.
 *
 * @package FPro\BearpawDealer\Tests
 */

namespace FPro\BearpawDealer\Tests;

use FPro\BearpawDealer\API\CurlParser;
use PHPUnit\Framework\TestCase;

/**
 * Test CurlParser functionality.
 */
class CurlParserTest extends TestCase {

	/**
	 * Test parsing standard cURL with single quotes.
	 */
	public function test_parse_standard_curl(): void {
		$curl = "curl 'https://www.b2bportal.bearpaw-products.de/products.json' -H 'accept: application/json' -H 'cookie: session=abc123' -b 'cart_currency=EUR; _shopify_y=xyz'";

		$parsed = CurlParser::parse( $curl );

		$this->assertNotFalse( $parsed );
		$this->assertEquals( 'https://www.b2bportal.bearpaw-products.de/products.json', $parsed['url'] );
		$this->assertEquals( 'cart_currency=EUR; _shopify_y=xyz', $parsed['cookies'] );
		$this->assertEquals( 'application/json', $parsed['headers']['accept'] );
		$this->assertEquals( 'session=abc123', $parsed['headers']['cookie'] );
	}

	/**
	 * Test parsing cURL with double quotes.
	 */
	public function test_parse_double_quotes(): void {
		$curl = 'curl "https://bearpaw-products.com/products.json" -H "accept: text/html" --cookie "session=abc"';

		$parsed = CurlParser::parse( $curl );

		$this->assertNotFalse( $parsed );
		$this->assertEquals( 'https://bearpaw-products.com/products.json', $parsed['url'] );
		$this->assertEquals( 'session=abc', $parsed['cookies'] );
		$this->assertEquals( 'text/html', $parsed['headers']['accept'] );
	}

	/**
	 * Test parsing cURL without quotes around URL.
	 */
	public function test_parse_unquoted_url(): void {
		$curl = "curl https://www.b2bportal.bearpaw-products.de/products.json -H 'accept: application/json'";

		$parsed = CurlParser::parse( $curl );

		$this->assertNotFalse( $parsed );
		$this->assertEquals( 'https://www.b2bportal.bearpaw-products.de/products.json', $parsed['url'] );
	}

	/**
	 * Test parsing cURL with multiple headers.
	 */
	public function test_parse_multiple_headers(): void {
		$curl = "curl 'https://api.example.com' -H 'header1: value1' -H 'header2: value2' -H 'header3: value3'";

		$parsed = CurlParser::parse( $curl );

		$this->assertNotFalse( $parsed );
		$this->assertCount( 3, $parsed['headers'] );
		$this->assertEquals( 'value1', $parsed['headers']['header1'] );
		$this->assertEquals( 'value2', $parsed['headers']['header2'] );
		$this->assertEquals( 'value3', $parsed['headers']['header3'] );
	}

	/**
	 * Test parsing cURL with escaped quotes in header values.
	 */
	public function test_parse_escaped_quotes(): void {
		$curl = "curl 'https://api.example.com' -H 'authorization: Bearer token\\\"with\\\"quotes'";

		$parsed = CurlParser::parse( $curl );

		$this->assertNotFalse( $parsed );
		$this->assertEquals( 'Bearer token"with"quotes', $parsed['headers']['authorization'] );
	}

	/**
	 * Test parsing cURL with Cookie header instead of -b flag.
	 */
	public function test_parse_cookie_header(): void {
		$curl = "curl 'https://api.example.com' -H 'cookie: session=abc123; cart=EUR'";

		$parsed = CurlParser::parse( $curl );

		$this->assertNotFalse( $parsed );
		$this->assertEquals( 'session=abc123; cart=EUR', $parsed['cookies'] );
		$this->assertArrayNotHasKey( 'cookie', $parsed['headers'] );
	}

	/**
	 * Test parsing cURL with both -b and Cookie header (prefers -b).
	 */
	public function test_parse_both_cookie_sources(): void {
		$curl = "curl 'https://api.example.com' -b 'from_flag=1' -H 'cookie: from_header=2'";

		$parsed = CurlParser::parse( $curl );

		$this->assertNotFalse( $parsed );
		$this->assertEquals( 'from_flag=1', $parsed['cookies'] );
	}

	/**
	 * Test parsing cURL with common flags that should be ignored.
	 */
	public function test_parse_ignored_flags(): void {
		$curl = "curl 'https://api.example.com' --compressed -s -v -H 'accept: json'";

		$parsed = CurlParser::parse( $curl );

		$this->assertNotFalse( $parsed );
		$this->assertEquals( 'json', $parsed['headers']['accept'] );
	}

	/**
	 * Test parsing empty or invalid cURL returns false.
	 */
	public function test_parse_empty_returns_false(): void {
		$this->assertFalse( CurlParser::parse( '' ) );
		$this->assertFalse( CurlParser::parse( '   ' ) );
		$this->assertFalse( CurlParser::parse( 'not a curl command' ) );
		$this->assertFalse( CurlParser::parse( 'wget https://example.com' ) );
	}

	/**
	 * Test parsing cURL without URL returns false.
	 */
	public function test_parse_no_url_returns_false(): void {
		$curl = "curl -H 'accept: json'";
		$this->assertFalse( CurlParser::parse( $curl ) );
	}

	/**
	 * Test validate_for_bearpaw with valid B2B URL.
	 */
	public function test_validate_b2b_url(): void {
		$parsed = [
			'url'     => 'https://www.b2bportal.bearpaw-products.de/products.json',
			'cookies' => 'session=abc',
			'headers' => [],
		];

		$this->assertTrue( CurlParser::validate_for_bearpaw( $parsed ) );
	}

	/**
	 * Test validate_for_bearpaw with valid Retail URL.
	 */
	public function test_validate_retail_url(): void {
		$parsed = [
			'url'     => 'https://bearpaw-products.com/products.json',
			'cookies' => 'session=abc',
			'headers' => [],
		];

		$this->assertTrue( CurlParser::validate_for_bearpaw( $parsed ) );
	}

	/**
	 * Test validate_for_bearpaw rejects invalid URLs.
	 */
	public function test_validate_rejects_invalid(): void {
		$parsed = [
			'url'     => 'https://example.com/api',
			'cookies' => 'session=abc',
			'headers' => [],
		];

		$this->assertFalse( CurlParser::validate_for_bearpaw( $parsed ) );
	}

	/**
	 * Test validate_for_bearpaw rejects missing cookies.
	 */
	public function test_validate_rejects_no_cookies(): void {
		$parsed = [
			'url'     => 'https://www.b2bportal.bearpaw-products.de/products.json',
			'cookies' => '',
			'headers' => [],
		];

		$this->assertFalse( CurlParser::validate_for_bearpaw( $parsed ) );
	}

	/**
	 * Test extract_base_url removes query parameters.
	 */
	public function test_extract_base_url(): void {
		$parsed = [
			'url'     => 'https://www.b2bportal.bearpaw-products.de/products.json?page=1&limit=250',
			'cookies' => 'session=abc',
			'headers' => [],
		];

		$base = CurlParser::extract_base_url( $parsed );
		$this->assertEquals( 'https://www.b2bportal.bearpaw-products.de/products.json', $base );
	}

	/**
	 * Test parsing real-world cURL from Chrome DevTools.
	 */
	public function test_parse_chrome_devtools_format(): void {
		// Typical format from Chrome "Copy as cURL" (single line, no backslash continuations)
		$curl = "curl 'https://www.b2bportal.bearpaw-products.de/products.json?page=1&limit=250' -H 'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8' -H 'accept-language: en-GB,en-US;q=0.9,en;q=0.8,uk;q=0.7' -H 'cache-control: max-age=0' -H 'cookie: cart_currency=EUR; _shopify_y=abc123; localization=DE' -H 'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36' --compressed";

		$parsed = CurlParser::parse( $curl );

		$this->assertNotFalse( $parsed );
		$this->assertStringContainsString( 'b2bportal.bearpaw-products.de', $parsed['url'] );
		$this->assertStringContainsString( 'cart_currency=EUR', $parsed['cookies'] );
		$this->assertStringContainsString( '_shopify_y=abc123', $parsed['cookies'] );
		$this->assertEquals( 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8', $parsed['headers']['accept'] );
	}
}