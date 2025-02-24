<?php

function f_scripts_styles()
{
//    wp_enqueue_style('f_style', get_template_directory_uri() . '/../veldrin/css/main.min.css');
    wp_enqueue_style('style', get_template_directory_uri() . '/../veldrin/style.css');

//    wp_enqueue_script('f_scripts', get_template_directory_uri() . '/js/main.min.js', array('jquery'), '1.0', true);
}
// Create action where we connected scripts and styles in function f_scripts_styles
add_action('wp_enqueue_scripts', 'f_scripts_styles', 1);

add_theme_support('menus');
//custom locations for menus
function f_register_menus() {
    register_nav_menu( 'header', __( 'Header menu', 'theme-slug' ) );
    register_nav_menu( 'footer', __( 'Footer menu', 'theme-slug' ) );
}
add_action( 'after_setup_theme', 'f_register_menus' );