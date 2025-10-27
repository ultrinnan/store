<?php
get_header();
?>
<section>
  <div class="container">
    <?php the_archive_title( '<h1 class="page-title">', '</h1>' ); ?>
    <?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>

    <?php if ( have_posts() ) : ?>
      <?php while ( have_posts() ) : the_post(); ?>
        <article <?php post_class(); ?>>
            <h2>
              <a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a>
            </h2>
          <div class="entry-summary">
            <?php the_excerpt(); ?>
          </div>
        </article>
      <?php endwhile; ?>
      <?php the_posts_navigation(); ?>
    <?php else : ?>
      <p><?php esc_html_e( 'No posts found.', 'Veldrin' ); ?></p>
    <?php endif; ?>
  </div>
</section>
<?php
get_footer();
?>


