# F-PRO Simple WebP Converter

A simple WordPress plugin that automatically compresses and converts uploaded images to WEBP format.

## Features

- Automatically converts JPEG and PNG images to WEBP format on upload
- Compresses images during conversion for smaller file sizes
- Processes all image sizes (thumbnails) automatically
- Preserves PNG transparency
- Updates WordPress metadata correctly

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- PHP GD library with WEBP support (usually included with modern PHP installations)

## Installation

1. Copy the `f-pro-webp-converter` folder to your WordPress `wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

## How It Works

When you upload an image through any WordPress functionality (Media Library, theme uploads, etc.), the plugin will:

1. Detect the uploaded image (JPEG or PNG)
2. Convert it to WEBP format with compression
3. Replace the original file with the WEBP version
4. Process all generated thumbnails
5. Update WordPress attachment metadata

## Configuration

You can adjust the compression quality through the Settings → F-PRO WebP Converter page in WordPress admin. The default is 85 (0-100 scale, where lower = smaller file size but potentially lower quality).

## Notes

- Only JPEG and PNG images are converted
- Images already in WEBP format are skipped
- The original files are replaced (not kept alongside)
- Requires PHP GD library with WEBP support

## Checking WEBP Support

To verify your server supports WEBP conversion, you can check if the following PHP functions are available:

- `imagewebp()`
- `imagecreatefromjpeg()`
- `imagecreatefrompng()`

These are typically available if GD library is installed with WEBP support.
