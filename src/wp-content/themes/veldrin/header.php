<?php
?>
<!doctype html>
<html <?php language_attributes(); ?> class="no-js" prefix="og: http://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php 
          if (isset($_SERVER['HTTP_USER_AGENT']) &&
    (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false))
        header('X-UA-Compatible: IE=edge,chrome=1');
    ?>
    <?php if ( ! function_exists('has_site_icon') || ! has_site_icon() ) : ?>
      <link rel="icon" href="<?php echo esc_url( get_site_url( null, '/favicon.ico' ) ); ?>" sizes="any">
      <link rel="icon" type="image/svg+xml" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/img/favicon.svg' ); ?>">
      <link rel="apple-touch-icon" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/img/apple-touch-icon.png' ); ?>">
    <?php endif; ?>
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
    <header class="<?=is_front_page()?'frontpage':''?>" role="banner">
        <div class="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <div class="header_logo">
            <a href="<?=home_url();?>" title="UArchery"></a>
        </div>
        <nav class="header_menu" aria-label="Primary">
            <?php wp_nav_menu([
                'theme_location' => 'header',
                'container' => false,
                'items_wrap' => '<ul id="%1$s" class="%2$s">%3$s</ul>',
                'menu_class' => 'menu_list'
            ]);?>
        </nav>
    </header>
    <main>
