<?php
/**
 * Plugin Name: WP Search Insights
 * Plugin URI: https://www.wordpress.org/plugins/wp-search-insights
 * Description: WP Search Insights shows you what your users are looking for on your site, and which searches don't have results
 * Version: 1.3.6
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

/**
 * Checks if the plugin can safely be activated, at least php 5.6 and wp 4.6
 * @since 2.1.5
 */
if (!function_exists('wpsi_activation_check')) {
	function wpsi_activation_check()
	{
		if (version_compare(PHP_VERSION, '5.6', '<')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die(__('WP Search Insights cannot be activated. The plugin requires PHP 5.6 or higher', 'complianz-gdpr'));
		}

		global $wp_version;
		if (version_compare($wp_version, '4.6', '<')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die(__('WP Search Insights cannot be activated. The plugin requires WordPress 4.6 or higher', 'complianz-gdpr'));
		}
	}
}
register_activation_hook( __FILE__, 'wpsi_activation_check' );

if ( ! class_exists( 'WPSI' ) ) {
	class WPSI {
		public static $instance;
		public static $search;
		public static $admin;
		public static $tour;
		public static $review;
		public static $help;
		public static $export;

		private function __construct() {
			self::setup_constants();
			self::includes();
			self::load_translation();

			self::$search = new search();

			if ( is_admin() ) {
				self::$review = new wpsi_review();
				self::$admin  = new WPSI_ADMIN();
				self::$export  = new WPSI_EXPORT();
				self::$tour   = new wpsi_tour();
				self::$help   = new wpsi_help();
			}

			self::hooks();
		}

		/**
		 * Instantiate the class.
		 *
		 * @return WPSI
		 * @since 1.0.0
		 *
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance )
			     && ! ( self::$instance instanceof WPSI )
			) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		private function setup_constants() {
			define( 'wpsi_url', plugin_dir_url( __FILE__ ) );
			define( 'wpsi_path',
				trailingslashit( plugin_dir_path( __FILE__ ) ) );
			define( 'wpsi_plugin', plugin_basename( __FILE__ ) );
			define( 'wpsi_plugin_file', __FILE__ );

			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			$plugin_data = get_plugin_data( __FILE__ );
			$debug       = defined( "WP_DEBUG" ) && WP_DEBUG ? time() : "";
			define( 'wpsi_version',
				$plugin_data['Version'] . $debug );
		}

		private function includes() {
			if ( is_admin() ) {
				require_once( wpsi_path . 'upgrade.php' );
				require_once( wpsi_path . 'class-admin.php' );
				require_once( wpsi_path . 'class-export.php' );
				require_once( wpsi_path . 'dashboard_tabs.php' );
				require_once( wpsi_path . 'class-help.php' );
				require_once( wpsi_path . 'class-review.php' );
				require_once( wpsi_path . 'shepherd/tour.php' );
				require_once( wpsi_path . 'grid/grid-enqueue.php' );
			}
			require_once( wpsi_path . 'class-search.php' );
			require_once( wpsi_path . 'integrations/integrations.php' );
		}

		/**
		 * Load plugin translations.
		 *
		 * @return void
		 * @since 1.0.0
		 *
		 */
		private function load_translation() {
			load_plugin_textdomain( 'wp-search-insights', false,
				wpsi_path . '/languages/' );
		}

		/**
		 * Get directory of free plugin
		 * @return string
		 */

		public static function get_actual_directory_name() {
			return basename( __DIR__ );
		}

		private function hooks() {

		}
	}

	/**
	 * Load the plugins main class.
	 */
	add_action(
		'plugins_loaded',
		function () {
			WPSI::get_instance();
		},
		9
	);
}

function search_insights_activation_hook() {
	update_option( 'wpsi_min_term_length', 0 );
	update_option( 'wpsi_max_term_length', 50 );
	update_option( 'wpsi_select_dashboard_capability', 'activate_plugins' );
}
//Call register activation hook outside of class.
register_activation_hook( __FILE__, 'search_insights_activation_hook' );