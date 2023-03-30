<?php
defined( 'ABSPATH' ) or die( "you do not have access to this page!" );
//test command
//curl -v -H "content-type:text/xml" http://localhost/reallysimplessl/xmlrpc.php --data @test.xml
if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
	function rsssl_check_xmlrpc_request( $method, $args, $obj ){
		$logged_in = is_user_logged_in();
		if ( rsssl_get_option('xmlrpc_status') ==='enforce' && !rsssl_xmlrpc_request_allowed($method, $args, $logged_in ) ){
			wp_die();
		} else if ( rsssl_get_option('xmlrpc_status')==='learning_mode' ){
			global $wpdb;
			$table_name = $wpdb->prefix . "rsssl_xmlrpc";
			$status = $logged_in;
			$count = $wpdb->get_var( $wpdb->prepare("select count(*) from {$wpdb->prefix}rsssl_xmlrpc where method=%s and login_status=%s", $method, $logged_in ));
			//if the login was successful, we set it to allowed
			if ( $count==0 ) {
				$wpdb->insert( $table_name, array(
					'time'    => time(),
					'method'    => $method,
					'args'  => serialize($args),
					'login_status' => $logged_in,
					'count' => 1,
					'status' => $status,
				) );
			} else {
				$wpdb->update( $table_name, [
					'time'    => time(),
					'count' => $count+1,
					'args'  => serialize($args),
				], [
					'method'    => $method,
					'login_status' => $logged_in,
				]);
			}
		}
	}
	add_action( 'xmlrpc_call', 'rsssl_check_xmlrpc_request' , 999, 3);
}

/**
 * XML data
 * @return array
 */
function rsssl_xmlrpc_get_data(){
	if ( !rsssl_user_can_manage() ){
		return [];
	}

	global $wpdb;
	$data = $wpdb->get_results( "select * from {$wpdb->prefix}rsssl_xmlrpc");
	return $data;
}

/**
 * Check if this request is allowed
 *
 * @param string $method
 * @param array $args
 * @param int $user_id
 *
 * @return bool
 */
function rsssl_xmlrpc_request_allowed($method, $args, $user_id){
	global $wpdb;
	$count = $wpdb->get_var( $wpdb->prepare("select count(*) from {$wpdb->prefix }rsssl_xmlrpc where method=%s AND status=1", $method)) ;
	$xmlrpc_enabled = apply_filters('xmlrpc_enabled', true );
	return $count>0 && $xmlrpc_enabled;
}

/**
 * Check if there is at least one succesfull request
 *
 * @return bool
 */
function rsssl_xmlrpc_has_successful_requests(){
	global $wpdb;
	$count = $wpdb->get_var("select count(*) from {$wpdb->prefix }rsssl_xmlrpc where login_status=1") ;
	return $count>0;
}

/**
 * @param array  $value
 * @param int    $update_item_id
 * @param string $action
 *
 * @return void
 */
function rsssl_xml_update_allowlist( array $value, int $update_item_id, string $action='update') {
	if ( ! rsssl_user_can_manage() ) {
		return;
	}

	if ( ! is_array( $value ) ) {
		return;
	}

	if ( $action === 'update' ) {
		foreach ( $value as $index => $item ) {
			if ( ! isset( $item['id'] ) ) {
				continue;
			}
			if ( intval($item['id']) == $update_item_id ) {
				global $wpdb;
				$status = isset( $item['status'] ) ? intval( $item['status'] ) : 0;
				$wpdb->update( $wpdb->prefix . "rsssl_xmlrpc", [
					'status' => $status,
				], [
						'id' => $update_item_id
					]
				);
			}
		}
	}

	if ( $action === 'delete' ) {
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . "rsssl_xmlrpc", [
				'id' => $update_item_id
			]
		);
	}
}

/**
 * Dismiss the learning mode after a week
 *
 * @return void
 */
function rsssl_maybe_disable_xml_learning_mode_after_period(){
	if ( rsssl_get_option( 'xmlrpc_status' )==='learning_mode' ) {
		//disable learning mode after one week
		$activation_time = get_site_option( 'rsssl_xmlrpc_learning_mode_activation_time' );
		$nr_of_days_learning_mode = apply_filters( 'rsssl_pause_after_days', 7 );
		$one_week_ago = strtotime( "-$nr_of_days_learning_mode days" );
		if ( $activation_time < $one_week_ago ) {
			//ensure the functions are included
			if ( !function_exists('rsssl_update_option' )) {
				require_once( rsssl_path . 'settings/settings.php' );
			}
			rsssl_update_option( 'xmlrpc_status', 'completed' );
			//if, after running a full week, no successfull login attempt has been detected, disable xml rpc.
			if ( !rsssl_xmlrpc_has_successful_requests() ){
				rsssl_update_option( 'disable_xmlrpc', true );
			}
		}
	}
}

/**
 * If csp reporting is enabled, save the time so we track how long it's running
 * @return void
 */
function rsssl_save_time_on_xmlrpc_learning_mode_start($field_id, $field_value, $prev_value, $field_type ){
	if ( $field_id==='xmlrpc_status' && $field_value=='learning_mode' ){
		update_site_option("rsssl_xmlrpc_learning_mode_activation_time", time() );
	}
}
add_action( "rsssl_after_save_field", 'rsssl_save_time_on_xmlrpc_learning_mode_start', 100, 4 );

/**
 * @return void
 * Add the learning mode table
 */
function rsssl_add_learning_mode_table() {
	if ( rsssl_pro_version === get_option( 'rsssl_xmlrpc_db_version' ) ) {
		return;
	}

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	$table_name = $wpdb->prefix . "rsssl_xmlrpc";
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE $table_name (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		time int(10) NOT NULL,
		method text NOT NULL,
		args text  NOT NULL,
		login_status int(10)  NOT NULL,
		status int(10)  NOT NULL,
		count int(10)  NOT NULL,
		PRIMARY KEY  (id)
		) $charset_collate";
	dbDelta( $sql );
	update_option( 'rsssl_xmlrpc_db_version', rsssl_pro_version );
}
//plugins_loaded on priority 11, as security loads on 10.
add_action( 'plugins_loaded', 'rsssl_add_learning_mode_table', 11 );


