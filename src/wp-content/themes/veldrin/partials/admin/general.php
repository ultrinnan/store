<?php
if ( ! current_user_can('manage_options') ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'Veldrin' ) );
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    check_admin_referer( 'veldrin_general_settings' );

    $fields = array( 'header_phone','footer_phone','email','address1','address2' );
    $general_options = array();
    foreach ( $fields as $field ) {
        $value = isset($_POST[$field]) ? wp_unslash($_POST[$field]) : '';
        switch ( $field ) {
            case 'email':
                $general_options[$field] = $value ? sanitize_email( $value ) : '';
                break;
            case 'header_phone':
            case 'footer_phone':
                $general_options[$field] = $value ? sanitize_text_field( $value ) : '';
                break;
            case 'address1':
            case 'address2':
                $general_options[$field] = $value ? sanitize_textarea_field( $value ) : '';
                break;
            default:
                $general_options[$field] = $value ? sanitize_text_field( $value ) : '';
        }
    }

    if ( update_option( 'general_options', $general_options ) ) {
        echo '<div id="message" class="updated notice notice-success is-dismissible"><p>' . esc_html__( 'Settings updated.', 'Veldrin' ) . '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' . esc_html__( 'Dismiss this notice.', 'Veldrin' ) . '</span></button></div>';
    }
}
$result = get_option('general_options');
?>
<div class="wrap">
    <h1 class="wp-heading-inline">General settings</h1>

    <form method="POST">
        <?php wp_nonce_field( 'veldrin_general_settings' ); ?>
        <div class="form-group">
            <label for="header_phone">Header phone:</label>
            <input type="tel" class="form-control" id="header_phone" name="header_phone" value="<?= isset($result['header_phone']) ? esc_attr(stripslashes($result['header_phone'])) : '' ?>">
        </div>

        <div class="form-group">
            <label for="footer_phone">Footer phone:</label>
            <input type="tel" class="form-control" id="footer_phone" name="footer_phone" value="<?= isset($result['footer_phone']) ? esc_attr(stripslashes($result['footer_phone'])) : '' ?>">
        </div>

        <div class="form-group">
            <label for="email">E-mail:</label>
            <input type="email" class="form-control" id="email" name="email" value="<?= isset($result['email']) ? esc_attr(stripslashes($result['email'])) : '' ?>">
        </div>

        <div class="form-group">
            <label for="address1">Address 1:</label>
            <textarea name="address1" id="address1" cols="30" rows="10"><?= isset($result['address1']) ? esc_textarea(stripslashes($result['address1'])) : '' ?></textarea>
        </div>

        <div class="form-group">
            <label for="address2">Address 2:</label>
            <textarea name="address2" id="address2" cols="30" rows="10"><?= isset($result['address2']) ? esc_textarea(stripslashes($result['address2'])) : '' ?></textarea>
        </div>

        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
        </p>
    </form>
</div>