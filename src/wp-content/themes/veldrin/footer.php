<?php
?>

</main>
<footer>
  <div class="footer-container">
    <div class="footer-block">
      <h3>Veldrin Craftworks</h3>
      <?php get_search_form(); ?>
      <div>We work with:</div>
      <div class="payment-methods">
        <img src="<?=get_template_directory_uri()?>/../veldrin/img/icons/Visa.svg" alt="Visa logo" loading="lazy">
        <img src="<?=get_template_directory_uri()?>/../veldrin/img/icons/Mastercard.svg" alt="Mastercard logo" loading="lazy">
<!--        <img src="--><?php //=get_template_directory_uri()?><!--/../veldrin/img/icons/PayPal.svg" alt="PayPal logo" loading="lazy">-->
        <img src="<?=get_template_directory_uri()?>/../veldrin/img/icons/ApplePay.svg" alt="ApplePay logo" loading="lazy">
        <img src="<?=get_template_directory_uri()?>/../veldrin/img/icons/GooglePay.svg" alt="GooglePay logo" loading="lazy">
      </div>
    </div>
    <div class="footer-block">
        <?php wp_nav_menu([
            'theme_location' => 'footer_shop',
            'container' => false,
            'menu_class' => 'footer-menu'
        ]);?>
    </div>
    <div class="footer-block">
        <?php wp_nav_menu([
            'theme_location' => 'footer',
            'container' => false,
            'menu_class' => 'footer-menu'
        ]);?>
    </div>
    <div class="footer-block">
      <h4>Follow us!</h4>
        <?php
        get_template_part( 'partials/social' );
        ?>
    </div>
  </div>
  <div class="copyright">
    <div class="copy-left">
        <?=date('Y');?> &copy; Veldrin Craftworks
    </div>
    <div class="copy-right">
        <?php wp_nav_menu([
            'theme_location' => 'bottom',
            'container' => false,
            'menu_class' => 'bottom-menu'
        ]);?>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>

<?php
if (defined('WP_DEBUG') && WP_DEBUG === true) {
    echo '<div class="debugger">debug mode ON</div>';
}
?>

</body>
</html>
