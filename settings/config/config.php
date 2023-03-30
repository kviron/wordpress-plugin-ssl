<?php defined('ABSPATH') or die();

/**
 * Add premium fields
 * @param array $fields
 *
 * @return array
 */

function rsssl_pro_add_premium_fields($fields)
{
	foreach ( $fields as $key => $item ) {
		if ( $item['id'] === 'content_security_policy' ) {
			$fields[$key]['data_source'] = ["RSSSL_PRO", "csp_backend", "get"];
			$fields[$key]['data_endpoint'] = [ "RSSSL_PRO", "csp_backend", "update" ];
		}
		if ( $item['id'] === 'mixedcontentscan' ) {
			$fields[$key]['data_source'] = ["RSSSL_PRO", "scan", "get"];
		}
		if ( $item['id'] === 'xmlrpc_allow_list' ) {
			$fields[$key]['data_source'] = "rsssl_xmlrpc_get_data";
			$fields[$key]['data_endpoint'] = "rsssl_xml_update_allowlist";
		}
	}
	return $fields;
}
add_filter('rsssl_fields', 'rsssl_pro_add_premium_fields' );