<?php
defined( 'ABSPATH' ) or die( "you do not have access to this page!" );

/**
 * Upgrade all entries to use post_id where possible
 */

add_action('plugins_loaded', 'wpsi_upgrade_database', 100);
function wpsi_upgrade_database(){
	if (!get_option('wpsi_database_postids_upgrade_completed')){
		global $wpdb;
		$rows = $wpdb->get_results("select * from {$wpdb->prefix}searchinsights_single where referrer_id is null order by time DESC limit 50");
		foreach ($rows as $row ) {
			$referrer = $row->referrer;
			$post_id = WPSI::$admin->get_post_by_title($referrer);
			$post_id = $post_id ? $post_id : 0;

			$wpdb->update($wpdb->prefix."searchinsights_single",
				array('referrer_id' => $post_id),
				array('id' => $row->id)
			);
		}

		if ( count($rows) == 0 ) update_option('wpsi_database_postids_upgrade_completed', true);
	}
}