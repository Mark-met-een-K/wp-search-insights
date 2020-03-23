<?php
// If uninstall is not called from WordPress, exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}


/**
 *
 * Option to exclude admin searches from search results
 *
 * @since 1.0
 *
 */

function remove_db_entries_on_uninstall()
{

	global $wpdb;

	$table_name_single = $wpdb->prefix . 'searchinsights_single';
	$table_name_archive = $wpdb->prefix . 'searchinsights_archive';
	$wpdb->query("DROP TABLE IF EXISTS $table_name_single , $table_name_archive");

}

if (get_option('wpsi_cleardatabase')) {
	$options = array(
		'wpsi_exclude_admin',
		'wpsi_min_term_length',
		'wpsi_max_term_length',
		'search_insights_db_version',
		'wpsi_ten_searches_viewed_settings_page',
	);
	foreach($options as $option_name){
		delete_option( $option_name );
		// For site options in Multisite
		delete_site_option( $option_name );
	}
	remove_db_entries_on_uninstall();
}


