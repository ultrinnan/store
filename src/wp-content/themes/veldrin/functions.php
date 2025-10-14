<?php

//dashboard customization (few lines for style)
require 'admin/admin_customizations.php';

function f_scripts_styles()
{
    $css_file = get_stylesheet_directory() . '/css/main.min.css';
    $style_file = get_stylesheet_directory() . '/style.css';
    
    $js_file = get_stylesheet_directory() . '/js/main.min.js';
    
    wp_enqueue_style('f_style', get_stylesheet_directory_uri() . '/css/main.min.css', array(), file_exists($css_file) ? filemtime($css_file) : '1.0');
    wp_enqueue_style('style', get_stylesheet_directory_uri() . '/style.css', array(), file_exists($style_file) ? filemtime($style_file) : '1.0');

    wp_enqueue_script('f_scripts', get_stylesheet_directory_uri() . '/js/main.min.js', array('jquery'), file_exists($js_file) ? filemtime($js_file) : '1.0', true);

    // LiveReload (development only)
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $lr_host = 'localhost';
        $lr_port = 35729;
        // Always enqueue in development; the browser connects to localhost on the host machine.
        wp_enqueue_script('livereload', 'http://' . $lr_host . ':' . $lr_port . '/livereload.js?snipver=1', array(), null, true);
    }
}
add_action('wp_enqueue_scripts', 'f_scripts_styles');

function custom_theme_setup() {
    add_theme_support('menus');

    add_theme_support('woocommerce');
    // Enable product gallery features (optional but recommended)
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    register_nav_menu( 'header', __( 'Header menu', 'theme-slug' ) );
    register_nav_menu( 'footer', __( 'Footer menu', 'theme-slug' ) );
    register_nav_menu( 'footer_shop', __( 'Footer shop menu', 'theme-slug' ) );
    register_nav_menu( 'bottom', __( 'Bottom menu', 'theme-slug' ) );
}
add_action('after_setup_theme', 'custom_theme_setup');

/**
 * Simple image fallback system
 * Prevents 404 errors for missing image sizes
 */
function simple_image_fallback($image, $attachment_id, $size, $icon) {
    if (empty($image) || !is_array($image) || empty($image[0])) {
        $file_path = get_attached_file($attachment_id);
        
        if ($file_path && file_exists($file_path)) {
            $upload_dir = wp_upload_dir();
            $original_url = $upload_dir['baseurl'] . '/' . get_post_meta($attachment_id, '_wp_attached_file', true);
            
            $image_data = wp_get_attachment_metadata($attachment_id);
            $width = isset($image_data['width']) ? $image_data['width'] : 0;
            $height = isset($image_data['height']) ? $image_data['height'] : 0;
            
            return array($original_url, $width, $height, false);
        }
    }
    
    return $image;
}

// Apply fallback with high priority
add_filter('wp_get_attachment_image_src', 'simple_image_fallback', 999, 4);