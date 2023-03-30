<?php
defined( 'ABSPATH' ) or die();
/**
 * Schedule cron jobs if useCron is true
 * Else start the functions.
 */
function rsssl_pro_schedule_cron() {
	$useCron = true;//for testing
	if ( $useCron ) {
		if ( ! wp_next_scheduled( 'rsssl_pro_every_day_hook' ) ) {
			wp_schedule_event( time(), 'rsssl_pro_daily', 'rsssl_pro_every_day_hook' );
		}
		if ( ! wp_next_scheduled( 'rsssl_pro_five_minutes_hook' ) ) {
			wp_schedule_event( time(), 'rsssl_pro_five_minutes', 'rsssl_pro_five_minutes_hook' );
		}
		add_action( 'rsssl_pro_every_day_hook', 'rsssl_maybe_disable_learning_mode_after_period' );
		add_action( 'rsssl_pro_every_day_hook', 'rsssl_maybe_disable_xml_learning_mode_after_period' );
		add_action( 'rsssl_pro_five_minutes_hook', 'rsssl_maybe_enable_learning_mode' );

	} else {
		add_action( 'init', 'rsssl_maybe_disable_learning_mode_after_period' );
		add_action( 'init', 'rsssl_maybe_disable_xml_learning_mode_after_period' );
		add_action( 'init', 'rsssl_maybe_enable_learning_mode' );
	}
}
add_action( 'plugins_loaded', 'rsssl_pro_schedule_cron' );

function rsssl_pro_filter_cron_schedules( $schedules ) {
	$schedules['rsssl_pro_daily']   = array(
		'interval' => DAY_IN_SECONDS,
		'display'  => __( 'Once every day' )
	);
	$schedules['rsssl_pro_five_minutes']   = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __( 'Once every five minutes' )
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'rsssl_pro_filter_cron_schedules' );

function rsssl_pro_clear_scheduled_hooks() {
	wp_clear_scheduled_hook( 'rsssl_pro_every_day_hook' );
}
register_deactivation_hook( rsssl_pro_plugin_file, 'rsssl_pro_clear_scheduled_hooks' );




