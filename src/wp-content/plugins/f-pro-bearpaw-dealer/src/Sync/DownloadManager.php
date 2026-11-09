<?php
/**
 * Download Manager
 *
 * Orchestrates chunked downloads from Bearpaw APIs with progress tracking.
 */

namespace FPro\BearpawDealer\Sync;

use FPro\BearpawDealer\API\BearpawClient;
use FPro\BearpawDealer\API\CurlParser;
use FPro\BearpawDealer\Utilities\FileStorage;

/**
 * Manages the complete download process for B2B and Retail sources.
 */
class DownloadManager {
	/** @var int Pages per chunk. */
	private const CHUNK_SIZE = 5;

	/** @var int Delay between chunks in microseconds. */
	private const CHUNK_DELAY_US = 500000; // 0.5 seconds

	/** @var ProgressTracker */
	private ProgressTracker $progress_tracker;

	/** @var FileStorage */
	private FileStorage $file_storage;

	/** @var callable|null Progress callback for UI updates. */
	private $progress_callback = null;

	/**
	 * Constructor.
	 *
	 * @param ProgressTracker $progress_tracker Progress tracker instance.
	 * @param FileStorage     $file_storage     File storage instance.
	 */
	public function __construct( ProgressTracker $progress_tracker, FileStorage $file_storage ) {
		$this->progress_tracker = $progress_tracker;
		$this->file_storage     = $file_storage;
	}

	/**
	 * Set progress callback for real-time updates.
	 *
	 * @param callable $callback Function( string $source, array $progress_data ).
	 * @return self
	 */
	public function set_progress_callback( callable $callback ): self {
		$this->progress_callback = $callback;
		return $this;
	}

	/**
	 * Download all products for a source.
	 *
	 * @param string $source       Source name ('B2B' or 'Retail').
	 * @param string $curl_command cURL command from settings.
	 * @return array Result with success, products_count, file_path.
	 */
	public function download_source( string $source, string $curl_command ): array {
		// Parse cURL command
		$parsed = CurlParser::parse( $curl_command );

		if ( ! $parsed || ! CurlParser::validate_for_bearpaw( $parsed ) ) {
			return [
				'success' => false,
				'error'   => 'Invalid cURL command for ' . $source,
			];
		}

		// Create BearpawClient
		$client = $source === 'B2B'
			? BearpawClient::for_b2b( $parsed['cookies'], $parsed['headers'] )
			: BearpawClient::for_retail( $parsed['cookies'], $parsed['headers'] );

		// Test connection first
		$connection_test = $client->test_connection();
		if ( ! $connection_test['accessible'] ) {
			return [
				'success' => false,
				'error'   => $source . ' API not accessible: ' . $connection_test['error'],
			];
		}

		// Clear previous progress
		$this->progress_tracker->clear_progress( $source );

		// Initialize progress
		$progress = DownloadProgress::create( $source );
		$this->progress_tracker->save_progress( $progress );

		// Download in chunks
		$page = 1;
		$has_more = true;

		while ( $has_more ) {
			$chunk_result = $this->download_chunk( $client, $source, $page );

			if ( ! $chunk_result['success'] ) {
				$progress->error = $chunk_result['error'];
				$this->progress_tracker->save_progress( $progress );
				return $chunk_result;
			}

			$progress->current_page           = $chunk_result['next_page'];
			$progress->total_pages_downloaded = $chunk_result['total_pages'];
			$progress->total_products         = $chunk_result['total_products'];
			$progress->has_more               = $chunk_result['has_more'];
			$progress->updated_at             = time();

			$this->progress_tracker->save_progress( $progress );

			// Call progress callback if set
			if ( $this->progress_callback ) {
				( $this->progress_callback )( $source, $progress->to_array() );
			}

			if ( ! $chunk_result['has_more'] ) {
				$has_more = false;
			} else {
				$page = $chunk_result['next_page'];
				usleep( self::CHUNK_DELAY_US );
			}
		}

		// Finalize
		$final_result = $this->progress_tracker->finalize( $source );

		if ( $this->progress_callback ) {
			( $this->progress_callback )( $source, [
				...$progress->to_array(),
				'finalized' => true,
				'count'     => $final_result['products_count'] ?? 0,
			] );
		}

		return $final_result;
	}

	/**
	 * Download a single chunk (multiple pages).
	 *
	 * @param BearpawClient $client   API client.
	 * @param string        $source   Source name.
	 * @param int           $start_page Starting page number.
	 * @return array Chunk result.
	 */
	private function download_chunk( BearpawClient $client, string $source, int $start_page ): array {
		$all_products = [];
		$pages_downloaded = 0;
		$last_page_count = 0;
		$has_more = false;

		for ( $i = 0; $i < self::CHUNK_SIZE; $i++ ) {
			$page = $start_page + $i;

			try {
				$page_data = $client->get_page( $page );
			} catch ( \Exception $e ) {
				return [
					'success' => false,
					'error'   => 'API error on page ' . $page . ': ' . $e->getMessage(),
				];
			}

			if ( $page_data === false || ! isset( $page_data['products'] ) ) {
				break;
			}

			$products = $page_data['products'];

			if ( empty( $products ) ) {
				break;
			}

			$all_products = array_merge( $all_products, $products );
			$pages_downloaded++;
			$last_page_count = count( $products );

			if ( $last_page_count < BearpawClient::PAGE_LIMIT ) {
				$has_more = false;
				break;
			}
		}

		if ( empty( $all_products ) ) {
			return [
				'success' => true,
				'next_page' => $start_page,
				'total_pages' => 0,
				'total_products' => 0,
				'has_more' => false,
			];
		}

		// Save chunk to temp file
		$success = $this->progress_tracker->append_products( $source, $all_products );

		if ( ! $success ) {
			return [
				'success' => false,
				'error'   => 'Failed to save products to temp file.',
			];
		}

		// Get total count from temp file
		$total_products = count( $this->progress_tracker->get_accumulated_products( $source ) );

		return [
			'success'           => true,
			'next_page'         => $start_page + $pages_downloaded,
			'total_pages'       => $start_page - 1 + $pages_downloaded,
			'total_products'    => $total_products,
			'has_more'          => $last_page_count === BearpawClient::PAGE_LIMIT && $pages_downloaded === self::CHUNK_SIZE,
		];
	}

	/**
	 * Download both sources sequentially.
	 *
	 * @param string $b2b_curl    B2B cURL command.
	 * @param string $retail_curl Retail cURL command.
	 * @return array Combined results.
	 */
	public function download_both( string $b2b_curl, string $retail_curl ): array {
		$b2b_result = $this->download_source( 'B2B', $b2b_curl );
		$retail_result = $this->download_source( 'Retail', $retail_curl );

		return [
			'b2b'   => $b2b_result,
			'retail' => $retail_result,
			'success' => $b2b_result['success'] && $retail_result['success'],
		];
	}

	/**
	 * Get current progress for a source.
	 *
	 * @param string $source Source name.
	 * @return DownloadProgress
	 */
	public function get_progress( string $source ): DownloadProgress {
		return $this->progress_tracker->get_progress( $source );
	}

	/**
	 * Clear progress for a source.
	 *
	 * @param string $source Source name.
	 */
	public function clear_progress( string $source ): void {
		$this->progress_tracker->clear_progress( $source );
	}

	/**
	 * Clear all progress.
	 */
	public function clear_all(): void {
		$this->progress_tracker->clear_all();
	}
}