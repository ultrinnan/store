<?php
/**
 * The template for displaying My Account page
 *
 * @package Veldrin
 */

get_header();
?>

<section>
  <div class="container myaccount">
    <?php
    /**
     * Hook: woocommerce_before_main_content.
     *
     * @hooked woocommerce_output_content_wrapper - 10 (outputs opening divs for the content)
     * @hooked woocommerce_breadcrumb - 20
     */
    do_action( 'woocommerce_before_main_content' );
    ?>

    <header class="woocommerce-products-header">
      <h1 class="woocommerce-products-header__title page-title"><?php esc_html_e( 'My Account', 'Veldrin' ); ?></h1>
    </header>

    <?php
    /**
     * Hook: woocommerce_before_customer_login_form.
     */
    do_action( 'woocommerce_before_customer_login_form' );
    ?>

    <?php if ( is_user_logged_in() ) : ?>

      <?php
      /**
       * Hook: woocommerce_account_navigation.
       */
      do_action( 'woocommerce_account_navigation' );
      ?>

      <div class="woocommerce-MyAccount-content">
        <?php
        /**
         * Hook: woocommerce_account_content.
         *
         * @hooked woocommerce_account_dashboard - 10
         */
        do_action( 'woocommerce_account_content' );
        ?>
      </div>

    <?php else : ?>

      <div class="u-columns col2-set" id="customer_login">
        <div class="u-column1 col-1">
          <h2><?php esc_html_e( 'Login', 'Veldrin' ); ?></h2>

          <form class="woocommerce-form woocommerce-form-login login" method="post">

            <?php do_action( 'woocommerce_login_form_start' ); ?>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
              <label for="username"><?php esc_html_e( 'Username or email address', 'Veldrin' ); ?>&nbsp;<span class="required">*</span></label>
              <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" />
            </p>
            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
              <label for="password"><?php esc_html_e( 'Password', 'Veldrin' ); ?>&nbsp;<span class="required">*</span></label>
              <input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" />
            </p>

            <?php do_action( 'woocommerce_login_form' ); ?>

            <p class="form-row">
              <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
                <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" /> <span><?php esc_html_e( 'Remember me', 'Veldrin' ); ?></span>
              </label>
              <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
              <button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="<?php esc_attr_e( 'Log in', 'Veldrin' ); ?>"><?php esc_html_e( 'Log in', 'Veldrin' ); ?></button>
            </p>
            <p class="woocommerce-LostPassword lost_password">
              <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'Veldrin' ); ?></a>
            </p>

            <?php do_action( 'woocommerce_login_form_end' ); ?>

          </form>
        </div>

        <?php if ( 'yes' === get_option( 'woocommerce_enable_myaccount_registration' ) ) : ?>

          <div class="u-column2 col-2">
            <h2><?php esc_html_e( 'Register', 'Veldrin' ); ?></h2>

            <form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?> >

              <?php do_action( 'woocommerce_register_form_start' ); ?>

              <?php if ( 'no' === get_option( 'woocommerce_registration_generate_username' ) ) : ?>

                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                  <label for="reg_username"><?php esc_html_e( 'Username', 'Veldrin' ); ?>&nbsp;<span class="required">*</span></label>
                  <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo ( ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" />
                </p>

              <?php endif; ?>

              <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="reg_email"><?php esc_html_e( 'Email address', 'Veldrin' ); ?>&nbsp;<span class="required">*</span></label>
                <input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" />
              </p>

              <?php if ( 'no' === get_option( 'woocommerce_registration_generate_password' ) ) : ?>

                <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                  <label for="reg_password"><?php esc_html_e( 'Password', 'Veldrin' ); ?>&nbsp;<span class="required">*</span></label>
                  <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" />
                </p>

              <?php else : ?>

                <p><?php esc_html_e( 'A password will be sent to your email address.', 'Veldrin' ); ?></p>

              <?php endif; ?>

              <?php do_action( 'woocommerce_register_form' ); ?>

              <p class="woocommerce-form-row form-row">
                <?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
                <button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit" name="register" value="<?php esc_attr_e( 'Register', 'Veldrin' ); ?>"><?php esc_html_e( 'Register', 'Veldrin' ); ?></button>
              </p>

              <?php do_action( 'woocommerce_register_form_end' ); ?>

            </form>
          </div>

        <?php endif; ?>
      </div>

    <?php endif; ?>

    <?php
    /**
     * Hook: woocommerce_after_main_content.
     *
     * @hooked woocommerce_output_content_wrapper_end - 10 (outputs closing divs for the content)
     */
    do_action( 'woocommerce_after_main_content' );
    ?>
  </div>
</section>

<?php
get_footer();
?>
