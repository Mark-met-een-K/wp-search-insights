<?php

defined( 'ABSPATH' ) or die( "you do not have access to this page!" );

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @since             0.8
 * @package           Search Insights
 *
 * @wordpress-plugin
 * Plugin Name:       WP Search Insights
 * Description:       Plugin to provide insights into your users searches
 * Version:           1.0.1
 * Author:            Mark Wolters
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wp_search_insights
 * Domain Path:       /languages
 */

//Call register activation hook outside of class.
register_activation_hook(__FILE__, 'search_insights_activation_hook' );

class WP_SEARCH_INSIGHTS {

	private static $instance;

	public $WP_Search_Insights_Search;
	public $WP_Search_Insights_Admin;

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
				self::$instance->WP_Search_Insights_Admin
					= new WP_Search_Insights_Admin();
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
		define( 'wp_search_insights_version', $plugin_data['Version'] );
	}

	private function includes() {

		if ( is_admin() ) {
			require_once( wp_search_insights_path . 'class-admin.php' );
            require_once( wp_search_insights_path . 'class-help.php' );
        }

		require_once( wp_search_insights_path . 'class-search.php' );

	}

	private function hooks() {

		add_action( 'plugins_loaded',
			array( $this, 'search_insights_update_db_check' ) );

		if ( is_admin() ) {
			add_action( 'plugins_loaded',
				array( self::$instance->WP_Search_Insights_Admin, 'init' ),
				10 );
		}

	}

	/**
	 *
	 * Create two database tables, _single contains each individual result, including duplicates and one _archive table.
     *
     * @since 1.0
	 *
	 **/

	public function create_database_tables() {

		global $wpdb;
		global $search_insights_db_version;

		$table_name_single  = $wpdb->prefix . 'searchinsights_single';
		$table_name_archive = $wpdb->prefix . 'searchinsights_archive';

		$charset_collate = $wpdb->get_charset_collate();

		$sql
			= "CREATE TABLE IF NOT EXISTS $table_name_single (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
    term text NOT NULL,
    result_count INT(10),
    referer text,
    PRIMARY KEY  (id)
    ) $charset_collate";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );

		$sql
			= "CREATE TABLE IF NOT EXISTS $table_name_archive (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
	  term text NOT NULL,
	  frequency INT(10) NOT NULL,
	  referer text,
	  PRIMARY KEY  (id)
	) $charset_collate";

		dbDelta( $sql );

		add_option( 'search_insights_db_version', $search_insights_db_version );

		$installed_ver = get_option( "wp_search_insights_db_version" );

		if ( $installed_ver != $search_insights_db_version ) {

			$sql
				= "CREATE TABLE IF NOT EXISTS $table_name_single (
                      id mediumint(9) NOT NULL AUTO_INCREMENT,
                      time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                      term text NOT NULL,
                      result_count INT(10),
                      referer text,
                      PRIMARY KEY  (id)
                    )";

			dbDelta( $sql );

			$sql
				= "CREATE TABLE IF NOT EXISTS $table_name_archive (
                        id mediumint(9) NOT NULL AUTO_INCREMENT,
                        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                        term text NOT NULL,
                        frequency INT(10) NOT NULL,
                        referer text,
                        PRIMARY KEY  (id)
                      )";

			dbDelta( $sql );

			update_option( "search_insights_db_version",
				$search_insights_db_version );
		}
	}

	public function search_insights_update_db_check() {
		global $search_insights_db_version;
		if ( get_site_option( 'search_insights_db_version' )
			!= $search_insights_db_version
		) {
			$this->create_database_tables();
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
    WP_SEARCH_INSIGHTS()->create_database_tables();
}
