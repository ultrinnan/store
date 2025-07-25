<?php

//custom logos and styles for dashboard. located here instead of styles for speed purposes
function my_admin_logo() {
   echo '
    <script>
      window.onload = function() {
        document.getElementById("footer-thankyou").innerHTML = "Created by <a target=\"_blank\" href=\"//fedirko.pro\">FEDIRKO.PRO</a>";        
      }
    </script>';
}
add_action('admin_head', 'my_admin_logo');

function my_login_logo(){
   echo '
   <style>
        .login h1 a {
            display: none;
        }
    </style>';
}
add_action('login_head', 'my_login_logo');

//change link to site home instead of wordpress site
add_filter( 'login_headerurl', function(){
    return get_home_url();
});

/* remove from logo "works on wordpress" */
add_filter( 'login_headertext', function() {
    return false;
});
 
//our widgets in dashboard
function dashboard_widget_1(){
  echo "In this place can be your adds :)";
}

function add_dashboard_widgets() {
  wp_add_dashboard_widget('dashboard_widget_id_1', 'Custom widget example', 'dashboard_widget_1');
}
add_action('wp_dashboard_setup', 'add_dashboard_widgets' );

function admin_css_js() {
  $admin_css_file = get_template_directory() . '/admin/css/admin_styles.css';
  $admin_js_file = get_template_directory() . '/admin/js/admin_scripts.js';
  
  wp_enqueue_style('admin_style', get_template_directory_uri() .'/admin/css/admin_styles.css', '', file_exists($admin_css_file) ? filemtime($admin_css_file) : '1.0');
  wp_enqueue_script('admin_js', get_template_directory_uri() .'/admin/js/admin_scripts.js', 'jquery', file_exists($admin_js_file) ? filemtime($admin_js_file) : '1.0');
}
add_action( 'admin_enqueue_scripts', 'admin_css_js' );

//add a theme settings page to the dashboard menu
function f_add_theme_settings() {
    add_menu_page( 'Theme settings', 'Theme settings', 'administrator', 'f_theme_settings', 'f_theme_general_option', 'dashicons-desktop' );
    add_submenu_page( 'f_theme_settings', 'Social settings', 'Social settings', 'administrator', 'f_social_settings', 'f_display_social_settings');
}
add_action( 'admin_menu', 'f_add_theme_settings');

function f_display_social_settings(){
    get_template_part( 'partials/admin/social' );
}

function f_theme_general_option(){
    get_template_part( 'partials/admin/general' );
}