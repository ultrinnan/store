<?php
get_header();
?>
<section>
  <div class="container">
    <h1 class="page-title"><?php echo esc_html( get_the_author() ); ?></h1>
    <?php if ( get_the_author_meta( 'description' ) ) : ?>
      <div class="author-bio"><?php echo wp_kses_post( wpautop( get_the_author_meta( 'description' ) ) ); ?></div>
    <?php endif; ?>

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


