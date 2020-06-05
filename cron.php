<?php
defined('ABSPATH') or die("you do not have acces to this page!");

//switch to Cron here.

/*
  Schedule cron jobs if useCron is true
  Else start the functions.
*/

add_action('plugins_loaded','wpsi_schedule_cron');
function wpsi_schedule_cron() {
	$useCron = true;
	if ($useCron) {
		if ( ! wp_next_scheduled('cmplz_every_five_minutes_hook') ) {
			wp_schedule_event( time(), 'wpsi_every_five_minutes', 'wpsi_every_five_minutes_hook' );
		}

		add_action( 'wpsi_every_five_minutes_hook', array(WPSI::$export, 'process_csv_chunk') );

	} else {
		add_action( 'init', array(WPSI::$export, 'process_csv_chunk') );
	}
}

add_filter( 'cron_schedules', 'wpsi_filter_cron_schedules' );
function wpsi_filter_cron_schedules( $schedules ) {
	$schedules['wpsi_every_five_minutes'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __( 'Once every 5 minutes' )
	);

	return $schedules;
}


register_deactivation_hook( __FILE__, 'wpsi_clear_scheduled_hooks' );
function wpsi_clear_scheduled_hooks(){
	wp_clear_scheduled_hook( 'wpsi_every_five_minutes_hook' );
}



