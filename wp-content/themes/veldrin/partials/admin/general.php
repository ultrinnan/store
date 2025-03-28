<?php
if ($_POST) {
    $general_options = array();

    foreach ($_POST as $key => $value) {
        $general_options[$key] = ($value);
    }
    if (update_option('general_options', $general_options)){
        echo '<div id="message" class="updated notice notice-success is-dismissible"><p>Settings updated.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
    }
}
$result = get_option('general_options');
?>
<div class="wrap">
    <h1 class="wp-heading-inline">General settings</h1>

    <form method="POST">
        <div class="form-group">
            <label for="header_phone">Header phone:</label>
            <input type="tel" class="form-control" id="header_phone" name="header_phone" value="<?=stripslashes($result['header_phone']);?>">
        </div>

        <div class="form-group">
            <label for="footer_phone">Footer phone:</label>
            <input type="tel" class="form-control" id="footer_phone" name="footer_phone" value="<?=stripslashes($result['footer_phone']);?>">
        </div>

        <div class="form-group">
            <label for="email">E-mail:</label>
            <input type="email" class="form-control" id="email" name="email" value="<?=stripslashes($result['email']);?>">
        </div>

        <div class="form-group">
            <label for="address1">Address 1:</label>
            <textarea name="address1" id="address1" cols="30" rows="10"><?=stripslashes($result['address1']);?></textarea>
        </div>

        <div class="form-group">
            <label for="address2">Address 2:</label>
            <textarea name="address2" id="address2" cols="30" rows="10"><?=stripslashes($result['address2']);?></textarea>
        </div>

        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
        </p>
    </form>
</div>