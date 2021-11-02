<?php
// If uninstall is not called from WordPress, exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

rsssl_delete_all_options(
  array(
	  'rsssl-pro-current-version',
	  'rsssl_pro_defaults_set',
	  // Scan
      'rsssl_scan_progress',
      'rlrsssl_scan',
	  'rsssl_scan_type',
	  'rsssl_scan_active',
	  'rsssl_current_action',
	  'rsssl_progress',
	  'rsssl_iteration',
	  'rsssl_scan_completed_no_errors',
	  'rsssl_show_ignore_urls',
	  'rsssl_last_scan_time',
	  'rsssl_changed_files',
      'autoreplace_insecure_links_on_admin',
      'rsssl_cert_expiration_warning',
	  'rsssl_uses_elementor',
	  'rsssl_elementor_upgraded',
	  'rsssl_redirect_to_http_check',
	  // Security headers
	  'rsssl_content_security_policy',
	  'rsssl_enable_csp_reporting',
	  'rsssl_csp_reporting_activation_time',
	  'rsssl_add_csp_rules_to_htaccess',
	  'rsssl_pro_csp_enforce_rules_for_php',
	  'rsssl_csp_request_count',
	  'rsssl_turn_on_permissions_policy',
	  'rsssl_enable_php_headers',
	  'rsssl_csp_report_token',
	  'rsssl_content_security_policy_toggle',
	  'rsssl_csp_db_version',
	  'rsssl_permissions_policy',
	  'rsssl_permissions_policy_option',
	  'rsssl_security_headers_method',
	  'rsssl_turn_on_feature_policy',
	  'rsssl_feature_policy',
	  'rsssl_pro_feature_policy_headers_for_php',
	  'rsssl_pro_csp_report_only_rules_for_php',
	  'rsssl_pro_permissions_policy_headers_for_php',
	  'rsssl_upgrade_insecure_requests',
	  'rsssl_expect_ct',
	  'rsssl_hsts',
	  'rsssl_hsts_preload',
	  'rsssl_x_xss_protection',
	  'rsssl_x_frame_options',
	  'rsssl_x_content_type_options',
	  'rsssl_no_referrer_when_downgrade',
	  'rsssl_apitoken',
	  'rsssl_port_check_2082',
	  'rsssl_port_check_2222',
	  'rsssl_port_check_8443',
	  // Licensing
	  'rsssl_cert_expiration_warning',
	  'rsssl_pro_license_key',
	  'rsssl_key',
	  'rsssl_upgraded_license_key',
	  'rsssl_pro_license_expires',
	  'rsssl_pro_license_activation_limit',
	  'rsssl_pro_license_activations_left',
	  'rsssl_licensing_allowed_user_id',
	  'rsssl_pro_disable_license_for_other_users',
    )
  );

rsssl_delete_all_transients(
	array(
		'rsssl_tls_version',
		'rsssl_redirects_to_homepage',
		'rsssl_cert_expiration_date',
		'rsssl_sent_cert_expiration_warning',
		'rsssl_scan_post_count',
		'rlrsssl_scan',
		'rsssl_pro_redirect_to_settings_page',
		'rsssl_stop_certificate_expiration_check',
		'rsssl_pro_license_status',
	)
);

function rsssl_delete_csp_table() {
    global $wpdb;
    $csp_table = $wpdb->base_prefix . "rsssl_csp_log";
    $wpdb->query("DROP TABLE IF EXISTS $csp_table");
}

rsssl_delete_csp_table();

function rsssl_delete_all_options($options) {
  foreach($options as $option_name) {
    delete_option( $option_name );
    // For site options in Multisite
    delete_site_option( $option_name );
  }
}

function rsssl_delete_all_transients($transients) {
	foreach($transients as $transient) {
		delete_transient( $transient );
		delete_site_transient( $transient );
	}
}
