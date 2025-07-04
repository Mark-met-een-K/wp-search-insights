<?php
// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
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

    $table_name_single = esc_sql($wpdb->prefix . 'searchinsights_single');
    $table_name_archive = esc_sql($wpdb->prefix . 'searchinsights_archive');
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table names are properly escaped with esc_sql(), direct query required for dropping tables during uninstall, caching unnecessary for one-time uninstall operation
    $wpdb->query("DROP TABLE IF EXISTS $table_name_single , $table_name_archive");

}

if (get_option('wpsi_cleardatabase')) {
    $options = array(
        'wpsi_exclude_admin',
        'wpsi_min_term_length',
        'wpsi_max_term_length',
        'search_insights_db_version',
        'wpsi_ten_searches_viewed_settings_page',
        'wpsi_toolset_configured',
        'wpsi_select_dashboard_capability',
        'wpsi_select_term_deletion_period',
        'wpsi-current-version',
        'wpsi_checked_ajax_plugins',
        'wpsi_cleardatabase',
        'wpsi_track_ajax_searches',
        'wpsi_custom_search_parameter',
        'wpsi_file_name',
        'wpsi_filter_textarea',
        'wpsi_activation_time',
        'wpsi_export_progress',
        'wpsi_export_row_count',
        'wpsi_database_created',
        'wpsi_database_postids_upgrade_completed',
        'wpsi_tour_cancelled',
        'wpsi_version_two_installation_time'
    );
    foreach ($options as $option_name) {
        delete_option($option_name);
        // For site options in Multisite
        delete_site_option($option_name);
    }
    remove_db_entries_on_uninstall();
}


