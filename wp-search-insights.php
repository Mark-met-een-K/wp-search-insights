<?php
/**
 * Plugin Name: WP Search Insights
 * Plugin URI: https://www.wordpress.org/plugins/wp-search-insights
 * Description: WP Search Insights shows you what your users are looking for on your site, and which searches don't have results
 * Version: 1.0.1
 * Text Domain: wp-search-insights
 * Domain Path: /languages
 * Author: Mark Wolters, Rogier Lankhorst
 * Author URI: https://www.wpsearchinsights.com
 */

/*
    Copyright 2018  WP Search Insights  (email : support@wpsearchinsights.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/
defined( 'ABSPATH' ) or die( "you do not have access to this page!" );

//Call register activation hook outside of class.
register_activation_hook(__FILE__, 'search_insights_activation_hook' );

class WP_SEARCH_INSIGHTS {

	private static $instance;

	public $WP_Search_Insights_Search;
	public $WP_Search_Insights_Admin;
	public $tour;
	public $review;

	private function __construct() {
	}

	public static function instance() {

		if ( ! isset( self::$instance )
		     && ! ( self::$instance instanceof WP_SEARCH_INSIGHTS )
		) {

			self::$instance = new WP_SEARCH_INSIGHTS;
			self::$instance->setup_constants();
			self::$instance->includes();

			self::$instance->WP_Search_Insights_Search
				= new WP_Search_Insights_Search();

			if ( is_admin() ) {
				self::$instance->review = new wpsi_review();
				self::$instance->WP_Search_Insights_Admin = new WP_Search_Insights_Admin();
				self::$instance->tour = new wpsi_tour();
			}

            if ( is_admin() ) {
                self::$instance->wpsi_help
                    = new wpsi_help();
            }

			self::$instance->hooks();

		}

		return self::$instance;
	}

	private function setup_constants() {
		define( 'wp_search_insights_url', plugin_dir_url( __FILE__ ) );
		define( 'wp_search_insights_path',
			trailingslashit( plugin_dir_path( __FILE__ ) ) );
		define( 'wp_search_insights_plugin', plugin_basename( __FILE__ ) );
		define( 'wp_search_insights_plugin_file', __FILE__ );

		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$plugin_data = get_plugin_data( __FILE__ );
		$debug = defined("WP_DEBUG") && WP_DEBUG ? time() : "";
		define( 'wp_search_insights_version', $plugin_data['Version'].$debug );
	}

	private function includes() {

		if ( is_admin() ) {
			require_once( wp_search_insights_path . 'class-admin.php' );
            require_once( wp_search_insights_path . 'class-help.php' );
            require_once( wp_search_insights_path . 'class-review.php' );
			require_once( wp_search_insights_path . 'class-tour.php' );
        }

		require_once( wp_search_insights_path . 'class-search.php' );

	}

	private function hooks() {

		add_action( 'plugins_loaded',
			array( $this, 'search_insights_update_db_check' ) );

		if ( is_admin() ) {
			add_action( 'plugins_loaded', array( self::$instance->WP_Search_Insights_Admin, 'init' ), 10 );
			add_action( 'plugins_loaded', array( self::$instance->tour, 'init' ), 10 );
		}
	}

	/**
	 * Check if database is still up to date. If not, update
	 */

	public function search_insights_update_db_check() {
		if ( get_option( 'search_insights_db_version' ) != wp_search_insights_version ) {
			global $wpdb;
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			$charset_collate = $wpdb->get_charset_collate();

			$table_name_single  = $wpdb->prefix . 'searchinsights_single';
			$sql = "CREATE TABLE $table_name_single (
                      `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                      `time` INT(11) NOT NULL,
                      `term` text NOT NULL,
                      `referrer` text NOT NULL,
                      PRIMARY KEY (id)
                    ) $charset_collate;";
			dbDelta( $sql );

			$table_name_archive = $wpdb->prefix . 'searchinsights_archive';
			$sql = "CREATE TABLE $table_name_archive (
                        `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                        `time` INT(11) NOT NULL,
                        `term` text NOT NULL,
                        `frequency` INT(10) NOT NULL,
                        `result_count` INT(10) NOT NULL,
                        PRIMARY KEY  (id)
                      ) $charset_collate;";
			dbDelta( $sql );
			update_option( 'search_insights_db_version' , wp_search_insights_version);
		}
	}

}//Class closure

function wp_search_insights() {
	return WP_SEARCH_INSIGHTS::instance();
}

add_action( 'plugins_loaded', 'wp_search_insights', 8 );

function search_insights_activation_hook() {
    update_option('wpsi_min_term_length', 0);
    update_option('wpsi_max_term_length', 50);
    update_option('wpsi_select_dashboard_capability' , 'activate_plugins');
}
