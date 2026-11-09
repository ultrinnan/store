<?php
/**
 * File Storage Utility
 *
 * Handles temporary file operations for the plugin.
 */

namespace FPro\BearpawDealer\Utilities;

/**
 * Manages temporary file storage in wp-content/uploads.
 */
class FileStorage {
	/** @var string Base temp directory path. */
	private string $temp_dir;

	/** @var string Temp directory name. */
	private const TEMP_DIR_NAME = 'f-bearpaw-dealer-temp';

	/**
	 * Constructor.
	 *
	 * @param string|null $custom_dir Custom temp directory (for testing).
	 */
	public function __construct( ?string $custom_dir = null ) {
		if ( $custom_dir ) {
			$this->temp_dir = $custom_dir;
		} else {
			$upload_dir = wp_upload_dir();
			$this->temp_dir = $upload_dir['basedir'] . '/' . self::TEMP_DIR_NAME;
		}

		$this->ensure_directory();
	}

	/**
	 * Ensure temp directory exists.
	 */
	private function ensure_directory(): void {
		if ( ! file_exists( $this->temp_dir ) ) {
			wp_mkdir_p( $this->temp_dir );
		}
	}

	/**
	 * Get full path for a temp file.
	 *
	 * @param string $filename Filename.
	 * @return string Full path.
	 */
	public function get_temp_path( string $filename ): string {
		return $this->temp_dir . '/' . sanitize_file_name( $filename );
	}

	/**
	 * Save data as JSON to temp file.
	 *
	 * @param string $filename Filename.
	 * @param array  $data Data to encode.
	 * @return bool Success.
	 */
	public function save_json( string $filename, array $data ): bool {
		$file_path = $this->get_temp_path( $filename );
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		if ( $json === false ) {
			return false;
		}

		return file_put_contents( $file_path, $json ) !== false;
	}

	/**
	 * Load JSON from temp file.
	 *
	 * @param string $filename Filename.
	 * @return array|null Decoded data or null on failure.
	 */
	public function load_json( string $filename ): ?array {
		$file_path = $this->get_temp_path( $filename );

		if ( ! file_exists( $file_path ) ) {
			return null;
		}

		$content = file_get_contents( $file_path );
		if ( $content === false ) {
			return null;
		}

		$data = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return $data;
	}

	/**
	 * Delete a temp file.
	 *
	 * @param string $filename Filename.
	 * @return bool Success.
	 */
	public function delete( string $filename ): bool {
		$file_path = $this->get_temp_path( $filename );
		if ( file_exists( $file_path ) ) {
			return @unlink( $file_path );
		}
		return true;
	}

	/**
	 * Clear all temp files.
	 */
	public function clear_all(): void {
		if ( ! file_exists( $this->temp_dir ) ) {
			return;
		}

		$files = glob( $this->temp_dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				@unlink( $file );
			}
		}
	}

	/**
	 * Get temp directory path.
	 *
	 * @return string
	 */
	public function get_temp_dir(): string {
		return $this->temp_dir;
	}

	/**
	 * Check if a temp file exists.
	 *
	 * @param string $filename Filename.
	 * @return bool
	 */
	public function exists( string $filename ): bool {
		return file_exists( $this->get_temp_path( $filename ) );
	}
}