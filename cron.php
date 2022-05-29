<?php
defined('ABSPATH') or die("you do not have acces to this page!");

/**
  Schedule cron jobs if useCron is true
  Else start the functions.
*/
function wpsi_schedule_cron() {
	$useCron = true;
	if ($useCron) {
		if ( ! wp_next_scheduled('wpsi_every_five_minutes_hook') ) {
			wp_schedule_event( time(), 'wpsi_every_five_minutes', 'wpsi_every_five_minutes_hook' );
		}

		add_action( 'wpsi_every_five_minutes_hook', array(WPSI::$export, 'process_csv_chunk') );

	} else {
		add_action( 'init', array(WPSI::$export, 'process_csv_chunk') );
	}
}
add_action('plugins_loaded','wpsi_schedule_cron');


/**
 * Add our own schedule
 * @param $schedules
 *
 * @return mixed
 */
function wpsi_filter_cron_schedules( $schedules ) {
	$schedules['wpsi_every_five_minutes'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __( 'Once every 5 minutes' )
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'wpsi_filter_cron_schedules' );



