<?php defined( 'ABSPATH' ) or die();
/**
 * Disable changed debug log location
 */
add_action('admin_init', function(){
	if ( rsssl_is_in_deactivation_list('debug-log') ){
		rsssl_revert_debug_log_location();
	}
});

/**
 * Move debug.log to /debug_randomString/ directory
 * @return void
 * @since 6.0
 */

add_action( 'rsssl_after_saved_fields', 'rsssl_change_debug_log_location', 40 );
function rsssl_change_debug_log_location() {
	if ( !rsssl_user_can_manage() && !rsssl_is_logged_in_rest() ) {
		return;
	}

	//only change if currently default location
	if ( rsssl_debug_log_value_is_default() ) {
		$wpconfig_path = rsssl_find_wp_config_path();
		$wpconfig      = file_get_contents( $wpconfig_path );
		if ( ( strlen( $wpconfig ) != 0 ) && is_writable( $wpconfig_path ) ) {
			// Random folder suffix string
			$debug_log_folder_suffix = get_site_option( 'rsssl_debug_log_folder_suffix' );
			if ( ! $debug_log_folder_suffix ) {
				$debug_log_folder_suffix = strtolower( rsssl_generate_random_string( 10 ) );
				update_site_option( 'rsssl_debug_log_folder_suffix', $debug_log_folder_suffix );
			}
			$new_debug_log_folder = trailingslashit( ABSPATH . 'wp-content/debug_' . $debug_log_folder_suffix );
			// Create new debug_randomstring folder
			if ( ! file_exists( $new_debug_log_folder ) ) {
				mkdir( $new_debug_log_folder );
			}
			$new_debug_log_name = 'debug.log';
			$new_debug_log_path = trim( $new_debug_log_folder ) . $new_debug_log_name;

			// Copy over current content if debug.log exists
			if ( file_exists( WP_CONTENT_DIR . '/debug.log' ) ) {
				$old_debug_log = file_get_contents( WP_CONTENT_DIR . '/debug.log' );
			} else {
				$old_debug_log = '';
			}

			file_put_contents( $new_debug_log_path, $old_debug_log );
			$regex_rsssl_debug_log = "/^\s*define\([ ]{0,3}[\'|\"]WP_DEBUG_LOG[\'|\"][ ]{0,3},[ ]{0,3}(.*)[ ]{0,2}\);/m";
			if ( preg_match( $regex_rsssl_debug_log, $wpconfig, $matches ) ) {
				if ( $matches[0] ) {
					$wpconfig = preg_replace( $regex_rsssl_debug_log, "define( 'WP_DEBUG_LOG',  ABSPATH . 'wp-content/debug_$debug_log_folder_suffix/debug.log');", $wpconfig );
				}
				file_put_contents( $wpconfig_path, $wpconfig );
			}
		}
	}

	//ensure the public file is cleared and filled with bogus
	if ( rsssl_debug_log_file_exists_in_default_location() ) {
		$debug_log = WP_CONTENT_DIR . '/debug.log';
		if ( file_exists( $debug_log ) ) {
			file_put_contents($debug_log, 'Access denied');
		}
	}
}

/**
 * Revert to default debug.log location
 * @return void
 * @since 6.0
 */
function rsssl_revert_debug_log_location() {
	$wpconfig_path = rsssl_find_wp_config_path();
	$wpconfig      = file_get_contents( $wpconfig_path );
	// Get current declaration
	$rsssl_debug_log = ABSPATH . 'wp-content/debug_' . get_site_option( 'rsssl_debug_log_folder_suffix' ) . '/debug.log';

	if ( file_exists( $rsssl_debug_log ) && ! empty( $rsssl_debug_log ) ) {
		$contents = file_get_contents( $rsssl_debug_log );
	} else {
		$contents = '';
	}

	file_put_contents( WP_CONTENT_DIR . '/debug.log', $contents );
	// Check if this declaration exists in wp-config.php
	$regex_rsssl_debug_log = "/^\s*define\([ ]{0,2}[\'|\"]WP_DEBUG_LOG[\'|\"][ ]{0,2},[ ]{0,2}(.*)[ ]{0,2}\);/m";
	// If wp-config is writable, remove RSSSL debug.log path and uncomment regular debug.log declaration
	if ( is_writable( $wpconfig_path ) && preg_match( $regex_rsssl_debug_log, $wpconfig, $matches ) ) {
		$wpconfig = preg_replace( $regex_rsssl_debug_log, "define( 'WP_DEBUG_LOG', true);", $wpconfig );
		file_put_contents( $wpconfig_path, $wpconfig );
		rsssl_remove_from_deactivation_list('debug-log');
	}

	//cleanup file
	if ( file_exists( $rsssl_debug_log ) ) {
		unlink( $rsssl_debug_log );
		rmdir( ABSPATH . 'wp-content/debug_' . get_site_option( 'rsssl_debug_log_folder_suffix' ) );
	}
}