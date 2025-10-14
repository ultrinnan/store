<?php if ( is_active_sidebar( 'primary' ) ) : ?>
<aside id="secondary" class="widget-area" role="complementary" aria-label="Sidebar">
  <?php dynamic_sidebar( 'primary' ); ?>
</aside>
<?php endif; ?>