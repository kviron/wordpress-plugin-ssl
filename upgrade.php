<?php
defined('ABSPATH') or die();

/**
 * Run Upgrade procedure
 */
function rsssl_pro_upgrade() {
	#only run upgrade check if cron, or if admin.
	if ( !is_admin() && !wp_doing_cron() ) {
		return;
	}

	$prev_version = get_option( 'rsssl-pro-current-version', false );
	/**
	 * Set a "first version" variable, so we can check if some notices need to be shown
	 */
	if ( ! get_option( 'rsssl_first_version' ) ) {
		update_option( 'rsssl_first_version', rsssl_pro_version, false );
	}

	if ( rsssl_pro_version === $prev_version ) {
		return;
	}

	//wait with upgrading until header check completed
	if ( RSSSL_PRO()->headers->is_header_check_running() ) {
		return;
	}

	if ( $prev_version && version_compare( $prev_version, '4.1.3', '<' ) ) {
		$upgrade_insecure_requests = get_site_option('rsssl_content_security_policy');
		if ($upgrade_insecure_requests) {
			rsssl_update_option('rsssl_upgrade_insecure_requests', $upgrade_insecure_requests);
		}
		if ( get_site_option('rsssl_enable_csp_reporting') ) {
			rsssl_update_option('content_security_policy_status', 'report-only');
		}

		if ( get_site_option('rsssl_add_csp_rules_to_htaccess') ) {
			rsssl_update_option('content_security_policy_status', 'enforce');
		}

		delete_site_option('rsssl_enable_csp_reporting');
		delete_option('rsssl_enable_csp_reporting');
		delete_site_option('rsssl_add_csp_rules_to_htaccess');
		delete_option('rsssl_add_csp_rules_to_htaccess');
		delete_site_option('rsssl_csp_reporting_dismissed_timestamp');
		delete_option('rsssl_csp_reporting_dismissed_timestamp');
		delete_option('rsssl_pro_csp_notice_next_steps_notice_postponed');
		delete_site_option('rsssl_pro_csp_notice_next_steps_notice_postponed');

		// Defaults have been set before
		update_site_option('rsssl_pro_defaults_set', true );
	}

	if ( $prev_version && version_compare( $prev_version, '5.2', '<' ) ) {
		delete_site_option('rsssl_enable_php_headers');
	}

	if ( $prev_version && version_compare( $prev_version, '5.3.1', '<' ) ) {

		if ( get_site_option('rsssl_cross_origin_policies' ) === 'same-origin' ) {
			rsssl_update_option('block_third_party_popups', 'yes' );
			rsssl_update_option('share_resources_third_parties', 'no' );
		}

		if ( get_site_option('rsssl_cross_origin_policies') === 'cross-origin') {
			rsssl_update_option('block_third_party_popups', 'no' );
			rsssl_update_option('share_resources_third_parties', 'yes' );
		}

		if ( get_site_option('rsssl_cross_origin_policies') === 'disabled' ) {
			rsssl_update_option('block_third_party_popups', 'no' );
			rsssl_update_option('share_resources_third_parties', 'yes' );
		}

		delete_site_option('rsssl_cross_origin_policies' );
		delete_option('rsssl_cross_origin_policies' );
	}

	if ( $prev_version && version_compare( $prev_version, '5.4.0', '<' ) ) {
		$permissions_policy = RSSSL_PRO()->admin->get_networkwide_option('rsssl_permissions_policy');
		$obsolete_features = array(
			'gyroscope',
			'magnetometer',
			'picture-in-picture',
			'sync-xhr',
			'usb',
			'interest-cohort',
		);

		foreach ( $obsolete_features as $obsolete_feature ) {
			if ( isset ( $permissions_policy[$obsolete_feature] ) ) {
				unset ( $permissions_policy[$obsolete_feature]);
			}
		}

		$new_features = array(
			'display-capture' => '*',
		);

		foreach ( $new_features as $new_feature => $value ) {
			if ( ! isset( $permissions_policy[$new_feature] ) ) {
				$permissions_policy[ $new_feature ] = $value;
			}
		}

		RSSSL_PRO()->admin->update_networkwide_option('rsssl_permissions_policy', $permissions_policy );
		RSSSL_PRO()->admin->delete_networkwide_option( 'rsssl_expect_ct' );
		RSSSL_PRO()->headers->insert_security_headers( true );
	}

	if ( $prev_version && version_compare( $prev_version, '5.5.1', '<' ) ) {
		if ( RSSSL_PRO()->admin->get_networkwide_option('rsssl_security_headers_method') === 'php' ) {
			RSSSL_PRO()->admin->update_networkwide_option( 'rsssl_security_headers_method', 'advancedheaders' );
			RSSSL_PRO()->headers->insert_security_headers( true );
		}
	}

	if ( $prev_version && version_compare( $prev_version, '5.5.4', '<' ) ) {
		if ( RSSSL_PRO()->admin->get_networkwide_option('rsssl_security_headers_method') === 'advancedheaders' ) {
			RSSSL_PRO()->headers->insert_security_headers( true );
		}
		if ( RSSSL_PRO()->admin->get_networkwide_option('rsssl_security_headers_method') === 'htaccess' ) {
			RSSSL_PRO()->admin->update_networkwide_option( 'rsssl_security_headers_method', 'advancedheaders' );
			RSSSL_PRO()->headers->insert_security_headers( true );
		}
	}

	//upgrade advanced headers to relative path
	if ( $prev_version && version_compare( $prev_version, '5.5.5', '<' ) ) {
		RSSSL_PRO()->headers->remove_advanced_headers();
		RSSSL_PRO()->headers->insert_security_headers( true );
	}
	if ( $prev_version && version_compare( $prev_version, '6.0.0', '<' ) ) {
		if ( is_multisite() && rsssl_is_networkwide_active() ) {
			$new_options = get_site_option('rsssl_options', []);
		} else {
			$new_options = get_option('rsssl_options', []);
		}

		RSSSL_PRO()->admin->remove_htaccess_rules('Really_Simple_SSL_SECURITY_HEADERS', true );
		delete_transient( 'rsssl_show_nginxconf_notice' );
		RSSSL_PRO()->admin->remove_htaccess_rules( 'Really_Simple_SSL_CSP_Report_Only' );
		RSSSL_PRO()->admin->remove_htaccess_rules( 'Really_Simple_SSL_Content_Security_Policy' );

		$permissions_policy = RSSSL_PRO()->admin->get_networkwide_option( 'rsssl_permissions_policy' );
		$new_permissions_policy = [];
		$name_mapping = [
			'accelerometer'   => 'Accelerometer',
			'autoplay'        => 'Autoplay',
			'camera'          => 'Camera',
			'encrypted-media' => 'Encrypted Media',
			'fullscreen'      => 'Fullscreen',
			'geolocation'     => 'Geolocation',
			'microphone'      => 'Microphone',
			'midi'            => 'Midi',
			'payment'         => 'Payment',
			'display-capture' => 'Display Capture',
		];

		if ( !is_array($permissions_policy)) $permissions_policy = [];
		foreach ( $permissions_policy as $name => $value ) {
			if (!isset($name_mapping[$name])) {
				continue;
			}
			$value = $value === 'none' ? '()' : $value;
			//sanitize value
			if (!in_array($value, ['*', '()','self'])) {
				$value = 'self';
			}
			$new_permissions_policy[] = [
				'id' => $name,
				'title' => $name_mapping[$name],
				'status' => 1,
				'value' => $value,
			];
		}
		$new_options['permissions_policy'] = $new_permissions_policy;
		$new_options['enable_permissions_policy'] = RSSSL_PRO()->admin->get_networkwide_option('rsssl_turn_on_permissions_policy');
		$new_options['x_content_type_options'] = RSSSL_PRO()->admin->get_networkwide_option('rsssl_x_content_type_options');
		if ( RSSSL_PRO()->admin->get_networkwide_option('rsssl_no_referrer_when_downgrade') ) {
			$new_options['referrer_policy'] = 'strict-origin-when-cross-origin';
		} else {
			$new_options['referrer_policy'] = 'disabled';
		}
		$new_options['x_xss_protection'] = RSSSL_PRO()->admin->get_networkwide_option('rsssl_x_xss_protection');
		$new_options['admin_mixed_content_fixer'] = RSSSL_PRO()->admin->get_networkwide_option('rsssl_admin_mixed_content_fixer');
		$new_options['upgrade_insecure_requests'] = RSSSL_PRO()->admin->get_networkwide_option('rsssl_upgrade_insecure_requests');
		$new_options['license'] = RSSSL_PRO()->admin->get_networkwide_option('rsssl_pro_license_key');

		if ( RSSSL_PRO()->admin->get_networkwide_option('rsssl_x_frame_options') ) {
			$new_options['x_frame_options'] = 'SAMEORIGIN';
		} else {
			$new_options['x_frame_options'] = 'disabled';
		}
		if ( RSSSL_PRO()->admin->get_networkwide_option('rsssl_content_security_policy') === 'enforce' ) {
			$new_options['csp_status'] = 'enforce';
		} else if ( RSSSL_PRO()->admin->get_networkwide_option('rsssl_content_security_policy') === 'report-only' ) {
			$new_options['csp_status'] = 'learning_mode';
		} else {
			$new_options['csp_status'] = 'disabled';
		}
		$block_third_party_popups = RSSSL_PRO()->admin->get_networkwide_option('rsssl_block_third_party_popups');
		$share_resources_third_parties = RSSSL_PRO()->admin->get_networkwide_option('rsssl_share_resources_third_parties');
		RSSSL_PRO()->admin->delete_networkwide_option('rsssl_block_third_party_popups');
		RSSSL_PRO()->admin->delete_networkwide_option('rsssl_share_resources_third_parties');
		// Cors headers
		if ( $block_third_party_popups === 'no' ) {
			$new_options['cross_origin_opener_policy'] = 'same-origin-allow-popups';
		} else if ( $block_third_party_popups === 'yes' ) {
			$new_options['cross_origin_opener_policy'] = 'same-origin';
		}

		if ( $share_resources_third_parties === 'no' ) {
			$new_options['cross_origin_resource_policy'] = 'same-origin';
		} else if ( $share_resources_third_parties === 'yes' ) {
			$new_options['cross_origin_resource_policy'] = 'cross-origin';
		} else if ( $share_resources_third_parties === 'yes_own_domain' ) {
			$new_options['cross_origin_resource_policy'] = 'same-site';
		}
		$new_options['cross_origin_embedder_policy'] = 'disabled';
		$hsts = RSSSL_PRO()->admin->get_networkwide_option('rsssl_hsts');
		$hsts_preload = RSSSL_PRO()->admin->get_networkwide_option('rsssl_hsts_preload');
		$new_options['hsts'] = $hsts;
		$new_options['hsts_preload'] = $hsts_preload;
		$new_options['hsts_subdomains'] = $hsts_preload;
		$new_options['hsts_max_age'] = $hsts_preload ? '63072000' : '31536000';

		if ( is_multisite() ){
			$network_options = get_site_option('rlrsssl_network_options');
			$mixed_content_admin = isset($network_options["mixed_content_admin"]) ? $network_options["mixed_content_admin"] : false;
			$new_options['admin_mixed_content_fixer'] = $mixed_content_admin;
		}

		if ( is_multisite() && rsssl_is_networkwide_active() ) {
			update_site_option( 'rsssl_options', $new_options );
		} else {
			update_option( 'rsssl_options', $new_options );
		}
	}

	if ( $prev_version && version_compare( $prev_version, '6.0.3', '<' ) ) {
		do_action( "rsssl_update_rules" );
	}

	// Upgrade x_xss_protection
	if ( $prev_version && version_compare( $prev_version, '6.0.3', '<' ) ) {
		if ( rsssl_get_option( 'x_xss_protection') == '1' ) {
			rsssl_update_option('x_xss_protection', 'zero');
		}
	}

	// Update multisite users which have the change DB prefix enabled and where some database values have not been updated yet. See rename-db-prefix
	if ( $prev_version && version_compare( $prev_version, '6.2.1', '<' ) && is_multisite() && rsssl_get_option( 'rename_db_prefix' ) == '1' ) {
		global $wpdb;
		$new_prefix = get_site_option('rsssl_db_prefix');
		$to_update = array(
			1 => array (
				'table' => 'usermeta',
				'column' => 'meta_key',
				'value_no_prefix' => 'capabilities',
			),
			2 => array(
				'table' => 'usermeta',
				'column' => 'meta_key',
				'value_no_prefix' => 'user_level',
			),
			3 => array(
				'table' => 'usermeta',
				'column' => 'meta_key',
				'value_no_prefix' => 'autosave_draft_ids',
			),
			4 => array(
				'table' => 'options',
				'column' => 'option_name',
				'value_no_prefix' => 'user_roles',
			),
		);

		$sites = get_sites();
		foreach ( $to_update as $key => $option ) {
			$table = $option['table'];
			$column = $option['column'];
			$value_no_prefix = $option['value_no_prefix'];
			foreach ($sites as $site) {
				$blog_id = $site->blog_id;
				$new_prefix_plus_blog_id = $new_prefix . $blog_id . '_';
				$wp_prefix = 'wp_';
				$wp_prefix_plus_blog_id = $wp_prefix . $blog_id . '_';
				$table_plus_blog_id = $blog_id . '_'. $table;
				switch_to_blog($site->blog_id);
				if ( ! is_main_site() ) {
					if (isset ($option['table']) && $option['table'] === 'options') {
						$wpdb->query("UPDATE `$new_prefix$table_plus_blog_id` set `$column` = '$new_prefix_plus_blog_id$value_no_prefix' where `$column` = '$wp_prefix_plus_blog_id$value_no_prefix'");
					} else {
						$wpdb->query("UPDATE `$new_prefix$table` set `$column` = '$new_prefix_plus_blog_id$value_no_prefix' where `$column` = '$wp_prefix$value_no_prefix'");
					}
				} else {
					$wpdb->query("UPDATE `$new_prefix$table` set `$column` = '$new_prefix$value_no_prefix' where `$column` = '$wp_prefix$value_no_prefix'");
				}
				// Restore blog
				restore_current_blog();
			}
		}
	}

	//delete old options in future release
	//	delete_option('rsssl_licensing_allowed_user_id' );
	//	delete_option( "rsssl_pro_disable_license_for_other_users" );
	//	delete_option('rsssl_block_third_party_popups');
	//	delete_option('rsssl_share_resources_third_parties');
	//	delete_option('rsssl_content_security_policy');
	//	delete_option('rsssl_permissions_policy');
	//	delete_option('rsssl_turn_on_permissions_policy');

//	if ( $prev_version && version_compare( $prev_version, '6.0.4', '<' ) ) {
//		RSSSL_PRO()->headers->insert_security_headers( true );
//	}
	update_option( 'rsssl-pro-current-version', rsssl_pro_version );

}
add_action( 'admin_init', 'rsssl_pro_upgrade' );
