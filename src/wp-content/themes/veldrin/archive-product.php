<?php
/**
 * The template for displaying product archives
 *
 * @package Veldrin
 */

get_header();
?>

<section>
  <div class="container archive-product">
    <?php
    /**
     * Hook: woocommerce_before_main_content.
     *
     * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
     * @hooked woocommerce_breadcrumb - 20
     */
    do_action( 'woocommerce_before_main_content' );
    if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>
        <h1><?php woocommerce_page_title(); ?></h1>
    <?php endif; 

    /**
     * Hook: woocommerce_archive_description.
     *
     * @hooked woocommerce_taxonomy_archive_description - 10
     * @hooked woocommerce_product_archive_description - 10
     */
    do_action( 'woocommerce_archive_description' );

    if ( woocommerce_product_loop() ) {

      /**
       * Hook: woocommerce_before_shop_loop.
       *
       * @hooked woocommerce_output_all_notices - 10
       * @hooked woocommerce_result_count - 20
       * @hooked woocommerce_catalog_ordering - 30
       */
      do_action( 'woocommerce_before_shop_loop' );

      woocommerce_product_loop_start();

      if ( wc_get_loop_prop( 'is_shortcode' ) ) {
        $columns = absint( wc_get_loop_prop( 'columns' ) );
        $GLOBALS['woocommerce_loop']['columns'] = $columns;
      }

      if ( wc_get_loop_prop( 'is_paginated' ) ) {
        $total    = wc_get_loop_prop( 'total' );
        $per_page = wc_get_loop_prop( 'per_page' );
        $current  = wc_get_loop_prop( 'current_page' );
        $total_pages = wc_get_loop_prop( 'total_pages' );
      }

      while ( have_posts() ) {
        the_post();

        /**
         * Hook: woocommerce_shop_loop.
         */
        do_action( 'woocommerce_shop_loop' );

        wc_get_template_part( 'content', 'product' );
      }

      woocommerce_product_loop_end();

      /**
       * Hook: woocommerce_after_shop_loop.
       *
       * @hooked woocommerce_pagination - 10
       */
      do_action( 'woocommerce_after_shop_loop' );
    } else {
      /**
       * Hook: woocommerce_no_products_found.
       *
       * @hooked wc_no_products_found - 10
       */
      do_action( 'woocommerce_no_products_found' );
    }

    /**
     * Hook: woocommerce_after_main_content.
     *
     * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
     */
    do_action( 'woocommerce_after_main_content' );

    /**
     * Hook: woocommerce_sidebar.
     *
     * @hooked woocommerce_get_sidebar - 10
     */
    do_action( 'woocommerce_sidebar' );
    ?>
  </div>
</section>

<?php
get_footer();
?>
