<?php
// If uninstall is not called from WordPress, exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

$settings = get_option('rsssl_options');
if (isset($settings['delete_data_on_uninstall']) && $settings['delete_data_on_uninstall']) {
	$options = [
		"rsssl_changed_files",
		"rsssl_enable_csp_defaults",
		"rsssl_elementor_upgraded",
		"rsssl_redirect_to_http_check",
		"rsssl_pro_permissions_policy_headers_for_php",
		"rsssl_license_attempts",
		"rsssl_csp_report_url",
		"rsssl_iteration",
		"rsssl_scan",
		"rsssl_progress",
		"rsssl_current_action",
		"rsssl_scan_type",
		"rsssl_scan_active",
		"rsssl_xmlrpc_db_version",
		"rsssl_first_version",
		"rsssl-pro-current-version",
		"rsssl_xmlrpc_learning_mode_activation_time",
		"rsssl_pro_defaults_set",
		"rsssl_after_default_setup_completed",
		"rsssl_ms_elementor_private_replace_progress",
		"rsssl_ms_elementor_urls_upgraded",
		"rsssl_ms_elementor_public_replace_progress",
		"rsssl_csp_report_only_activation_time",
		"rsssl_csp_report_token",
		"rsssl_key",
		"rsssl_transients",
		"rsssl_pro_license_activation_limit",
		"rsssl_ssl_verify",
		"rsssl_pro_license_activations_left",
		"rsssl_pro_license_expires",
		"rsssl_csp_db_version",
		"rsssl_debug_log_folder_suffix",
		"rsssl_xmlrpc_learning_mode_activation_time",
		"rsssl_port_check_2082",
		"rsssl_port_check_8443",
		"rsssl_port_check_2222",
		"rsssl_csp_db_upgraded",
		"rsssl_scan_completed_no_errors",
		"rsssl_last_scan_time",
	];

	foreach ( $options as $option_name ) {
		delete_option( $option_name );
		delete_site_option( $option_name );
	}

	$transients = [
		"detected_headers",
		'rsssl_tls_version',
		'rsssl_redirects_to_homepage',
		'rsssl_cert_expiration_date',
		'rsssl_sent_cert_expiration_warning',
		'rsssl_scan_post_count',
		'rsssl_scan',
		'rsssl_pro_redirect_to_settings_page',
		'rsssl_stop_certificate_expiration_check',
		'rsssl_pro_license_status',
	];

	foreach($transients as $transient) {
		delete_transient( $transient );
		delete_site_transient( $transient );
	}

	global $wpdb;
	$table_names = array(
		$wpdb->base_prefix . 'rsssl_csp_log',
		$wpdb->prefix . 'rsssl_xmlrpc',
	);

	foreach($table_names as $table_name){
		$sql = "DROP TABLE IF EXISTS $table_name";
		$wpdb->query($sql);
	}
}
