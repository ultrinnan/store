<?php
$social = get_option('social_options');

if ($social) {
  echo '<div class="social-box">';
	foreach ($social as $key => $value) {
		if ($value) {
			?>
            <a class="social <?=$key?>" href="<?=$value?>" target="_blank" title="<?=$key?>"></a>
			<?php
		}
	}
  echo '</div>';
}