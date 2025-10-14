<?php
$social = get_option('social_options');

if ($social) {
  echo '<div class="social-box">';
    foreach ($social as $key => $value) {
        if ($value) {
            $class = 'social ' . sanitize_html_class($key);
            $url   = esc_url($value);
            $title = esc_attr($key);
            echo '<a class="' . $class . '" href="' . $url . '" target="_blank" title="' . $title . '" rel="noopener noreferrer"></a>';
        }
    }
  echo '</div>';
}