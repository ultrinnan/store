<?php
/**
 * Plugin Name: Fixer – Complianz empty contact lines
 * Description: Removes empty address/telephone lines from Complianz legal documents (Serhii chose email-only contact details, 24 Nov 2026).
 * Version: 1.0.0
 */
defined( "ABSPATH" ) || exit;
add_filter( "cmplz_document_html", function ( $html ) {
	if ( ! is_string( $html ) ) {
		return $html;
	}
	return preg_replace( "#<span class=\"cmplz-contact-(address|telephone)\">\s*</span>\s*(<br\s*/?>)?\s*#i", "", $html );
}, 20 );
