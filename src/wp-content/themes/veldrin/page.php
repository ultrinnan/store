<?php
get_header();
?>
<section>
  <div class="container">
  <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
    <article <?php post_class(); ?>>
      <h1><?php the_title(); ?></h1>
      <div class="content">
        <?php the_content(); ?>
      </div>
    </article>
  <?php endwhile; endif; ?>
  </div>
</section>
<?php
get_footer();
?>


