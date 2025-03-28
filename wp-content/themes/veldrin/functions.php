<?php

//security hooks
require 'admin/security_hooks.php';
//dashboard customization
require 'admin/admin_customizations.php';

function f_scripts_styles()
{
    wp_enqueue_style('f_style', get_template_directory_uri() . '/../veldrin/css/main.min.css');
    wp_enqueue_style('style', get_template_directory_uri() . '/../veldrin/style.css');

//    wp_enqueue_script('f_scripts', get_template_directory_uri() . '/js/main.min.js', array('jquery'), '1.0', true);
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