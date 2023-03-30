<?php

add_filter('rsssl_integrations_path', 'rsssl_pro_integrations_path', 10, 2);
function rsssl_pro_integrations_path($path, $plugin ){
	$pro_plugins = [
		'disable-http-methods',
		'xmlrpc',
		'debug-log',
		'rename-db-prefix',
		'application-passwords',
	];
	if (in_array($plugin, $pro_plugins)) {
		return rsssl_pro_path;
	}
	return $path;
}

function rsssl_pro_integrations($integrations) {
		$integrations = $integrations + ['disable-http-methods' => array(
			'label'                => __('Disable HTTP methods', 'really-simple-ssl'),
			'folder'               => 'wordpress',
			'impact'               => 'low',
			'risk'                 => 'medium',
			'option_id'            => 'disable_http_methods',
		),
         'xmlrpc' => array(
			'label'                => 'XMLRPC',
			'folder'               => 'wordpress',
			'impact'               => 'medium',
			'risk'                 => 'low',
			'always_include'       => true,
	    ),
	    'debug-log' => array(
			'label'                => __('Move debug.log', 'really-simple-ssl'),
			'folder'               => 'wordpress',
			'impact'               => 'medium',
			'risk'                 => 'medium',
			'option_id'            => 'change_debug_log_location',
			'always_include'       => false,
			'has_deactivation'     => true,
		),
		'rename-db-prefix' => array(
			'label'                => __('Rename DB prefix', 'really-simple-ssl'),
			'folder'               => 'wordpress',
			'impact'               => 'high',
			'risk'                 => 'high',
			'learning_mode'        => false,
			'option_id'            => 'rename_db_prefix',
		),
         'application-passwords' => array(
             'label'                => __('Disable Application passwords', 'really-simple-ssl'),
             'folder'               => 'wordpress',
             'impact'               => 'low',
             'risk'                 => 'high',
             'learning_mode'        => false,
             'option_id'            => 'disable_application_passwords',
             'always_include'       => false,
             'has_deactivation'     => true,
         ),
	];
	return $integrations;
}
add_filter( 'rsssl_integrations', 'rsssl_pro_integrations' );
