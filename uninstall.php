<?php
// If uninstall is not called from WordPress, exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

if (!get_option('wpsi_cleardatabase')) exit();

delete_all_options('wpsi_exclude_admin');
delete_all_options('wpsi_min_term_length');
delete_all_options('wpsi_max_term_length');
delete_all_options('wpsi_welcome_message_shown');
delete_all_options('search_insights_db_version');

function delete_all_options($option_name) {
  delete_option( $option_name );
  // For site options in Multisite
  delete_site_option( $option_name );
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

remove_db_entries_on_uninstall();