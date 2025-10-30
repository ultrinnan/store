<?php
get_header();
?>
<section>
  <div class="container search-results">
    <h1 class="search-title"><?php printf( esc_html__( 'Search results for: %s', 'Veldrin' ), '<span class="search-query">"' . get_search_query() . '"</span>' ); ?></h1>

    <?php if ( have_posts() ) : ?>
      <div class="search-grid">
        <?php while ( have_posts() ) : the_post();
          $post_type = get_post_type();
          $is_product = ($post_type === 'product');

          // Get thumbnail
          $thumbnail_id = get_post_thumbnail_id();
          $thumbnail_url = $thumbnail_id ? get_the_post_thumbnail_url(get_the_ID(), 'medium') : '';

          // For products, get price
          $price_html = '';
          if ($is_product && function_exists('wc_get_product')) {
            $product = wc_get_product(get_the_ID());
            if ($product) {
              $price_html = $product->get_price_html();
            }
          }
        ?>
          <article <?php post_class('search-card'); ?>>
            <a href="<?php the_permalink(); ?>" class="search-card-link">
              <?php if ($thumbnail_url) : ?>
                <div class="search-card-image">
                  <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php echo esc_attr(get_the_title()); ?>">
                </div>
              <?php else : ?>
                <div class="search-card-image search-card-no-image">
                  <span>📄</span>
                </div>
              <?php endif; ?>

              <div class="search-card-content">
                <span class="search-card-type"><?php echo esc_html(ucfirst($post_type)); ?></span>
                <h2 class="search-card-title"><?php the_title(); ?></h2>

                <?php if ($is_product && $price_html) : ?>
                  <div class="search-card-price"><?php echo $price_html; ?></div>
                <?php else : ?>
                  <div class="search-card-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 15, '...'); ?></div>
                <?php endif; ?>
              </div>
            </a>
          </article>
        <?php endwhile; ?>
      </div>

      <div class="search-navigation">
        <?php the_posts_navigation(); ?>
      </div>
    <?php else : ?>
      <div class="search-no-results">
        <p><?php esc_html_e( 'No results found. Maybe try another search?', 'Veldrin' ); ?></p>
        <div class="search-form-wrapper">
          <?php get_search_form(); ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php
get_footer();
?>


