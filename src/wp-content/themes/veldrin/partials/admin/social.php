<?php
if ($_POST) {
    $social_options = array();

    foreach ($_POST as $key => $value) {
        $social_options[$key] = $value;
    }
	if (update_option('social_options', $social_options)){
		echo '<div id="message" class="updated notice notice-success is-dismissible"><p>Settings updated.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
	}
}
$result = get_option('social_options');

//todo: dynamic fields
?>
<div class="wrap admin_custom">
    <h1 class="wp-heading-inline">Social settings</h1>

    <form method="POST">

        <div class="form-group">
            <label for="facebook">Facebook link:</label>
            <input type="url" class="form-control" id="facebook" name="facebook" value="<?=$result['facebook']??null?>">
        </div>

        <div class="form-group">
            <label for="twitter">Twitter link:</label>
            <input type="url" class="form-control" id="twitter" name="twitter" value="<?=$result['twitter']??null?>">
        </div>

        <div class="form-group">
            <label for="instagram">Instagram link:</label>
            <input type="url" class="form-control" id="instagram" name="instagram" value="<?=$result['instagram']??null?>">
        </div>

        <div class="form-group">
            <label for="youtube">Youtube link:</label>
            <input type="url" class="form-control" id="youtube" name="youtube" value="<?=$result['youtube']??null?>">
        </div>

        <p class="submit">
            <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
        </p>
    </form>
</div>