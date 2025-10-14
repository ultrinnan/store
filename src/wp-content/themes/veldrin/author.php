<?php
get_header();
?>
<main id="primary" class="site-main">
  <header class="page-header">
    <h1 class="page-title"><?php echo esc_html( get_the_author() ); ?></h1>
    <?php if ( get_the_author_meta( 'description' ) ) : ?>
      <div class="author-bio"><?php echo wp_kses_post( wpautop( get_the_author_meta( 'description' ) ) ); ?></div>
    <?php endif; ?>
  </header>

  <?php if ( have_posts() ) : ?>
    <?php while ( have_posts() ) : the_post(); ?>
      <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <header class="entry-header">
          <h2 class="entry-title"><a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a></h2>
        </header>
        <div class="entry-summary">
          <?php the_excerpt(); ?>
        </div>
      </article>
    <?php endwhile; ?>
    <?php the_posts_navigation(); ?>
  <?php else : ?>
    <p><?php esc_html_e( 'No posts found.', 'Veldrin' ); ?></p>
  <?php endif; ?>
</main>
<?php
get_footer();
?>


