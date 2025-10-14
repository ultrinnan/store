<?php
//pingback and trackback disabing
function true_disable_self_ping( &$links ) {
  foreach ( $links as $l => $link )
    if ( 0 === strpos( $link, get_option( 'home' ) ) )
      unset($links[$l]);
}
add_action( 'pre_ping', 'true_disable_self_ping' );

//hide WordPress version
function true_remove_wp_version_wp_head_feed() {
  return '';
}
add_filter('the_generator', 'true_remove_wp_version_wp_head_feed');

//delete README with wp version
// $file=$_SERVER['DOCUMENT_ROOT'].'/readme.html'; // will be disabled using .htaccess
// if (file_exists($file)) {
//     unlink($file);
// }