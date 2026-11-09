<?php
/**
 * cURL Command Parser
 *
 * Robustly parses cURL commands from browser DevTools to extract
 * URL, cookies, and headers for API authentication.
 */

namespace FPro\BearpawDealer\API;

/**
 * Parses cURL command strings into structured data.
 */
class CurlParser {
	/**
	 * Parse a cURL command and extract URL, cookies, and headers.
	 *
	 * Supports various cURL formats from browser DevTools "Copy as cURL":
	 * - curl 'url' -H 'header: value' -b 'cookies'
	 * - curl "url" -H "header: value" --cookie "cookies"
	 * - curl url -H 'header: value' (no quotes)
	 *
	 * @param string $curl_command Full cURL command string.
	 * @return array|false Parsed data or false on failure.
	 *         Structure: ['url' => string, 'cookies' => string, 'headers' => array]
	 */
	public static function parse( string $curl_command ): array|false {
		if ( empty( trim( $curl_command ) ) ) {
			return false;
		}

		$command = trim( $curl_command );

		// Remove leading 'curl' command
		if ( ! preg_match( '/^curl\s+(.+)$/i', $command, $matches ) ) {
			return false;
		}
		$command = $matches[1];

		$result = [
			'url'     => '',
			'cookies' => '',
			'headers' => [],
		];

		// Parse using a state machine approach for robustness
		$tokens = self::tokenize_curl_command( $command );

		$i = 0;
		while ( $i < count( $tokens ) ) {
			$token = $tokens[ $i ];

			if ( $token === '-H' || $token === '--header' ) {
				$i++;
				if ( $i < count( $tokens ) ) {
					self::parse_header( $tokens[ $i ], $result['headers'] );
				}
			} elseif ( $token === '-b' || $token === '--cookie' ) {
				$i++;
				if ( $i < count( $tokens ) ) {
					$result['cookies'] = self::strip_quotes( $tokens[ $i ] );
				}
			} elseif ( $token === '--compressed' || $token === '-s' || $token === '--silent' || $token === '-v' || $token === '--verbose' ) {
				// Ignore common flags
			} elseif ( $result['url'] === '' && ! self::is_flag( $token ) ) {
				// First non-flag token is the URL
				$result['url'] = self::strip_quotes( $token );
			}
			// Ignore unknown flags

			$i++;
		}

		// If no cookies found in -b/--cookie, check for Cookie header
		if ( empty( $result['cookies'] ) ) {
			foreach ( $result['headers'] as $name => $value ) {
				if ( strtolower( $name ) === 'cookie' ) {
					$result['cookies'] = $value;
					unset( $result['headers'][ $name ] );
					break;
				}
			}
		}

		// Validate required fields
		if ( empty( $result['url'] ) ) {
			return false;
		}

		return $result;
	}

	/**
	 * Tokenize cURL command respecting quoted strings.
	 *
	 * @param string $command Command string (without 'curl').
	 * @return array Array of tokens.
	 */
	private static function tokenize_curl_command( string $command ): array {
		$tokens    = [];
		$current   = '';
		$in_single = false;
		$in_double = false;
		$escaped   = false;

		for ( $i = 0; $i < strlen( $command ); $i++ ) {
			$char = $command[ $i ];

			if ( $escaped ) {
				$current .= $char;
				$escaped = false;
				continue;
			}

			if ( $char === '\\' ) {
				$escaped = true;
				continue;
			}

			if ( $char === '\'' && ! $in_double ) {
				$in_single = ! $in_single;
				$current  .= $char;
				continue;
			}

			if ( $char === '"' && ! $in_single ) {
				$in_double = ! $in_double;
				$current  .= $char;
				continue;
			}

			if ( $char === ' ' && ! $in_single && ! $in_double ) {
				if ( $current !== '' ) {
					$tokens[] = $current;
					$current  = '';
				}
				continue;
			}

			$current .= $char;
		}

		if ( $current !== '' ) {
			$tokens[] = $current;
		}

		return $tokens;
	}

	/**
	 * Check if token is a flag (starts with - or --).
	 *
	 * @param string $token Token to check.
	 * @return bool
	 */
	private static function is_flag( string $token ): bool {
		return str_starts_with( $token, '-' );
	}

	/**
	 * Strip surrounding quotes from a string.
	 *
	 * @param string $str String potentially wrapped in quotes.
	 * @return string String without surrounding quotes.
	 */
	private static function strip_quotes( string $str ): string {
		$str = trim( $str );
		if ( ( str_starts_with( $str, '"' ) && str_ends_with( $str, '"' ) ) ||
			 ( str_starts_with( $str, "'" ) && str_ends_with( $str, "'" ) ) ) {
			return substr( $str, 1, -1 );
		}
		return $str;
	}

	/**
	 * Parse a header string into name/value and add to headers array.
	 *
	 * @param string $header_string Header string (e.g., "Content-Type: application/json").
	 * @param array  $headers       Headers array to modify by reference.
	 */
	private static function parse_header( string $header_string, array &$headers ): void {
		$header_string = self::strip_quotes( trim( $header_string ) );

		$pos = strpos( $header_string, ':' );
		if ( $pos === false ) {
			return;
		}

		$name  = trim( substr( $header_string, 0, $pos ) );
		$value = trim( substr( $header_string, $pos + 1 ) );

		if ( $name !== '' ) {
			$headers[ $name ] = $value;
		}
	}

	/**
	 * Validate that parsed cURL has required authentication for Bearpaw APIs.
	 *
	 * @param array $parsed Parsed cURL data.
	 * @return bool True if valid for Bearpaw API.
	 */
	public static function validate_for_bearpaw( array $parsed ): bool {
		return ! empty( $parsed['url'] ) &&
			   ! empty( $parsed['cookies'] ) &&
			   ( str_contains( $parsed['url'], 'b2bportal.bearpaw-products.de' ) ||
				 str_contains( $parsed['url'], 'bearpaw-products.com' ) );
	}

	/**
	 * Extract base URL from parsed cURL (without query parameters).
	 *
	 * @param array $parsed Parsed cURL data.
	 * @return string Base URL.
	 */
	public static function extract_base_url( array $parsed ): string {
		return strtok( $parsed['url'], '?' );
	}
}