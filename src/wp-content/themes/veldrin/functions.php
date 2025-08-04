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
 * ОПТИМІЗАЦІЯ ЗОБРАЖЕНЬ
 * Контроль розмірів зображень та видалення зайвих
 */

// Видаляємо стандартні розміри зображень WordPress
function remove_default_image_sizes() {
    // Видаляємо зайві розміри
    remove_image_size('thumbnail');
    remove_image_size('medium');
    remove_image_size('medium_large');
    remove_image_size('large');
    remove_image_size('1536x1536');
    remove_image_size('2048x2048');
}
add_action('init', 'remove_default_image_sizes');

// Встановлюємо тільки потрібні розміри
function add_custom_image_sizes() {
    // Додаємо тільки необхідні розміри
    add_image_size('product-thumbnail', 300, 300, true);
    add_image_size('product-medium', 600, 600, true);
    add_image_size('product-large', 1024, 1024, false);
}
add_action('after_setup_theme', 'add_custom_image_sizes');

// Видаляємо зайві розміри при завантаженні зображення
function remove_unused_image_sizes($sizes) {
    // Залишаємо тільки потрібні розміри
    $allowed_sizes = array(
        'product-thumbnail',
        'product-medium', 
        'product-large',
        'full'
    );
    
    return array_intersect_key($sizes, array_flip($allowed_sizes));
}
add_filter('intermediate_image_sizes_advanced', 'remove_unused_image_sizes');

// Оптимізація якості JPEG
function optimize_jpeg_quality() {
    return 85; // Оптимальна якість
}
add_filter('jpeg_quality', 'optimize_jpeg_quality');

// Автоматичне очищення при видаленні зображення
function cleanup_image_sizes_on_delete($post_id) {
    $post = get_post($post_id);
    
    if ($post->post_type === 'attachment') {
        $file_path = get_attached_file($post_id);
        $file_info = pathinfo($file_path);
        $upload_dir = wp_upload_dir();
        
        // Видаляємо всі розміри крім оригіналу
        $sizes = get_intermediate_image_sizes();
        foreach ($sizes as $size) {
            $size_data = image_get_intermediate_size($post_id, $size);
            if ($size_data) {
                $size_file = $upload_dir['basedir'] . '/' . $size_data['file'];
                if (file_exists($size_file)) {
                    unlink($size_file);
                }
            }
        }
    }
}
add_action('before_delete_post', 'cleanup_image_sizes_on_delete');

// Додаємо адмін-сторінку для очищення медіа
function add_media_cleanup_page() {
    add_management_page(
        'Очищення медіа',
        'Очищення медіа', 
        'manage_options',
        'media-cleanup',
        'media_cleanup_page'
    );
}
add_action('admin_menu', 'add_media_cleanup_page');

// Сторінка очищення медіа
function media_cleanup_page() {
    if (isset($_POST['cleanup_media'])) {
        // Логіка очищення
        $removed = 0;
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            if (file_exists($file_path)) {
                // Видаляємо зайві розміри
                $sizes = get_intermediate_image_sizes();
                foreach ($sizes as $size) {
                    $size_data = image_get_intermediate_size($attachment->ID, $size);
                    if ($size_data) {
                        $size_file = dirname($file_path) . '/' . basename($size_data['file']);
                        if (file_exists($size_file) && $size_file !== $file_path) {
                            unlink($size_file);
                            $removed++;
                        }
                    }
                }
            }
        }
        
        echo '<div class="notice notice-success"><p>Видалено ' . $removed . ' зайвих файлів.</p></div>';
    }
    
    echo '<div class="wrap">';
    echo '<h1>Очищення медіа-файлів</h1>';
    echo '<p>Цей інструмент видалить зайві розміри зображень.</p>';
    echo '<form method="post">';
    echo '<input type="submit" name="cleanup_media" value="Очистити медіа" class="button button-primary">';
    echo '</form>';
    echo '</div>';
}

