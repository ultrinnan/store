<?php
/**
 * Plugin Name: F-PRO Bearpaw Dealer
 * Plugin URI: https://fedirko.pro/plugins/f-pro-bearpaw-dealer/
 * Description: Tools for managing Bearpaw dealer products in WooCommerce. Tracks and manages products imported from Bearpaw B2B portal.
 * Version: 1.0.0
 * Author: Serhii Fedirko
 * Author URI: https://fedirko.pro
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: f-pro-bearpaw-dealer
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 9.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if WooCommerce is active
 */
if ( ! function_exists( 'f_bearpaw_is_woocommerce_active' ) ) {
	function f_bearpaw_is_woocommerce_active() {
		// Check if WooCommerce class exists
		if ( class_exists( 'WooCommerce' ) ) {
			return true;
		}
		
		// Fallback: check if WooCommerce plugin is in active plugins list
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
		if ( in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) ) {
			return true;
		}
		
		return false;
	}
}

/**
 * Main plugin class
 */
class F_Bearpaw_Dealer {
	
	/**
	 * Meta key for identifying Bearpaw products
	 * This is the dealer_product_id from Bearpaw B2B portal (Shopify product ID)
	 */
	const BEARPAW_META_KEY = 'dealer_product_id';
	
	/**
	 * Settings option name
	 */
	private $option_name = 'f_bearpaw_dealer_settings';
	
	/**
	 * Temporary directory for storing JSON files
	 */
	private $temp_dir;
	
	/**
	 * Initialize the plugin
	 */
	public function __construct() {
		// Declare WooCommerce features compatibility (must be in before_woocommerce_init hook)
		add_action( 'before_woocommerce_init', array( $this, 'declare_woocommerce_features_compatibility' ) );
		
		// Initialize after plugins are loaded to ensure WooCommerce is available
		add_action( 'plugins_loaded', array( $this, 'init' ), 20 );
	}
	
	/**
	 * Declare compatibility with WooCommerce features
	 */
	public function declare_woocommerce_features_compatibility() {
		// Check if FeaturesUtil class is available (WooCommerce 8.2+)
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			$plugin_file = plugin_basename( __FILE__ );
			
			// Declare compatibility with custom order tables (HPOS)
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $plugin_file, true );
			
			// Declare compatibility with cart and checkout blocks
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', $plugin_file, true );
		}
	}
	
	/**
	 * Initialize plugin functionality
	 */
	public function init() {
		// Check if WooCommerce is active
		if ( ! f_bearpaw_is_woocommerce_active() ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}
		
		// Setup temporary directory
		$upload_dir = wp_upload_dir();
		$this->temp_dir = $upload_dir['basedir'] . '/f-bearpaw-dealer-temp';
		
		// Create temp directory if it doesn't exist
		if ( ! file_exists( $this->temp_dir ) ) {
			wp_mkdir_p( $this->temp_dir );
		}
		
		add_action( 'admin_menu', array( $this, 'add_admin_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		
		// Add script to pre-fill product form when opened from missing products list
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_product_form_prefill_script' ) );
		
		// Register AJAX handlers for chunked download
		add_action( 'wp_ajax_f_bearpaw_download_chunk', array( $this, 'ajax_download_chunk' ) );
		add_action( 'wp_ajax_f_bearpaw_finalize_download', array( $this, 'ajax_finalize_download' ) );
		add_action( 'wp_ajax_f_bearpaw_clear_progress', array( $this, 'ajax_clear_progress' ) );
	}
	
	/**
	 * Show notice if WooCommerce is not active
	 */
	public function woocommerce_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><strong>F-PRO Bearpaw Dealer:</strong> WooCommerce plugin is required for this plugin to work. Please install and activate WooCommerce.</p>
		</div>
		<?php
	}
	
	/**
	 * Add admin page to WordPress admin menu
	 */
	public function add_admin_page() {
		add_menu_page(
			'Bearpaw Dealer',
			'Bearpaw Dealer',
			'manage_options',
			'f-bearpaw-dealer',
			array( $this, 'render_admin_page' ),
			'dashicons-products',
			56
		);
	}
	
	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting(
			'f_bearpaw_dealer_settings_group',
			$this->option_name,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
		
		// Add settings sections and fields
		add_settings_section(
			'f_bearpaw_dealer_auth_section',
			'cURL Commands',
			array( $this, 'render_auth_section' ),
			'f-bearpaw-dealer'
		);
		
		add_settings_field(
			'b2b_curl',
			'B2B Portal cURL Command',
			array( $this, 'render_b2b_curl_field' ),
			'f-bearpaw-dealer',
			'f_bearpaw_dealer_auth_section'
		);
		
		add_settings_field(
			'retail_curl',
			'Retail Site cURL Command',
			array( $this, 'render_retail_curl_field' ),
			'f-bearpaw-dealer',
			'f_bearpaw_dealer_auth_section'
		);
		
		// Add pricing settings section
		add_settings_section(
			'f_bearpaw_dealer_pricing_section',
			'Pricing Settings',
			array( $this, 'render_pricing_section' ),
			'f-bearpaw-dealer'
		);
		
		add_settings_field(
			'margin_percent',
			'Dealer Margin (%)',
			array( $this, 'render_margin_percent_field' ),
			'f-bearpaw-dealer',
			'f_bearpaw_dealer_pricing_section'
		);
		
		add_settings_field(
			'discount_percent',
			'Retail Discount (%)',
			array( $this, 'render_discount_percent_field' ),
			'f-bearpaw-dealer',
			'f_bearpaw_dealer_pricing_section'
		);
		
		add_settings_field(
			'price_change_threshold',
			'Price Change Threshold (EUR)',
			array( $this, 'render_price_change_threshold_field' ),
			'f-bearpaw-dealer',
			'f_bearpaw_dealer_pricing_section'
		);
	}
	
	/**
	 * Render authentication section
	 */
	public function render_auth_section() {
		echo '<p>Paste cURL commands from browser DevTools (Network tab → Right click on products.json request → Copy as cURL).</p>';
	}
	
	/**
	 * Render pricing section
	 */
	public function render_pricing_section() {
		echo '<p>Configure pricing rules for automatic price updates.</p>';
	}
	
	/**
	 * Enqueue script to pre-fill product form when opened from missing products list
	 * 
	 * Automatically fills WooCommerce product creation form with data from URL parameters:
	 * - SKU (product SKU)
	 * - Title (product title)
	 * - Regular Price (calculated recommended price)
	 * 
	 * Also opens B2B product page in a new tab for reference.
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_product_form_prefill_script( $hook ) {
		// Only on product edit/new page
		if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
			return;
		}
		
		// Only for product post type
		global $post_type;
		if ( $post_type !== 'product' ) {
			return;
		}
		
		// Check if we have URL parameters
		if ( ! isset( $_GET['sku'] ) && ! isset( $_GET['post_title'] ) ) {
			return;
		}
		
		// Add inline script to pre-fill form
		$script = "
		jQuery(document).ready(function($) {
			// Wait for WooCommerce product form to be ready
			setTimeout(function() {
				var urlParams = new URLSearchParams(window.location.search);
				var hasParams = false;
				
				// Fill SKU if provided
				if ($('#_sku').length > 0) {
					var sku = urlParams.get('sku');
					if (sku) {
						$('#_sku').val(decodeURIComponent(sku)).trigger('change');
						hasParams = true;
					}
				}
				
				// Fill title if provided (only on new post)
				if ($('#title').length > 0 && $('#title').val() === '') {
					var title = urlParams.get('post_title');
					if (title) {
						$('#title').val(decodeURIComponent(title)).trigger('input');
						hasParams = true;
					}
				}
				
				// Fill regular price if provided
				var regularPrice = urlParams.get('regular_price');
				if (regularPrice && $('#_regular_price').length > 0) {
					$('#_regular_price').val(decodeURIComponent(regularPrice)).trigger('change');
					hasParams = true;
				}
				
				// Show notification
				if (hasParams) {
					$('<div class=\"notice notice-info is-dismissible\" style=\"margin: 10px 0;\"><p><strong>ℹ️ Product form pre-filled</strong> with data from missing products list (SKU, Title, Price). Please review and complete the remaining fields before saving.</p></div>').insertAfter('.wrap h1');
				}
			}, 500);
		});
		";
		
		wp_add_inline_script( 'jquery', $script );
	}
	
	/**
	 * Render margin percent field
	 */
	public function render_margin_percent_field() {
		$settings = get_option( $this->option_name, array() );
		$value = isset( $settings['margin_percent'] ) ? floatval( $settings['margin_percent'] ) : 20.0;
		?>
		<input type="number" name="<?php echo esc_attr( $this->option_name ); ?>[margin_percent]" value="<?php echo esc_attr( $value ); ?>" step="0.1" min="0" max="100" style="width: 100px;" />
		<p class="description">Margin above dealer (B2B) price. Default: 20% (your price will be 20% higher than B2B price)</p>
		<?php
	}
	
	/**
	 * Render discount percent field
	 */
	public function render_discount_percent_field() {
		$settings = get_option( $this->option_name, array() );
		$value = isset( $settings['discount_percent'] ) ? floatval( $settings['discount_percent'] ) : 10.0;
		?>
		<input type="number" name="<?php echo esc_attr( $this->option_name ); ?>[discount_percent]" value="<?php echo esc_attr( $value ); ?>" step="0.1" min="0" max="100" style="width: 100px;" />
		<p class="description">Discount below retail price. Default: 10% (your price will be at least 10% lower than retail price)</p>
		<?php
	}
	
	/**
	 * Render price change threshold field
	 */
	public function render_price_change_threshold_field() {
		$settings = get_option( $this->option_name, array() );
		$value = isset( $settings['price_change_threshold'] ) ? floatval( $settings['price_change_threshold'] ) : 0.2;
		?>
		<input type="number" name="<?php echo esc_attr( $this->option_name ); ?>[price_change_threshold]" value="<?php echo esc_attr( $value ); ?>" step="0.01" min="0" max="10" style="width: 100px;" />
		<p class="description">Minimum price difference (in EUR) to trigger update. Default: 0.2 EUR (prices will only update if difference is more than 0.2 EUR)</p>
		<?php
	}
	
	/**
	 * Render B2B cURL field
	 */
	public function render_b2b_curl_field() {
		$settings = get_option( $this->option_name, array() );
		$value = isset( $settings['b2b_curl'] ) ? $settings['b2b_curl'] : '';
		?>
		<textarea name="<?php echo esc_attr( $this->option_name ); ?>[b2b_curl]" rows="8" cols="80" class="large-text code" style="font-family: monospace; font-size: 12px;"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">cURL command for B2B Portal (https://www.b2bportal.bearpaw-products.de/products.json)</p>
		<?php
	}
	
	/**
	 * Render retail cURL field
	 */
	public function render_retail_curl_field() {
		$settings = get_option( $this->option_name, array() );
		$value = isset( $settings['retail_curl'] ) ? $settings['retail_curl'] : '';
		?>
		<textarea name="<?php echo esc_attr( $this->option_name ); ?>[retail_curl]" rows="8" cols="80" class="large-text code" style="font-family: monospace; font-size: 12px;"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description">cURL command for Retail Site (https://bearpaw-products.com/products.json)</p>
		<?php
	}
	
	/**
	 * Sanitize settings input
	 *
	 * @param array $input Raw settings input
	 * @return array Sanitized settings
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();
		
		if ( isset( $input['b2b_curl'] ) ) {
			$sanitized['b2b_curl'] = sanitize_textarea_field( $input['b2b_curl'] );
		}
		
		if ( isset( $input['retail_curl'] ) ) {
			$sanitized['retail_curl'] = sanitize_textarea_field( $input['retail_curl'] );
		}
		
		if ( isset( $input['margin_percent'] ) ) {
			$sanitized['margin_percent'] = floatval( $input['margin_percent'] );
		}
		
		if ( isset( $input['discount_percent'] ) ) {
			$sanitized['discount_percent'] = floatval( $input['discount_percent'] );
		}
		
		if ( isset( $input['price_change_threshold'] ) ) {
			$sanitized['price_change_threshold'] = floatval( $input['price_change_threshold'] );
		}
		
		return $sanitized;
	}
	
	/**
	 * Parse cURL command and extract URL, cookies, and headers
	 *
	 * @param string $curl_command cURL command string
	 * @return array|false Parsed data (url, cookies, headers) or false on failure
	 */
	private function parse_curl_command( $curl_command ) {
		if ( empty( $curl_command ) ) {
			return false;
		}
		
		$result = array(
			'url' => '',
			'cookies' => '',
			'headers' => array(),
		);
		
		// Extract URL (between single quotes after curl)
		if ( preg_match( "/curl\s+['\"]([^'\"]+)['\"]/", $curl_command, $matches ) ) {
			$result['url'] = $matches[1];
		} elseif ( preg_match( "/curl\s+([^\s]+)/", $curl_command, $matches ) ) {
			$result['url'] = $matches[1];
		}
		
		// Extract cookies from -b or --cookie parameter
		if ( preg_match( "/-b\s+['\"]([^'\"]+)['\"]/", $curl_command, $matches ) || 
		     preg_match( "/--cookie\s+['\"]([^'\"]+)['\"]/", $curl_command, $matches ) ) {
			$result['cookies'] = $matches[1];
		}
		
		// Extract all headers from -H parameters
		preg_match_all( "/-H\s+['\"]([^'\"]+)['\"]/", $curl_command, $header_matches );
		if ( ! empty( $header_matches[1] ) ) {
			foreach ( $header_matches[1] as $header_line ) {
				$parts = explode( ':', $header_line, 2 );
				if ( count( $parts ) === 2 ) {
					$header_name = trim( $parts[0] );
					$header_value = trim( $parts[1] );
					// Skip Cookie header as we extract it separately
					if ( strtolower( $header_name ) !== 'cookie' ) {
						$result['headers'][ $header_name ] = $header_value;
					}
				}
			}
		}
		
		// If no cookies found in -b, try to extract from Cookie header
		if ( empty( $result['cookies'] ) ) {
			foreach ( $result['headers'] as $name => $value ) {
				if ( strtolower( $name ) === 'cookie' ) {
					$result['cookies'] = $value;
					unset( $result['headers'][ $name ] );
					break;
				}
			}
		}
		
		return ! empty( $result['url'] ) ? $result : false;
	}
	
	/**
	 * Clear temporary directory
	 * 
	 * Removes all JSON files from the temporary directory.
	 * Called before each new download to ensure fresh data.
	 */
	private function clear_temp_dir() {
		if ( ! file_exists( $this->temp_dir ) ) {
			return;
		}
		
		$files = glob( $this->temp_dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
	}
	
	/**
	 * Save JSON data to temporary file
	 * 
	 * Saves JSON data to wp-content/uploads/f-bearpaw-dealer-temp/ directory.
	 * Creates directory if it doesn't exist.
	 *
	 * @param string $filename Filename (e.g., 'b2b_products.json')
	 * @param array  $data Data to save as JSON
	 * @return string|false File path on success, false on failure
	 */
	private function save_json_to_temp( $filename, $data ) {
		if ( ! file_exists( $this->temp_dir ) ) {
			wp_mkdir_p( $this->temp_dir );
		}
		
		$file_path = $this->temp_dir . '/' . sanitize_file_name( $filename );
		$json_content = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		
		if ( file_put_contents( $file_path, $json_content ) !== false ) {
			return $file_path;
		}
		
		return false;
	}
	
	/**
	 * Load JSON data from temporary file
	 * 
	 * Loads previously saved JSON data from temporary directory.
	 * Used to avoid repeated API calls during processing.
	 *
	 * @param string $filename Filename to load (e.g., 'b2b_products.json')
	 * @return array|false Decoded JSON data or false if file doesn't exist
	 */
	private function load_json_from_temp( $filename ) {
		$file_path = $this->temp_dir . '/' . sanitize_file_name( $filename );
		
		if ( ! file_exists( $file_path ) ) {
			return false;
		}
		
		$content = file_get_contents( $file_path );
		if ( $content === false ) {
			return false;
		}
		
		$data = json_decode( $content, true );
		return json_last_error() === JSON_ERROR_NONE ? $data : false;
	}
	
	/**
	 * Download all products from source
	 * 
	 * Parses cURL command, fetches all products with pagination support,
	 * and saves them to a temporary JSON file for later processing.
	 * Clears temporary directory before starting new download.
	 *
	 * @param string $curl_command Full cURL command from browser DevTools
	 * @param string $source_name Source name ('B2B' or 'Retail') for file naming
	 * @return array Result array with success status, product count, and file path
	 */
	public function download_all_products( $curl_command, $source_name = 'Unknown' ) {
		$parsed = $this->parse_curl_command( $curl_command );
		
		if ( ! $parsed || empty( $parsed['url'] ) ) {
			return array(
				'success' => false,
				'error' => 'Failed to parse cURL command or extract URL',
			);
		}
		
		// Extract base URL (without query parameters for pagination)
		$base_url = strtok( $parsed['url'], '?' );
		
		// Get all products with pagination
		$all_products = $this->get_all_products_paginated( $base_url, $parsed['cookies'], $parsed['headers'] );
		
		if ( empty( $all_products ) ) {
			return array(
				'success' => false,
				'error' => 'No products retrieved',
			);
		}
		
		// Save to temporary file
		$filename = $source_name === 'B2B' ? 'b2b_products.json' : 'retail_products.json';
		$file_path = $this->save_json_to_temp( $filename, array( 'products' => $all_products ) );
		
		if ( ! $file_path ) {
			return array(
				'success' => false,
				'error' => 'Failed to save JSON file',
			);
		}
		
		return array(
			'success' => true,
			'products_count' => count( $all_products ),
			'file_path' => $file_path,
		);
	}
	
	/**
	 * Get total count of Bearpaw products
	 * 
	 * Counts all products that have the Bearpaw meta field (dealer_product_id).
	 * Includes all post statuses (published, draft, private, etc.).
	 *
	 * @return int Total count of Bearpaw products
	 */
	public function get_bearpaw_products_count() {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => self::BEARPAW_META_KEY,
					'compare' => 'EXISTS',
				),
			),
			'fields'         => 'ids',
		);
		
		$query = new WP_Query( $args );
		return $query->found_posts;
	}
	
	/**
	 * Get count of published Bearpaw products
	 * 
	 * Counts only products with 'publish' status that have the Bearpaw meta field.
	 *
	 * @return int Count of published Bearpaw products
	 */
	public function get_bearpaw_products_count_published() {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => self::BEARPAW_META_KEY,
					'compare' => 'EXISTS',
				),
			),
			'fields'         => 'ids',
		);
		
		$query = new WP_Query( $args );
		return $query->found_posts;
	}
	
	/**
	 * Get all products from Bearpaw API with pagination
	 *
	 * @param string $base_url Base API URL
	 * @param string $cookies Cookies string
	 * @param array  $headers Additional headers
	 * @return array All products from all pages
	 */
	private function get_all_products_paginated( $base_url, $cookies, $headers = array() ) {
		$all_products = array();
		$page = 1;
		$has_more = true;
		$max_pages = 50; // Safety limit to prevent infinite loops
		
		while ( $has_more && $page <= $max_pages ) {
			// Add page parameter to URL (Shopify uses page parameter)
			$url = $base_url . ( strpos( $base_url, '?' ) !== false ? '&' : '?' ) . 'page=' . $page . '&limit=250';
			
			$data = $this->get_bearpaw_json( $url, $cookies, $headers );
			
			if ( $data === false || ! isset( $data['products'] ) ) {
				$has_more = false;
				break;
			}
			
			$products = $data['products'];
			
			if ( empty( $products ) ) {
				$has_more = false;
				break;
			}
			
			$all_products = array_merge( $all_products, $products );
			
			// If we got less than 250 products, it's probably the last page
			if ( count( $products ) < 250 ) {
				$has_more = false;
			} else {
				$page++;
			}
			
			// Small delay to avoid overwhelming the server
			if ( $has_more ) {
				usleep( 200000 ); // 0.2 seconds
			}
		}
		
		return $all_products;
	}
	
	/**
	 * Download a chunk of products (5 pages at a time)
	 * 
	 * Downloads products starting from a specific page, processes up to 5 pages per chunk.
	 * Stores progress in transient to allow resuming.
	 *
	 * @param string $source_name Source name ('B2B' or 'Retail')
	 * @param int    $start_page Starting page number (1-based)
	 * @param string $base_url Base URL without pagination parameters
	 * @param string $cookies Cookies string for authentication
	 * @param array  $headers Additional headers from cURL command
	 * @return array Result with success status, downloaded pages, products count, and whether more pages exist
	 */
	private function download_products_chunk( $source_name, $start_page, $base_url, $cookies, $headers = array() ) {
		$chunk_size = 5; // Download 5 pages per chunk
		$all_products = array();
		$pages_downloaded = 0;
		$has_more = false;
		$last_page_products_count = 0;
		
		for ( $i = 0; $i < $chunk_size; $i++ ) {
			$page = $start_page + $i;
			$url = $base_url . ( strpos( $base_url, '?' ) !== false ? '&' : '?' ) . 'page=' . $page . '&limit=250';
			
			$data = $this->get_bearpaw_json( $url, $cookies, $headers );
			
			if ( $data === false || ! isset( $data['products'] ) ) {
				break; // Stop if error or no products key
			}
			
			$products = $data['products'];
			
			if ( empty( $products ) ) {
				break; // No more products
			}
			
			$all_products = array_merge( $all_products, $products );
			$pages_downloaded++;
			$last_page_products_count = count( $products );
			
			// If we got less than 250 products, it's probably the last page
			if ( $last_page_products_count < 250 ) {
				break;
			}
			
			// Small delay between pages
			if ( $i < $chunk_size - 1 ) {
				usleep( 200000 ); // 0.2 seconds
			}
		}
		
		// Check if there might be more pages (if we got exactly 250 products on last page)
		if ( $last_page_products_count === 250 && $pages_downloaded === $chunk_size ) {
			$has_more = true;
		}
		
		// Store products in a temporary file instead of transient (to avoid max_allowed_packet issues)
		$temp_filename = strtolower( $source_name ) . '_products_temp.json';
		$temp_file_path = $this->temp_dir . '/' . $temp_filename;
		
		// Load existing products from temp file if exists
		$existing_products = array();
		if ( file_exists( $temp_file_path ) ) {
			$existing_data = json_decode( file_get_contents( $temp_file_path ), true );
			if ( $existing_data && isset( $existing_data['products'] ) ) {
				$existing_products = $existing_data['products'];
			}
		}
		
		// Merge new products with existing
		$all_products_merged = array_merge( $existing_products, $all_products );
		
		// Save merged products to temp file
		$result = $this->save_json_to_temp( $temp_filename, array( 'products' => $all_products_merged ) );
		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => 'Failed to save products to temporary file',
			);
		}
		
		// Store only metadata in transient (small data - no max_allowed_packet issue)
		$transient_key = 'f_bearpaw_download_progress_' . strtolower( $source_name );
		$progress = get_transient( $transient_key );
		
		if ( $progress === false ) {
			// First chunk - initialize progress
			$progress = array(
				'current_page' => $start_page + $pages_downloaded,
				'total_pages_downloaded' => $pages_downloaded,
				'total_products' => count( $all_products_merged ),
			);
		} else {
			// Update progress
			$progress['current_page'] = $start_page + $pages_downloaded;
			$progress['total_pages_downloaded'] += $pages_downloaded;
			$progress['total_products'] = count( $all_products_merged );
		}
		
		// Save progress metadata (expires in 1 hour)
		set_transient( $transient_key, $progress, 3600 );
		
		return array(
			'success' => true,
			'pages_downloaded' => $pages_downloaded,
			'products_in_chunk' => count( $all_products ),
			'total_products' => count( $all_products_merged ),
			'current_page' => $progress['current_page'],
			'total_pages_downloaded' => $progress['total_pages_downloaded'],
			'has_more' => $has_more,
		);
	}
	
	/**
	 * AJAX handler for downloading a chunk of products
	 */
	public function ajax_download_chunk() {
		// Set headers to return JSON
		header( 'Content-Type: application/json' );
		
		// Verify nonce (die on failure but handle gracefully)
		if ( ! check_ajax_referer( 'f_bearpaw_download_products', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed. Please refresh the page and try again.' ) );
			wp_die();
		}
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
			wp_die();
		}
		
		$source_name = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : '';
		$start_page = isset( $_POST['start_page'] ) ? intval( $_POST['start_page'] ) : 1;
		
		if ( empty( $source_name ) || ! in_array( $source_name, array( 'B2B', 'Retail' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid source name' ) );
			wp_die();
		}
		
		$settings = get_option( $this->option_name, array() );
		$curl_field = $source_name === 'B2B' ? 'b2b_curl' : 'retail_curl';
		$curl_command = isset( $settings[ $curl_field ] ) ? trim( $settings[ $curl_field ] ) : '';
		
		if ( empty( $curl_command ) ) {
			wp_send_json_error( array( 'message' => ucfirst( $source_name ) . ' cURL command not configured' ) );
			wp_die();
		}
		
		$parsed = $this->parse_curl_command( $curl_command );
		
		if ( ! $parsed || empty( $parsed['url'] ) ) {
			wp_send_json_error( array( 'message' => 'Failed to parse cURL command' ) );
			wp_die();
		}
		
		// Extract base URL (without query parameters for pagination)
		$base_url = strtok( $parsed['url'], '?' );
		
		// Increase execution time for this request
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // 2 minutes per chunk
		}
		
		// Turn off output buffering to prevent HTML output
		if ( ob_get_level() ) {
			ob_clean();
		}
		
		try {
			$result = $this->download_products_chunk( $source_name, $start_page, $base_url, $parsed['cookies'], $parsed['headers'] );
			wp_send_json_success( $result );
		} catch ( Exception $e ) {
			// Log error for debugging
			error_log( 'F-Bearpaw-Dealer AJAX Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() );
			wp_send_json_error( array( 'message' => 'Exception: ' . $e->getMessage() . ' (Check error log for details)' ) );
		} catch ( Error $e ) {
			// Catch PHP 7+ fatal errors
			error_log( 'F-Bearpaw-Dealer AJAX Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine() );
			wp_send_json_error( array( 'message' => 'Fatal error: ' . $e->getMessage() . ' (Check error log for details)' ) );
		}
		
		wp_die(); // Ensure script stops here
	}
	
	/**
	 * AJAX handler for finalizing download
	 */
	public function ajax_finalize_download() {
		// Set headers to return JSON
		header( 'Content-Type: application/json' );
		
		// Verify nonce (die on failure but handle gracefully)
		if ( ! check_ajax_referer( 'f_bearpaw_download_products', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed. Please refresh the page and try again.' ) );
			wp_die();
		}
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
			wp_die();
		}
		
		$source_name = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : '';
		
		if ( empty( $source_name ) || ! in_array( $source_name, array( 'B2B', 'Retail' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid source name' ) );
			wp_die();
		}
		
		// Turn off output buffering to prevent HTML output
		if ( ob_get_level() ) {
			ob_clean();
		}
		
		try {
			$result = $this->finalize_download( $source_name );
			
			if ( $result['success'] ) {
				wp_send_json_success( $result );
			} else {
				wp_send_json_error( $result );
			}
		} catch ( Exception $e ) {
			error_log( 'F-Bearpaw-Dealer Finalize Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => 'Exception: ' . $e->getMessage() ) );
		} catch ( Error $e ) {
			error_log( 'F-Bearpaw-Dealer Finalize Fatal Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => 'Fatal error: ' . $e->getMessage() ) );
		}
		
		wp_die(); // Ensure script stops here
	}
	
	/**
	 * AJAX handler for clearing download progress
	 */
	public function ajax_clear_progress() {
		// Set headers to return JSON
		header( 'Content-Type: application/json' );
		
		// Verify nonce (die on failure but handle gracefully)
		if ( ! check_ajax_referer( 'f_bearpaw_download_products', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'Security check failed. Please refresh the page and try again.' ) );
			wp_die();
		}
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
			wp_die();
		}
		
		// Turn off output buffering to prevent HTML output
		if ( ob_get_level() ) {
			ob_clean();
		}
		
		$source_name = isset( $_POST['source'] ) ? sanitize_text_field( $_POST['source'] ) : '';
		
		if ( ! empty( $source_name ) && in_array( $source_name, array( 'B2B', 'Retail' ), true ) ) {
			$this->clear_download_progress( $source_name );
			wp_send_json_success( array( 'message' => 'Progress cleared' ) );
		} else {
			// Clear both
			$this->clear_download_progress( 'B2B' );
			$this->clear_download_progress( 'Retail' );
			wp_send_json_success( array( 'message' => 'All progress cleared' ) );
		}
		
		wp_die(); // Ensure script stops here
	}
	
	/**
	 * Finalize download by saving all accumulated products to file
	 * 
	 * Loads all products from transient and saves them to JSON file.
	 * Clears transient after successful save.
	 *
	 * @param string $source_name Source name ('B2B' or 'Retail')
	 * @return array Result with success status and file path
	 */
	private function finalize_download( $source_name ) {
		$temp_filename = strtolower( $source_name ) . '_products_temp.json';
		$temp_file_path = $this->temp_dir . '/' . $temp_filename;
		
		// Check if temp file exists
		if ( ! file_exists( $temp_file_path ) ) {
			return array(
				'success' => false,
				'error' => 'No products to save. Download may not have started or temp file was deleted.',
			);
		}
		
		// Load products from temp file
		$temp_data = json_decode( file_get_contents( $temp_file_path ), true );
		
		if ( ! $temp_data || empty( $temp_data['products'] ) ) {
			return array(
				'success' => false,
				'error' => 'Temp file exists but contains no products.',
			);
		}
		
		$products = $temp_data['products'];
		$filename = $source_name === 'B2B' ? 'b2b_products.json' : 'retail_products.json';
		$final_file_path = $this->temp_dir . '/' . $filename;
		
		// Save to final file
		$result = $this->save_json_to_temp( $filename, array( 'products' => $products ) );
		
		if ( ! $result ) {
			return array(
				'success' => false,
				'error' => 'Failed to save JSON file',
			);
		}
		
		// Delete temp file
		if ( file_exists( $temp_file_path ) ) {
			@unlink( $temp_file_path );
		}
		
		// Clear progress transient
		$transient_key = 'f_bearpaw_download_progress_' . strtolower( $source_name );
		$progress = get_transient( $transient_key );
		delete_transient( $transient_key );
		
		return array(
			'success' => true,
			'products_count' => count( $products ),
			'file_path' => $final_file_path,
			'total_pages_downloaded' => $progress['total_pages_downloaded'] ?? 0,
		);
	}
	
	/**
	 * Clear download progress for a source
	 *
	 * @param string $source_name Source name ('B2B' or 'Retail')
	 */
	private function clear_download_progress( $source_name ) {
		$transient_key = 'f_bearpaw_download_progress_' . strtolower( $source_name );
		delete_transient( $transient_key );
		
		// Also delete temp file if exists
		$temp_filename = strtolower( $source_name ) . '_products_temp.json';
		$temp_file_path = $this->temp_dir . '/' . $temp_filename;
		if ( file_exists( $temp_file_path ) ) {
			@unlink( $temp_file_path );
		}
	}
	
	/**
	 * Test function to get products with hardcoded cookies (for testing)
	 *
	 * @return array|false Decoded JSON data or false on failure
	 */
	public function test_get_products_hardcoded() {
		$url = 'https://www.b2bportal.bearpaw-products.de/products.json';
		
		// Hardcoded cookies from curl command
		$cookies = 'cart_currency=EUR; _shopify_y=3b111f6c-85c5-421e-8cc4-0f97b9c2a571; _shopify_analytics=:AZnAfy32AAEAOBfINrI0B3bqdZ5f9__v41DBHmyk0FzIE0m3AsNTabpHXxq-ZIEyih_FCHGiZUYvxJpgMzmerIt3vssaHzelch-8:; shopify_client_id=3b111f6c-85c5-421e-8cc4-0f97b9c2a571; __kla_id=eyJjaWQiOiJaR1JpWm1Rd05qVXRPVFZrT1MwMFlqWmlMVGxqWVRFdE9UazBaakpoWW1Wa1pHSTIifQ==; _shopify_s=187585d1-9c69-48d3-8924-fdb3091647a3; localization=DE; _shopify_essential=:AZnAfyv4AAH_jDLlCur-Bjx9yb42JbZryQKbGwf8TT2aI1YD1OFbqEJDZ2UpxDKrmmDxdjLc8T_vBAlXXKOhiZNFBnWRffC9hIhp_lYep7R-pOG5_7OSruO-IeDFVRr-GWS6RNfcl8uTSohgMICP2O479NKXZIEVtfFM6M8Nk3iqiurkkDO60ksqH0Qv9v8B14xprXnlCNWM7vh9AAaUgAzCUVe59A3TQKONZhQ-0STzq-fK_LZ41zvGaTKoHrnHDin2kFE32KgrG5z_fqkdAU4pcB8kbPdNf6J_4uFHr0eoMeGlIZlweiIbaExVLkQtwhY0Qih163jS6-13sH105zPYgtmnirJnOZx5E97i1BarYRmWV0uu6ye8KBc_3lwGlSe-MZTYCsMkTv18WdcqQbLXTixMM8GjT-9ZwgWZi0KFbta6HDLoUcZTkP7vkYVnHmwXg2Q5KnYLzY8DapUNvR_5lt4TgAUHZkfS4JI8KBTft8NiHPjLjH0b7CYAppcTma67qe699e9GegBnog4-fhsXQna93-II-JTE97jZVj7e3QZh-W0Lg6J5TOHmzEYDrFPGEg7il90qQRBjRy7YrRlv8wfQzchGadNOccWvI88m0xKYsGE7fqI_0CmCa-ETTh4WHxaxFuLXWl2n-D0QniGFVREcDO5AOY2QnTNJuIMLKmke45IbVTb__aC89SDFBC9Pz8UB1Vb1wTGxaua96iOVt0-_1dcDy5r4Ts_l5yumiOKmHFLbLBs7BTqrrM4HqZM3LF2fr4-nI9o9k6XNQIMZCwTPJduBoEuphp2MYMdqBg5VuuD_DMe4LZ61GPh77fw8BakmXBJjnxcG4qwMUcSHQloQdfrzVeznU9PYvUr8db1VXxb0v1-s9eZ1s1S_JHqDNigwnRcSByEb0HHTxH406lv39FKv-TwudlIzzZG803oILEudraeiTR882oEPbp800XZ1X-34AfYVt_oJp270yspdhpCCeZViqHdKODK3fLOXMDnFpcYzK2VXsjqQX9Js13_9j7qXvVVPvdwI7KK5m0gbBpcpDQEo_T5C7XtATBhDvoQjt9oyAQ4r3Rf_QJB98k8jRPcjssDik98v7JOw3GqKHKxzDPlGKtr0bOzbS3hIc2YEkY3H6tIPc54dopKJyC-hh9-tHse4uKFQMF4bJph5f8B41sNE5FHKkG9heMmV30xhUEpm0HoBiNINuGRL-hpbFlr-j16mLmbKUU3T24eQwB0ApJZniACg5CMYmk0SUziHFpCkuLivP-H0Sic9iLeo75vxptplBCDAxmrHdVYu145PcN7T6Jp3q9jedXIYpba9wuzYD1vKz4VevWPIVFRNbX4aMfZFq1NMYX_laqGk7LT8lxYOilB0zabMdn9U-quv7ZlYBMy8dQgbgBS3KlM5F1AbXrwZ1YzDVI82yrtcF8S6qJ_i5yi16A8dkMhC64KRUGQnQSJF33HQ--_j7RatrdjU6uqvR0f48qHyjDDVEXt5qhXKFjsE:; _shopify_marketing=:AZtrxqFpAAEAL5h3ONmh783PkDdl9o-VKBDWe9QKB2efvHfnp-kzq3k445wbfWzXLhAeK-3fakMjNP1mY2-vxPeNgKixGA:; keep_alive=eyJ2IjoyLCJ0cyI6MTc2NzA5MDQ0NzQ3NCwiZW52Ijp7IndkIjowLCJ1YSI6MSwiY3YiOjEsImJyIjoxfSwiYmh2Ijp7Im1hIjoxMCwiY2EiOjEsImthIjowLCJzYSI6MCwia2JhIjowLCJ0YSI6MCwidCI6ODgsIm5tIjoxLCJtcyI6MC4zOCwibWoiOjEuOTQsIm1zcCI6MS4wMiwidmMiOjAsImNwIjowLCJyYyI6MCwia2oiOjAsImtpIjowLCJzcyI6MCwic2oiOjAsInNzbSI6MCwic3AiOjAsInRzIjowLCJ0aiI6MCwidHAiOjAsInRzbSI6MH0sInNlcyI6eyJwIjo5LCJzIjoxNzY3MDM5NzM3Mjk0LCJkIjo1MDQ0N319';
		
		// Headers from curl command (without if-none-match to avoid 304)
		$headers = array(
			'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
			'accept-language' => 'en-GB,en-US;q=0.9,en;q=0.8,uk;q=0.7',
			'cache-control' => 'no-cache', // Changed from max-age=0 to no-cache to force fresh data
			'Cookie' => $cookies,
			// Removed 'if-none-match' header to avoid 304 responses
			'priority' => 'u=0, i',
			'sec-ch-ua' => '"Google Chrome";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
			'sec-ch-ua-mobile' => '?0',
			'sec-ch-ua-platform' => '"macOS"',
			'sec-fetch-dest' => 'document',
			'sec-fetch-mode' => 'navigate',
			'sec-fetch-site' => 'none',
			'sec-fetch-user' => '?1',
			'upgrade-insecure-requests' => '1',
			'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
		);
		
		$args = array(
			'timeout'     => 30,
			'sslverify'   => true,
			'headers'    => $headers,
			'redirection' => 5,
		);
		
		$response = wp_remote_get( $url, $args );
		
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'error' => $response->get_error_message(),
			);
		}
		
		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );
		
		// Handle 304 Not Modified - try again without cache headers
		if ( $code === 304 ) {
			// Remove cache-control and try again
			unset( $headers['cache-control'] );
			$headers['cache-control'] = 'no-cache, no-store, must-revalidate';
			$headers['pragma'] = 'no-cache';
			$headers['expires'] = '0';
			
			$args['headers'] = $headers;
			$response = wp_remote_get( $url, $args );
			
			if ( is_wp_error( $response ) ) {
				return array(
					'success' => false,
					'error' => 'Retry after 304 failed: ' . $response->get_error_message(),
				);
			}
			
			$body = wp_remote_retrieve_body( $response );
			$code = wp_remote_retrieve_response_code( $response );
		}
		
		if ( $code !== 200 ) {
			return array(
				'success' => false,
				'error' => 'HTTP Code: ' . $code,
				'body' => substr( $body, 0, 500 ), // First 500 chars for debugging
			);
		}
		
		if ( empty( $body ) ) {
			return array(
				'success' => false,
				'error' => 'Empty response body',
			);
		}
		
		$data = json_decode( $body, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return array(
				'success' => false,
				'error' => 'JSON decode error: ' . json_last_error_msg(),
				'body_preview' => substr( $body, 0, 500 ),
			);
		}
		
		// Try to get all products with pagination
		$all_products = $this->get_all_products_paginated( 'https://www.b2bportal.bearpaw-products.de/products.json', $cookies );
		
		// Calculate pages (assuming 250 products per page)
		$pages_fetched = ceil( count( $all_products ) / 250 );
		
		return array(
			'success' => true,
			'data' => array( 'products' => $all_products ),
			'products_count' => count( $all_products ),
			'pages_fetched' => $pages_fetched,
		);
	}
	
	/**
	 * Get JSON data from Bearpaw API
	 * 
	 * Fetches JSON data from Bearpaw API endpoints with proper authentication.
	 * Handles HTTP 304 (Not Modified) responses by retrying with no-cache headers.
	 * Removes 'if-none-match' header to force fresh data.
	 *
	 * @param string $url API URL
	 * @param string $cookies Cookies string for authentication
	 * @param array  $additional_headers Additional headers from cURL command
	 * @return array|false Decoded JSON data or false on failure
	 */
	private function get_bearpaw_json( $url, $cookies, $additional_headers = array() ) {
		if ( empty( $cookies ) ) {
			return false;
		}
		
		// Prepare headers - merge additional headers from cURL with defaults
		$default_headers = array(
			'Cookie' => $cookies,
			'cache-control' => 'no-cache', // Force fresh data
			'pragma' => 'no-cache',
		);
		
		// Merge additional headers, but don't override Cookie
		$headers = array_merge( $default_headers, $additional_headers );
		$headers['Cookie'] = $cookies; // Ensure Cookie is set
		
		// Remove if-none-match to avoid 304 responses
		unset( $headers['if-none-match'] );
		
		$args = array(
			'timeout'     => 30,
			'sslverify'   => true,
			'headers'    => $headers,
			'redirection' => 5,
		);
		
		$response = wp_remote_get( $url, $args );
		
		if ( is_wp_error( $response ) ) {
			return false;
		}
		
		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );
		
		if ( $code !== 200 || empty( $body ) ) {
			return false;
		}
		
		$data = json_decode( $body, true );
		
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return false;
		}
		
		return $data;
	}
	
	/**
	 * Check if JSON APIs are accessible
	 * 
	 * Tests connectivity to both B2B and Retail Bearpaw JSON APIs.
	 * Uses configured cURL commands to authenticate and fetch data.
	 * Returns status for each API endpoint.
	 *
	 * @return array Status information with 'b2b_accessible', 'b2b_error', 'retail_accessible', 'retail_error'
	 */
	public function check_json_access() {
		$settings = get_option( $this->option_name, array() );
		$b2b_curl = isset( $settings['b2b_curl'] ) ? trim( $settings['b2b_curl'] ) : '';
		$retail_curl = isset( $settings['retail_curl'] ) ? trim( $settings['retail_curl'] ) : '';
		
		$status = array(
			'b2b_accessible' => false,
			'b2b_error' => '',
			'b2b_products_count' => 0,
			'retail_accessible' => false,
			'retail_error' => '',
			'retail_products_count' => 0,
		);
		
		// Check B2B portal
		if ( ! empty( $b2b_curl ) ) {
			$b2b_parsed = $this->parse_curl_command( $b2b_curl );
			if ( $b2b_parsed && ! empty( $b2b_parsed['url'] ) && ! empty( $b2b_parsed['cookies'] ) ) {
				// Extract base URL for products.json
				$b2b_url = 'https://www.b2bportal.bearpaw-products.de/products.json';
				$b2b_data = $this->get_bearpaw_json( $b2b_url, $b2b_parsed['cookies'], $b2b_parsed['headers'] );
				if ( $b2b_data !== false && isset( $b2b_data['products'] ) ) {
					$status['b2b_accessible'] = true;
					$status['b2b_products_count'] = count( $b2b_data['products'] );
				} else {
					$status['b2b_error'] = 'Failed to access B2B JSON or invalid response';
				}
			} else {
				$status['b2b_error'] = 'Failed to parse B2B cURL command';
			}
		} else {
			$status['b2b_error'] = 'B2B cURL command not configured';
		}
		
		// Check retail site
		if ( ! empty( $retail_curl ) ) {
			$retail_parsed = $this->parse_curl_command( $retail_curl );
			if ( $retail_parsed && ! empty( $retail_parsed['url'] ) && ! empty( $retail_parsed['cookies'] ) ) {
				// Extract base URL for products.json
				$retail_url = 'https://bearpaw-products.com/products.json';
				$retail_data = $this->get_bearpaw_json( $retail_url, $retail_parsed['cookies'], $retail_parsed['headers'] );
				if ( $retail_data !== false && isset( $retail_data['products'] ) ) {
					$status['retail_accessible'] = true;
					$status['retail_products_count'] = count( $retail_data['products'] );
				} else {
					$status['retail_error'] = 'Failed to access Retail JSON or invalid response';
				}
			} else {
				$status['retail_error'] = 'Failed to parse Retail cURL command';
			}
		} else {
			$status['retail_error'] = 'Retail cURL command not configured';
		}
		
		return $status;
	}
	
	/**
	 * Find product by SKU in JSON data
	 * 
	 * Searches through all products and variants to find a product with matching SKU.
	 * Returns product and variant information including price.
	 *
	 * @param array  $products_data Products array from JSON (Bearpaw API response)
	 * @param string $sku SKU to search for
	 * @return array|false Product variant data with 'product_id', 'product_title', 'variant_id', 'price', 'sku' or false if not found
	 */
	private function find_product_by_sku( $products_data, $sku ) {
		if ( ! isset( $products_data['products'] ) || ! is_array( $products_data['products'] ) ) {
			return false;
		}
		
		foreach ( $products_data['products'] as $product ) {
			if ( ! isset( $product['variants'] ) || ! is_array( $product['variants'] ) ) {
				continue;
			}
			
			foreach ( $product['variants'] as $variant ) {
				if ( isset( $variant['sku'] ) && $variant['sku'] === $sku ) {
					return array(
						'product_id' => isset( $product['id'] ) ? $product['id'] : '',
						'product_title' => isset( $product['title'] ) ? $product['title'] : '',
						'variant_id' => isset( $variant['id'] ) ? $variant['id'] : '',
						'price' => isset( $variant['price'] ) ? floatval( $variant['price'] ) : 0,
						'sku' => $sku,
					);
				}
			}
		}
		
		return false;
	}
	
	/**
	 * Analyze products matching between WooCommerce, B2B, and Retail
	 * 
	 * Compares products across all three sources using SKU as the primary identifier.
	 * Builds indexes for fast lookup and counts matches by source.
	 * Provides statistics on how many products match in each combination.
	 *
	 * @return array Analysis results with match counts and sample products
	 */
	public function analyze_products_matching() {
		$analysis = array(
			'wc_total' => 0,
			'b2b_total' => 0,
			'retail_total' => 0,
			'matches' => array(
				'wc_b2b_sku' => 0,
				'wc_retail_sku' => 0,
				'wc_both_sku' => 0,
				'wc_none_sku' => 0,
			),
			'sample_products' => array(),
		);
		
		// Load JSON files
		$b2b_data = $this->load_json_from_temp( 'b2b_products.json' );
		$retail_data = $this->load_json_from_temp( 'retail_products.json' );
		
		if ( ! $b2b_data || ! $retail_data ) {
			return $analysis;
		}
		
		$analysis['b2b_total'] = isset( $b2b_data['products'] ) ? count( $b2b_data['products'] ) : 0;
		$analysis['retail_total'] = isset( $retail_data['products'] ) ? count( $retail_data['products'] ) : 0;
		
		// Build SKU index for B2B
		$b2b_sku_index = array();
		if ( isset( $b2b_data['products'] ) && is_array( $b2b_data['products'] ) ) {
			foreach ( $b2b_data['products'] as $product ) {
				if ( isset( $product['variants'] ) && is_array( $product['variants'] ) ) {
					foreach ( $product['variants'] as $variant ) {
						$sku = isset( $variant['sku'] ) ? trim( $variant['sku'] ) : '';
						if ( ! empty( $sku ) ) {
							$b2b_sku_index[ $sku ] = true;
						}
					}
				}
			}
		}
		
		// Build SKU index for Retail
		$retail_sku_index = array();
		if ( isset( $retail_data['products'] ) && is_array( $retail_data['products'] ) ) {
			foreach ( $retail_data['products'] as $product ) {
				if ( isset( $product['variants'] ) && is_array( $product['variants'] ) ) {
					foreach ( $product['variants'] as $variant ) {
						$sku = isset( $variant['sku'] ) ? trim( $variant['sku'] ) : '';
						if ( ! empty( $sku ) ) {
							$retail_sku_index[ $sku ] = true;
						}
					}
				}
			}
		}
		
		// Get all WooCommerce products with Bearpaw meta field
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => self::BEARPAW_META_KEY,
					'compare' => 'EXISTS',
				),
			),
		);
		
		$wc_products_query = new WP_Query( $args );
		$analysis['wc_total'] = $wc_products_query->found_posts;
		
		if ( $wc_products_query->have_posts() ) {
			while ( $wc_products_query->have_posts() ) {
				$wc_products_query->the_post();
				$product_id = get_the_ID();
				$product = wc_get_product( $product_id );
				
				if ( ! $product ) {
					continue;
				}
				
				$sku = $product->get_sku();
				$sku_clean = $sku ? trim( $sku ) : '';
				
				// Check SKU matches only
				$b2b_sku_match = ! empty( $sku_clean ) && isset( $b2b_sku_index[ $sku_clean ] );
				$retail_sku_match = ! empty( $sku_clean ) && isset( $retail_sku_index[ $sku_clean ] );
				
				// Count matches
				if ( $b2b_sku_match ) {
					$analysis['matches']['wc_b2b_sku']++;
				}
				if ( $retail_sku_match ) {
					$analysis['matches']['wc_retail_sku']++;
				}
				if ( $b2b_sku_match && $retail_sku_match ) {
					$analysis['matches']['wc_both_sku']++;
				}
				if ( ! $b2b_sku_match && ! $retail_sku_match ) {
					$analysis['matches']['wc_none_sku']++;
				}
				
				// Add to sample (first 20)
				if ( count( $analysis['sample_products'] ) < 20 ) {
					$analysis['sample_products'][] = array(
						'wc_id' => $product_id,
						'sku' => $sku_clean,
						'title' => get_the_title(),
						'b2b_match' => $b2b_sku_match,
						'retail_match' => $retail_sku_match,
					);
				}
			}
			wp_reset_postdata();
		}
		
		return $analysis;
	}
	
	/**
	 * Get products from B2B that are not in WooCommerce
	 * 
	 * Compares B2B products with existing WooCommerce products by SKU.
	 * Products are considered missing if their SKU is not found in WooCommerce.
	 * Also calculates recommended prices using the same logic as automatic price updates.
	 * Duplicates (same SKU) are automatically filtered out.
	 *
	 * @return array List of missing products with calculated prices and B2B links
	 */
	public function get_missing_products() {
		$result = array(
			'products' => array(),
			'total_b2b' => 0,
			'total_missing' => 0,
			'total_with_b2b_link' => 0,
		);
		
		// Load JSON files
		$b2b_data = $this->load_json_from_temp( 'b2b_products.json' );
		$retail_data = $this->load_json_from_temp( 'retail_products.json' );
		
		if ( ! $b2b_data ) {
			return $result;
		}
		
		// Build index of existing WooCommerce products by SKU only
		$wc_skus = array();
		
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => self::BEARPAW_META_KEY,
					'compare' => 'EXISTS',
				),
			),
		);
		
		$wc_products_query = new WP_Query( $args );
		
		if ( $wc_products_query->have_posts() ) {
			while ( $wc_products_query->have_posts() ) {
				$wc_products_query->the_post();
				$product_id = get_the_ID();
				$product = wc_get_product( $product_id );
				
				if ( $product ) {
					$sku = $product->get_sku();
					
					if ( $sku ) {
						$wc_skus[ trim( $sku ) ] = true;
					}
				}
			}
			wp_reset_postdata();
		}
		
		// Build Retail price index for price calculation
		$retail_prices_by_sku = array();
		if ( $retail_data && isset( $retail_data['products'] ) && is_array( $retail_data['products'] ) ) {
			foreach ( $retail_data['products'] as $product ) {
				if ( isset( $product['variants'] ) && is_array( $product['variants'] ) ) {
					foreach ( $product['variants'] as $variant ) {
						$sku = isset( $variant['sku'] ) ? trim( $variant['sku'] ) : '';
						$price = isset( $variant['price'] ) ? floatval( $variant['price'] ) : 0;
						if ( ! empty( $sku ) && $price > 0 ) {
							// Store minimum price if multiple variants have same SKU
							if ( ! isset( $retail_prices_by_sku[ $sku ] ) || $price < $retail_prices_by_sku[ $sku ] ) {
								$retail_prices_by_sku[ $sku ] = $price;
							}
						}
					}
				}
			}
		}
		
		// Build B2B price index
		$b2b_prices_by_sku = array();
		if ( isset( $b2b_data['products'] ) && is_array( $b2b_data['products'] ) ) {
			foreach ( $b2b_data['products'] as $product ) {
				if ( isset( $product['variants'] ) && is_array( $product['variants'] ) ) {
					foreach ( $product['variants'] as $variant ) {
						$sku = isset( $variant['sku'] ) ? trim( $variant['sku'] ) : '';
						$price = isset( $variant['price'] ) ? floatval( $variant['price'] ) : 0;
						if ( ! empty( $sku ) && $price > 0 ) {
							// Store minimum price if multiple variants have same SKU
							if ( ! isset( $b2b_prices_by_sku[ $sku ] ) || $price < $b2b_prices_by_sku[ $sku ] ) {
								$b2b_prices_by_sku[ $sku ] = $price;
							}
						}
					}
				}
			}
		}
		
		// Load pricing settings
		$settings = get_option( $this->option_name, array() );
		$margin_percent = isset( $settings['margin_percent'] ) ? floatval( $settings['margin_percent'] ) : 20.0;
		$discount_percent = isset( $settings['discount_percent'] ) ? floatval( $settings['discount_percent'] ) : 10.0;
		
		// Track seen SKUs to avoid duplicates
		$seen_skus = array();
		
		// Check B2B products
		if ( isset( $b2b_data['products'] ) && is_array( $b2b_data['products'] ) ) {
			$result['total_b2b'] = count( $b2b_data['products'] );
			
			foreach ( $b2b_data['products'] as $product ) {
				$product_id = isset( $product['id'] ) ? (string) $product['id'] : '';
				$product_title = isset( $product['title'] ) ? $product['title'] : '';
				$has_match = false;
				$first_sku = '';
				$first_price = 0;
				
				// Check variants by SKU only
				if ( isset( $product['variants'] ) && is_array( $product['variants'] ) ) {
					foreach ( $product['variants'] as $variant ) {
						$sku = isset( $variant['sku'] ) ? trim( $variant['sku'] ) : '';
						if ( ! empty( $sku ) ) {
							if ( empty( $first_sku ) ) {
								$first_sku = $sku;
							}
							if ( $first_price == 0 ) {
								$first_price = isset( $variant['price'] ) ? floatval( $variant['price'] ) : 0;
							}
							
							if ( isset( $wc_skus[ $sku ] ) ) {
								$has_match = true;
								break;
							}
						}
					}
				}
				
				// If product not found in WooCommerce, add to missing list
				if ( ! $has_match && ! empty( $first_sku ) ) {
					// Skip if we already have this SKU (avoid duplicates)
					if ( isset( $seen_skus[ $first_sku ] ) ) {
						continue;
					}
					$seen_skus[ $first_sku ] = true;
					
					// Build B2B product URL
					$b2b_product_url = '';
					$product_handle = isset( $product['handle'] ) ? $product['handle'] : '';
					if ( ! empty( $product_handle ) ) {
						// Use handle if available (standard Shopify format)
						$b2b_product_url = 'https://www.b2bportal.bearpaw-products.de/products/' . $product_handle;
					} elseif ( ! empty( $first_sku ) ) {
						// Fallback: use SKU search
						$b2b_product_url = 'https://www.b2bportal.bearpaw-products.de/search?q=' . urlencode( $first_sku );
					} elseif ( ! empty( $product_id ) ) {
						// Last resort: try by product ID (may not work, but worth trying)
						$b2b_product_url = 'https://www.b2bportal.bearpaw-products.de/products/' . $product_id;
					}
					
					// Calculate recommended price using same logic as price update
					$calculated_price = 0;
					if ( $first_price > 0 ) {
						$b2b_price = $first_price;
						$retail_price = isset( $retail_prices_by_sku[ $first_sku ] ) ? $retail_prices_by_sku[ $first_sku ] : 0;
						
						// Calculate new price: B2B price * (1 + margin_percent / 100)
						$desired_price = $b2b_price * ( 1 + $margin_percent / 100 );
						
						// But it should be at most (retail_price * (1 - discount_percent / 100))
						$max_allowed_price = 0;
						if ( $retail_price > 0 ) {
							$max_allowed_price = $retail_price * ( 1 - $discount_percent / 100 );
						}
						
						// Use the lower of calculated price or maximum allowed price
						if ( $max_allowed_price > 0 && $desired_price > $max_allowed_price ) {
							$calculated_price = $max_allowed_price;
						} else {
							$calculated_price = $desired_price;
						}
						
						// Round to 2 decimal places
						$calculated_price = round( $calculated_price, 2 );
					}
					
					$result['products'][] = array(
						'title' => $product_title,
						'sku' => $first_sku,
						'price' => $first_price,
						'calculated_price' => $calculated_price,
						'b2b_product_url' => $b2b_product_url,
					);
					
					$result['total_missing']++;
					if ( ! empty( $b2b_product_url ) ) {
						$result['total_with_b2b_link']++;
					}
				}
			}
		}
		
		return $result;
	}
	
	/**
	 * Update products prices based on B2B and Retail prices
	 * 
	 * Price calculation logic:
	 * 1. Desired price = B2B Price × (1 + Margin%)
	 * 2. Maximum allowed price = Retail Price × (1 - Discount%)
	 * 3. Final price = min(Desired price, Maximum allowed price)
	 * 4. Price is only updated if difference exceeds threshold
	 * 
	 * Products are matched by SKU across all three sources (WooCommerce, B2B, Retail).
	 * Only products with matches in both B2B and Retail are eligible for updates.
	 *
	 * @return array Update results with success status, counts, and details
	 */
	public function update_products_prices() {
		$result = array(
			'success' => false,
			'updated' => 0,
			'skipped' => 0,
			'errors' => 0,
			'details' => array(),
		);
		
		// Load settings
		$settings = get_option( $this->option_name, array() );
		$margin_percent = isset( $settings['margin_percent'] ) ? floatval( $settings['margin_percent'] ) : 20.0;
		$discount_percent = isset( $settings['discount_percent'] ) ? floatval( $settings['discount_percent'] ) : 10.0;
		$price_change_threshold = isset( $settings['price_change_threshold'] ) ? floatval( $settings['price_change_threshold'] ) : 0.2;
		
		// Load JSON files
		$b2b_data = $this->load_json_from_temp( 'b2b_products.json' );
		$retail_data = $this->load_json_from_temp( 'retail_products.json' );
		
		if ( ! $b2b_data || ! $retail_data ) {
			$result['error'] = 'B2B or Retail data not loaded. Please complete step 2 first.';
			return $result;
		}
		
		// Build price indexes by SKU
		$b2b_prices_by_sku = array();
		$retail_prices_by_sku = array();
		
		// Index B2B prices
		if ( isset( $b2b_data['products'] ) && is_array( $b2b_data['products'] ) ) {
			foreach ( $b2b_data['products'] as $product ) {
				if ( isset( $product['variants'] ) && is_array( $product['variants'] ) ) {
					foreach ( $product['variants'] as $variant ) {
						$sku = isset( $variant['sku'] ) ? trim( $variant['sku'] ) : '';
						$price = isset( $variant['price'] ) ? floatval( $variant['price'] ) : 0;
						if ( ! empty( $sku ) && $price > 0 ) {
							// Store minimum price if multiple variants have same SKU
							if ( ! isset( $b2b_prices_by_sku[ $sku ] ) || $price < $b2b_prices_by_sku[ $sku ] ) {
								$b2b_prices_by_sku[ $sku ] = $price;
							}
						}
					}
				}
			}
		}
		
		// Index Retail prices
		if ( isset( $retail_data['products'] ) && is_array( $retail_data['products'] ) ) {
			foreach ( $retail_data['products'] as $product ) {
				if ( isset( $product['variants'] ) && is_array( $product['variants'] ) ) {
					foreach ( $product['variants'] as $variant ) {
						$sku = isset( $variant['sku'] ) ? trim( $variant['sku'] ) : '';
						$price = isset( $variant['price'] ) ? floatval( $variant['price'] ) : 0;
						if ( ! empty( $sku ) && $price > 0 ) {
							// Store minimum price if multiple variants have same SKU
							if ( ! isset( $retail_prices_by_sku[ $sku ] ) || $price < $retail_prices_by_sku[ $sku ] ) {
								$retail_prices_by_sku[ $sku ] = $price;
							}
						}
					}
				}
			}
		}
		
		// Get all WooCommerce products with Bearpaw meta field
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => self::BEARPAW_META_KEY,
					'compare' => 'EXISTS',
				),
			),
		);
		
		$wc_products_query = new WP_Query( $args );
		
		if ( $wc_products_query->have_posts() ) {
			while ( $wc_products_query->have_posts() ) {
				$wc_products_query->the_post();
				$product_id = get_the_ID();
				$product = wc_get_product( $product_id );
				
				if ( ! $product ) {
					$result['skipped']++;
					continue;
				}
				
				$sku = $product->get_sku();
				$sku_clean = $sku ? trim( $sku ) : '';
				
				if ( empty( $sku_clean ) ) {
					$result['skipped']++;
					continue;
				}
				
				// Get B2B and Retail prices
				$b2b_price = isset( $b2b_prices_by_sku[ $sku_clean ] ) ? $b2b_prices_by_sku[ $sku_clean ] : 0;
				$retail_price = isset( $retail_prices_by_sku[ $sku_clean ] ) ? $retail_prices_by_sku[ $sku_clean ] : 0;
				
				if ( $b2b_price <= 0 ) {
					$result['skipped']++;
					continue;
				}
				
				// Calculate new price
				// Ціна має бути на 20% вище B2B (маржа)
				$desired_price = $b2b_price * ( 1 + $margin_percent / 100 );
				
				// Але має бути мінімум на 10% нижче роздрібної (щоб конкурувати)
				$max_allowed_price = 0;
				if ( $retail_price > 0 ) {
					$max_allowed_price = $retail_price * ( 1 - $discount_percent / 100 );
				}
				
				// Використовуємо меншу з двох: бажана ціна або максимально дозволена
				if ( $max_allowed_price > 0 && $desired_price > $max_allowed_price ) {
					$calculated_price = $max_allowed_price;
				} else {
					$calculated_price = $desired_price;
				}
				
				// Round to 2 decimal places
				$new_price = round( $calculated_price, 2 );
				
				// Get current price
				$current_price = $product->get_regular_price();
				if ( ! $current_price ) {
					$current_price = $product->get_price();
				}
				$current_price = floatval( $current_price );
				
				// Update if price changed by more than threshold
				if ( abs( $current_price - $new_price ) > $price_change_threshold ) {
					$product->set_regular_price( $new_price );
					$product->set_price( $new_price );
					
					// Save product
					if ( $product->save() ) {
						$result['updated']++;
						$result['details'][] = array(
							'product_id' => $product_id,
							'sku' => $sku_clean,
							'old_price' => $current_price,
							'new_price' => $new_price,
							'b2b_price' => $b2b_price,
							'retail_price' => $retail_price,
						);
					} else {
						$result['errors']++;
					}
				} else {
					$result['skipped']++;
				}
			}
			wp_reset_postdata();
		}
		
		$result['success'] = true;
		return $result;
	}
	
	/**
	 * Get sample Bearpaw products for price comparison
	 *
	 * @param int $limit Number of products to return
	 * @return array Array of product data
	 */
	public function get_bearpaw_products_sample( $limit = 10 ) {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => self::BEARPAW_META_KEY,
					'compare' => 'EXISTS',
				),
			),
		);
		
		$products_query = new WP_Query( $args );
		$products = array();
		
		if ( $products_query->have_posts() ) {
			while ( $products_query->have_posts() ) {
				$products_query->the_post();
				$product_id = get_the_ID();
				$product = wc_get_product( $product_id );
				
				if ( $product ) {
					$sku = $product->get_sku();
					$price = $product->get_regular_price();
					if ( ! $price ) {
						$price = $product->get_price();
					}
					
					$products[] = array(
						'id'        => $product_id,
						'title'     => get_the_title(),
						'sku'       => $sku,
						'price'     => $price,
					);
				}
			}
			wp_reset_postdata();
		}
		
		return $products;
	}
	
	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Initialize variables
		$download_result = null;
		$price_update_result = null;
		
		// Handle price update button click
		if ( isset( $_POST['update_prices'] ) && check_admin_referer( 'f_bearpaw_update_prices' ) ) {
			$price_update_result = $this->update_products_prices();
			
			// Save result to transient so it persists after redirect
			set_transient( 'f_bearpaw_price_update_result', $price_update_result, 60 );
			
			// Redirect to open step 3 to show results
			wp_redirect( add_query_arg( 'open_step', '3', admin_url( 'admin.php?page=f-bearpaw-dealer' ) ) );
			exit;
		}
		
		// Load price update result from transient if exists
		$stored_price_update_result = get_transient( 'f_bearpaw_price_update_result' );
		if ( $stored_price_update_result !== false ) {
			$price_update_result = $stored_price_update_result;
			delete_transient( 'f_bearpaw_price_update_result' );
		}
		
		// Handle download products button click (now just clears progress, actual download via AJAX)
		if ( isset( $_POST['download_products'] ) && check_admin_referer( 'f_bearpaw_download_products' ) ) {
			// Clear temp directory and progress
			$this->clear_temp_dir();
			$this->clear_download_progress( 'B2B' );
			$this->clear_download_progress( 'Retail' );
			
			// Redirect to Step 2 where AJAX will handle the download
			wp_redirect( add_query_arg( 'open_step', '2', admin_url( 'admin.php?page=f-bearpaw-dealer' ) ) );
			exit;
		}
		
		// Handle settings save
		$settings_saved = false;
		if ( isset( $_POST['submit'] ) && check_admin_referer( 'f_bearpaw_dealer_settings' ) ) {
			// Settings are saved via register_settings
			$settings_saved = true;
			
			// Redirect to open step 2 after saving
			wp_redirect( add_query_arg( 'open_step', '2', admin_url( 'admin.php?page=f-bearpaw-dealer' ) ) );
			exit;
		}
		
		// Load download result from transient if exists (after redirect)
		$stored_download_result = get_transient( 'f_bearpaw_download_result' );
		if ( $stored_download_result !== false ) {
			$download_result = $stored_download_result;
			delete_transient( 'f_bearpaw_download_result' );
		} else {
			$download_result = null;
		}
		
		$total_count = $this->get_bearpaw_products_count();
		$published_count = $this->get_bearpaw_products_count_published();
		$draft_count = $total_count - $published_count;
		$sample_products = $this->get_bearpaw_products_sample( 10 );
		
		// Load JSON data from temp files if they exist
		$b2b_data = $this->load_json_from_temp( 'b2b_products.json' );
		$retail_data = $this->load_json_from_temp( 'retail_products.json' );
		
		// Check JSON API access status (always check, especially for Step 2)
		$json_status = $this->check_json_access();
		
		// Enrich products with prices from JSON
		if ( $b2b_data || $retail_data ) {
			foreach ( $sample_products as &$product ) {
				if ( ! empty( $product['sku'] ) ) {
					if ( $b2b_data ) {
						$b2b_product = $this->find_product_by_sku( $b2b_data, $product['sku'] );
						if ( $b2b_product ) {
							$product['b2b_price'] = $b2b_product['price'];
						}
					}
					
					if ( $retail_data ) {
						$retail_product = $this->find_product_by_sku( $retail_data, $product['sku'] );
						if ( $retail_product ) {
							$product['retail_price'] = $retail_product['price'];
						}
					}
				}
			}
			unset( $product );
		}
		
		// Determine which step should be open
		$step1_completed = false;
		$step2_completed = false;
		$step3_available = false;
		$step4_available = false;
		
		$settings = get_option( $this->option_name, array() );
		$b2b_curl = isset( $settings['b2b_curl'] ) ? trim( $settings['b2b_curl'] ) : '';
		$retail_curl = isset( $settings['retail_curl'] ) ? trim( $settings['retail_curl'] ) : '';
		
		if ( ! empty( $b2b_curl ) && ! empty( $retail_curl ) ) {
			$step1_completed = true;
		}
		
		$b2b_file_exists = file_exists( $this->temp_dir . '/b2b_products.json' );
		$retail_file_exists = file_exists( $this->temp_dir . '/retail_products.json' );
		
		if ( $b2b_file_exists && $retail_file_exists ) {
			$step2_completed = true;
			$step3_available = true;
		}
		
		// Check if prices were updated (step 4 available)
		if ( $price_update_result !== null && isset( $price_update_result['success'] ) && $price_update_result['success'] ) {
			$step4_available = true;
		}
		
		// Determine which step to open
		$open_step = 1;
		if ( $step1_completed && ! $step2_completed ) {
			$open_step = 2;
		} elseif ( $step2_completed && ! $step4_available ) {
			$open_step = 3;
		} elseif ( $step4_available ) {
			$open_step = 4;
		}
		
		// If download was just completed, open appropriate step
		if ( $download_result !== null ) {
			if ( isset( $download_result['b2b']['success'] ) && $download_result['b2b']['success'] &&
			     isset( $download_result['retail']['success'] ) && $download_result['retail']['success'] ) {
				// Both successful - open step 3
				$open_step = 3;
			} else {
				// Some errors - stay on step 2 to show errors
				$open_step = 2;
			}
		}
		
		// Check URL parameter for which step to open
		if ( isset( $_GET['open_step'] ) ) {
			$requested_step = intval( $_GET['open_step'] );
			if ( $requested_step >= 1 && $requested_step <= 4 ) {
				$open_step = $requested_step;
			}
		}
		
		?>
		<style>
			.f-bearpaw-accordion {
				margin-top: 20px;
			}
			.f-bearpaw-accordion-item {
				background: #fff;
				border: 1px solid #c3c4c7;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
				margin-bottom: 10px;
			}
			.f-bearpaw-accordion-header {
				padding: 15px 20px;
				cursor: pointer;
				user-select: none;
				display: flex;
				justify-content: space-between;
				align-items: center;
				background: #f6f7f7;
				border-bottom: 1px solid #c3c4c7;
			}
			.f-bearpaw-accordion-header:hover {
				background: #f0f0f1;
			}
			.f-bearpaw-accordion-header h2 {
				margin: 0;
				font-size: 16px;
				display: flex;
				align-items: center;
				gap: 10px;
			}
			.f-bearpaw-accordion-header .step-number {
				display: inline-block;
				width: 24px;
				height: 24px;
				line-height: 24px;
				text-align: center;
				background: #2271b1;
				color: #fff;
				border-radius: 50%;
				font-weight: bold;
				font-size: 12px;
			}
			.f-bearpaw-accordion-header .step-status {
				font-size: 12px;
				color: #646970;
				font-weight: normal;
			}
			.f-bearpaw-accordion-header.completed .step-number {
				background: #00a32a;
			}
			.f-bearpaw-accordion-content {
				padding: 20px;
				display: none;
			}
			.f-bearpaw-accordion-item.active .f-bearpaw-accordion-content {
				display: block;
			}
			.f-bearpaw-accordion-toggle {
				font-size: 18px;
				color: #646970;
			}
			.f-bearpaw-progress {
				margin: 20px 0;
				padding: 15px;
				background: #f0f6fc;
				border-left: 4px solid #2271b1;
			}
			.f-bearpaw-progress-bar {
				width: 100%;
				height: 20px;
				background: #ddd;
				border-radius: 10px;
				overflow: hidden;
				margin: 10px 0;
			}
			.f-bearpaw-progress-fill {
				height: 100%;
				background: #2271b1;
				width: 0%;
				transition: width 0.3s ease;
			}
		</style>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<!-- Bearpaw Products Statistics -->
			<div class="f-bearpaw-stats" style="margin-top: 20px; margin-bottom: 30px;">
				<h2>Bearpaw Products Statistics</h2>
				
				<div class="f-bearpaw-stats-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
					<div class="card" style="background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px;">
						<h3 style="margin-top: 0; color: #1d2327;">Total Bearpaw Products</h3>
						<div style="font-size: 48px; font-weight: bold; color: #2271b1; line-height: 1;">
							<?php echo number_format( $total_count ); ?>
						</div>
						<p style="margin-bottom: 0; color: #646970; font-size: 14px;">
							All products with Bearpaw meta field
						</p>
					</div>
					
					<div class="card" style="background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px;">
						<h3 style="margin-top: 0; color: #1d2327;">Published</h3>
						<div style="font-size: 48px; font-weight: bold; color: #00a32a; line-height: 1;">
							<?php echo number_format( $published_count ); ?>
						</div>
						<p style="margin-bottom: 0; color: #646970; font-size: 14px;">
							Published Bearpaw products
						</p>
					</div>
					
					<?php if ( $draft_count > 0 ) : ?>
					<div class="card" style="background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px;">
						<h3 style="margin-top: 0; color: #1d2327;">Draft/Private</h3>
						<div style="font-size: 48px; font-weight: bold; color: #d63638; line-height: 1;">
							<?php echo number_format( $draft_count ); ?>
						</div>
						<p style="margin-bottom: 0; color: #646970; font-size: 14px;">
							Draft or private products
						</p>
					</div>
					<?php endif; ?>
				</div>
			</div>
			
			<div class="f-bearpaw-accordion">
				<!-- Step 1: Configure cURL Commands -->
				<div class="f-bearpaw-accordion-item <?php echo $open_step === 1 ? 'active' : ''; ?>">
					<div class="f-bearpaw-accordion-header <?php echo $step1_completed ? 'completed' : ''; ?>" onclick="toggleAccordion(1)">
						<h2>
							<span class="step-number"><?php echo $step1_completed ? '✓' : '1'; ?></span>
							<span>Step 1: Configure cURL Commands</span>
							<?php if ( $step1_completed ) : ?>
								<span class="step-status">(Completed)</span>
							<?php endif; ?>
						</h2>
						<span class="f-bearpaw-accordion-toggle"><?php echo $open_step === 1 ? '−' : '+'; ?></span>
					</div>
					<div class="f-bearpaw-accordion-content">
						<form method="post" action="options.php" id="step1-form">
							<?php
							settings_fields( 'f_bearpaw_dealer_settings_group' );
							do_settings_sections( 'f-bearpaw-dealer' );
							submit_button( 'Save cURL Commands', 'primary', 'submit', false, array( 'onclick' => 'handleStep1Save(event)' ) );
							?>
						</form>
					</div>
				</div>
				
				<!-- Step 2: Download All Products -->
				<div class="f-bearpaw-accordion-item <?php echo $open_step === 2 ? 'active' : ''; ?>">
					<div class="f-bearpaw-accordion-header <?php echo $step2_completed ? 'completed' : ''; ?>" onclick="toggleAccordion(2)">
						<h2>
							<span class="step-number"><?php echo $step2_completed ? '✓' : '2'; ?></span>
							<span>Step 2: Download All Products</span>
							<?php if ( $step2_completed ) : ?>
								<span class="step-status">(Completed)</span>
							<?php endif; ?>
						</h2>
						<span class="f-bearpaw-accordion-toggle"><?php echo $open_step === 2 ? '−' : '+'; ?></span>
					</div>
					<div class="f-bearpaw-accordion-content">
						<!-- JSON API Access Status (shown before download button) -->
						<div class="f-bearpaw-json-status" style="margin-bottom: 30px; padding: 15px; background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
							<h3 style="margin-top: 0;">JSON API Access Status</h3>
							<table class="widefat">
								<thead>
									<tr>
										<th>Source</th>
										<th>Status</th>
										<th>Products Available</th>
										<th>Message</th>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td><strong>B2B Portal</strong><br/><small>https://www.b2bportal.bearpaw-products.de/products.json</small></td>
										<td>
											<?php if ( $b2b_data && ! empty( $b2b_data['products'] ) ) : ?>
												<span style="color: #00a32a; font-weight: bold;">✓ Loaded</span>
											<?php elseif ( $json_status['b2b_accessible'] ) : ?>
												<span style="color: #00a32a; font-weight: bold;">✓ Accessible</span>
											<?php else : ?>
												<span style="color: #d63638; font-weight: bold;">✗ Not Accessible</span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ( $b2b_data && ! empty( $b2b_data['products'] ) ) : ?>
												<strong><?php echo number_format( count( $b2b_data['products'] ) ); ?></strong> products
											<?php elseif ( ! empty( $json_status['b2b_products_count'] ) ) : ?>
												<strong><?php echo number_format( $json_status['b2b_products_count'] ); ?></strong> products
											<?php else : ?>
												-
											<?php endif; ?>
										</td>
										<td>
											<?php if ( ! empty( $json_status['b2b_error'] ) ) : ?>
												<span style="color: #d63638;"><?php echo esc_html( $json_status['b2b_error'] ); ?></span>
											<?php else : ?>
												<span style="color: #00a32a;">OK</span>
											<?php endif; ?>
										</td>
									</tr>
									<tr>
										<td><strong>Retail Site</strong><br/><small>https://bearpaw-products.com/products.json</small></td>
										<td>
											<?php if ( $retail_data && ! empty( $retail_data['products'] ) ) : ?>
												<span style="color: #00a32a; font-weight: bold;">✓ Loaded</span>
											<?php elseif ( $json_status['retail_accessible'] ) : ?>
												<span style="color: #00a32a; font-weight: bold;">✓ Accessible</span>
											<?php else : ?>
												<span style="color: #d63638; font-weight: bold;">✗ Not Accessible</span>
											<?php endif; ?>
										</td>
										<td>
											<?php if ( $retail_data && ! empty( $retail_data['products'] ) ) : ?>
												<strong><?php echo number_format( count( $retail_data['products'] ) ); ?></strong> products
											<?php elseif ( ! empty( $json_status['retail_products_count'] ) ) : ?>
												<strong><?php echo number_format( $json_status['retail_products_count'] ); ?></strong> products
											<?php else : ?>
												-
											<?php endif; ?>
										</td>
										<td>
											<?php if ( ! empty( $json_status['retail_error'] ) ) : ?>
												<span style="color: #d63638;"><?php echo esc_html( $json_status['retail_error'] ); ?></span>
											<?php else : ?>
												<span style="color: #00a32a;">OK</span>
											<?php endif; ?>
										</td>
									</tr>
								</tbody>
							</table>
							<?php if ( ! $json_status['b2b_accessible'] || ! $json_status['retail_accessible'] ) : ?>
								<p style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
									<strong>⚠️ Warning:</strong> One or both APIs are not accessible. Please check your cURL commands in Step 1 before attempting to download products.
								</p>
							<?php endif; ?>
							<p style="margin-top: 15px; padding: 10px; background: #f0f6fc; border-left: 4px solid #2271b1; font-size: 12px;">
								<strong>How to get cURL commands:</strong> Log in to each site in your browser, open Developer Tools (F12), go to Network tab, 
								visit the products.json URL, right-click on the request → Copy → Copy as cURL. Paste the full cURL command into the fields in Step 1 above.
							</p>
						</div>
						
						<p style="color: #646970; margin-bottom: 15px;">
							This will clear the temporary directory and download all products from both B2B and Retail stores.
							<strong>The process may take several minutes</strong> depending on the number of products. Please do not close this page during download.
						</p>
						
						<?php if ( $download_result === null ) : ?>
							<form method="post" id="step2-form" style="margin: 15px 0;" onsubmit="handleStep2Download(event); return false;">
								<?php 
								$download_nonce = wp_create_nonce( 'f_bearpaw_download_products' );
								wp_nonce_field( 'f_bearpaw_download_products', '_wpnonce', false );
								?>
								<input type="hidden" id="download-nonce" value="<?php echo esc_attr( $download_nonce ); ?>" />
								<button type="submit" name="download_products" class="button button-primary button-large" style="font-size: 16px; padding: 12px 24px;" id="download-button" <?php echo ( ! $json_status['b2b_accessible'] || ! $json_status['retail_accessible'] ) ? 'disabled title="Please fix API access issues before downloading"' : ''; ?>>
									📥 Download All Products
								</button>
								<?php if ( ! $json_status['b2b_accessible'] || ! $json_status['retail_accessible'] ) : ?>
									<p style="margin-top: 10px; color: #d63638; font-size: 13px;">
										⚠️ Cannot download: One or both APIs are not accessible. Please check the status above and fix the cURL commands in Step 1.
									</p>
								<?php endif; ?>
							</form>
						<?php endif; ?>
				
						<?php if ( $download_result !== null ) : ?>
							<div style="margin-top: 20px;">
								<h3>Download Results:</h3>
								
								<!-- B2B Results -->
								<div style="margin-top: 15px; padding: 15px; background: <?php echo $download_result['b2b']['success'] ? '#d4edda' : '#f8d7da'; ?>; border: 1px solid <?php echo $download_result['b2b']['success'] ? '#c3e6cb' : '#f5c6cb'; ?>; border-radius: 4px;">
									<h4 style="margin-top: 0;">B2B Portal</h4>
									<?php if ( $download_result['b2b']['success'] ) : ?>
										<p style="color: #155724; font-weight: bold;">
											✅ Successfully downloaded <strong><?php echo number_format( $download_result['b2b']['products_count'] ); ?></strong> products
										</p>
										<p style="color: #646970; font-size: 12px;">
											Saved to: <?php echo esc_html( basename( $download_result['b2b']['file_path'] ) ); ?>
										</p>
									<?php else : ?>
										<p style="color: #721c24;">
											❌ Error: <?php echo esc_html( $download_result['b2b']['error'] ?? 'Unknown error' ); ?>
										</p>
									<?php endif; ?>
								</div>
								
								<!-- Retail Results -->
								<div style="margin-top: 15px; padding: 15px; background: <?php echo $download_result['retail']['success'] ? '#d4edda' : '#f8d7da'; ?>; border: 1px solid <?php echo $download_result['retail']['success'] ? '#c3e6cb' : '#f5c6cb'; ?>; border-radius: 4px;">
									<h4 style="margin-top: 0;">Retail Site</h4>
									<?php if ( $download_result['retail']['success'] ) : ?>
										<p style="color: #155724; font-weight: bold;">
											✅ Successfully downloaded <strong><?php echo number_format( $download_result['retail']['products_count'] ); ?></strong> products
										</p>
										<p style="color: #646970; font-size: 12px;">
											Saved to: <?php echo esc_html( basename( $download_result['retail']['file_path'] ) ); ?>
										</p>
									<?php else : ?>
										<p style="color: #721c24;">
											❌ Error: <?php echo esc_html( $download_result['retail']['error'] ?? 'Unknown error' ); ?>
										</p>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>
						
						<!-- Show current files status -->
						<?php
						$b2b_file_exists = file_exists( $this->temp_dir . '/b2b_products.json' );
						$retail_file_exists = file_exists( $this->temp_dir . '/retail_products.json' );
						?>
						<?php if ( $b2b_file_exists || $retail_file_exists ) : ?>
							<div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
								<h4 style="margin-top: 0;">Current Status:</h4>
								<ul>
									<li>
										B2B Products: <?php echo $b2b_file_exists ? '✅ File exists' : '❌ Not downloaded'; ?>
										<?php if ( $b2b_file_exists && $b2b_data ) : ?>
											(<?php echo number_format( count( $b2b_data['products'] ?? array() ) ); ?> products)
										<?php endif; ?>
									</li>
									<li>
										Retail Products: <?php echo $retail_file_exists ? '✅ File exists' : '❌ Not downloaded'; ?>
										<?php if ( $retail_file_exists && $retail_data ) : ?>
											(<?php echo number_format( count( $retail_data['products'] ?? array() ) ); ?> products)
										<?php endif; ?>
									</li>
								</ul>
							</div>
						<?php endif; ?>
					</div>
				</div>
				
				<!-- Step 3: Analyze Products -->
				<div class="f-bearpaw-accordion-item <?php echo $open_step === 3 ? 'active' : ''; ?>">
					<div class="f-bearpaw-accordion-header <?php echo $step3_available ? '' : 'disabled'; ?>" onclick="<?php echo $step3_available ? 'toggleAccordion(3)' : ''; ?>">
						<h2>
							<span class="step-number">3</span>
							<span>Step 3: Analyze Products</span>
							<?php if ( ! $step3_available ) : ?>
								<span class="step-status">(Complete previous steps first)</span>
							<?php endif; ?>
						</h2>
						<span class="f-bearpaw-accordion-toggle"><?php echo $open_step === 3 ? '−' : '+'; ?></span>
					</div>
					<div class="f-bearpaw-accordion-content">
						<?php if ( $step3_available ) : ?>
							<?php
							// Perform analysis
							$analysis = $this->analyze_products_matching();
							?>
							<h3>Products Matching Analysis</h3>
							
							<!-- Summary Statistics -->
							<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
								<div style="padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
									<h4 style="margin: 0 0 10px 0;">WooCommerce</h4>
									<div style="font-size: 24px; font-weight: bold; color: #2271b1;">
										<?php echo number_format( $analysis['wc_total'] ); ?>
									</div>
									<small style="color: #646970;">Bearpaw products</small>
								</div>
								
								<div style="padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
									<h4 style="margin: 0 0 10px 0;">B2B Portal</h4>
									<div style="font-size: 24px; font-weight: bold; color: #2271b1;">
										<?php echo number_format( $analysis['b2b_total'] ); ?>
									</div>
									<small style="color: #646970;">Total products</small>
								</div>
								
								<div style="padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
									<h4 style="margin: 0 0 10px 0;">Retail Site</h4>
									<div style="font-size: 24px; font-weight: bold; color: #2271b1;">
										<?php echo number_format( $analysis['retail_total'] ); ?>
									</div>
									<small style="color: #646970;">Total products</small>
								</div>
							</div>
							
							<!-- Matching Statistics -->
							<div style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #c3c4c7;">
								<h4>Matching by SKU</h4>
								<table class="widefat">
									<thead>
										<tr>
											<th>Match Type</th>
											<th>Count</th>
											<th>Percentage</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td><strong>WC → B2B (by SKU)</strong></td>
											<td><?php echo number_format( $analysis['matches']['wc_b2b_sku'] ); ?></td>
											<td><?php echo $analysis['wc_total'] > 0 ? number_format( ( $analysis['matches']['wc_b2b_sku'] / $analysis['wc_total'] ) * 100, 1 ) : 0; ?>%</td>
										</tr>
										<tr>
											<td><strong>WC → Retail (by SKU)</strong></td>
											<td><?php echo number_format( $analysis['matches']['wc_retail_sku'] ); ?></td>
											<td><?php echo $analysis['wc_total'] > 0 ? number_format( ( $analysis['matches']['wc_retail_sku'] / $analysis['wc_total'] ) * 100, 1 ) : 0; ?>%</td>
										</tr>
										<tr>
											<td><strong>WC → Both (by SKU)</strong></td>
											<td><?php echo number_format( $analysis['matches']['wc_both_sku'] ); ?></td>
											<td><?php echo $analysis['wc_total'] > 0 ? number_format( ( $analysis['matches']['wc_both_sku'] / $analysis['wc_total'] ) * 100, 1 ) : 0; ?>%</td>
										</tr>
										<tr>
											<td><strong>WC → None (by SKU)</strong></td>
											<td><?php echo number_format( $analysis['matches']['wc_none_sku'] ); ?></td>
											<td><?php echo $analysis['wc_total'] > 0 ? number_format( ( $analysis['matches']['wc_none_sku'] / $analysis['wc_total'] ) * 100, 1 ) : 0; ?>%</td>
										</tr>
									</tbody>
								</table>
							</div>
							
							<!-- Price Update Section -->
							<?php if ( $analysis['wc_total'] > 0 && $analysis['matches']['wc_both_sku'] > 0 ) : ?>
								<div style="margin: 30px 0; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107;">
									<h4 style="margin-top: 0;">Automatic Price Update</h4>
									<p>
										Found <strong><?php echo number_format( $analysis['matches']['wc_both_sku'] ); ?></strong> products with matches in both B2B and Retail stores.
										You can automatically update their prices based on configured pricing rules.
									</p>
									
									<?php
									$settings = get_option( $this->option_name, array() );
									$margin_percent = isset( $settings['margin_percent'] ) ? floatval( $settings['margin_percent'] ) : 20.0;
									$discount_percent = isset( $settings['discount_percent'] ) ? floatval( $settings['discount_percent'] ) : 10.0;
									?>
									<div style="margin: 15px 0; padding: 15px; background: #fff; border: 1px solid #ddd;">
										<p><strong>Current Pricing Rules:</strong></p>
										<ul>
											<li>Margin above B2B price: <strong><?php echo number_format( $margin_percent, 1 ); ?>%</strong></li>
											<li>Discount below Retail price: <strong><?php echo number_format( $discount_percent, 1 ); ?>%</strong></li>
										</ul>
										<p style="color: #646970; font-size: 12px; margin-bottom: 0;">
											<em>You can change these values in the settings form above.</em>
										</p>
									</div>
									
									<?php if ( $price_update_result !== null ) : ?>
										<div style="margin-top: 20px; padding: 15px; background: <?php echo $price_update_result['success'] ? '#d4edda' : '#f8d7da'; ?>; border: 1px solid <?php echo $price_update_result['success'] ? '#c3e6cb' : '#f5c6cb'; ?>; border-radius: 4px;">
											<?php if ( $price_update_result['success'] ) : ?>
												<h4 style="margin-top: 0; color: #155724;">✅ Price Update Completed</h4>
												<p style="color: #155724; font-weight: bold; font-size: 18px;">
													Updated: <strong><?php echo number_format( $price_update_result['updated'] ); ?></strong> products
												</p>
												<p>
													Skipped: <?php echo number_format( $price_update_result['skipped'] ); ?> products<br/>
													<?php if ( $price_update_result['errors'] > 0 ) : ?>
														Errors: <?php echo number_format( $price_update_result['errors'] ); ?> products<br/>
													<?php endif; ?>
												</p>
												
												<?php if ( ! empty( $price_update_result['details'] ) ) : ?>
													<?php
													// Show last 10 updated products
													$last_10_products = array_slice( $price_update_result['details'], -10 );
													$total_updated = count( $price_update_result['details'] );
													?>
													<details style="margin-top: 15px;">
														<summary style="cursor: pointer; font-weight: bold;">
															Show last 10 updated products
															<?php if ( $total_updated > 10 ) : ?>
																(<?php echo number_format( $total_updated ); ?> total)
															<?php endif; ?>
														</summary>
														<table class="widefat" style="margin-top: 10px;">
															<thead>
																<tr>
																	<th>SKU</th>
																	<th>Old Price</th>
																	<th>New Price</th>
																	<th>B2B Price</th>
																	<th>Retail Price</th>
																</tr>
															</thead>
															<tbody>
																<?php foreach ( $last_10_products as $detail ) : ?>
																<tr>
																	<td><strong><?php echo esc_html( $detail['sku'] ); ?></strong></td>
																	<td><?php echo number_format( $detail['old_price'], 2, '.', '' ); ?> EUR</td>
																	<td><strong style="color: #00a32a;"><?php echo number_format( $detail['new_price'], 2, '.', '' ); ?> EUR</strong></td>
																	<td><?php echo number_format( $detail['b2b_price'], 2, '.', '' ); ?> EUR</td>
																	<td><?php echo $detail['retail_price'] > 0 ? number_format( $detail['retail_price'], 2, '.', '' ) . ' EUR' : 'N/A'; ?></td>
																</tr>
																<?php endforeach; ?>
															</tbody>
														</table>
													</details>
												<?php endif; ?>
											<?php else : ?>
												<h4 style="margin-top: 0; color: #721c24;">❌ Price Update Failed</h4>
												<p style="color: #721c24;">
													<?php echo esc_html( $price_update_result['error'] ?? 'Unknown error' ); ?>
												</p>
											<?php endif; ?>
										</div>
									<?php else : ?>
										<form method="post" style="margin-top: 15px;">
											<?php wp_nonce_field( 'f_bearpaw_update_prices' ); ?>
											<button type="submit" name="update_prices" class="button button-primary button-large" style="font-size: 16px; padding: 12px 24px;">
												💰 Update Prices Automatically
											</button>
										</form>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							
						<?php else : ?>
							<p>Please complete steps 1 and 2 first to download products data.</p>
						<?php endif; ?>
					</div>
				</div>
				
				<!-- Step 4: Missing Products -->
				<div class="f-bearpaw-accordion-item <?php echo $open_step === 4 ? 'active' : ''; ?>">
					<div class="f-bearpaw-accordion-header" onclick="toggleAccordion(4)">
						<h2>
							<span class="step-number">4</span>
							<span>Step 4: Missing Products from B2B</span>
						</h2>
						<span class="f-bearpaw-accordion-toggle"><?php echo $open_step === 4 ? '−' : '+'; ?></span>
					</div>
					<div class="f-bearpaw-accordion-content">
						<?php if ( $step3_available ) : ?>
							<?php
							// Get missing products
							$missing_products = $this->get_missing_products();
							?>
							<h3>Products Available in B2B but Not in Your Store</h3>
							
							<div style="margin: 20px 0; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
								<p style="margin: 0;">
									<strong>Total B2B products:</strong> <?php echo number_format( $missing_products['total_b2b'] ); ?><br/>
									<strong>Missing in your store:</strong> <?php echo number_format( $missing_products['total_missing'] ); ?><br/>
									<strong>With B2B link:</strong> <?php echo number_format( $missing_products['total_with_b2b_link'] ?? 0 ); ?>
								</p>
							</div>
							
							<?php if ( ! empty( $missing_products['products'] ) ) : ?>
								<p style="color: #646970;">
									These products are available in the B2B portal but not yet in your WooCommerce store. 
									You can add them manually later. Click "Add Product" to open the product form and B2B product page.
								</p>
								
								<div style="margin: 20px 0;">
									<input type="text" id="missing-products-search" placeholder="Search by SKU or title..." style="width: 100%; padding: 8px; margin-bottom: 10px;" onkeyup="filterMissingProducts()" />
								</div>
								
								<table class="widefat striped" id="missing-products-table">
									<thead>
										<tr>
											<th style="width: 5%;">#</th>
											<th style="width: 15%; cursor: pointer;" onclick="sortTable(1, 'string')" title="Click to sort">
												SKU <span class="sort-indicator">⇅</span>
											</th>
											<th style="width: 40%; cursor: pointer;" onclick="sortTable(2, 'string')" title="Click to sort">
												Product Title <span class="sort-indicator">⇅</span>
											</th>
											<th style="width: 12%; cursor: pointer;" onclick="sortTable(3, 'number')" title="Click to sort">
												B2B Price <span class="sort-indicator">⇅</span>
											</th>
											<th style="width: 15%;">B2B Link</th>
											<th style="width: 13%;">Actions</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $missing_products['products'] as $index => $product ) : ?>
										<tr class="missing-product-row" data-sku="<?php echo esc_attr( strtolower( $product['sku'] ) ); ?>" data-title="<?php echo esc_attr( strtolower( $product['title'] ) ); ?>">
											<td><?php echo number_format( $index + 1 ); ?></td>
											<td><strong style="font-family: monospace; color: #2271b1;"><?php echo esc_html( $product['sku'] ?: 'N/A' ); ?></strong></td>
											<td><?php echo esc_html( $product['title'] ); ?></td>
											<td>
												<?php if ( $product['price'] > 0 ) : ?>
													<strong><?php echo number_format( $product['price'], 2, '.', '' ); ?> EUR</strong>
												<?php else : ?>
													<span style="color: #646970;">N/A</span>
												<?php endif; ?>
											</td>
											<td>
												<?php
												$b2b_url = isset( $product['b2b_product_url'] ) && ! empty( $product['b2b_product_url'] ) ? $product['b2b_product_url'] : '';
												if ( ! empty( $b2b_url ) ) : ?>
													<a href="<?php echo esc_url( $b2b_url ); ?>" target="_blank" class="button button-small button-secondary" style="text-decoration: none; font-size: 11px;">
														View B2B →
													</a>
												<?php else : ?>
													<span style="color: #646970; font-size: 11px;">Not available</span>
												<?php endif; ?>
											</td>
											<td>
												<?php
												$add_product_url = admin_url( 'post-new.php?post_type=product' );
												// Add product data as URL parameters for pre-filling
												if ( ! empty( $product['sku'] ) ) {
													$add_product_url .= '&sku=' . urlencode( $product['sku'] );
												}
												if ( ! empty( $product['title'] ) ) {
													$add_product_url .= '&post_title=' . urlencode( $product['title'] );
												}
												// Add calculated price if available
												if ( isset( $product['calculated_price'] ) && $product['calculated_price'] > 0 ) {
													$add_product_url .= '&regular_price=' . urlencode( number_format( $product['calculated_price'], 2, '.', '' ) );
												}
												?>
												<a href="<?php echo esc_url( $add_product_url ); ?>" 
												   target="_blank" 
												   class="button button-small add-product-btn" 
												   style="text-decoration: none; font-size: 11px;"
												   data-b2b-url="<?php echo esc_attr( $b2b_url ); ?>"
												   onclick="
												   <?php if ( ! empty( $b2b_url ) ) : ?>
												   setTimeout(function() { window.open('<?php echo esc_js( $b2b_url ); ?>', '_blank'); }, 100);
												   <?php endif; ?>
												   return true;">
													Add Product
												</a>
											</td>
										</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								
								<script>
								var currentSortColumn = -1;
								var currentSortDirection = 'asc';
								
								function filterMissingProducts() {
									var input = document.getElementById('missing-products-search');
									var filter = input.value.toLowerCase();
									var table = document.getElementById('missing-products-table');
									var rows = table.getElementsByClassName('missing-product-row');
									var visibleCount = 0;
									
									for (var i = 0; i < rows.length; i++) {
										var row = rows[i];
										var sku = row.getAttribute('data-sku') || '';
										var title = row.getAttribute('data-title') || '';
										
										if (sku.indexOf(filter) > -1 || title.indexOf(filter) > -1) {
											row.style.display = '';
											visibleCount++;
										} else {
											row.style.display = 'none';
										}
									}
									
									// Update count if needed
									var countElement = document.getElementById('filtered-count');
									if (countElement) {
										countElement.textContent = visibleCount;
									}
								}
								
								function sortTable(columnIndex, dataType) {
									var table = document.getElementById('missing-products-table');
									var tbody = table.getElementsByTagName('tbody')[0];
									var rows = Array.from(tbody.getElementsByClassName('missing-product-row'));
									
									// Determine sort direction
									if (currentSortColumn === columnIndex) {
										currentSortDirection = currentSortDirection === 'asc' ? 'desc' : 'asc';
									} else {
										currentSortColumn = columnIndex;
										currentSortDirection = 'asc';
									}
									
									// Update sort indicators
									var headers = table.getElementsByTagName('th');
									for (var i = 0; i < headers.length; i++) {
										var indicator = headers[i].querySelector('.sort-indicator');
										if (indicator) {
											if (i === columnIndex) {
												indicator.textContent = currentSortDirection === 'asc' ? ' ↑' : ' ↓';
											} else {
												indicator.textContent = ' ⇅';
											}
										}
									}
									
									// Sort rows
									rows.sort(function(a, b) {
										var aValue, bValue;
										
										if (columnIndex === 1) {
											// SKU
											aValue = a.cells[1].textContent.trim();
											bValue = b.cells[1].textContent.trim();
										} else if (columnIndex === 2) {
											// Product Title
											aValue = a.cells[2].textContent.trim();
											bValue = b.cells[2].textContent.trim();
										} else if (columnIndex === 3) {
											// B2B Price
											var aText = a.cells[3].textContent.trim();
											var bText = b.cells[3].textContent.trim();
											aValue = parseFloat(aText.replace(/[^0-9.,]/g, '').replace(',', '.')) || 0;
											bValue = parseFloat(bText.replace(/[^0-9.,]/g, '').replace(',', '.')) || 0;
										} else {
											return 0;
										}
										
										if (dataType === 'number') {
											return currentSortDirection === 'asc' ? aValue - bValue : bValue - aValue;
										} else {
											// String comparison
											if (aValue < bValue) {
												return currentSortDirection === 'asc' ? -1 : 1;
											}
											if (aValue > bValue) {
												return currentSortDirection === 'asc' ? 1 : -1;
											}
											return 0;
										}
									});
									
									// Re-append sorted rows
									rows.forEach(function(row) {
										tbody.appendChild(row);
									});
								}
								</script>
								
								<div style="margin-top: 15px; padding: 12px; background: #fff3cd; border-left: 4px solid #ffc107;">
									<p style="margin: 0; font-size: 13px;">
										<strong>ℹ️ Note:</strong> These products are for reference only. You can manually add them to your WooCommerce store using the "Add Product" button. 
										The button will open both the product creation form (pre-filled with SKU and title) and the B2B product page for reference.
									</p>
								</div>
							<?php else : ?>
								<div style="padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">
									<p style="margin: 0; color: #155724; font-weight: bold;">
										✅ All B2B products are already in your store!
									</p>
								</div>
							<?php endif; ?>
						<?php else : ?>
							<p>Please complete previous steps first to load products data.</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
			
		</div>
		
		<script>
		// Make ajaxurl available
		var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
		// Get nonce from hidden input
		var downloadNonce = document.getElementById('download-nonce') ? document.getElementById('download-nonce').value : '';
		
		function toggleAccordion(step) {
			// Close all accordion items
			document.querySelectorAll('.f-bearpaw-accordion-item').forEach(function(item) {
				item.classList.remove('active');
				var toggle = item.querySelector('.f-bearpaw-accordion-toggle');
				if (toggle) {
					toggle.textContent = '+';
				}
			});
			
			// Open selected step
			var targetItem = document.querySelector('.f-bearpaw-accordion-item:nth-child(' + step + ')');
			if (targetItem && !targetItem.classList.contains('disabled')) {
				targetItem.classList.add('active');
				var toggle = targetItem.querySelector('.f-bearpaw-accordion-toggle');
				if (toggle) {
					toggle.textContent = '−';
				}
			}
		}
		
		function handleStep1Save(event) {
			// Form will submit normally, but we'll handle the accordion on page reload
			// Add a hidden input to indicate step 1 was saved
			var form = document.getElementById('step1-form');
			if (form) {
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = 'step1_saved';
				input.value = '1';
				form.appendChild(input);
			}
		}
		
		function handleStep2Download(event) {
			event.preventDefault();
			
			var form = document.getElementById('step2-form');
			if (!form) return;
			
			var button = form.querySelector('button[type="submit"]');
			if (button) {
				button.disabled = true;
				button.textContent = '⏳ Starting download...';
			}
			
			// Clear previous progress
			var existingContainer = document.getElementById('download-progress-container');
			if (existingContainer) {
				existingContainer.remove();
			}
			
			// Create progress container
			var progressContainer = document.createElement('div');
			progressContainer.id = 'download-progress-container';
			progressContainer.style.cssText = 'margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04);';
			progressContainer.innerHTML = '<h3 style="margin-top: 0;">Download Progress</h3>' +
				'<div id="b2b-progress" style="margin-bottom: 20px;"></div>' +
				'<div id="retail-progress" style="margin-bottom: 20px;"></div>' +
				'<div class="f-bearpaw-progress-bar"><div class="f-bearpaw-progress-fill" id="overall-progress-bar" style="width: 0%;"></div></div>' +
				'<p id="overall-progress-text" style="margin-top: 10px; color: #646970;">Initializing...</p>';
			form.insertAdjacentElement('afterend', progressContainer);
			
			// Get nonce from form or hidden input
			var nonce = downloadNonce || (form.querySelector('input[name="_wpnonce"]') ? form.querySelector('input[name="_wpnonce"]').value : '');
			
			if (!nonce) {
				alert('Security token missing. Please refresh the page and try again.');
				if (button) button.disabled = false;
				return;
			}
			
			// Clear progress via AJAX first
			jQuery.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'f_bearpaw_clear_progress',
					nonce: nonce,
					source: '' // Clear both
				},
				success: function() {
					// Start downloading both sources sequentially (B2B first, then Retail)
					downloadSource('B2B', function() {
						downloadSource('Retail', function() {
							// Both downloads completed
							if (button) {
								button.textContent = '✅ Download Completed';
							}
							document.getElementById('overall-progress-text').textContent = '✅ All downloads completed! Page will reload in 3 seconds...';
							setTimeout(function() {
								window.location.href = '<?php echo add_query_arg( 'open_step', '3', admin_url( 'admin.php?page=f-bearpaw-dealer' ) ); ?>';
							}, 3000);
						});
					});
				},
				error: function() {
					// Continue anyway
					downloadSource('B2B', function() {
						downloadSource('Retail', function() {
							if (button) {
								button.textContent = '✅ Download Completed';
							}
							document.getElementById('overall-progress-text').textContent = '✅ All downloads completed! Page will reload in 3 seconds...';
							setTimeout(function() {
								window.location.href = '<?php echo add_query_arg( 'open_step', '3', admin_url( 'admin.php?page=f-bearpaw-dealer' ) ); ?>';
							}, 3000);
						});
					});
				}
			});
		}
		
		function downloadSource(source, callback) {
			var progressDiv = document.getElementById(source.toLowerCase() + '-progress');
			progressDiv.innerHTML = '<p><strong>' + source + ':</strong> <span id="' + source.toLowerCase() + '-status">Starting...</span></p>' +
				'<div class="f-bearpaw-progress-bar"><div class="f-bearpaw-progress-fill" id="' + source.toLowerCase() + '-progress-bar" style="width: 0%;"></div></div>' +
				'<p id="' + source.toLowerCase() + '-progress-text" style="font-size: 12px; color: #646970; margin-top: 5px;">Preparing...</p>';
			
			var startPage = 1;
			var totalProducts = 0;
			var totalPages = 0;
			
			function downloadChunk() {
				var statusEl = document.getElementById(source.toLowerCase() + '-status');
				var progressBar = document.getElementById(source.toLowerCase() + '-progress-bar');
				var progressText = document.getElementById(source.toLowerCase() + '-progress-text');
				var overallProgressBar = document.getElementById('overall-progress-bar');
				var overallProgressText = document.getElementById('overall-progress-text');
				
				var nonce = downloadNonce || (document.getElementById('download-nonce') ? document.getElementById('download-nonce').value : '') || 
					(document.querySelector('input[name="_wpnonce"]') ? document.querySelector('input[name="_wpnonce"]').value : '');
				
				jQuery.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'f_bearpaw_download_chunk',
						nonce: nonce,
						source: source,
						start_page: startPage
					},
					dataType: 'json',
					success: function(response) {
						if (response.success && response.data) {
							var data = response.data;
							totalProducts = data.total_products || 0;
							totalPages = data.total_pages_downloaded || 0;
							
							// Update source-specific progress (estimate based on pages)
							var estimatedTotalPages = data.has_more ? totalPages + 10 : totalPages; // Rough estimate
							var sourceProgress = estimatedTotalPages > 0 ? Math.min(90, (totalPages / estimatedTotalPages) * 100) : 50;
							if (progressBar) progressBar.style.width = sourceProgress + '%';
							
							if (statusEl) {
								statusEl.textContent = data.has_more ? 
									'Downloading... ' + totalPages + ' pages, ' + totalProducts.toLocaleString() + ' products' :
									'✅ Completed: ' + totalPages + ' pages, ' + totalProducts.toLocaleString() + ' products';
							}
							if (progressText) {
								progressText.textContent = 'Pages downloaded: ' + totalPages + ' | Products: ' + totalProducts.toLocaleString() + 
									(data.has_more ? ' | Loading more...' : ' | Finalizing...');
							}
							
							// Update overall progress (B2B is 50%, Retail is 50%)
							var overallProgress = 0;
							if (source === 'B2B') {
								overallProgress = data.has_more ? Math.min(45, sourceProgress * 0.5) : 50;
							} else {
								overallProgress = data.has_more ? Math.min(95, 50 + (sourceProgress * 0.5)) : 100;
							}
							if (overallProgressBar) overallProgressBar.style.width = overallProgress + '%';
							
							if (data.has_more) {
								// Continue with next chunk
								startPage = data.current_page;
								setTimeout(downloadChunk, 500); // Small delay between chunks
							} else {
								// Finalize this source
								if (progressBar) progressBar.style.width = '100%';
								finalizeDownload(source, function() {
									if (callback) callback();
								});
							}
						} else {
							// Error occurred
							if (statusEl) statusEl.textContent = '❌ Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error');
							if (progressText) progressText.textContent = 'Error occurred. Please try again.';
							if (progressBar) progressBar.style.background = '#d63638';
							if (overallProgressText) overallProgressText.textContent = '❌ Download failed for ' + source;
							if (callback) callback(); // Continue anyway
						}
					},
					error: function(xhr, status, error) {
						var statusEl = document.getElementById(source.toLowerCase() + '-status');
						var progressText = document.getElementById(source.toLowerCase() + '-progress-text');
						var overallProgressText = document.getElementById('overall-progress-text');
						var errorMsg = error;
						
						// Try to parse error response
						if (xhr.responseText) {
							try {
								var errorResponse = JSON.parse(xhr.responseText);
								if (errorResponse.data && errorResponse.data.message) {
									errorMsg = errorResponse.data.message;
								}
							} catch (e) {
								// If response is HTML, extract error message
								if (xhr.responseText.indexOf('<') !== -1) {
									errorMsg = 'Server returned HTML instead of JSON. This usually means a PHP error occurred. Please check server logs.';
								}
							}
						}
						
						if (statusEl) statusEl.textContent = '❌ Error: ' + errorMsg;
						if (progressText) progressText.textContent = 'AJAX error occurred: ' + status + ' - ' + errorMsg;
						if (overallProgressText) overallProgressText.textContent = '❌ Download failed for ' + source + ': ' + errorMsg;
						if (callback) callback(); // Continue anyway
					}
				});
			}
			
			// Start downloading chunks
			downloadChunk();
		}
		
		function finalizeDownload(source, callback) {
			var nonce = downloadNonce || (document.getElementById('download-nonce') ? document.getElementById('download-nonce').value : '') || 
				(document.querySelector('input[name="_wpnonce"]') ? document.querySelector('input[name="_wpnonce"]').value : '');
			
			jQuery.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'f_bearpaw_finalize_download',
					nonce: nonce,
					source: source
				},
				dataType: 'json',
				success: function(response) {
					if (response.success && response.data) {
						var progressText = document.getElementById(source.toLowerCase() + '-progress-text');
						if (progressText) {
							progressText.textContent = '✅ Saved: ' + response.data.products_count.toLocaleString() + ' products to file';
						}
					}
					if (callback) callback();
				},
				error: function() {
					if (callback) callback(); // Continue anyway
				}
			});
		}
		
		// Auto-open accordion on page load based on URL parameter or completed steps
		document.addEventListener('DOMContentLoaded', function() {
			var urlParams = new URLSearchParams(window.location.search);
			var openStep = urlParams.get('open_step');
			if (openStep) {
				toggleAccordion(parseInt(openStep));
			}
		});
		</script>
		<?php
	}
}

// Initialize the plugin
new F_Bearpaw_Dealer();

