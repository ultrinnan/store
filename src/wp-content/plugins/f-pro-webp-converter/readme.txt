=== F-PRO WebP Converter ===
Contributors: fedirkopro
Tags: webp, images, compression, optimization, media, jpeg, png, convert, performance
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically converts uploaded JPEG and PNG images to WEBP format for better performance and smaller file sizes.

== Description ==

F-PRO WebP Converter automatically compresses and converts all uploaded images (JPEG and PNG) to WEBP format. This results in significantly smaller file sizes (typically 25-35% smaller) while maintaining excellent image quality, leading to faster page load times and reduced server storage.

= Key Features =

* Automatic conversion of JPEG and PNG images to WEBP format on upload
* Configurable compression quality (default: 85)
* Processes all image sizes including thumbnails automatically
* Preserves PNG transparency
* Space savings statistics tracking
* Shows registered image sizes and their sources
* Updates WordPress metadata correctly
* Settings page for easy configuration

= How It Works =

When you upload an image through any WordPress functionality (Media Library, posts, themes, etc.), the plugin:

1. Detects JPEG or PNG images
2. Converts them to WEBP format with compression
3. Replaces original files with WEBP versions
4. Processes all generated thumbnails
5. Updates WordPress attachment metadata

= Requirements =

* WordPress 5.0 or higher
* PHP 7.2 or higher
* PHP GD library with WEBP support (usually included with modern PHP installations)

= Server Requirements =

To use this plugin, your server must support WEBP conversion. Most modern PHP installations include GD library with WEBP support. You can check if your server supports it by verifying these PHP functions are available:

* `imagewebp()`
* `imagecreatefromjpeg()`
* `imagecreatefrompng()`

= Configuration =

After activation, visit **Settings → F-PRO WebP Converter** to:

* Adjust WEBP quality setting (0-100, default: 85)
* View registered image sizes and their sources
* Monitor space savings statistics

= Notes =

* Only JPEG and PNG images are converted (WEBP images are skipped)
* Original files are replaced with WEBP versions (originals are not kept)
* Changes only affect new uploads - existing images are not automatically converted
* To regenerate existing images, use a plugin like "Regenerate Thumbnails"

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Search for "F-PRO WebP Converter"
4. Click "Install Now"
5. Click "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Log in to your WordPress admin panel
3. Navigate to Plugins → Add New → Upload Plugin
4. Choose the ZIP file and click "Install Now"
5. Click "Activate"

= Via FTP =

1. Extract the ZIP file
2. Upload the `f-pro-webp-converter` folder to `/wp-content/plugins/`
3. Log in to your WordPress admin panel
4. Navigate to Plugins
5. Find "F-PRO WebP Converter" and click "Activate"

== Frequently Asked Questions ==

= Does this plugin work with all WordPress themes? =

Yes, this plugin works with any WordPress theme. It hooks into WordPress's standard image upload process.

= Will existing images be converted? =

No, only new images uploaded after plugin activation will be converted. To convert existing images, use a plugin like "Regenerate Thumbnails" or upload them again.

= What happens to the original files? =

Original JPEG/PNG files are replaced with WEBP versions. The originals are not kept alongside the WEBP files.

= Can I adjust the compression quality? =

Yes, you can adjust the quality setting (0-100) in Settings → F-PRO WebP Converter. Lower values result in smaller files but potentially lower quality. Recommended: 80-90.

= What if my server doesn't support WEBP? =

The plugin will check if WEBP support is available and show a warning on the settings page if it's not. Most modern PHP installations support WEBP conversion.

= Does it work with WooCommerce? =

Yes, the plugin works seamlessly with WooCommerce and all other WordPress plugins that handle image uploads.

= Are all image sizes converted? =

Yes, the plugin converts all registered image sizes including thumbnails, medium, large, and custom sizes (including WooCommerce product images).

== Screenshots ==

1. Settings page with quality control and WEBP support status
2. Image sizes information table showing all registered sizes
3. Space savings statistics dashboard

== Changelog ==

= 1.0.0 =
* Initial release
* Automatic WEBP conversion for JPEG and PNG images
* Configurable compression quality
* Space savings statistics tracking
* Image sizes information display
* Settings page for configuration
* Support for all WordPress image sizes including thumbnails
* PNG transparency preservation

== Upgrade Notice ==

= 1.0.0 =
Initial release of F-PRO WebP Converter.

