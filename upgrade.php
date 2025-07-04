<?php
defined('ABSPATH') or die("you do not have access to this page!");

/**
 * Upgrade all entries to use post_id where possible
 */

add_action('plugins_loaded', 'wpsi_upgrade_database', 100);
function wpsi_upgrade_database()
{
    if (!get_option('wpsi_database_postids_upgrade_completed')) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required for custom plugin tables during one-time upgrade, caching unnecessary for upgrade operation
        $rows = $wpdb->get_results("select * from {$wpdb->prefix}searchinsights_single where referrer_id is null order by time DESC limit 50");
        foreach ($rows as $row) {
            $referrer = $row->referrer;
            $post_id = WPSI::$admin->get_post_by_title($referrer);
            $post_id = $post_id ? $post_id : 0;

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required for updating custom plugin tables during one-time upgrade, caching unnecessary for upgrade operation
            $wpdb->update($wpdb->prefix . "searchinsights_single",
                array('referrer_id' => $post_id),
                array('id' => $row->id)
            );
        }

        if (count($rows) == 0) update_option('wpsi_database_postids_upgrade_completed', true);
    }
}

/**
 * Check if database is still up-to-date. If not, update
 */
function wpsi_update_db()
{
    if (get_option('search_insights_db_version') != wpsi_version) {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();

        $table_name_single = esc_sql($wpdb->prefix . 'searchinsights_single');
        $sql = "CREATE TABLE $table_name_single (
          `id` mediumint(9) NOT NULL AUTO_INCREMENT,
          `time` INT(11) NOT NULL,
          `term` text NOT NULL,
          `referrer` text NOT NULL,
          `referrer_id` INT(11),
          `landing_page` varchar(255) DEFAULT NULL,
          `is_conversion` tinyint(1) DEFAULT NULL,
          `landing_time` int(11) DEFAULT NULL,
          `is_internal` tinyint(1) DEFAULT NULL,
          `search_id` varchar(100) DEFAULT NULL,
          PRIMARY KEY (id),
          INDEX wpsi_time_idx (time),
          INDEX wpsi_term_idx (term(64)),
          INDEX wpsi_conversion_idx (is_conversion),
          INDEX wpsi_term_time_idx (term(64), time),
          INDEX wpsi_search_id_idx (search_id)
        ) $charset_collate;";
        dbDelta($sql);

        $table_name_archive = esc_sql($wpdb->prefix . 'searchinsights_archive');
        $sql = "CREATE TABLE $table_name_archive (
                `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                `time` INT(11) NOT NULL,
                `term` text NOT NULL,
                `frequency` INT(10) NOT NULL,
                `result_count` INT(10) NOT NULL,
                PRIMARY KEY  (id),
                INDEX wpsi_archive_time_idx (time),
                INDEX wpsi_archive_term_idx (term(64)),
                INDEX wpsi_archive_term_time_idx (term(64), time)
              ) $charset_collate;";
        dbDelta($sql);
        update_option('search_insights_db_version', wpsi_version);
    }
    update_option('wpsi_database_created', true);
}
add_action('plugins_loaded', 'wpsi_update_db');
