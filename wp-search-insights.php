<?php
/**
 * Plugin Name: Search Insights - Privacy-Friendly Search Analytics
 * Plugin URI: https://www.wordpress.org/plugins/wp-search-insights
 * License: GPLv2
 * Description: Uncover exactly what visitors search for on your site. Make data-driven content decisions, identify content gaps, and improve user experience with privacy-focused search analytics.
 * Version: 2.1
 * Text Domain: wp-search-insights
 * Domain Path: /languages
 * Author: Mark Wolters
 * Requires PHP: 7.0
 * Requires at least: 4.8
 * Author URI: https://www.wpsi.io
 */

/*
    Copyright 2025  Search Insights  (email : support@wpsi.io)

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
defined('ABSPATH') or die("you do not have access to this page!");

/**
 * Checks if the plugin can safely be activated, at least php 5.6 and wp 4.6
 * @since 2.1.5
 */
if (!function_exists('wpsi_activation_check')) {
    function wpsi_activation_check()
    {
        if (version_compare(PHP_VERSION, '5.6', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(esc_html__('Search Insights cannot be activated. The plugin requires PHP 5.6 or higher', 'wp-search-insights'));
        }

        global $wp_version;
        if (version_compare($wp_version, '4.6', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(esc_html__('Search Insights cannot be activated. The plugin requires WordPress 4.6 or higher', 'wp-search-insights'));
        }
    }
}
register_activation_hook(__FILE__, 'wpsi_activation_check');

if (!class_exists('WPSI')) {
    class WPSI
    {
        public static $instance;
        public static $search;
        public static $admin;
        public static $tour;
        public static $review;
        public static $help;
        public static $export;
        public static $spam_filter;

        private function __construct()
        {
            $this->setup_constants();
            $this->includes();

            self::$spam_filter = new WPSI_Spam_Filter();
            self::$search = new search();

            if (is_admin()) {
                self::$review = new wpsi_review();
                self::$admin = new WPSI_ADMIN();
                self::$export = new WPSI_EXPORT();
                self::$tour = new wpsi_tour();
                self::$help = new wpsi_help();
            }

            $this->hooks();

        }

        /**
         * Instantiate the class.
         *
         * @return WPSI
         * @since 1.0.0
         *
         */
        public static function get_instance()
        {
            if (!isset(self::$instance)
                && !(self::$instance instanceof WPSI)
            ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        private function setup_constants()
        {
            define('wpsi_url', plugin_dir_url(__FILE__));
            define('wpsi_path',
                trailingslashit(plugin_dir_path(__FILE__)));
            define('wpsi_plugin', plugin_basename(__FILE__));
            define('wpsi_plugin_file', __FILE__);

            define('wpsi_version', '2.1');
        }

        private function includes()
        {
            if (is_admin()) {
                require_once(wpsi_path . 'upgrade.php');
                require_once(wpsi_path . 'class-admin.php');
                require_once(wpsi_path . 'class-export.php');
                require_once(wpsi_path . 'dashboard_tabs.php');
                require_once(wpsi_path . 'class-help.php');
                require_once(wpsi_path . 'class-review.php');
                require_once(wpsi_path . 'shepherd/tour.php');
                require_once(wpsi_path . 'grid/grid-enqueue.php');
                require_once(wpsi_path . 'includes/class-modal.php');
            }

            // We should load pro on front-end, what shouldn't be included on the front-end won't be loaded
            if (file_exists(wpsi_path . 'pro/class-pro.php')) {
                require_once(wpsi_path . 'pro/class-pro.php');
                WPSI_PRO::get_instance();
            }

            require_once(wpsi_path . 'class-search.php');
            require_once(wpsi_path . 'includes/class-spam-filter.php');
            require_once(wpsi_path . 'integrations/integrations.php');
        }

        private function hooks()
        {

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

/**
 * Redirect to dashboard after activation for tour
 */
function wpsi_activation_redirect($plugin)
{
    if ($plugin === plugin_basename(__FILE__) && !get_option('wpsi_tour_cancelled')) {
        // Ensure we don't redirect when tour is already cancelled
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_redirect is a safe WordPress function
        exit(wp_redirect(admin_url('admin.php?page=wpsi-settings-page&tour=1')));
    }
}

add_action('activated_plugin', 'wpsi_activation_redirect');

function search_insights_activation_hook()
{
    update_option('wpsi_min_term_length', 0);
    update_option('wpsi_max_term_length', 50);
    update_option('wpsi_select_dashboard_capability', 'activate_plugins');
    update_option('wpsi_select_term_deletion_period', 'never');

    if ( ! get_option('wpsi_version_two_installation_time' ) ) {
        update_option('wpsi_version_two_installation_time', time() );
    }
}

//Call register activation hook outside of class.
register_activation_hook(__FILE__, 'search_insights_activation_hook');


function wpsi_clear_scheduled_hooks()
{
    wp_clear_scheduled_hook('wpsi_every_five_minutes_hook');
}

register_deactivation_hook(__FILE__, 'wpsi_clear_scheduled_hooks');
