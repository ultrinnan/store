<?php
get_header();
?>
<section>
  <div class="container">
    <?php if ( have_posts() ) : ?>
      <?php while ( have_posts() ) : the_post(); ?>
        <article <?php post_class(); ?>>
          <?php if ( is_singular() ) : ?>
            <h1>
              <?php the_title(); ?>
            </h1>
          <?php else : ?>
            <h2>
              <a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a>
            </h2>
          <?php endif; ?>
          <div class="content">
            <?php is_singular() ? the_content() : the_excerpt(); ?>
          </div>
        </article>
      <?php endwhile; ?>
      <?php the_posts_navigation(); ?>
    <?php else : ?>
      <p><?php esc_html_e( 'Nothing found.', 'Veldrin' ); ?></p>
    <?php endif; ?>
  </div>
</section>
<?php
get_footer();
?>


