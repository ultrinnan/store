<?php
get_header();
?>
<main id="primary" class="site-main">
  <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
      <header class="entry-header">
        <h1 class="entry-title"><?php the_title(); ?></h1>
      </header>
      <div class="entry-content">
        <?php the_content(); ?>
      </div>
      <footer class="entry-footer">
        <?php the_post_navigation(); ?>
      </footer>
      <?php if ( comments_open() || get_comments_number() ) : comments_template(); endif; ?>
    </article>
  <?php endwhile; endif; ?>
</main>
<?php
get_footer();
?>


