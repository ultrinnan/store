<?php
/**
 * Template Name: Articles
 * Description: Display a list of blog posts with pagination
 */

get_header();
?>
<section>
  <div class="container articles-page">
    <?php
    // Store page permalink before entering any loops
    $articles_page_url = '';
    if ( have_posts() ) : while ( have_posts() ) : the_post();
      $articles_page_url = get_permalink();
    ?>
      <h1><?php the_title(); ?></h1>
    <?php endwhile; endif; ?>

    <?php
    // Get current page number (for page templates use 'paged' or 'page' query var)
    $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : ( ( get_query_var( 'page' ) ) ? get_query_var( 'page' ) : 1 );

    // Query for posts
    $args = array(
      'post_type'      => 'post',
      'posts_per_page' => 10,
      'paged'          => $paged,
      'post_status'    => 'publish',
      'orderby'        => 'date',
      'order'          => 'DESC',
    );

    $articles_query = new WP_Query( $args );

    if ( $articles_query->have_posts() ) : ?>
      <div class="articles-list">
        <?php while ( $articles_query->have_posts() ) : $articles_query->the_post(); ?>
          <article <?php post_class( 'article-item' ); ?>>
            <div class="article-image">
              <a href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
                <?php
                if ( has_post_thumbnail() ) {
                  the_post_thumbnail( 'medium_large' );
                } else {
                  $default_image = get_stylesheet_directory_uri() . '/img/default_article.png';
                  echo '<img src="' . esc_url( $default_image ) . '" alt="' . esc_attr( get_the_title() ) . '" />';
                }
                ?>
              </a>
            </div>

            <div class="article-content">
              <h2 class="article-title">
                <a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a>
              </h2>

              <div class="article-meta">
                <time class="article-date" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                  <?php echo get_the_date(); ?>
                </time>
              </div>

              <div class="article-excerpt">
                <?php
                if ( has_excerpt() ) {
                  the_excerpt();
                } else {
                  echo wp_trim_words( get_the_content(), 30, '...' );
                }
                ?>
              </div>

              <a href="<?php the_permalink(); ?>" class="article-read-more">
                <?php esc_html_e( 'Read more', 'veldrin' ); ?>
              </a>
            </div>
          </article>
        <?php endwhile; ?>
      </div>

      <?php
      // Pagination
      $total_pages = $articles_query->max_num_pages;

      if ( $total_pages > 1 ) :
        $current_page = max( 1, $paged );
        ?>
        <nav class="woocommerce-pagination articles-pagination" aria-label="<?php esc_attr_e( 'Articles pagination', 'veldrin' ); ?>">
          <?php
          // Pagination with proper URL structure using stored page URL
          $page_url = trailingslashit( $articles_page_url );

          echo paginate_links( array(
            'base'      => $page_url . 'page/%#%/',
            'format'    => '',
            'add_args'  => false,
            'current'   => $current_page,
            'total'     => $total_pages,
            'prev_text' => '&larr;',
            'next_text' => '&rarr;',
            'mid_size'  => 2,
            'end_size'  => 1,
            'type'      => 'list',
          ) );
          ?>
        </nav>
      <?php endif; ?>

      <?php
      // Reset post data
      wp_reset_postdata();
    else : ?>
      <p class="no-articles"><?php esc_html_e( 'No articles found.', 'veldrin' ); ?></p>
    <?php endif; ?>
  </div>
</section>
<?php
get_footer();
?>
