<?php
get_header();
?>
<section>
  <div class="container">
    <h1><?php printf( esc_html__( 'Search results for: %s', 'Veldrin' ), '<span>"' . get_search_query() . '"</span>' ); ?></h1>

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
      <p><?php esc_html_e( 'No results found. Maybe try another search?', 'Veldrin' ); ?></p>
    <?php endif; ?>
  </div>
</section>
  
<?php
get_footer();
?>


