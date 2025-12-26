<?php
/**
 * Plugin Name: F-PRO Simple WebP Converter
 * Plugin URI: https://fedirko.pro
 * Description: Automatically compresses and converts uploaded images to WEBP format for better performance and smaller file sizes.
 * Version: 1.0.0
 * Author: Serhii Fedirko
 * Author URI: https://fedirko.pro
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: f-pro-webp-converter
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class
 */
class F_WebP_Converter {
	
	/**
	 * Settings option name
	 */
	private $option_name = 'f_webp_converter_settings';
	
	/**
	 * Initialize the plugin
	 */
	public function __construct() {
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'process_image_upload' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}
	
	/**
	 * Get WEBP quality setting
	 *
	 * @return int Quality value (0-100)
	 */
	private function get_webp_quality() {
		$settings = get_option( $this->option_name, array() );
		$quality = isset( $settings['webp_quality'] ) ? intval( $settings['webp_quality'] ) : 85;
		
		// Ensure quality is between 0 and 100
		return max( 0, min( 100, $quality ) );
	}
	
	/**
	 * Add settings page to WordPress Settings menu
	 */
	public function add_settings_page() {
		add_options_page(
			'F-PRO WebP Converter Settings',
			'F-PRO WebP Converter',
			'manage_options',
			'f-webp-converter',
			array( $this, 'render_settings_page' )
		);
	}
	
	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		register_setting(
			'f_webp_converter_settings_group',
			$this->option_name,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
		
		add_settings_section(
			'f_webp_converter_main_section',
			'Conversion Settings',
			array( $this, 'render_section_description' ),
			'f-webp-converter'
		);
		
		add_settings_field(
			'webp_quality',
			'WEBP Quality',
			array( $this, 'render_quality_field' ),
			'f-webp-converter',
			'f_webp_converter_main_section'
		);
	}
	
	/**
	 * Sanitize settings input
	 *
	 * @param array $input Raw settings input
	 * @return array Sanitized settings
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();
		
		if ( isset( $input['webp_quality'] ) ) {
			$quality = intval( $input['webp_quality'] );
			$sanitized['webp_quality'] = max( 0, min( 100, $quality ) );
		}
		
		return $sanitized;
	}
	
	/**
	 * Render section description
	 */
	public function render_section_description() {
		echo '<p>Configure the WEBP conversion settings below.</p>';
		
		// Check WEBP support
		if ( ! $this->is_webp_supported() ) {
			echo '<div class="notice notice-error inline"><p><strong>Warning:</strong> WEBP conversion is not supported on this server. Please ensure PHP GD library with WEBP support is installed.</p></div>';
		} else {
			echo '<div class="notice notice-success inline"><p><strong>✓</strong> WEBP conversion is supported on this server.</p></div>';
		}
	}
	
	/**
	 * Detect and display image sizes and their sources
	 */
	private function render_image_sizes_info() {
		// Get all registered image sizes (WordPress 5.3+)
		if ( function_exists( 'wp_get_registered_image_subsizes' ) ) {
			$image_sizes = wp_get_registered_image_subsizes();
		} else {
			// Fallback for older WordPress versions
			$image_sizes = array();
			$size_names = get_intermediate_image_sizes();
			global $_wp_additional_image_sizes;
			
			foreach ( $size_names as $size_name ) {
				if ( isset( $_wp_additional_image_sizes[ $size_name ] ) ) {
					$image_sizes[ $size_name ] = $_wp_additional_image_sizes[ $size_name ];
				} else {
					// Core sizes
					$width = get_option( $size_name . '_size_w' );
					$height = get_option( $size_name . '_size_h' );
					$crop = get_option( $size_name . '_crop' );
					
					if ( ! $width && $size_name === 'medium_large' ) {
						$width = 768;
						$height = 0;
					}
					
					$image_sizes[ $size_name ] = array(
						'width'  => $width ?: 0,
						'height' => $height ?: 0,
						'crop'   => $crop ?: false,
					);
				}
			}
		}
		
		if ( empty( $image_sizes ) ) {
			return;
		}
		
		// Define WordPress core sizes
		$wp_core_sizes = array(
			'thumbnail'     => 'WordPress Core',
			'medium'        => 'WordPress Core',
			'medium_large'  => 'WordPress Core',
			'large'         => 'WordPress Core',
			'1536x1536'     => 'WordPress Core (Responsive)',
			'2048x2048'     => 'WordPress Core (Responsive)',
		);
		
		// Define WooCommerce sizes
		$woocommerce_sizes = array(
			'woocommerce_thumbnail'      => 'WooCommerce',
			'woocommerce_single'         => 'WooCommerce',
			'woocommerce_gallery_thumbnail' => 'WooCommerce',
		);
		
		// Check if WooCommerce is active
		$is_woocommerce_active = class_exists( 'WooCommerce' );
		
		// Get WordPress media settings
		$thumbnail_size = get_option( 'thumbnail_size_w' ) . 'x' . get_option( 'thumbnail_size_h' );
		$medium_size = get_option( 'medium_size_w' ) . 'x' . get_option( 'medium_size_h' );
		$large_size = get_option( 'large_size_w' ) . 'x' . get_option( 'large_size_h' );
		
		?>
		<h2>Image Sizes Information</h2>
		<p style="margin-bottom: 15px;">The following image sizes are <strong>registered</strong> in your WordPress installation. Note that not all sizes will be generated for every image (e.g., responsive sizes like 1536x1536 and 2048x2048 are only generated if the original image is larger than those dimensions). All generated sizes will be converted to WEBP format:</p>
			<table class="widefat striped" style="margin-top: 10px;">
				<thead>
					<tr>
						<th style="width: 25%;">Size Name</th>
						<th style="width: 20%;">Dimensions</th>
						<th style="width: 20%;">Crop</th>
						<th style="width: 35%;">Source</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $image_sizes as $size_name => $size_data ) {
						$width = isset( $size_data['width'] ) ? $size_data['width'] : 0;
						$height = isset( $size_data['height'] ) ? $size_data['height'] : 0;
						
						// Handle crop field (can be bool or array)
						$crop_value = isset( $size_data['crop'] ) ? $size_data['crop'] : false;
						if ( is_array( $crop_value ) ) {
							$crop = 'Yes (array)';
						} else {
							$crop = $crop_value ? 'Yes' : 'No';
						}
						
						$dimensions = $width . ' × ' . $height;
						
						// Determine source
						$source = 'Unknown';
						$source_class = '';
						
						if ( isset( $wp_core_sizes[ $size_name ] ) ) {
							$source = $wp_core_sizes[ $size_name ];
							$source_class = 'wp-core';
							
							// Add configurable info for core sizes
							if ( $size_name === 'thumbnail' ) {
								$source .= ' (Config: ' . $thumbnail_size . 'px)';
							} elseif ( $size_name === 'medium' ) {
								$source .= ' (Config: ' . $medium_size . 'px)';
							} elseif ( $size_name === 'large' ) {
								$source .= ' (Config: ' . $large_size . 'px)';
							} elseif ( $size_name === 'medium_large' ) {
								$source .= ' (Hardcoded: 768×0, not configurable)';
							} elseif ( $size_name === '1536x1536' || $size_name === '2048x2048' ) {
								$source .= ' (Responsive image, generated only if original is larger)';
							}
						} elseif ( isset( $woocommerce_sizes[ $size_name ] ) ) {
							if ( $is_woocommerce_active ) {
								$source = $woocommerce_sizes[ $size_name ];
								$source_class = 'woocommerce';
								
								// Try to get WooCommerce settings
								if ( function_exists( 'wc_get_image_size' ) ) {
									$wc_size = wc_get_image_size( str_replace( 'woocommerce_', '', $size_name ) );
									if ( $wc_size && isset( $wc_size['width'] ) ) {
										$source .= ' (Config: WooCommerce → Settings → Products → Product Images)';
									}
								}
							} else {
								$source = 'WooCommerce (Plugin inactive - size may not be generated)';
								$source_class = 'inactive';
							}
						} elseif ( strpos( $size_name, 'woocommerce' ) === 0 ) {
							$source = $is_woocommerce_active ? 'WooCommerce (Custom)' : 'WooCommerce (Plugin inactive)';
							$source_class = $is_woocommerce_active ? 'woocommerce' : 'inactive';
						} else {
							// Could be from theme or another plugin
							$source = 'Theme or Plugin';
							$source_class = 'custom';
							
							// Check if it might be from current theme
							if ( function_exists( 'get_template_directory' ) ) {
								$theme_functions = get_template_directory() . '/functions.php';
								if ( file_exists( $theme_functions ) ) {
									$theme_functions_content = file_get_contents( $theme_functions );
									if ( strpos( $theme_functions_content, "'" . $size_name . "'" ) !== false || 
										 strpos( $theme_functions_content, '"' . $size_name . '"' ) !== false ) {
										$source = 'Current Theme (' . wp_get_theme()->get( 'Name' ) . ')';
										$source_class = 'theme';
									}
								}
							}
						}
						?>
						<tr>
							<td><strong><?php echo esc_html( $size_name ); ?></strong></td>
							<td><?php echo esc_html( $dimensions ); ?></td>
							<td><?php echo esc_html( $crop ); ?></td>
							<td>
								<span class="source-<?php echo esc_attr( $source_class ); ?>" style="
									<?php if ( $source_class === 'wp-core' ) echo 'color: #0073aa;'; ?>
									<?php if ( $source_class === 'woocommerce' ) echo 'color: #96588a;'; ?>
									<?php if ( $source_class === 'theme' ) echo 'color: #00a32a;'; ?>
									<?php if ( $source_class === 'custom' ) echo 'color: #d63638;'; ?>
									<?php if ( $source_class === 'inactive' ) echo 'color: #d63638; font-style: italic;'; ?>
								">
									<?php echo esc_html( $source ); ?>
								</span>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
			<div style="margin-top: 15px; padding: 12px; background: #f0f6fc; border-left: 4px solid #2271b1;">
				<p style="margin: 0; font-size: 13px;">
					<strong>ℹ️ Note:</strong> All of these image sizes will be automatically converted to WEBP format when you upload images. 
					Note that responsive sizes (1536x1536, 2048x2048) are only generated if the original image is larger than those dimensions.
					Changes to sizes only affect <strong>new uploads</strong>. To regenerate existing images, use a plugin like "Regenerate Thumbnails". 
				</p>
				<p style="margin: 8px 0 0 0; font-size: 13px;">
					<strong>Configuration:</strong> WordPress core sizes can be configured in <strong>Settings → Media</strong>. 
					WooCommerce sizes can be configured in <strong>WooCommerce → Settings → Products → Product Images</strong>.
				</p>
			</div>
		<?php
	}
	
	/**
	 * Render quality field
	 */
	public function render_quality_field() {
		$settings = get_option( $this->option_name, array() );
		$quality = isset( $settings['webp_quality'] ) ? esc_attr( $settings['webp_quality'] ) : 85;
		?>
		<input type="number" 
		       id="webp_quality" 
		       name="<?php echo esc_attr( $this->option_name ); ?>[webp_quality]" 
		       value="<?php echo esc_attr( $quality ); ?>" 
		       min="0" 
		       max="100" 
		       step="1" 
		       class="small-text" />
		<p class="description">
			Quality setting for WEBP conversion (0-100). Lower values result in smaller file sizes but may reduce image quality. 
			Recommended: 80-90. Default: 85
		</p>
		<?php
	}
	
	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Show success message after save
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'f_webp_converter_messages',
				'f_webp_converter_message',
				'Settings saved successfully!',
				'success'
			);
		}
		
		settings_errors( 'f_webp_converter_messages' );
		
		// Add some basic styles for the image sizes table
		?>
		<style>
			.source-wp-core { font-weight: 600; }
			.source-woocommerce { font-weight: 600; }
			.source-theme { font-weight: 600; }
			.source-custom { font-weight: 500; }
			.source-inactive { font-weight: 500; }
			.f-webp-image-sizes-info {
				margin-top: 30px;
				padding: 20px;
				background: #fff;
				border: 1px solid #c3c4c7;
				box-shadow: 0 1px 1px rgba(0,0,0,.04);
			}
			.f-webp-image-sizes-info h2 {
				margin-top: 0;
				padding-bottom: 10px;
				border-bottom: 1px solid #c3c4c7;
			}
		</style>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'f_webp_converter_settings_group' );
				do_settings_sections( 'f-webp-converter' );
				submit_button( 'Save Settings' );
				?>
			</form>
			
			<!-- Image Sizes Information Section -->
			<div class="f-webp-image-sizes-info">
				<?php $this->render_image_sizes_info(); ?>
			</div>
		</div>
		<?php
	}
	
	/**
	 * Process image upload - convert to WEBP and compress
	 *
	 * @param array $metadata Attachment metadata
	 * @param int   $attachment_id Attachment ID
	 * @return array Modified metadata
	 */
	public function process_image_upload( $metadata, $attachment_id ) {
		// Only process images
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return $metadata;
		}
		
		// Get the original file path
		$file_path = get_attached_file( $attachment_id );
		
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return $metadata;
		}
		
		// Check if WEBP conversion is supported
		if ( ! $this->is_webp_supported() ) {
			return $metadata;
		}
		
		// Get image info
		$image_info = wp_getimagesize( $file_path );
		if ( ! $image_info ) {
			return $metadata;
		}
		
		$mime_type = $image_info['mime'];
		
		// Only process JPEG and PNG images (skip if already WEBP)
		if ( ! in_array( $mime_type, array( 'image/jpeg', 'image/png' ) ) ) {
			return $metadata;
		}
		
		// Convert original image to WEBP and replace
		$webp_path = $this->convert_to_webp( $file_path );
		
		if ( $webp_path ) {
			// Get relative path for metadata
			$relative_path = $this->get_relative_path( $webp_path );
			
			// Update attachment file path in postmeta
			update_post_meta( $attachment_id, '_wp_attached_file', $relative_path );
			
			// Update post mime type
			wp_update_post( array(
				'ID'             => $attachment_id,
				'post_mime_type' => 'image/webp',
			) );
			
			// Process all image sizes (thumbnails)
			if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				$base_dir = dirname( $webp_path );
				
				foreach ( $metadata['sizes'] as $size => $size_data ) {
					if ( isset( $size_data['file'] ) ) {
						$thumb_path = $base_dir . '/' . $size_data['file'];
						if ( file_exists( $thumb_path ) ) {
							$thumb_webp = $this->convert_to_webp( $thumb_path );
							if ( $thumb_webp ) {
								// Update thumbnail file reference in metadata
								$thumb_file = basename( $thumb_webp );
								$metadata['sizes'][ $size ]['file'] = $thumb_file;
								$metadata['sizes'][ $size ]['mime-type'] = 'image/webp';
							}
						}
					}
				}
			}
			
			// Update main metadata (WordPress will save this automatically)
			$metadata['file'] = $relative_path;
			$metadata['mime-type'] = 'image/webp';
		}
		
		return $metadata;
	}
	
	/**
	 * Convert image to WEBP format and replace original
	 *
	 * @param string $file_path Path to the image file
	 * @return string|false New file path on success, false on failure
	 */
	private function convert_to_webp( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}
		
		// Get image info
		$image_info = wp_getimagesize( $file_path );
		if ( ! $image_info ) {
			return false;
		}
		
		$mime_type = $image_info['mime'];
		
		// Create image resource from file
		$image = null;
		
		switch ( $mime_type ) {
			case 'image/jpeg':
				$image = imagecreatefromjpeg( $file_path );
				break;
			case 'image/png':
				$image = imagecreatefrompng( $file_path );
				// Preserve transparency for PNG
				imagealphablending( $image, false );
				imagesavealpha( $image, true );
				break;
			default:
				return false;
		}
		
		if ( ! $image ) {
			return false;
		}
		
		// Generate WEBP file path (temporary name)
		$webp_path = preg_replace( '/\.(jpg|jpeg|png)$/i', '.webp', $file_path );
		
		// Get quality setting
		$quality = $this->get_webp_quality();
		
		// Convert and save as WEBP
		$success = imagewebp( $image, $webp_path, $quality );
		
		// Clean up memory
		imagedestroy( $image );
		
		if ( $success && file_exists( $webp_path ) ) {
			// Delete original file
			@unlink( $file_path );
			
			return $webp_path;
		}
		
		return false;
	}
	
	/**
	 * Get relative path from absolute path
	 *
	 * @param string $absolute_path Absolute file path
	 * @return string Relative path
	 */
	private function get_relative_path( $absolute_path ) {
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'];
		
		if ( strpos( $absolute_path, $base_dir ) === 0 ) {
			return ltrim( str_replace( $base_dir, '', $absolute_path ), '/' );
		}
		
		return basename( $absolute_path );
	}
	
	/**
	 * Check if WEBP conversion is supported by PHP
	 *
	 * @return bool
	 */
	public function is_webp_supported() {
		return function_exists( 'imagewebp' ) && function_exists( 'imagecreatefromjpeg' ) && function_exists( 'imagecreatefrompng' );
	}
}

// Initialize the plugin
new F_WebP_Converter();

