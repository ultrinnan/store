<?php
if ( ! current_user_can('manage_options') ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'Veldrin' ) );
}

if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    check_admin_referer( 'veldrin_social_settings' );

    $fields = array( 'facebook', 'twitter', 'instagram', 'youtube' );
    $social_options = array();
    foreach ( $fields as $field ) {
        $value = isset($_POST[$field]) ? wp_unslash($_POST[$field]) : '';
        $social_options[$field] = $value ? esc_url_raw( $value ) : '';
    }

    if ( update_option( 'social_options', $social_options ) ) {
        echo '<div id="message" class="updated notice notice-success is-dismissible"><p>' . esc_html__( 'Settings updated.', 'Veldrin' ) . '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">' . esc_html__( 'Dismiss this notice.', 'Veldrin' ) . '</span></button></div>';
    }
}
$result = get_option('social_options');

//todo: dynamic fields
?>
<div class="wrap admin_custom">
    <h1 class="wp-heading-inline">Social settings</h1>

    <form method="POST">
        <?php wp_nonce_field( 'veldrin_social_settings' ); ?>

        <div class="form-group">
            <label for="facebook">Facebook link:</label>
            <input type="url" class="form-control" id="facebook" name="facebook" value="<?= isset($result['facebook']) ? esc_attr($result['facebook']) : '' ?>">
        </div>

        <div class="form-group">
            <label for="twitter">Twitter link:</label>
            <input type="url" class="form-control" id="twitter" name="twitter" value="<?= isset($result['twitter']) ? esc_attr($result['twitter']) : '' ?>">
        </div>

        <div class="form-group">
            <label for="instagram">Instagram link:</label>
            <input type="url" class="form-control" id="instagram" name="instagram" value="<?= isset($result['instagram']) ? esc_attr($result['instagram']) : '' ?>">
        </div>

        <div class="form-group">
            <label for="youtube">Youtube link:</label>
            <input type="url" class="form-control" id="youtube" name="youtube" value="<?= isset($result['youtube']) ? esc_attr($result['youtube']) : '' ?>">
        </div>

        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
        </p>
    </form>
</div>