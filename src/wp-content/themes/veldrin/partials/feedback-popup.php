<?php
/**
 * Small corner prompt: contact CTA after a delay (see main.min.js).
 */
if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
	return;
}

$contacts_page = get_page_by_path( 'contacts' );
$contacts_url  = $contacts_page ? get_permalink( $contacts_page ) : home_url( '/contacts/' );
$contacts_url  = esc_url( $contacts_url );

if ( is_page( 'contacts' ) || ( $contacts_page && is_page( $contacts_page->ID ) ) ) {
	return;
}
?>
<div id="veldrin-feedback-popup" class="veldrin-feedback-popup" role="dialog" aria-labelledby="veldrin-feedback-popup-title" aria-hidden="true" hidden>
	<button type="button" class="veldrin-feedback-popup__close" aria-label="<?php echo esc_attr__( 'Close', 'veldrin' ); ?>">&times;</button>
	<p id="veldrin-feedback-popup-title" class="veldrin-feedback-popup__text">
		<?php esc_html_e( 'Does everything work as expected? Any questions or concerns?', 'veldrin' ); ?>
	</p>
	<a class="veldrin-feedback-popup__cta button" href="<?php echo $contacts_url; ?>">
		<?php esc_html_e( 'Ask a question!', 'veldrin' ); ?>
	</a>
</div>
