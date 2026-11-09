<?php
/**
 * Download Progress Tracker
 *
 * Manages progress state for chunked downloads using transients and temp files.
 */

namespace FPro\BearpawDealer\Sync;

use FPro\BearpawDealer\Utilities\FileStorage;

/**
 * Tracks download progress for a single source (B2B or Retail).
 */
class DownloadProgress {
	/** @var string */
	public string $source;

	/** @var int Current page being processed. */
	public int $current_page = 1;

	/** @var int Total pages downloaded so far. */
	public int $total_pages_downloaded = 0;

	/** @var int Total products accumulated. */
	public int $total_products = 0;

	/** @var bool Whether more pages are expected. */
	public bool $has_more = true;

	/** @var int Unix timestamp of last update. */
	public int $updated_at = 0;

	/** @var string|null Error message if failed. */
	public ?string $error = null;

	/**
	 * Create from source name.
	 *
	 * @param string $source Source name ('B2B' or 'Retail').
	 * @return self
	 */
	public static function create( string $source ): self {
		$progress = new self();
		$progress->source = $source;
		$progress->updated_at = time();
		return $progress;
	}

	/**
	 * Check if progress is stale (older than 1 hour).
	 *
	 * @return bool
	 */
	public function is_stale(): bool {
		return ( time() - $this->updated_at ) > 3600;
	}

	/**
	 * Check if download is complete.
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		return ! $this->has_more && $this->error === null;
	}

	/**
	 * Check if download has failed.
	 *
	 * @return bool
	 */
	public function has_failed(): bool {
		return $this->error !== null;
	}

	/**
	 * Convert to array for storage.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'source'                   => $this->source,
			'current_page'             => $this->current_page,
			'total_pages_downloaded'   => $this->total_pages_downloaded,
			'total_products'           => $this->total_products,
			'has_more'                 => $this->has_more,
			'updated_at'               => $this->updated_at,
			'error'                    => $this->error,
		];
	}

	/**
	 * Create from stored array.
	 *
	 * @param array $data
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$progress = new self();
		$progress->source                   = $data['source'] ?? '';
		$progress->current_page             = $data['current_page'] ?? 1;
		$progress->total_pages_downloaded   = $data['total_pages_downloaded'] ?? 0;
		$progress->total_products           = $data['total_products'] ?? 0;
		$progress->has_more                 = $data['has_more'] ?? true;
		$progress->updated_at               = $data['updated_at'] ?? time();
		$progress->error                    = $data['error'] ?? null;
		return $progress;
	}
}

/**
 * Progress Tracker - manages progress state using transients and temp files.
 */
class ProgressTracker {
	/** @var string Transient prefix. */
	private const TRANSIENT_PREFIX = 'f_bearpaw_download_progress_';

	/** @var FileStorage */
	private FileStorage $file_storage;

	/**
	 * Constructor.
	 *
	 * @param FileStorage $file_storage Temp file storage.
	 */
	public function __construct( FileStorage $file_storage ) {
		$this->file_storage = $file_storage;
	}

	/**
	 * Get transient key for a source.
	 *
	 * @param string $source Source name.
	 * @return string
	 */
	private function get_transient_key( string $source ): string {
		return self::TRANSIENT_PREFIX . strtolower( $source );
	}

	/**
	 * Get temp file path for a source.
	 *
	 * @param string $source Source name.
	 * @return string
	 */
	private function get_temp_file( string $source ): string {
		return $this->file_storage->get_temp_path( strtolower( $source ) . '_products_temp.json' );
	}

	/**
	 * Get final file path for a source.
	 *
	 * @param string $source Source name.
	 * @return string
	 */
	private function get_final_file( string $source ): string {
		$filename = $source === 'B2B' ? 'b2b_products.json' : 'retail_products.json';
		return $this->file_storage->get_temp_path( $filename );
	}

	/**
	 * Load progress for a source.
	 *
	 * @param string $source Source name.
	 * @return DownloadProgress
	 */
	public function get_progress( string $source ): DownloadProgress {
		$transient_key = $this->get_transient_key( $source );
		$data = get_transient( $transient_key );

		if ( $data === false ) {
			return DownloadProgress::create( $source );
		}

		return DownloadProgress::from_array( $data );
	}

	/**
	 * Save progress for a source.
	 *
	 * @param DownloadProgress $progress Progress to save.
	 * @return bool Success.
	 */
	public function save_progress( DownloadProgress $progress ): bool {
		$transient_key = $this->get_transient_key( $progress->source );
		$progress->updated_at = time();
		return set_transient( $transient_key, $progress->to_array(), 3600 );
	}

	/**
	 * Clear progress for a source.
	 *
	 * @param string $source Source name.
	 */
	public function clear_progress( string $source ): void {
		$transient_key = $this->get_transient_key( $source );
		delete_transient( $transient_key );

		$temp_file = $this->get_temp_file( $source );
		if ( file_exists( $temp_file ) ) {
			@unlink( $temp_file );
		}
	}

	/**
	 * Clear progress for all sources.
	 */
	public function clear_all(): void {
		$this->clear_progress( 'B2B' );
		$this->clear_progress( 'Retail' );
	}

	/**
	 * Append products to temp file.
	 *
	 * @param string $source Source name.
	 * @param array $products Products to append.
	 * @return bool Success.
	 */
	public function append_products( string $source, array $products ): bool {
		$temp_file = $this->get_temp_file( $source );
		$existing = [];

		if ( file_exists( $temp_file ) ) {
			$content = file_get_contents( $temp_file );
			$data = json_decode( $content, true );
			if ( $data && isset( $data['products'] ) ) {
				$existing = $data['products'];
			}
		}

		$merged = array_merge( $existing, $products );
		return $this->file_storage->save_json( basename( $temp_file ), [ 'products' => $merged ] );
	}

	/**
	 * Get accumulated products from temp file.
	 *
	 * @param string $source Source name.
	 * @return array Products array.
	 */
	public function get_accumulated_products( string $source ): array {
		$temp_file = $this->get_temp_file( $source );

		if ( ! file_exists( $temp_file ) ) {
			return [];
		}

		$content = file_get_contents( $temp_file );
		$data = json_decode( $content, true );

		if ( ! $data || ! isset( $data['products'] ) ) {
			return [];
		}

		return $data['products'];
	}

	/**
	 * Finalize download - move temp file to final location.
	 *
	 * @param string $source Source name.
	 * @return array Final result with count and file path.
	 */
	public function finalize( string $source ): array {
		$temp_file = $this->get_temp_file( $source );
		$final_file = $this->get_final_file( $source );

		if ( ! file_exists( $temp_file ) ) {
			return [
				'success' => false,
				'error'   => 'No products to finalize. Temp file not found.',
			];
		}

		$content = file_get_contents( $temp_file );
		$data = json_decode( $content, true );

		if ( ! $data || ! isset( $data['products'] ) ) {
			return [
				'success' => false,
				'error'   => 'Temp file exists but contains no products.',
			];
		}

		$products = $data['products'];
		$count = count( $products );

		// Get progress metadata BEFORE clearing transient
		$progress = $this->get_progress( $source );

		// Save to final location
		$success = $this->file_storage->save_json( basename( $final_file ), [ 'products' => $products ] );

		if ( ! $success ) {
			return [
				'success' => false,
				'error'   => 'Failed to save final JSON file.',
			];
		}

		// Clean up temp file
		@unlink( $temp_file );

		// Clear progress transient
		$transient_key = $this->get_transient_key( $source );
		delete_transient( $transient_key );

		return [
			'success'               => true,
			'products_count'        => $count,
			'file_path'             => $final_file,
			'total_pages_downloaded'=> $progress->total_pages_downloaded,
		];
	}

	/**
	 * Check if final file exists for source.
	 *
	 * @param string $source Source name.
	 * @return bool
	 */
	public function has_final_file( string $source ): bool {
		return file_exists( $this->get_final_file( $source ) );
	}

	/**
	 * Load final products from file.
	 *
	 * @param string $source Source name.
	 * @return array|null Products array or null if not found.
	 */
	public function load_final_products( string $source ): ?array {
		$final_file = $this->get_final_file( $source );

		if ( ! file_exists( $final_file ) ) {
			return null;
		}

		$content = file_get_contents( $final_file );
		$data = json_decode( $content, true );

		if ( ! $data || ! isset( $data['products'] ) ) {
			return null;
		}

		return $data['products'];
	}
}