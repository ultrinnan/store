<?php
?>
<!doctype html>
<html <?php language_attributes(); ?> class="no-js" prefix="og: http://ogp.me/ns#">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ( ! has_site_icon() ) : ?>
    <link rel="icon" type="image/png" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/img/logo_600.png' ); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/img/logo_600.png' ); ?>">
    <?php endif; ?>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
    <a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'veldrin' ); ?></a>
    <header>
        <div class="header_top">
            <div class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <div class="header_logo">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" class="site-logo-link" aria-label="<?php esc_attr_e( 'Veldrin home', 'veldrin' ); ?>"></a>
            </div>
            <div class="header_icons">
                <?php
                // User account icon
                if (function_exists('wc_get_page_id') && function_exists('veldrin_get_icon_svg')) {
                    $myaccount_page_id = get_option('woocommerce_myaccount_page_id');
                    if ($myaccount_page_id) {
                        $myaccount_url = get_permalink($myaccount_page_id);
                        $user_icon_svg = veldrin_get_icon_svg('icon-user');
                        echo '<a href="' . esc_url($myaccount_url) . '" class="header-icon-link menu-icon-link menu-account-link" aria-label="' . esc_attr__('My Account', 'veldrin') . '">';
                        echo $user_icon_svg;
                        echo '</a>';
                    }
                }

                // Cart icon with count - use helper function for consistency
                if (function_exists('veldrin_get_cart_link_markup')) {
                    echo veldrin_get_cart_link_markup();
                }
                ?>
            </div>
        </div>
        <div class="menu_wrapper">
            <nav class="header_menu" aria-label="Primary">
                <?php wp_nav_menu([
                    'theme_location' => 'header',
                    'container' => false,
                    'items_wrap' => '<ul id="%1$s" class="%2$s">%3$s</ul>',
                    'menu_class' => 'menu_list',
                    'fallback_cb' => 'wp_page_menu'
                ]);?>
            </nav>
            <div class="header_search" role="search" aria-label="Site search">
                <?php get_search_form(); ?>
            </div>
        </div>
    </header>
    <main id="content" role="main">
