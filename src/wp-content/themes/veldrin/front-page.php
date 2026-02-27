<?php
get_header(); ?>

<!-- <section>
<div class="container">
	<h2 class="entry-title">Slider here later</h2>
</div>
</section> -->

<!-- Latest Products Section -->
<section>
	<div class="container latest-block">
		<h2 class="title">Our latest and greatest</h2>
		
		<?php
		// Featured products first (max 10) - product_visibility taxonomy, sorted by priority
		$featured_query = new WP_Query( array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'tax_query'      => array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'slug',
					'terms'    => array( 'featured' ),
				),
				array(
					'taxonomy' => 'product_visibility',
					'field'    => 'slug',
					'terms'    => array( 'exclude-from-catalog' ),
					'operator' => 'NOT IN',
				),
			),
		) );
		$all_featured = $featured_query->posts;
		// Sort by priority (lower = first), then date, then ID. Default priority = 1.
		usort( $all_featured, function( $a, $b ) {
			$prio_a = max( 1, (int) get_post_meta( $a->ID, '_featured_priority', true ) );
			$prio_b = max( 1, (int) get_post_meta( $b->ID, '_featured_priority', true ) );
			if ( $prio_a !== $prio_b ) {
				return $prio_a <=> $prio_b;
			}
			$date_a = strtotime( $a->post_date );
			$date_b = strtotime( $b->post_date );
			if ( $date_a !== $date_b ) {
				return $date_b <=> $date_a; // newer first
			}
			return $b->ID <=> $a->ID;
		} );
		$featured_posts = array_slice( $all_featured, 0, 10 );
		$featured_ids   = wp_list_pluck( $featured_posts, 'ID' );
		$need = 10 - count( $featured_ids );

		// Fill remaining slots with most popular (by views), then latest if needed
		$fill_posts = array();
		if ( $need > 0 ) {
			$popular_query = new WP_Query( array(
				'post_type'      => 'product',
				'posts_per_page' => $need,
				'post__not_in'   => $featured_ids,
				'post_status'    => 'publish',
				'meta_key'       => '_product_views_count',
				'orderby'        => array(
					'meta_value_num' => 'DESC',
					'date'           => 'DESC',
					'ID'             => 'DESC',
				),
			) );
			$fill_posts = $popular_query->posts;
			$filled_ids = wp_list_pluck( $fill_posts, 'ID' );
			$still_need = $need - count( $fill_posts );

			// Fallback: if no products have views yet, use latest by date
			if ( $still_need > 0 ) {
				$latest_query = new WP_Query( array(
					'post_type'      => 'product',
					'posts_per_page' => $still_need,
					'post__not_in'   => array_merge( $featured_ids, $filled_ids ),
					'post_status'    => 'publish',
					'orderby'        => array(
						'date' => 'DESC',
						'ID'   => 'DESC',
					),
				) );
				$fill_posts = array_merge( $fill_posts, $latest_query->posts );
			}
		}

		$latest_products = array_merge( $featured_posts, $fill_posts );
		$has_products = ! empty( $latest_products );
		?>
		<?php if ( $has_products ) : ?>
			<div class="products-grid">
				<?php foreach ( $latest_products as $post ) :
					setup_postdata( $post );
					global $product;
					$product = wc_get_product( $post );
					if ( ! $product ) {
						continue;
					}
					$product_id = get_the_ID();
					$product_image = get_the_post_thumbnail_url( $product_id, 'medium' );
					$product_title = get_the_title();
					$product_price = $product->get_price_html();
					$product_link = get_permalink();
				?>
					<div class="product-card">
						<a href="<?php echo esc_url($product_link); ?>" class="product-link">
							<?php if ($product_image) : ?>
								<div class="product-image">
									<img src="<?php echo esc_url($product_image); ?>" alt="<?php echo esc_attr($product_title); ?>">
								</div>
							<?php endif; ?>
							<div class="product-info">
								<h3 class="product-title"><?php echo esc_html($product_title); ?></h3>
								<div class="product-price"><?php echo $product_price; ?></div>
							</div>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
			
			<div class="see-all-products">
				<a href="<?php echo esc_url(get_permalink(wc_get_page_id('shop'))); ?>" class="btn-see-all">See all products</a>
			</div>
		<?php else : ?>
			<p>No products found.</p>
		<?php endif;
		wp_reset_postdata(); ?>
	</div>
</section>

<section>
	<div class="container second-shot">
		<div class="left-side">
			<p><strong>Super deals!</strong></p>
			<h2 class="title">Up to 50% off</h2>
			<p>🔥&nbsp;<strong>Get Quality Gear for Less!</strong></p>
			<p><strong>Explore our curated selection of used items – like new at a fraction of the price! 🏹💰</strong></p>
			<div class="wp-block-buttons is-layout-flex wp-container-core-buttons-is-layout-27eaf71a wp-block-buttons-is-layout-flex">
				<div class="wp-block-button is-style-fill"><a class="wp-block-button__link has-custom-font-size wp-element-button" href="https://veldrin.store/product-category/secondshot/" style="border-style:none;border-width:0px;font-size:clamp(14px, 0.875rem + ((1vw - 3.2px) * 0.208), 16px);">Check “SecondShot”</a></div>
			</div>
		</div>
		<div class="right-side">
			<figure class="wp-block-image size-full"><img width="1024" height="576" src="https://veldrin.store/wp-content/uploads/2025/01/472428633_17991480077776047_1187928364496114401_n-edited.jpg" alt=""></figure>
		</div>
	</div>
</section>

<section>
	<div class="container top-categories">
		<div class="column" style="background: url('https://veldrin.store/wp-content/uploads/2025/02/PXL_20250204_135729242.png') no-repeat center center; background-size: cover;">
			<h4 class="category-title"">Custom arrows</h4>
			<p class="-52de7820aa0befa19b497defbc51078d" style="margin-top:0;margin-bottom:0;padding-top:0;padding-bottom:0"><a href="https://veldrin.store/product/red-leather-boots/" data-type="link" data-id="https://veldrin.store/shop/">Shop Now</a></p>
		</div>

		<div class="column" style="background: url('https://veldrin.store/wp-content/uploads/2025/01/466301442_1096664248523697_5081023465224852960_n.jpg') no-repeat center center; background-size: cover;">
				<h4 class="category-title">Shoes and accessories</h4>
				<p class="-276cc12e34281eb6be892fba58f0e243" style="margin-top:0;margin-bottom:0;padding-top:0;padding-bottom:0"><a href="https://veldrin.store/product-category/tools/" data-type="link" data-id="https://veldrin.store/shop/">Shop Now</a></p>
		</div>

		<div class="column" style="background: url('https://veldrin.store/wp-content/uploads/2025/01/PXL_20241121_151700593.png') no-repeat center center; background-size: cover;">
				<h4 class="category-title">Tools and souvenirs</h4>
				<p class="-276cc12e34281eb6be892fba58f0e243" style="margin-top:0;margin-bottom:0;padding-top:0;padding-bottom:0"><a href="https://veldrin.store/product-category/tools/" data-type="link" data-id="https://veldrin.store/shop/">Shop Now</a></p>
		</div>
	</div>
</section>

<!-- gen images here later -->
 <section>
	<div class="container gen-block">
		<div class="right-side" style="background-image: url('https://veldrin.store/wp-content/uploads/2025/01/armour-painting.png');"></div>
		<div class="left-side">
			<h2 class="title">Unstoppable imagination</h2>
			<p>From custom accessories to full armor sets or stunning costumes, we bring your wildest dreams to life with attention to details and true craftsmanship</p>
			<div class="wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex">
				<div class="wp-block-button has-custom-font-size"><a class="wp-block-button__link has-text-align-left wp-element-button" href="https://veldrin.store/contacts">Contact for details</a></div>
			</div>
		</div>
		<div class="left-side">
			<h2 class="title">Quality Materials</h2>
			<p>We use only the highest-quality materials in our products, ensuring that they look great and last for years to come.</p>
		</div>
		<div class="right-side" style="background-image: url('https://veldrin.store/wp-content/uploads/2025/01/leather-copy.png');"></div>
	</div>
</section>

<section class="services">
	<div class="container our-services">
		<h2 class="title">Our services</h2>
		<div class="services-block">
			<div class="service-card">
				<img src="https://veldrin.store/wp-content/uploads/2025/01/457488246_380807271522582_8551271373445581880_n-edited.jpg" alt="side-quiver-owl">
				<h3 class="service-title">Custom equipment</h3>
				<p>If you need some custom equipment, like quivers, armguard or bags – we can create it for you!</p>
			</div>
			<div class="service-card">
				<img src="https://veldrin.store/wp-content/uploads/2025/01/arrow-repair-1.png" alt="arrow-repair">
				<h3 class="service-title">Arrow repair</h3>
				<p>Some arrows (or shafts) are so great, that you just cannot trow it away. We can repair old feathers, nocks, points, etc. Custom painting is also available!</p>
			</div>
			<div class="service-card">
				<img src="https://veldrin.store/wp-content/uploads/2025/01/457435389_3762637267337214_7731624224978544732_n-edited.jpg" alt="costume-creation">
				<h3 class="service-title">Costume creation</h3>
				<p>Express yourself with amazing LARP costume, create perfect medieval character of any sort of cosplay. Share the Idea and let’s create it!</p>
			</div>
		</div>
	</div>
</section>

<?php get_footer(); ?>


