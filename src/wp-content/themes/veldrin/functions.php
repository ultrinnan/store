<?php

//dashboard customization (few lines for style)
require 'admin/admin_customizations.php';
require 'admin/security_hooks.php';

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
    add_theme_support('title-tag');

    add_theme_support('woocommerce');
    // Enable product gallery features (optional but recommended)
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');

    register_nav_menu( 'header', __( 'Header menu', 'Veldrin' ) );
    register_nav_menu( 'footer', __( 'Footer menu', 'Veldrin' ) );
}
add_action('after_setup_theme', 'custom_theme_setup');

// Register sidebar(s)
function veldrin_widgets_init() {
    register_sidebar( array(
        'name'          => __( 'Primary Sidebar', 'Veldrin' ),
        'id'            => 'primary',
        'description'   => __( 'Main sidebar that appears on the right.', 'Veldrin' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ) );
}
add_action( 'widgets_init', 'veldrin_widgets_init' );

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

// ----------------------------
// Header menu icons (Account, Cart) with live cart count
// ----------------------------
if ( ! function_exists( 'veldrin_is_woocommerce_active' ) ) {
    function veldrin_is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }
}

if ( ! function_exists( 'veldrin_get_icon_svg' ) ) {
    /**
     * Get inline SVG icon from file with sanitization
     *
     * @param string $icon_name Icon filename without extension (e.g., 'icon-user', 'icon-cart')
     * @return string Sanitized SVG markup or empty string if file not found
     */
    function veldrin_get_icon_svg( $icon_name ) {
        $icon_path = get_template_directory() . '/img/icons/' . $icon_name . '.svg';

        // Define allowed SVG tags and attributes for security
        $allowed_svg = array(
            'svg' => array(
                'class' => true,
                'width' => true,
                'height' => true,
                'viewBox' => true,
                'viewbox' => true,
                'aria-hidden' => true,
                'focusable' => true,
                'xmlns' => true,
                'fill' => true,
                'stroke' => true,
            ),
            'path' => array(
                'fill' => true,
                'd' => true,
                'stroke' => true,
                'stroke-width' => true,
                'stroke-linecap' => true,
                'stroke-linejoin' => true,
            ),
            'circle' => array(
                'cx' => true,
                'cy' => true,
                'r' => true,
                'fill' => true,
                'stroke' => true,
            ),
            'rect' => array(
                'x' => true,
                'y' => true,
                'width' => true,
                'height' => true,
                'fill' => true,
                'stroke' => true,
                'rx' => true,
                'ry' => true,
            ),
            'g' => array(
                'fill' => true,
                'stroke' => true,
            ),
        );

        if ( file_exists( $icon_path ) ) {
            $svg_content = file_get_contents( $icon_path );
            if ( $svg_content !== false ) {
                // Sanitize SVG content to prevent XSS attacks
                return wp_kses( $svg_content, $allowed_svg );
            }
        }

        // Fallback inline SVG if file not found (already sanitized)
        if ( $icon_name === 'icon-user' ) {
            return '<svg class="icon icon-user" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-4.33 0-8 2.17-8 5v1h16v-1c0-2.83-3.67-5-8-5Z"/></svg>';
        } elseif ( $icon_name === 'icon-cart' ) {
            return '<svg class="icon icon-cart" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg"><path fill="currentColor" d="M7 18a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm10 0a2 2 0 1 0 .001 4.001A2 2 0 0 0 17 18ZM6.2 5l.31 2H21l-2 8H8l-.27-1.35L5.1 4H2V2h4a1 1 0 0 1 .98.8L8.2 11H18l1.2-4H6.51L6.2 5Z"/></svg>';
        }

        return '';
    }
}

if ( ! function_exists( 'veldrin_get_cart_count_markup' ) ) {
    function veldrin_get_cart_count_markup() {
        $count = 0;
        if ( veldrin_is_woocommerce_active() && function_exists( 'WC' ) && WC()->cart ) {
            $count = (int) WC()->cart->get_cart_contents_count();
        }
        // Only show badge if count > 0
        if ( $count > 0 ) {
            return '<span class="cart-count" data-cart-count>' . intval( $count ) . '</span>';
        }
        return '';
    }
}

if ( ! function_exists( 'veldrin_get_cart_link_markup' ) ) {
    function veldrin_get_cart_link_markup() {
        $href = function_exists( 'wc_get_cart_url' ) ? esc_url( wc_get_cart_url() ) : '#';
        $aria_label = esc_attr__( 'Cart', 'veldrin' );
        $icon_svg = veldrin_get_icon_svg( 'icon-cart' );
        return '<a class="header-icon-link menu-icon-link menu-cart-link" href="' . $href . '" aria-label="' . $aria_label . '">' . $icon_svg . veldrin_get_cart_count_markup() . '</a>';
    }
}

// Replace specific header menu items with icons
add_filter( 'walker_nav_menu_start_el', function( $item_output, $item, $depth, $args ) {
    if ( empty( $args->theme_location ) || $args->theme_location !== 'header' ) {
        return $item_output;
    }
    if ( ! veldrin_is_woocommerce_active() ) {
        return $item_output;
    }

    $item_url = isset( $item->url ) ? $item->url : '';
    $cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';
    $account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';

    $is_cart    = $cart_url && untrailingslashit( esc_url( $item_url ) ) === untrailingslashit( esc_url( $cart_url ) );
    $is_account = $account_url && untrailingslashit( esc_url( $item_url ) ) === untrailingslashit( esc_url( $account_url ) );

    // Allow partial match fallback by slug if direct match fails
    if ( ! $is_cart && strpos( $item_url, 'cart' ) !== false ) {
        $is_cart = true;
    }
    if ( ! $is_account && ( strpos( $item_url, 'my-account' ) !== false || strpos( $item_url, 'account' ) !== false ) ) {
        $is_account = true;
    }

    if ( ! $is_cart && ! $is_account ) {
        return $item_output;
    }

    // Build icon-only markup, preserving the original URL
    $link_classes = 'menu-icon-link ' . ( $is_cart ? 'menu-cart-link' : 'menu-account-link' );
    $aria_label   = $is_cart ? esc_attr__( 'Cart', 'veldrin' ) : esc_attr__( 'My account', 'veldrin' );
    $href         = esc_url( $item_url );

    // Load SVG icons from files
    if ( $is_cart ) {
        $icon_svg = veldrin_get_icon_svg( 'icon-cart' );
        $item_output = '<a class="' . esc_attr( $link_classes ) . '" href="' . $href . '" aria-label="' . $aria_label . '">' . $icon_svg . veldrin_get_cart_count_markup() . '</a>';
    } else {
        $icon_svg = veldrin_get_icon_svg( 'icon-user' );
        $item_output = '<a class="' . esc_attr( $link_classes ) . '" href="' . $href . '" aria-label="' . $aria_label . '">' . $icon_svg . '</a>';
    }

    return $item_output;
}, 10, 4 );

// WooCommerce fragments to live-update the cart count in header
add_filter( 'woocommerce_add_to_cart_fragments', function( $fragments ) {
    if ( ! veldrin_is_woocommerce_active() ) {
        return $fragments;
    }
    // Replace the entire cart link to ensure consistent updates across pages
    $fragments['a.menu-cart-link'] = veldrin_get_cart_link_markup();
    // Also provide fine-grained count replacement if present
    $fragments['.menu-cart-link .cart-count'] = veldrin_get_cart_count_markup();
    return $fragments;
} );

// Also handle general cart fragment refreshes (e.g., on cart/checkout pages)
add_filter( 'woocommerce_get_refreshed_fragments', function( $fragments ) {
    if ( ! veldrin_is_woocommerce_active() ) {
        return $fragments;
    }
    $fragments['a.menu-cart-link'] = veldrin_get_cart_link_markup();
    $fragments['.menu-cart-link .cart-count'] = veldrin_get_cart_count_markup();
    return $fragments;
} );



// Hide Cart menu item on the Cart page (header menu only)
add_filter( 'wp_nav_menu_objects', function( $items, $args ) {
    if ( empty( $args->theme_location ) || $args->theme_location !== 'header' ) {
        return $items;
    }
    if ( ! veldrin_is_woocommerce_active() ) {
        return $items;
    }
    if ( function_exists( 'is_cart' ) && is_cart() ) {
        $cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';
        foreach ( $items as $index => $item ) {
            $url = isset( $item->url ) ? $item->url : '';
            $is_cart_item = false;
            if ( $cart_url ) {
                $is_cart_item = untrailingslashit( esc_url( $url ) ) === untrailingslashit( esc_url( $cart_url ) );
            }
            if ( ! $is_cart_item && strpos( $url, 'cart' ) !== false ) {
                $is_cart_item = true;
            }
            if ( $is_cart_item ) {
                unset( $items[ $index ] );
            }
        }
    }
    return $items;
}, 10, 2 );

// Fix pagination for custom page templates
add_action( 'init', function() {
    add_rewrite_rule( '^articles/page/?([0-9]{1,})/?$', 'index.php?pagename=articles&paged=$matches[1]', 'top' );
} );

// Make sure 'paged' query var is recognized
add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'paged';
    return $vars;
} );

// ----------------------------
// Product view tracking & featured product
// ----------------------------

/**
 * Track product views for "most popular" sorting
 */
function veldrin_track_product_view() {
    if ( ! veldrin_is_woocommerce_active() ) {
        return;
    }
    if ( ! is_singular( 'product' ) || is_admin() || current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return;
    }
    if ( wp_doing_ajax() ) {
        return;
    }
    $product_id = get_queried_object_id();
    if ( ! $product_id ) {
        return;
    }
    $count = (int) get_post_meta( $product_id, '_product_views_count', true );
    update_post_meta( $product_id, '_product_views_count', $count + 1 );
}
add_action( 'template_redirect', 'veldrin_track_product_view' );

/**
 * Add Featured product checkbox to product edit page (General tab)
 */
function veldrin_add_featured_checkbox() {
    $product = wc_get_product( get_the_ID() );
    $value   = $product && $product->get_featured() ? 'yes' : '';
    $priority = $product ? (int) $product->get_meta( '_featured_priority' ) : 1;
    if ( $priority < 1 ) {
        $priority = 1;
    }
    echo '<div class="options_group">';
    woocommerce_wp_checkbox( array(
        'id'          => '_featured',
        'value'       => $value,
        'label'       => __( 'Featured product', 'veldrin' ),
        'description' => __( 'Show in "Our latest and greatest" section on the homepage.', 'veldrin' ),
        'desc_tip'    => true,
    ) );
    woocommerce_wp_text_input( array(
        'id'                => '_featured_priority',
        'type'              => 'number',
        'label'             => __( 'Priority in featured list', 'veldrin' ),
        'value'             => $priority,
        'description'       => __( 'Lower number = higher priority. Only top 10 by priority appear on the homepage. Default: 1.', 'veldrin' ),
        'desc_tip'          => true,
        'custom_attributes' => array(
            'min'  => '1',
            'step' => '1',
        ),
    ) );
    echo '</div>';
}
add_action( 'woocommerce_product_options_general_product_data', 'veldrin_add_featured_checkbox' );

/**
 * Save Featured product checkbox (classic editor)
 */
function veldrin_save_featured_checkbox( $id, $post ) {
    $product = wc_get_product( $id );
    if ( ! $product ) {
        return;
    }
    // Check for 'yes' - unchecked sends nothing or hidden field value, not 'yes'
    $product->set_featured( 'yes' === ( $_POST['_featured'] ?? '' ) );
    $priority = isset( $_POST['_featured_priority'] ) ? max( 1, (int) $_POST['_featured_priority'] ) : 1;
    $product->update_meta_data( '_featured_priority', $priority );
    $product->save();
}
add_action( 'woocommerce_process_product_meta', 'veldrin_save_featured_checkbox', 10, 2 );