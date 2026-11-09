<?php
/**
 * Bearpaw API Client
 *
 * Handles all HTTP communication with Bearpaw B2B and Retail APIs.
 */

namespace FPro\BearpawDealer\API;

use WP_Error;

/**
 * Client for Bearpaw JSON API endpoints.
 */
class BearpawClient {
	/**
	 * B2B Portal base URL.
	 *
	 * @var string
	 */
	private const B2B_BASE_URL = 'https://www.b2bportal.bearpaw-products.de';

	/**
	 * Retail site base URL.
	 *
	 * @var string
	 */
	private const RETAIL_BASE_URL = 'https://bearpaw-products.com';

	/**
	 * Products endpoint path.
	 *
	 * @var string
	 */
	private const PRODUCTS_ENDPOINT = '/products.json';

	/**
	 * Default request timeout in seconds.
	 *
	 * @var int
	 */
	private const DEFAULT_TIMEOUT = 30;

	/**
	 * Default limit per page (Shopify max).
	 *
	 * @var int
	 */
	private const PAGE_LIMIT = 250;

	/**
	 * Maximum pages to fetch (safety limit).
	 *
	 * @var int
	 */
	private const MAX_PAGES = 50;

	/**
	 * Delay between requests in microseconds.
	 *
	 * @var int
	 */
	private const REQUEST_DELAY_US = 200000; // 0.2 seconds

	/**
	 * Authentication cookies.
	 *
	 * @var string
	 */
	private string $cookies = '';

	/**
	 * Additional headers.
	 *
	 * @var array
	 */
	private array $headers = [];

	/**
	 * Base URL for API requests.
	 *
	 * @var string
	 */
	private string $base_url = '';

	/**
	 * HTTP client args.
	 *
	 * @var array
	 */
	private array $http_args = [];

	/**
	 * Logger instance (optional).
	 *
	 * @var callable|null
	 */
	private $logger = null;

	/**
	 * Constructor.
	 *
	 * @param string $base_url     Base URL (B2B or Retail).
	 * @param string $cookies      Authentication cookies.
	 * @param array  $headers      Additional headers.
	 * @param array  $options      Additional options (timeout, logger, etc.).
	 */
	public function __construct(
		string $base_url,
		string $cookies,
		array $headers = [],
		array $options = []
	) {
		$this->base_url = rtrim( $base_url, '/' );
		$this->cookies  = $cookies;
		$this->headers  = $headers;
		$this->http_args = array_merge( [
			'timeout'     => self::DEFAULT_TIMEOUT,
			'sslverify'   => true,
			'redirection' => 5,
		], $options );

		if ( isset( $options['logger'] ) && is_callable( $options['logger'] ) ) {
			$this->logger = $options['logger'];
		}
	}

	/**
	 * Create client for B2B Portal.
	 *
	 * @param string $cookies Authentication cookies from cURL command.
	 * @param array  $headers Additional headers from cURL command.
	 * @param array  $options Additional options.
	 * @return self
	 */
	public static function for_b2b( string $cookies, array $headers = [], array $options = [] ): self {
		return new self( self::B2B_BASE_URL, $cookies, $headers, $options );
	}

	/**
	 * Create client for Retail site.
	 *
	 * @param string $cookies Authentication cookies from cURL command.
	 * @param array  $headers Additional headers from cURL command.
	 * @param array  $options Additional options.
	 * @return self
	 */
	public static function for_retail( string $cookies, array $headers = [], array $options = [] ): self {
		return new self( self::RETAIL_BASE_URL, $cookies, $headers, $options );
	}

	/**
	 * Fetch all products with pagination.
	 *
	 * @param int $max_pages Maximum pages to fetch (0 = no limit).
	 * @return array All products from all pages.
	 * @throws \Exception On API error.
	 */
	public function get_all_products( int $max_pages = 0 ): array {
		$all_products = [];
		$page         = 1;
		$has_more     = true;
		$limit        = $max_pages > 0 ? $max_pages : self::MAX_PAGES;

		while ( $has_more && $page <= $limit ) {
			$url = $this->build_products_url( $page );

			$response = $this->fetch_page( $url );

			if ( $response === false ) {
				$has_more = false;
				break;
			}

			if ( ! isset( $response['products'] ) || ! is_array( $response['products'] ) ) {
				$this->log( 'warning', 'Invalid response structure', [ 'page' => $page ] );
				$has_more = false;
				break;
			}

			$products = $response['products'];

			if ( empty( $products ) ) {
				$has_more = false;
				break;
			}

			$all_products = array_merge( $all_products, $products );

			if ( count( $products ) < self::PAGE_LIMIT ) {
				$has_more = false;
			} else {
				$page++;
			}

			if ( $has_more ) {
				usleep( self::REQUEST_DELAY_US );
			}
		}

		$this->log( 'info', 'Fetched all products', [
			'total_products' => count( $all_products ),
			'pages'          => $page,
		] );

		return $all_products;
	}

	/**
	 * Fetch a single page of products.
	 *
	 * @param int $page Page number (1-based).
	 * @return array|false Products data or false on failure.
	 */
	public function get_page( int $page ): array|false {
		$url = $this->build_products_url( $page );
		return $this->fetch_page( $url );
	}

	/**
	 * Build products URL with pagination parameters.
	 *
	 * @param int $page Page number.
	 * @return string Full URL.
	 */
	private function build_products_url( int $page ): string {
		return $this->base_url . self::PRODUCTS_ENDPOINT . '?page=' . $page . '&limit=' . self::PAGE_LIMIT;
	}

	/**
	 * Fetch a single page from the API.
	 *
	 * Handles 304 Not Modified by retrying with no-cache headers.
	 *
	 * @param string $url API URL.
	 * @return array|false Decoded JSON data or false on failure.
	 */
	private function fetch_page( string $url ): array|false {
		$headers = $this->prepare_headers();

		$args = array_merge( $this->http_args, [
			'headers' => $headers,
		] );

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( 'error', 'HTTP request failed', [
				'url'   => $url,
				'error' => $response->get_error_message(),
			] );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// Handle 304 Not Modified - retry with no-cache headers
		if ( $code === 304 ) {
			$this->log( 'debug', 'Received 304, retrying with no-cache', [ 'url' => $url ] );

			$nocache_headers = $headers;
			$nocache_headers['cache-control'] = 'no-cache, no-store, must-revalidate';
			$nocache_headers['pragma']        = 'no-cache';
			$nocache_headers['expires']       = '0';
			unset( $nocache_headers['if-none-match'] );

			$args['headers'] = $nocache_headers;
			$response        = wp_remote_get( $url, $args );

			if ( is_wp_error( $response ) ) {
				$this->log( 'error', 'Retry after 304 failed', [
					'url'   => $url,
					'error' => $response->get_error_message(),
				] );
				return false;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
		}

		if ( $code !== 200 ) {
			$this->log( 'error', 'Unexpected HTTP status', [
				'url'  => $url,
				'code' => $code,
				'body' => substr( $body, 0, 500 ),
			] );
			return false;
		}

		if ( empty( $body ) ) {
			$this->log( 'error', 'Empty response body', [ 'url' => $url ] );
			return false;
		}

		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->log( 'error', 'JSON decode error', [
				'url'     => $url,
				'error'   => json_last_error_msg(),
				'preview' => substr( $body, 0, 500 ),
			] );
			return false;
		}

		return $data;
	}

	/**
	 * Prepare request headers with authentication.
	 *
	 * @return array Headers array.
	 */
	private function prepare_headers(): array {
		$default_headers = [
			'Cookie'       => $this->cookies,
			'cache-control' => 'no-cache',
			'pragma'       => 'no-cache',
		];

		// Merge additional headers, but don't override Cookie
		$headers = array_merge( $default_headers, $this->headers );
		$headers['Cookie'] = $this->cookies;

		// Remove if-none-match to avoid 304 responses
		unset( $headers['if-none-match'] );

		return $headers;
	}

	/**
	 * Log a message if logger is configured.
	 *
	 * @param string $level   Log level (debug, info, warning, error).
	 * @param string $message Log message.
	 * @param array  $context Additional context.
	 */
	private function log( string $level, string $message, array $context = [] ): void {
		if ( $this->logger ) {
			( $this->logger )( $level, $message, $context );
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG && $level === 'error' ) {
			error_log( '[F-Bearpaw-Dealer] ' . strtoupper( $level ) . ': ' . $message . ' ' . json_encode( $context ) );
		}
	}

	/**
	 * Test API connectivity.
	 *
	 * @return array Status information.
	 */
	public function test_connection(): array {
		$url = $this->base_url . self::PRODUCTS_ENDPOINT . '?page=1&limit=1';

		$headers = $this->prepare_headers();
		$args    = array_merge( $this->http_args, [ 'headers' => $headers ] );

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return [
				'accessible'      => false,
				'error'           => $response->get_error_message(),
				'products_count'  => 0,
			];
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code !== 200 ) {
			return [
				'accessible'      => false,
				'error'           => 'HTTP ' . $code,
				'products_count'  => 0,
			];
		}

		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['products'] ) ) {
			return [
				'accessible'      => false,
				'error'           => 'Invalid response format',
				'products_count'  => 0,
			];
		}

		return [
			'accessible'      => true,
			'error'           => '',
			'products_count'  => count( $data['products'] ),
		];
	}
}