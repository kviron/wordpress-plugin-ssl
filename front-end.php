<?php
defined('ABSPATH') or die();
if ( ! function_exists( 'rsssl_secure_logged_in_cookie' ) ) {
	/**
	 * Set a secure cookie, but only if the site is enabled for SSL, not per page.
	 * This setting can be used on multisite as well, as it will decide per site what setting to use.
	 * @since 2.0.10
	 * */

	function rsssl_secure_logged_in_cookie( $secure_logged_in_cookie, $user_id, $secure ) {
		if ( rsssl_get_option('ssl_enabled') ) {
			return true;
		}
		return $secure_logged_in_cookie;
	}
	add_filter( 'secure_logged_in_cookie', 'rsssl_secure_logged_in_cookie', 10, 3 );
}

