<?php defined('ABSPATH') or die();

require_once( rsssl_pro_path . 'settings/config/config.php' );
require_once( rsssl_pro_path . 'settings/sync-settings.php' );
/**
 * Add datatypes for the datatable fields
 * @param array $types
 *
 * @return string[]
 */
function rsssl_pro_datatable_datatypes_permissionspolicy(array $types): array {
	$types += [
		'value' => 'string',
	];
	return $types;
}
add_filter('rsssl_datatable_datatypes_permissionspolicy', 'rsssl_pro_datatable_datatypes_permissionspolicy');
/**
 * Add datatypes for the datatable fields
 * @param $types
 *
 * @return string[]
 */
function rsssl_pro_datatable_datatypes_contentsecuritypolicy($types){
	$types += [
		'time'              => 'string',
		'documenturi'       => 'string',
		'violateddirective' => 'string',
		'blockeduri'        => 'string',
	];
	unset($types['title']);//drop title, as we don't have this column in the csp database
	return $types;
}
add_filter('rsssl_datatable_datatypes_contentsecuritypolicy', 'rsssl_pro_datatable_datatypes_contentsecuritypolicy');

/**
 * Change premium icon into open
 */
add_filter('rsssl_notices', 'rsssl_update_notices', 100);
function rsssl_update_notices($notices) {
	foreach ($notices as $id => $notice ) {
		foreach ($notice['output'] as $index => $item ) {
			if ($notices[$id]['output'][$index]['icon']==='premium'){
				$notices[$id]['output'][$index]['icon'] = 'open';
			}
		}
	}

	//change url to fix button
	unset($notices['db-prefix-notice']['output']['true']['url'] );
	$notices['db-prefix-notice']['output']['true']['highlight_field_id'] = 'rename_db_prefix';

	return $notices;
}
