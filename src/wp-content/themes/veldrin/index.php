<?php
get_header();
?>
<main id="primary" class="site-main">
  <?php if ( have_posts() ) : ?>
    <?php while ( have_posts() ) : the_post(); ?>
      <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
          <?php if ( is_singular() ) : ?>
            <h1 class="entry-title"><?php the_title(); ?></h1>
          <?php else : ?>
            <h2 class="entry-title"><a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a></h2>
          <?php endif; ?>
        </header>
        <div class="entry-content">
          <?php is_singular() ? the_content() : the_excerpt(); ?>
        </div>
      </article>
    <?php endwhile; ?>
    <?php the_posts_navigation(); ?>
  <?php else : ?>
    <p><?php esc_html_e( 'Nothing found.', 'Veldrin' ); ?></p>
  <?php endif; ?>
</main>
<?php
get_footer();
?>


