<?php
/*
 * Template Name: Articles page
 */

get_header();
?>
    <section class="events">
        <div class="container">
            <div class="content">
                <div class="article">
					<?php if (have_posts()): while (have_posts()): the_post(); ?>
						<?php the_content(); ?>

          <?php endwhile; endif; ?>
                </div>
            </div>
        </div>
    </section>

<?php get_footer(); ?>