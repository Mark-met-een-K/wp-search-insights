<?php
defined( 'ABSPATH' ) or die( "you do not have access to this page!" );

if ( ! class_exists( 'WPSI_ADMIN' ) ) {
    class WPSI_ADMIN{

        private static $_this;
        public $grid_items;
        public $capability = 'activate_plugins';
        public $tabs;
        public $rows_batch = 200;

		static function this() {
			return self::$_this;
		}

        function __construct()
        {
            if (isset(self::$_this)) {
                wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.',
                    'wp-search-insights'), get_class($this)));
            }

            self::$_this = $this;

            $this->capability = get_option('wpsi_select_dashboard_capability');
            add_action('admin_menu', array($this, 'add_settings_page'), 40);
            add_action('admin_init', array($this, 'wpsi_settings_section_and_fields'));
            add_action('admin_init', array($this, 'maybe_enable_ajax_tracking'));
            add_action( 'admin_init', array( $this, 'check_upgrade' ), 10, 2 );

            $is_wpsi_page = isset($_GET['page']) && $_GET['page'] === 'wpsi-settings-page' ? true : false;
            if ($is_wpsi_page) {
                add_action('admin_init', array($this, 'init_grid') );
                add_action('admin_head', array($this, 'inline_styles'));

                // Dot not add action to clear entries from db when the option is set to never
                if (get_option('wpsi_select_term_deletion_period') && get_option('wpsi_select_term_deletion_period') !== 'never') {
                    add_action('admin_init', array($this, 'clear_entries_from_database'));
                }
            }

            add_action('admin_init', array($this, 'add_privacy_info'));

            $plugin = wpsi_plugin;

            add_filter("plugin_action_links_$plugin", array($this, 'plugin_settings_link'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('wp_ajax_wpsi_get_datatable', array($this, 'ajax_get_datatable'));

            if (current_user_can('manage_options')) {
	            add_action('shutdown', array($this, 'redirect_to_settings_tab'));
                add_action('admin_init', array($this, 'listen_for_clear_database'), 40);
            }

            add_action('wp_dashboard_setup', array($this, 'add_wpsi_dashboard_widget'));
			add_action('admin_menu', array($this, 'maybe_add_plus_one') );
			add_action('wpsi_on_settings_page', array($this, 'reset_plus_one_ten_searches') );
        }

        /**
         *
         * Stuff to update after plugin upgrade
         *
         * @since 1.3
         *
         */

        public function check_upgrade() {

            $prev_version = get_option( 'wpsi-current-version', false );

            if ( $prev_version && version_compare( $prev_version, '1.3.7', '<' ) ) {
                update_option('wpsi_select_term_deletion_period' , 'never');
            }

            update_option( 'wpsi-current-version', wpsi_version );
        }

	    /**
	     * Do a one time check if a known ajax plugin is installed. If so, activate Ajax tracking.
	     */

        public function maybe_enable_ajax_tracking(){

			if (!get_option('wpsi_checked_ajax_plugins')){
				$ajax_plugin_active =
					function_exists('searchwp_live_search_request_handler') //SearchWP Live Ajax Search
					|| defined('ASL_CURRENT_VERSION' ) //ajax search lite
                    ;
				if ($ajax_plugin_active) {
					update_option('wpsi_track_ajax_searches', true);
				}
				update_option('wpsi_checked_ajax_plugins', true);
			}
        }


	    /**
	     * Set up grid array
	     */

        public function init_grid(){
		    $this->tabs = apply_filters('wpsi_tabs', array(
		            'dashboard' => array(
		                    'title'=> __( "General", "wp-search-insights" ),
                    ),
		            'settings' => array(
			            'title'=> __( "Settings", "wp-search-insights" ),
			            'capability' => 'manage_options',
		            ),
            ));

            $this->grid_items = array(
                1 => array(
                    'title' => __("All Searches", "wp-search-insights"),
                    'content' => '<div class="wpsi-skeleton"></div>',
                    'class' => 'table-overview wpsi-load-ajax',
                    'type' => 'all',
                    'controls' => '',
                    'can_hide' => true,

                ),
                2 => array(
                    'title' => __("Results", "wp-search-insights"),
                    'content' => '<div class="wpsi-skeleton"></div>',
                    'class' => 'small wpsi-load-ajax',
                    'type' => 'results',
                    'controls' => '',
                    'can_hide' => true,
                    'ajax_load' => true,

                ),
                3 => array(
                    'title' => __("Most Popular Searches", "wp-search-insights"),
                    'content' => '<div class="wpsi-skeleton"></div>',
                    'class' => 'small wpsi-load-ajax',
                    'type' => 'popular',
                    'controls' => '',
                    'can_hide' => true,
                    'ajax_load' => true,

                ),
                4 => array(
                    'title' => __("Tips & Tricks", "wp-search-insights"),
                    'content' => $this->generate_tips_tricks(),
                    'type' => 'tasks',
                    'class' => 'half-height wpsi-tips-tricks',
                    'can_hide' => true,
                    'controls' => '',
                ),
            );
        }

        public function inline_styles()
        {
            ?>
	        <script type="text/javascript">
                jQuery(document).ready(function ($) {
                    "use strict";

                    var unixStart = localStorage.getItem('wpsi_range_start');
                    var unixEnd = localStorage.getItem('wpsi_range_end');

                     if (unixStart === null || unixEnd === null ) {
                        unixStart = moment().endOf('day').subtract(1, 'week').unix();
                        unixEnd = moment().endOf('day').unix();
                        localStorage.setItem('wpsi_range_start', unixStart);
                        localStorage.setItem('wpsi_range_end', unixEnd);
                     }

	                unixStart = parseInt(unixStart);
                    unixEnd = parseInt(unixEnd);
                    wpsiUpdateDate(moment.unix(unixStart), moment.unix(unixEnd));

                    function wpsiUpdateDate(start, end) {
                        $('.wpsi-date-container span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
                        localStorage.setItem('wpsi_range_start', start.add( moment().utcOffset(), 'm' ).unix());
                        localStorage.setItem('wpsi_range_end', end.add( moment().utcOffset(), 'm' ).unix());
                    }
                    var todayStart = moment().endOf('day').subtract(1, 'days').add(1, 'minutes');
                    var todayEnd = moment().endOf('day');
                    var yesterdayStart = moment().endOf('day').subtract(2, 'days').add(1, 'minutes');

                    var yesterdayEnd = moment().endOf('day').subtract(1, 'days');
                    var lastWeekStart = moment().endOf('day').subtract(8, 'days').add(1, 'minutes');
                    var lastWeekEnd = moment().endOf('day').subtract(1, 'days');

                    var wpsiPluginActivated = '<?php echo get_option('wpsi_activation_time')?>';

                    $('.wpsi-date-container.wpsi-table-range').daterangepicker(
                        {
                            ranges: {
                                'Today': [todayStart, todayEnd],
                                'Yesterday': [yesterdayStart, yesterdayEnd],
                                'Last 7 Days': [lastWeekStart, lastWeekEnd],
                                'Last 30 Days': [moment().subtract(31, 'days'), yesterdayEnd],
                                'This Month': [moment().startOf('month'), moment().endOf('month')],
                                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                                'All time': [moment.unix(wpsiPluginActivated), moment()]
                            },
                            "locale": {
                                "format": "<?php _e( 'MM/DD/YYYY', 'wp-search-insights' );?>",
                                "separator": " - ",
                                "applyLabel": "<?php _e("Apply","wp-search-insights")?>",
                                "cancelLabel": "<?php _e("Cancel","wp-search-insights")?>",
                                "fromLabel": "<?php _e("From","wp-search-insights")?>",
                                "toLabel": "<?php _e("To","wp-search-insights")?>",
                                "customRangeLabel": "<?php _e("Custom","wp-search-insights")?>",
                                "weekLabel": "<?php _e("W","wp-search-insights")?>",
                                "daysOfWeek": [
                                    "<?php _e("Mo","wp-search-insights")?>",
                                    "<?php _e("Tu","wp-search-insights")?>",
                                    "<?php _e("We","wp-search-insights")?>",
                                    "<?php _e("Th","wp-search-insights")?>",
                                    "<?php _e("Fr","wp-search-insights")?>",
                                    "<?php _e("Sa","wp-search-insights")?>",
                                    "<?php _e("Su","wp-search-insights")?>",

                                ],
                                "monthNames": [
                                    "<?php _e("January")?>",
                                    "<?php _e("February")?>",
                                    "<?php _e("March")?>",
                                    "<?php _e("April")?>",
                                    "<?php _e("May")?>",
                                    "<?php _e("June")?>",
                                    "<?php _e("July")?>",
                                    "<?php _e("August")?>",
                                    "<?php _e("September")?>",
                                    "<?php _e("October")?>",
                                    "<?php _e("November")?>",
                                    "<?php _e("December")?>"
                                ],
                                "firstDay": 1
                            },
                            "alwaysShowCalendars": true,
                            startDate: moment.unix(unixStart),
                            endDate: moment.unix(unixEnd),
                            "opens": "left",
                        }, function (start, end, label) {
                            wpsiUpdateDate(start, end);
                            window.wpsiLoadAjaxTables();
                        });


                    $('.wpsi-date-container.wpsi-export').daterangepicker(
                        {
                            ranges: {
                                'Today': [todayStart, todayEnd],
                                'Yesterday': [yesterdayStart, yesterdayEnd],
                                'Last 7 Days': [lastWeekStart, lastWeekEnd],
                                'Last 30 Days': [moment().subtract(31, 'days'), yesterdayEnd],
                                'This Month': [moment().startOf('month'), moment().endOf('month')],
                                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                                'All time': [moment.unix(wpsiPluginActivated), moment()]
                            },
                            "locale": {
                                "format": "<?php _e( 'MM/DD/YYYY', 'wp-search-insights' );?>",
                                "separator": " - ",
                                "applyLabel": "<?php _e("Apply","wp-search-insights")?>",
                                "cancelLabel": "<?php _e("Cancel","wp-search-insights")?>",
                                "fromLabel": "<?php _e("From","wp-search-insights")?>",
                                "toLabel": "<?php _e("To","wp-search-insights")?>",
                                "customRangeLabel": "<?php _e("Custom","wp-search-insights")?>",
                                "weekLabel": "<?php _e("W","wp-search-insights")?>",
                                "daysOfWeek": [
                                    "<?php _e("Mo","wp-search-insights")?>",
                                    "<?php _e("Tu","wp-search-insights")?>",
                                    "<?php _e("We","wp-search-insights")?>",
                                    "<?php _e("Th","wp-search-insights")?>",
                                    "<?php _e("Fr","wp-search-insights")?>",
                                    "<?php _e("Sa","wp-search-insights")?>",
                                    "<?php _e("Su","wp-search-insights")?>",

                                ],
                                "monthNames": [
                                    "<?php _e("January")?>",
                                    "<?php _e("February")?>",
                                    "<?php _e("March")?>",
                                    "<?php _e("April")?>",
                                    "<?php _e("May")?>",
                                    "<?php _e("June")?>",
                                    "<?php _e("July")?>",
                                    "<?php _e("August")?>",
                                    "<?php _e("September")?>",
                                    "<?php _e("October")?>",
                                    "<?php _e("November")?>",
                                    "<?php _e("December")?>"
                                ],
                                "firstDay": 1
                            },
                            "alwaysShowCalendars": true,
                            startDate: moment.unix(unixStart),
                            endDate: moment.unix(unixEnd),
                            "opens": "right",
                        }, function (start, end, label) {
                            wpsiUpdateDate(start, end);
                        });

                });
	        </script>
            <!--    Thickbox needs inline style, otherwise the style is overriden by WordPres thickbox.css-->
            <style>
                div#TB_window {
                    height: 260px;
                    width: 450px;
                }

                div#TB_ajaxWindowTitle {
                    line-height: 50px;
                }

                div#TB_ajaxContent {
                    font-size: 1.1em;
                }

                div#TB_title {
                    font-size: 1.2em;
                    font-weight: 900;
                    height: 50px;
                    background-color: #d7263d;
                    color: #f2f2f2;
                }

                span.tb-close-icon {
                    visibility: hidden;
                }

                span.tb-close-icon::before {
                    font-family: dashicons;
                    font-size: 2.3em;
                    line-height: 50px;
                    margin-left: -25px;
                    color: #f2f2f2;
                    visibility: visible;
                    display: inline-block;
                    content: "\f335";
                    opacity: 0.7;
                }

            </style>

			<?php
		}

        public function enqueue_assets($hook)
        {
            global $search_insights_settings_page;
            // Enqueue assest when on index.php (WP dashboard) or plugins settings page

            if ($hook == 'index.php' || $hook == $search_insights_settings_page) {
	            //Datatables javascript for interactive tables
	            wp_register_script('wpsi-datatables',
		            trailingslashit(wpsi_url)
		            . 'assets/js/datatables.min.js', array("jquery"), wpsi_version);
	            wp_enqueue_script('wpsi-datatables');

	            //datapicker
	            wp_enqueue_style( 'wpsi-datepicker' , trailingslashit(wpsi_url) . 'assets/datepicker/datepicker.css', "",
		            wpsi_version);

	            wp_register_script('wpsi-moment',
		            trailingslashit(wpsi_url)
		            . 'assets/datepicker/moment.js', array("jquery"), wpsi_version);

	            wp_register_script('wpsi-datepicker',
		            trailingslashit(wpsi_url)
		            . 'assets/datepicker/datepicker.js', array("jquery", "moment"), wpsi_version);

                wp_register_style('wpsi',
                    trailingslashit(wpsi_url) . "assets/css/style.css", "",
                    wpsi_version);
                wp_enqueue_style('wpsi');

                wp_register_script('wpsi',
                    trailingslashit(wpsi_url)
                    . 'assets/js/scripts.js', array("jquery", "wpsi-datepicker", "wpsi-datatables"), wpsi_version);

                wp_enqueue_script('wpsi');
                wp_localize_script('wpsi', 'wpsi',
                    array(
		                'ajaxurl' => admin_url( 'admin-ajax.php' ),
		                'skeleton' => '<div class="wpsi-skeleton"></div>',
		                'strings' => array(
		                        'download' => __("Download", 'wp-search-insights')
                        ),
		                'export_in_progress' => get_transient('wpsi_export_in_progress'),
		                'token'   => wp_create_nonce( 'search_insights_nonce'),
		                'localize'   => array(
		                	    'search'=> __('Search', 'wp-search-insights'),
		                	    'previous'=> __('Previous', 'wp-search-insights'),
		                	    'next'=> __('Next', 'wp-search-insights'),
		                	    'no-searches'=> __('No searches recorded in selected period.', 'wp-search-insights'),
		                ),
		                'dateFilter'   => '<select class="wpsi-date-filter">
                                                <option value="month">'.__("Month", "wp-search-insights").'</option>
                                                <option value="week" selected="selected">'.__("Week", "wp-search-insights").'</option>
                                                <option value="day">'.__("Day", "wp-search-insights").'</option>
                                            </select>',
	                )
                );

                // The dashboard widget doesn't use fontello or pagination, return here if we're on the WP dashboard.
                if ($hook == 'index.php') return;

	            //Datatables plugin to hide pagination when it isn't needed
	            wp_register_script('wpsi-datatables-pagination',
		            trailingslashit(wpsi_url)
		            . 'assets/js/dataTables.conditionalPaging.js', array("jquery"), wpsi_version);
	            wp_enqueue_script('wpsi-datatables-pagination');


            }
        }

		public function reset_plus_one_ten_searches(){
		    if (get_option('wpsi_ten_searches_viewed_settings_page')) return;

			$items        = WPSI::$search->get_searches_single();
			$search_count = count( $items );

			if ($search_count>10) {
				delete_transient('wpsi_plus_ones');
				update_option( 'wpsi_ten_searches_viewed_settings_page', true );
			}
        }

	    /**
         * Get number of plus ones
	     * @return int
	     */

		public function count_plusones(){
            $plus_ones = get_transient('wpsi_plus_ones');
            if (!$plus_ones){
	            $plus_ones = 0;

	            if (!get_option('wpsi_ten_searches_viewed_settings_page')) {
		            $items        = WPSI::$search->get_searches_single();
		            $search_count = count( $items );
		            if ( $search_count > 10 ) {
			            $plus_ones ++;
		            }
	            }

	            set_transient('wpsi_plus_ones',$plus_ones, DAY_IN_SECONDS);
            }

		    return $plus_ones;
        }

		/**
		 *
		 * Add a settings page
		 *
		 * @since 1.0
		 *
		 */

		public function add_settings_page() {
			if ( ! current_user_can( $this->capability ) ) {
				return;
			}

			global $search_insights_settings_page;

			$count = $this->count_plusones();
			$update_count = $count > 0 ? "<span class='update-plugins wpsi-update-count'><span class='update-count'>$count</span></span>":"";

			$search_insights_settings_page = add_submenu_page(
				'tools.php',
				"WP Search Insights",
				"WP Search Insights".$update_count,
				$this->capability, //capability
				'wpsi-settings-page', //url
				array( $this, 'settings_page' ) ); //function
		}

	    /**
	     *
	     * @since 3.1.6
	     *
	     * Add an update count to the WordPress admin Settings menu item
	     * Doesn't work when the Admin Menu Editor plugin is active
	     *
	     */

	    public function maybe_add_plus_one()
	    {
		    if (!current_user_can($this->capability)) return;

		    global $menu;

		    $count = $this->count_plusones();
		    $menu_slug = 'tools.php';
		    $menu_title = __('Tools');

		    foreach($menu as $index => $menu_item){
			    if (!isset($menu_item[2]) || !isset($menu_item[0])) continue;
			    if ($menu_item[2]===$menu_slug){
                    $pattern = '/<span.*>([1-9])<\/span><\/span>/i';
                    if (preg_match($pattern, $menu_item[0], $matches)){
                        if (isset($matches[1])) $count = intval($count) + intval($matches[1]);
                    }

                    $update_count = $count > 0 ? "<span class='update-plugins rsssl-update-count'><span class='update-count'>$count</span></span>":'';
				    $menu[$index][0] = $menu_title . $update_count;
                }

		    }

	    }

	    /**
	     * @return int
	     *
	     * @since 3.1.6
	     *
	     * Check if there is an existing update count after the Settings menu item
	     *
	     */

	    public function get_existing_tools_plusones($menu_slug)
	    {
		    global $menu;

		    $existing_count = "0";

            foreach($menu as $index => $menu_item){
                if (!isset($menu_item[2]) || !isset($menu_item[0])) continue;

                if ($menu_item[2]!==$menu_slug) continue;
	            $str = $menu_item[0];
                if (strpos($str, "plugin-count") != false) {
                    $pattern = '/(?<=[\'|\"]plugin-count[\'|\"]>)(.*?)(?=\<)/i';

	                if (preg_match($pattern, $str, $matches)){
		                $existing_count = $matches[1];
	                }
                }
            }

		    return intval($existing_count);
	    }


        /**
         * @param $links
         *
         * Create a settings link to show in plugins overview
         *
         * @return $links
         * @since 1.0
         *
         */
        public function plugin_settings_link($links)
        {
            $settings_link = '<span class="wpsi-settings-link"></span><a href="tools.php?page=wpsi-settings-page">'
                . __("Settings", "wp-search-insights") . '</a></span>';
            array_unshift($links, $settings_link);

            $faq_link
                = '<a href="https://wpsi.io/documentation/" target="_blank">'
                . __('Docs', 'wp-search-insights') . '</a>';
            array_unshift($links, $faq_link);

            return $links;
        }

        /**
         *
         * Define settings sections and fields
         *
         * @since 1.0
         *
         */

        public function wpsi_settings_section_and_fields()
        {
            if (!current_user_can('manage_options')) {
                return;
            }
	        add_thickbox();
            // Add a settings section to the 'Settings' tab
            add_settings_section(
                'wpsi-settings-tab',
                __("", "wpsi-search-insights"),
                array($this, 'wpsi_settings_tab_intro'),
                'wpsi-settings'
            );

            // Add the field with the names and function to use for our new
            // settings, put it in our new section

            add_settings_field(
                'exclude_admin_searches',
                __("Exclude admin searches", 'wp-search-insights').WPSI::$help->get_help_tip(__("With this option enabled all searches of logged in administrators will be ignored", "wp-search-insights")),
                array($this, 'option_wpsi_exclude_admin'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'min_search_length',
                __("Exclude searches shorter than characters", 'wp-search-insights').WPSI::$help->get_help_tip(__("All searches with a count below this value will be ignored. Set to 0 for no limitations.", "wp-search-insights")),
                array($this, 'option_min_term_length'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'max_search_length',
                __("Exclude searches longer than characters", 'wp-search-insights').WPSI::$help->get_help_tip(__("All searches with a count above this value will be ignored. Set to 0 for no limitations.", "wp-search-insights")),
                array($this, 'option_max_term_length'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'wpsi_select_dashboard_capability',
                __("Who can view the dashboard", 'wp-search-insights').WPSI::$help->get_help_tip(__("Select who can view the dashboard. Choose between administrators and all users", "wp-search-insights")),
                array($this, 'option_wpsi_select_dashboard_capability'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'wpsi_track_ajax_searches',
                __("Track Ajax searches", 'wp-search-insights').WPSI::$help->get_help_tip(__("Track searches made via an AJAX request. Enable if you use an AJAX search plugin", "wp-search-insights")),
                array($this, 'option_wpsi_track_ajax_searches'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'wpsi_delete_terms_after_period',
                __("Automatically delete terms from your database after this period", 'wp-search-insights').WPSI::$help->get_help_tip(__("Automatically delete all WP Search Insights entries from the database after this time period", "wp-search-insights")),
                array($this, 'option_wpsi_delete_terms_after_period'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'wpsi_custom_search_parameter',
                __("Custom search parameter", 'wp-search-insights').WPSI::$help->get_help_tip(__("Set a custom search parameter. Default WordPress is ?=s. Replace the 's' with your own paramater. For example 'search' for the Search REST API which uses ?=search", "wp-search-insights")),
                array($this, 'option_wpsi_custom_search_parameter'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            // Register our setting so that $_POST handling is done for us and
            // our callback function just has to echo the <input>
            register_setting('wpsi-settings-tab', 'wpsi_exclude_admin');
            register_setting('wpsi-settings-tab', 'wpsi_min_term_length');
            register_setting('wpsi-settings-tab', 'wpsi_max_term_length');
            register_setting('wpsi-settings-tab', 'wpsi_select_dashboard_capability');
            register_setting('wpsi-settings-tab', 'wpsi_track_ajax_searches');
            register_setting('wpsi-settings-tab', 'wpsi_select_term_deletion_period');
            register_setting('wpsi-settings-tab', 'wpsi_custom_search_parameter');

	        add_settings_section(
		        'wpsi-settings-tab',
		        __("", "wpsi-search-insights"),
		        array($this, 'wpsi_settings_tab_intro'),
		        'wpsi-filter'
	        );

	        /**
	         * filter grid
	         */

	        add_settings_field(
		        'wpsi_filter_textarea',
		        __("Search Filter","wp-search-insights"),
		        array($this, 'option_textarea_filter'),
		        'wpsi-filter',
		        'wpsi-settings-tab'
	        );

	        register_setting('wpsi-filter-tab', 'wpsi_filter_textarea');


	        /**
	         * data grid
             */

	        add_settings_section(
		        'wpsi-data-tab',
		        __("", "wpsi-search-insights"),
		        array($this, 'wpsi_settings_tab_intro'),
		        'wpsi-data'
	        );

	        add_settings_field(
		        'wpsi_cleardatabase',
		        __("Clear data on uninstall", 'wp-search-insights').WPSI::$help->get_help_tip(__("Enable this option if you want to delete the WP Search Insights database tables when you uninstall the plugin.", "wp-search-insights")),
		        array($this, 'option_clear_database_on_uninstall'),
		        'wpsi-data',
		        'wpsi-data-tab'
	        );

	        add_settings_field(
		        'wpsi_clear_database',
		        __("Clear database", 'wp-search-insights'),
		        array($this, 'option_wpsi_clear_database'),
		        'wpsi-data',
		        'wpsi-data-tab'
	        );

	        register_setting('wpsi-data-tab', 'wpsi_cleardatabase');


        }

	    /**
	     * @param string $msg
	     * @param string $type notice | warning | success
	     * @param bool $hide
	     * @param bool $echo
	     * @return string|void
	     */
	    public function notice($msg, $type = 'notice', $hide = false, $echo = true)
	    {
		    if ($msg == '') return;

		    $hide_class = $hide ? "wpsi-hide" : "";
		    $html = '<div class="wpsi-panel wpsi-' . $type . ' ' . $hide_class . '">' . $msg . '</div>';
		    if ($echo) {
			    echo $html;
		    } else {
			    return $html;
		    }
	    }

        public function add_privacy_info()
        {
            if (!function_exists('wp_add_privacy_policy_content')) {
                return;
            }

            $content = sprintf(
                __('WP Search Insights does not process any personal identifiable information, so the GDPR does not apply to these plugins or usage of these plugins on your website. You can find our privacy policy <a href="%s" target="_blank">here</a>.', 'wp-search-insights'),
                'https://wpsi.io/privacy-statement/'
            );

            wp_add_privacy_policy_content(
                'WP Search Insights',
                wp_kses_post(wpautop($content, false))
            );
        }


        /**
         *
         * Echo the into text for settings page
         *
         * @since 1.0
         *
         */

        public function wpsi_settings_tab_intro() {}

        public function option_wpsi_exclude_admin()
        {
            ?>
            <div class="tg-list-item">
                <label class="wpsi-switch">
                    <input name="wpsi_exclude_admin" type="hidden" value="0"/>

                    <input name="wpsi_exclude_admin" size="40" type="checkbox"
                           value="1" <?php checked(1, get_option('wpsi_exclude_admin'), true) ?> />
                    <span class="wpsi-slider wpsi-round"></span>
                </label>
            </div>
            <?php
        }

        public function option_wpsi_select_dashboard_capability()
        {
            ?>
            <label class="wpsi-select-capability">
                <select name="wpsi_select_dashboard_capability" id="wpsi_select_dashboard_capability">
                    <option value="activate_plugins" <?php if (get_option('wpsi_select_dashboard_capability') == 'activate_plugins') {
                        echo 'selected="selected"';
                    } ?>><?php _e('Administrators', 'wp-search-insights'); ?></option>
                    <option value="read" <?php if (get_option('wpsi_select_dashboard_capability') == 'read') {
                        echo 'selected="selected"';
                    } ?>><?php _e('All Users', 'wp-search-insights'); ?></option>
                </select>
            </label>
            <?php
        }


        public function option_clear_database_on_uninstall()
        {
            ?>
            <div class="tg-list-item">
                <label class="wpsi-switch">
                    <input name="wpsi_cleardatabase" type="hidden" value="0"/>

                    <input name="wpsi_cleardatabase" size="40" type="checkbox"
                           value="1" <?php checked(1, get_option('wpsi_cleardatabase'), true) ?> />
                    <span class="wpsi-slider wpsi-round"></span>
                </label>
            </div>
            <?php
        }

        public function option_wpsi_track_ajax_searches()
        {
            ?>
            <div class="tg-list-item">
                <label class="wpsi-switch">
                    <input name="wpsi_track_ajax_searches" type="hidden" value="0"/>
                    <input name="wpsi_track_ajax_searches" size="40" type="checkbox"
                           value="1" <?php checked(1, get_option('wpsi_track_ajax_searches'), true) ?> />
                    <span class="wpsi-slider wpsi-round"></span>
                </label>

                <?php
                    WPSI::$help->get_help_tip(__("Enable this option if you want to track searches while users are typing.", "wp-search-insights"));
                ?>
            </div>
            <?php
        }

        /**
         * Set the option to automatically delete search terms from the database after a certain time period
         *
         * @since 1.3
         *
         */

        public function option_wpsi_delete_terms_after_period()
        {
            ?>
            <label class="wpsi-select-deletion-period">
                <select name="wpsi_select_term_deletion_period" id="wpsi_select_term_deletion_period">
                    <option value="never" <?php if (get_option('wpsi_select_term_deletion_period') == 'never') {
                        echo 'selected="selected"';
                    } ?>><?php _e('Never', 'wp-search-insights'); ?></option>
                    <option value="week" <?php if (get_option('wpsi_select_term_deletion_period') == 'week') {
                        echo 'selected="selected"';
                    } ?>><?php _e('Week', 'wp-search-insights'); ?></option>
                    <option value="month" <?php if (get_option('wpsi_select_term_deletion_period') == 'month') {
                        echo 'selected="selected"';
                    } ?>><?php _e('Month', 'wp-search-insights'); ?></option>
                    <option value="year" <?php if (get_option('wpsi_select_term_deletion_period') == 'year') {
                        echo 'selected="selected"';
                    } ?>><?php _e('Year', 'wp-search-insights'); ?></option>
                </select>
            </label>
            <?php
            WPSI::$help->get_help_tip(__("When to delete terms from your database after this time period", "wp-search-insights"));
            ?>
            <?php
        }

        public function option_wpsi_custom_search_parameter(){
	        ?>
            <input id="wpsi_custom_search_parameter" class="wpsi_custom_search_parameter" name="wpsi_custom_search_parameter" size="40"  value="<?php echo get_option('wpsi_custom_search_parameter') ?>"
                   type="text">
	        <?php
        }

        public function option_min_term_length()
        {
            ?>
            <input id="wpsi_min_term_length" class="wpsi_term_length" name="wpsi_min_term_length" size="40" min="0"
                   max="24" value="<?php echo intval(get_option('wpsi_min_term_length')) ?>"
                   type="number">
            <?php
        }

        /**
         * shows option max term length
         */

        public function option_max_term_length()
        {
            ?>
            <input id="wpsi_max_term_length" class="wpsi_term_length" name="wpsi_max_term_length" size="40" min="0"
                   max="255" value="<?php echo intval(get_option('wpsi_max_term_length')) ?>"
                   type="number">
            <?php
        }

        public function option_wpsi_clear_database()
        {

            $args = array(
                'action_label' => __("Clear database", "wp-search-insights"),
                'title' => __("Are you sure?", "wp-search-insights"),
                'description' => __("Clearing the database deletes all recorded searches. You can create a backup by exporting the tables to either .csv or .xlsx format by pressing the download button beneath the tables.", "wp-search-insights"),
                'action' => 'wpsi_clear_database',
            );
            $this->add_thickbox_button($args);
            ?>
            <?php
        }


        /**
         * Create button with popup using WP core thickbox
         * @param $args
         */

        public function add_thickbox_button($args)
        {

            $default_args = array(
                "title" => '',
                "action_label" => '',
                "action" => '',
                "description" => '',
            );
            $args = wp_parse_args($args, $default_args);
            $token = wp_create_nonce('wpsi_thickbox_nonce');
            $action_url = add_query_arg(array('page' => 'wpsi-settings-page', 'action' => $args['action'], 'token' => $token), admin_url("tools.php"));

            ?>
            <div>
                <input class="thickbox button"
                       title="<?php _e("You're about to clear your database!", "wp-search-insights"); ?>"
                       type="button" style="display: block;"
                       alt="#TB_inline?height=260&width=450&inlineId=wpsi_<?php echo esc_attr($args['action']) ?>"
                       value="<?php echo __('Clear all searches', 'wp-search-insights'); ?>"/>
            </div>
            <div id="wpsi_<?php echo esc_attr($args['action']) ?>" style="display: none;">
	            <?php
	            /**
	             * Using a H1 tag here causes some strange issues with admin notices.
	             */
	            ?>
	            <div class="wpsi-thickboxheader"><?php echo $args["title"] ?></div>
	            <p><?php echo $args['description'] ?></p>
	            <script>
                    jQuery(document).ready(function ($) {
                        $('#wpsi_cancel_<?php echo esc_attr($args['action'])?>').click(tb_remove);
                    });
	            </script>
	            <a class="button button-primary"
	               style="width: 130px; height: 25px; line-height: 25px; margin-right:20px; text-align: center; font-weight: 700;"
	               href="<?php echo $action_url ?>">
		            <?php echo $args['action_label'] ?>
	            </a>

	            <a class="button" style="height: 25px; line-height: 25px;" href="#"
	               id="wpsi_cancel_<?php echo esc_attr($args['action']) ?>">
		            <?php _e("Cancel", "wp-search-insights") ?>
	            </a>

            </div>
            <?php
        }

        public function option_textarea_filter()
        {
            ?>
            <textarea name="wpsi_filter_textarea" rows="3" cols="40" id="wpsi_filter_textarea"><?php
                echo esc_html(get_option('wpsi_filter_textarea'));
            ?></textarea>
            <?php
        }

        /**
         *
         * Check if the clear database button is pressed
         *
         * @since 1.0
         *
         */

        public function listen_for_clear_database()
        {
            // Capability is checked before adding action for this function

            //check nonce
            if (!isset($_GET['token']) || (!wp_verify_nonce($_GET['token'], 'wpsi_thickbox_nonce'))) {
                return;
            }
            //check for action
            if (isset($_GET["action"]) && $_GET["action"] == 'wpsi_clear_database') {
                $this->clear_database_tables();
                $this->clear_cache();
            }
            wp_redirect(admin_url('tools.php?page=wpsi-settings-page'));
            exit;
        }

        /**
         * Delete entries from database after certain period
         *
         * @since 1.3.8
         *
         */

        public function clear_entries_from_database() {
            // Nonce is already verified before calling this function
            if (!current_user_can($this->capability)) {
                return;
            }

            $period = get_option('wpsi_select_term_deletion_period');

            $past_date = '';

            if ($period == 'week') {
                $past_date = strtotime("-1 week");
            }

            if ($period == 'month') {
                $past_date = strtotime("-1 month");
            }

            if ($period == 'year') {
                $past_date = strtotime("-1 year");
            }

            $this->delete_from_tables_after_period($past_date);
        }

        public function delete_from_tables_after_period($past_date) {

            if ( ! current_user_can( $this->capability ) ) return;

            global $wpdb;

            $table_name_single = $wpdb->prefix . 'searchinsights_single';
            $table_name_archive = $wpdb->prefix . 'searchinsights_archive';

            $wpdb->query("DELETE FROM $table_name_single WHERE (time < $past_date)");
            $wpdb->query("DELETE FROM $table_name_archive WHERE (time < $past_date)");

        }

        /**
         * Clear the transient caches
         */

        public function clear_cache(){
            delete_transient( 'wpsi_popular_searches' );
            delete_transient( 'wpsi_top_searches' );
            delete_transient( 'wpsi_top_searches_week' );
            delete_transient( 'wpsi_popular_searches_week' );
	        delete_transient( 'wpsi_plus_ones');
        }

        /**
         *
         * Content of the settings page
         *
         * @since 1.0
         *
         */

        public function settings_page()
        {

            if (!current_user_can($this->capability)) {
                return;
            }
            do_action('wpsi_on_settings_page' );
            ?>
            <style>
                #wpcontent {padding-left: 0 !important;}
            </style>
            <div class="wrap">
                <div id="wpsi-toggle-wrap">
                    <div id="wpsi-toggle-dashboard">
                        <div id="wpsi-toggle-dashboard-text">
                            <?php _e("Select which dashboard items should be displayed", "wp-search-insights") ?>
                        </div>
                        <div id="wpsi-checkboxes">
                            <?php
                            $grid_items = $this->grid_items;
                            foreach ($grid_items as $index => $grid_item) {
                                $style = "";
                                if (!$grid_item['can_hide']) {
                                    $style = 'style="display:none"';
                                }
                                ?>
                                <label for="wpsi-hide-panel-<?= $index ?>" <?php echo $style ?>>
                                    <input class="wpsi-toggle-items" name="wpsi_toggle_data_id_<?= $index ?>" type="checkbox"
                                           id="wpsi_toggle_data_id_<?= $index ?>" value="data_id_<?= $index ?>">
                                    <?= $grid_item['title'] ?>
                                </label>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div id="wpsi-dashboard">
                    <div class="wp-search-insights-container">
                        <ul class="tabs">
                            <div class="tabs-content">
                                <img class="wpsi-settings-logo" src="<?=trailingslashit(wpsi_url)?>assets/images/logo.png" alt="WP Search Insights logo">
                                 <div class="header-links">
                                    <ul class="tab-links">
                                        <?php foreach ($this->tabs as $key => $tab) {
                                            if (isset($tab['capability']) && !current_user_can($tab['capability'])) continue;
	                                        $current = $key=='dashboard' ? 'current' : '';
                                            ?>
                                            <li class="tab-link <?=$current?>" data-tab="<?=$key?>"><a class="tab-text tab-<?=$key?>" href="#<?=$key?>#top"><?=$tab['title']?></a></li>
                                        <?php }?>
                                    </ul>
                                    <?php do_action('wpsi_tab_options')?>
                                </div>
                            </div>
                        </ul>

	                    <div class="wp-search-insights-main">
		                    <?php foreach ($this->tabs as $key => $tab) {
			                    if (isset($tab['capability']) && !current_user_can($tab['capability'])) continue;
			                    $current = $key=='dashboard' ? 'current' : '';

			                    ?>
                                <div id="<?=$key?>" class="tab-content <?=$current?>">
			                    <?php do_action("wpsi_tab_content_$key");?>
                                </div>
		                    <?php }?>
	                    </div>
                    </div>
                </div>
	        </div>
            <?php
        }



        /**
         *
         * Remove entries from database
         *
         * @since 1.0
         *
         */

        private function clear_database_tables()
        {

            // Nonce is already verified before calling this function
            if (!current_user_can($this->capability)) {
                return;
            }

            global $wpdb;

            $table_name_single = $wpdb->prefix . 'searchinsights_single';
            $table_name_archive = $wpdb->prefix . 'searchinsights_archive';

            $wpdb->query("TRUNCATE TABLE $table_name_single");
            $wpdb->query("TRUNCATE TABLE $table_name_archive");

        }

        /**
         *
         * Redirect back to the settings tab
         *
         * @since 1.0
         *
         * @access public
         *
         */

        public function redirect_to_settings_tab()
        {
            if (isset($_GET['wpsi_redirect_to'])){
                $url = add_query_arg(array(
                "page" => "wpsi-settings-page#".sanitize_title($_GET['wpsi_redirect_to'])."#top",
            ), admin_url("tools.php"));
	            wp_safe_redirect($url);
	            exit;
            }

        }

        /**
         *
         * Add a dashboard widget
         *
         * @since 1.0
         *
         */

        public function add_wpsi_dashboard_widget()
        {
            wp_add_dashboard_widget('dashboard_widget_wpsi', 'WP Search Insights', array(
                $this,
                'generate_dashboard_widget_wrapper'
            ));
        }

        /**
         * Wrapper function for dashboard widget so params can be sent along
         */

        public function generate_dashboard_widget_wrapper() {
            echo $this->generate_dashboard_widget($on_grid = false);
        }


        public function get_template($file, $path = wpsi_path, $args = array())
        {

            $file = trailingslashit($path) . 'templates/' . $file;
            $theme_file = trailingslashit(get_stylesheet_directory()) . dirname(wpsi_path) . $file;

            if (file_exists($theme_file)) {
                $file = $theme_file;
            }

            if (isset($args['tooltip'])) {
                $args['tooltip'] = WPSI::$help->get_title_help_tip($args['tooltip']);
            } else {
	            $args['tooltip'] = '';
            }

            if (strpos($file, '.php') !== false) {
                ob_start();
                require $file;
                $contents = ob_get_clean();
            } else {
                $contents = file_get_contents($file);
            }

	        if (isset($args['type']) && ($args['type'] === 'settings' || $args['type'] === 'license')) {
		        $form_open =  '<form action="'.esc_url( add_query_arg(array('wpsi_redirect_to' => sanitize_title($args['type'])), admin_url( 'options.php' ))).'" method="post">';
                $form_close = '</form>';
		        $button = wpsi_save_button();
		        $contents = str_replace('{content}', $form_open.'{content}'.$button.$form_close, $contents);

	        }

            foreach ($args as $key => $value ){
                $contents = str_replace('{'.$key.'}', $value, $contents);
            }



	        return $contents;
        }

        public function dashboard_row()
        {

        }

        /**
         *
         * Generate the dashboard widget
         * Also generated the Top Searches grid item
         *
         * @param bool $on_grid true if on grid
         * @param int|bool $start
         * @param int|bool $end
         * @return false|string
         */
        public function generate_dashboard_widget($on_grid = false, $start=false, $end=false)
        {
            ob_start();

            if (!$on_grid) {
                $widget = $this->get_template('dashboard-widget.php');
            } else {
                $widget = $this->get_template('grid-dashboard-widget.php');
            }

            $html = "";

            //only use cached data on dash
            $popular_searches_no_results = get_transient("wpsi_popular_searches_week");
            if ($on_grid) $popular_searches_no_results = false;
            if (!$popular_searches_no_results) {
                $args = array(
                    'orderby' => 'frequency',
                    'order' => 'DESC',
                    'result_count' => 0,
                    'number' => 5,
                );

                //from the dashboard, we don't get a start-end range. We use default month.
                if (!$on_grid) {
                    $args['range'] = 'month';
                } else {
	                $args['date_from'] = $start;
	                $args['date_to'] = $end;
                }

	            $popular_searches_no_results = WPSI::$search->get_searches($args, $trend = true);
	            if (!$on_grid) set_transient("wpsi_popular_searches_week", $popular_searches_no_results, HOUR_IN_SECONDS);
            }

            if (!$on_grid) {
                $tmpl = $this->get_template('dashboard-row.php');
            } else {
                $tmpl = $this->get_template('grid-dashboard-row.php');
            }

            if (!$on_grid) {
	            if ( count( $popular_searches_no_results ) == 0 ) {
		            $html .= str_replace( array(
			            "{icon}",
			            "{link}",
			            "{searches}",
			            "{time}"
		            ), array(
			            'dashicons-no-alt',
			            __( "No recorded searches", "wp-search-insights" ),
			            '',
			            ''
		            ), $tmpl );
	            }

	            $home_url = home_url();
	            foreach ( $popular_searches_no_results as $search ) {
		            if ( $search->frequency == $search->previous_frequency ) {
			            $icon = 'dashicons-minus';
		            } elseif ( $search->frequency
		                       > $search->previous_frequency
		            ) {
			            $icon = 'dashicons-arrow-up-alt';
		            } else {
			            $icon = 'dashicons-arrow-down-alt';
		            }
		            $time = sprintf( __( "%s ago", "wp-search-insights" ),
			            human_time_diff( $search->time,
				            current_time( 'timestamp' ) ) );
		            $searches = sprintf( _n( '%s search', '%s searches',
			            $search->frequency, 'wpsi-search-insights' ),
			            number_format_i18n( $search->frequency ) );
		            $html .= str_replace( array(
			            "{icon}",
			            "{link}",
			            "{searches}",
			            "{time}"
		            ), array(
			            $icon,
			            $this->get_term_link( $search->term, $home_url ),
			            $searches,
			            $time
		            ), $tmpl );
	            }

	            $widget = str_replace( '{popular_searches}', $html, $widget );
            }

            //reset html
	        $html = '';
            $top_searches = get_transient("wpsi_top_searches_week");
	        if ($on_grid) $top_searches = false;

	        if (!$top_searches) {
                $args = array(
                    'orderby' => 'frequency',
                    'order' => 'DESC',
                    'number' => 5,
                );
		        if (!$on_grid) {
			        $args['range'] = 'month';
		        } else {
			        $args['date_from'] = $start;
			        $args['date_to'] = $end;
		        }
                $top_searches = WPSI::$search->get_searches($args, $trend = true);
		        if (!$on_grid) set_transient("wpsi_top_searches_week", $top_searches, HOUR_IN_SECONDS);
            }

            if (count($top_searches) == 0) {
                $html .= str_replace(array("{icon}", "{link}", "{searches}", "{time}"), array(
                    'dashicons-no-alt',
                    __("No recorded searches", "wp-search-insights"),
                    '',
                    ''
                ), $tmpl);
            }

	        $home_url = home_url();
            foreach ($top_searches as $search) {
                if ($search->frequency == $search->previous_frequency) {
                    $icon = 'dashicons-minus';
                } elseif ($search->frequency > $search->previous_frequency) {
                    $icon = 'dashicons-arrow-up-alt';
                } else {
                    $icon = 'dashicons-arrow-down-alt';
                }
                $time = sprintf(__("%s ago", "wp-search-insights"), human_time_diff($search->time, current_time('timestamp')));

                $searches = sprintf(_n('%s search', '%s searches', $search->frequency, 'wpsi-search-insights'), number_format_i18n($search->frequency));
                $html .= str_replace(array("{icon}", "{link}", "{searches}", "{time}"), array(
                    $icon,
                    $this->get_term_link($search->term, $home_url),
                    $searches,
                    $time
                ), $tmpl);
            }

            ob_get_clean();
            $widget = str_replace('{top_searches}', $html, $widget);
            return $widget;

        }


	    public function ajax_get_datatable()
	    {
		    $error = false;
		    $total = 0;
		    $html  = __("No data found", "wp-search-insights");
		    if (!current_user_can('manage_options')) {
			    $error = true;
		    }

		    if (!isset($_GET['start'])){
			    $error = true;
		    }

		    if (!isset($_GET['end'])){
			    $error = true;
		    }

		    if (!isset($_GET['type'])){
			    $error = true;
		    }

		    if (!isset($_GET['token'])){
			    $error = true;
		    }

		    $page = isset($_GET['page']) ? intval($_GET['page']) : false;

		    if (!$error && !wp_verify_nonce(sanitize_title($_GET['token']), 'search_insights_nonce')){
			    $error = true;
		    }

		    if (!$error){
			    $start = intval($_GET['start']);
			    $end = intval($_GET['end']);
			    $type = sanitize_title($_GET['type']);
			    $total = $this->get_results_count($type, $start, $end);
			    switch ($type){
                    case 'all':
	                    $html = $this->recent_table( $start, $end, $page);
	                    break;
                    case 'popular':
	                    $html = $this->generate_dashboard_widget(true, $start, $end);
	                    break;
				    case 'results':
					    $html = $this->results_table( $start, $end);
					    break;
                    default:
                        $html = apply_filters("wpsi_ajax_content_$type", '');
                        break;
			    }
		    }

		    $data = array(
			    'success' => !$error,
			    'html' => $html,
                'total_rows' => $total,
                'batch' => $this->rows_batch,
		    );

		    $response = json_encode($data);
		    header("Content-Type: application/json");
		    echo $response;
		    exit;
	    }

	    /**
         * Get total results count for an ajax request
         *
	     * @param string $type
	     * @param int $start
	     * @param int $end
         *
         * @return int
	     */

	    public function get_results_count($type, $start, $end){
	        $count = 0;
		    if ($type === 'all' ) {
                $args = array(
                    'date_from' => $start,
                    'date_to' => $end,
                    'count' => true,
                );
                $count = WPSI::$search->get_searches_single($args);
		    }

		    return $count;
        }

        /**
         * Generate the recent searches table in dashboard
         * @param int $start
         * @param int $end
         * @param int $page
         *
         * @return string|array
         * @since 1.0
         */

        public function recent_table($start, $end, $page)
        {
	        $home_url = home_url();
	        // Start generating rows
	        $args = array(
                'offset' => $this->rows_batch * ($page-1),
		        'number' =>$this->rows_batch,
		        'date_from' => $start,
		        'date_to' => $end,
		        'result_count' => true,
	        );
	        $recent_searches = WPSI::$search->get_searches_single($args);
	        if ( $page > 1 ) {
		        $output = array();
		        foreach ($recent_searches as $search) {
			        $output[] = '
                    <tr>
                        <td data-label="Term" class="wpsi-term"
                            data-term_id="'.$search->id.'">'.$this->get_term_link( $search->term , $home_url).'</td>
                        <td data-label="Result-count">'.$search->result_count.'</td>
                        <td data-label="When">'. $this->localize_date( $search->time ).'</td>
                        <td data-label="When-unix">'.$search->time.'</td>                        
                        <td>'.$this->get_referrer_link($search) .'</td>
                    </tr>';
		        }
		        return $output;
            } else {

                $output = '<table id="wpsi-recent-table" class="wpsi-table"><thead>
                    <tr class="wpsi-thead-th">
                        <th scope="col">'.__( "Search term", "wp-search-insights" ).'</th>
                        <th scope="col">'.__( "Results", "wp-search-insights" ).'</th>
                        <th scope="col" class="dashboard-tooltip-hits">'.__( "When", "wp-search-insights" ).'</th>
                        <th scope="col"></th>
                        <th scope="col" class="dashboard-tooltip-from">'.__( "From", "wp-search-insights" ).'</th>
                    </tr>
                    </thead>
                    <tbody>';

			        foreach ( $recent_searches as $search ) {
				        $output .=
                        '<tr>
                            <td data-label="Term" class="wpsi-term" data-term_id="'.$search->id.'">'.$this->get_term_link( $search->term, $home_url ).'</td>
                            <td>'.$search->result_count.'</td>
                            <td data-label="When">'. $this->localize_date( $search->time ).'</td>
                            <td data-label="When-unix">'.$search->time.'</td>
                            <td>'.$this->get_referrer_link( $search ).'</td>
                        </tr>';
			        }
		            $output .= '</tbody>
                </table>';

		        return $output;
	        }
        }

        public function localize_date($unix){
	        return sprintf("%s at %s", date( str_replace( 'F', 'M', get_option('date_format')), $unix  ), date( get_option('time_format'), $unix ) );
        }



        /**
         * Create a link which isn't included in the search results
         *
         * @param string $term
         * @param string $home_url
         *
         * @return string
         */

        public function get_term_link($term, $home_url = false)
        {
	        $custom_search_parameter = get_option('wpsi_custom_search_parameter');
	        $search_parameter = $custom_search_parameter ? sanitize_title($custom_search_parameter) : 's';

            $class = '';

	        if (!$home_url) $home_url = home_url();

            $search_url = $home_url. "?$search_parameter=" . $term . '&searchinsights';

	        // Add wpsi-ellipsis class to show long texts on hover
	        if (strlen($term)>40){
		        $class='wpsi-ellipsis';
	        }
            return '<a href="' . esc_html($search_url) . '" target="_blank">' . '<span class="' . $class .'" data-text="' . sanitize_text_field($term) . '">' . sanitize_text_field($term) . '</span>' . '</a>';
        }

        /**
        * Get referrer link
        * @param $search
        *
        * @return string
         */

        public function get_referrer_link($search){
            //legacy title search
            $post_id = $search->referrer_id;
            if ( $post_id != 0 ) {
	            $url = get_permalink($post_id);
                $referrer = get_the_title($post_id);
            } elseif ($search->referrer === 'home' || $search->referrer === '' || $search->referrer === '/') {
	            $url = site_url();
                $referrer = __('Home','wp-search-insights');
            } elseif (strpos($search->referrer, site_url()) === FALSE) {
	            $url = site_url( $search->referrer );
                $referrer = $search->referrer;
            } else {
	            $url = $search->referrer;
	            $referrer = $search->referrer;
            }
            //make sure the link is not too long
            if (strlen($referrer)>25){
                $referrer = mb_strcut($referrer, 0, 22).'...';
            }
            return '<a target="_blank" href="' . esc_url_raw($url) . '">' . sanitize_text_field($referrer) . '</a>';
        }

	    /**
         * Get post id from a string
	     * @param string $title
	     *
	     * @return string|null
	     */

        public function get_post_by_title($title){
			global $wpdb;

			$query = $wpdb->prepare(
				'SELECT ID FROM ' . $wpdb->posts . " WHERE post_title = '%s'",
				sanitize_text_field($title)
			);

			return $wpdb->get_var( $query );
		}

        /**
         *
         * Generate the no results overview in dashboard
         * @param int $start
         * @param int $end
         *
         * @return string
         * @since 1.2
         */

        public function results_table($start, $end)
        {
	        // Get the count of all searches made in period
            $args        = array(
                'date_from' => $start,
                'date_to'   => $end,
                'count'     => true,
            );
            $nr_of_terms = WPSI::$search->get_searches( $args );

	        // Get terms with more than one result
            $args = array(
                'date_from' => $start,
                'date_to' => $end,
                'result_count' => 0,
                'compare' => '>',
                'count' => true,
            );
            $have_results = WPSI::$search->get_searches($args);

	        $no_results = $nr_of_terms - $have_results;
	        if ( $have_results == 0 || $nr_of_terms == 0 ) {
		        $percentage_results = 0;
	        } else {
		        $percentage_results = round(($have_results / $nr_of_terms) * 100,0);
	        }

            ob_start();

            ?>
            <div class="wpsi-nr-overview">

                <div class="wpsi-nr-content">
                    <div class="progress-bar-container">
                        <div class="progress">
                            <div class="bar" style="width:<?php echo $percentage_results?>%"></div>
                        </div>
                    </div>

                    <?php if ($nr_of_terms != 0) { ?>
                        <div class="progress-text">
                            <span class="percentage"><?php echo $percentage_results . "% " ?></span>
                            <span class="percentage-text"><?php _e("of searches have results", "wp-search-insights");?></span>
                        </div>
                        <div class="wpsi-total-searches">
                            <span class="wpsi-nr-title-in-widget"><?php _e("Total Unique Searches", "wp-search-insights"); ?></span>
                            <span class="wpsi-search-count"><?= $nr_of_terms; ?></span>
                        </div>
                    <?php } else { ?>

                        <div class="progress-text">
                            <span class="percentage-text">
                                <?php _e("No searches in selected period", "wp-search-insights"); ?>
                            </span>
                        </div>

                    <?php } ?>

                    <div class="nr-widget-results-container">
                        <div class="wpsi-nr-has-result">
                            <div class="dot-and-text">
                                <div class="has-result-dot">
                                    <span class="dot dot-success"></span>
                                </div>
                                <div class="result-title">
                                    <?php _e("Have results", "wp-search-insights"); ?>
                                </div>
                            </div>
                            <div class="wpsi-result-count">
                                <?php printf(__("%s searches", "wp-search-insights"), $have_results ); ?>
                            </div>
                        </div>
                        <div class="wpsi-nr-no-result">
                            <div class="dot-and-text">
                                <div class="has-result-dot">
                                    <span class="dot dot-error"></span>
                                </div>
                                <div class="result-title">
                                    <?php _e("No results", "wp-search-insights"); ?>
                                </div>
                            </div>
                            <div class="wpsi-result-count">
                                <?php printf(__("%s searches", "wp-search-insights"), $no_results ); ?>
                            </div>
                        </div>
                    </div>


                <div class="popular-terms-title">
                    <?php _e("Most popular search term", "wp-search-insights"); ?>
                </div>

                <div class="nr-widget-results-container">
                    <div class="wpsi-nr-has-result">
                        <div class="dot-and-text">
                            <div class="has-result-dot">
                                <span class="dot dot-success"></span>
                            </div>
                            <div class="result-title">
                                <?php
                                    $args = array(
                                        'date_from' => $start,
                                        'date_to' => $end,
                                        'orderby' => 'frequency',
                                        'result_count' => 0,
                                        'compare' => '>',
                                        'number' => 1,
                                    );

                                    $top_search = WPSI::$search->get_searches( $args );
                                    $top_search_term = !empty($top_search) ? $top_search[0]->term : __("No result", "wp-search-insights");
                                    $top_search_frequency = !empty($top_search) ? $top_search[0]->frequency : '0';

                                    echo $top_search_term;
                                ?>
                            </div>
                        </div>
                        <div class="wpsi-result-count">
                            <?php printf(__("%s searches", "wp-search-insights"), $top_search_frequency); ?>
                        </div>
                    </div>
                    <div class="wpsi-nr-no-result">
                        <div class="dot-and-text">
                            <div class="has-result-dot">
                                <span class="dot dot-error"></span>
                            </div>
                            <div class="result-title">
                                <?php
                                $args = array(
                                    'date_from' => $start,
                                    'date_to' => $end,
                                    'orderby' => 'frequency',
                                    'result_count' => 0,
                                    'order' => 'DESC',
                                    'number' => 1,
                                );

                                $top_search_no_result = WPSI::$search->get_searches($args);
                                $top_search_no_result_term = !empty($top_search_no_result) ? $top_search_no_result[0]->term : __("No result", "wp-search-insights");
                                $top_search_no_result_frequency = !empty($top_search_no_result) ? $top_search_no_result[0]->frequency : '0';

                                echo $top_search_no_result_term;
                                ?>
                            </div>
                        </div>
                        <div class="wpsi-result-count">
	                        <?php printf(__("%s searches", "wp-search-insights"), $top_search_no_result_frequency); ?>
                        </div>
                    </div>
                </div>
            </div>
            </div>
            <?php
            return ob_get_clean();
        }

	    /**
         * Get status link for plugin, depending on installed, or premium availability
	     * @param $item
	     *
	     * @return string
	     */

        public function get_status_link($item){
            if (is_multisite()){
                $install_url = network_admin_url('plugin-install.php?s=');
            } else {
                $install_url = admin_url('plugin-install.php?s=');
            }

	        if (defined($item['constant_free']) && defined($item['constant_premium'])) {
		        $status = __("Installed", "wp-search-insights");
	        } elseif (defined($item['constant_free']) && !defined($item['constant_premium'])) {
		        $link = $item['website'];
		        $text = __('Upgrade to pro', 'wp-search-insights');
		        $status = "<a href=$link>$text</a>";
	        } else {
		        $link = $install_url.$item['search']."&tab=search&type=term";
		        $text = __('Install', 'wp-search-insights');
		        $status = "<a href=$link>$text</a>";
	        }
	        return $status;
        }




        public function generate_tips_tricks()
        {
            $items = array(
                1 => array(
                    'content' => __("Writing Content for Google", "wp-search-insights"),
                    'link'    => 'https://wpsi.io/writing-content-for-google/',
                ),
                2 => array(
                    'content' => __("WP Search Insights Beginner's Guide", "wp-search-insights"),
                    'link' => 'https://wpsi.io/wp-search-insights-beginners-guide/',
                ),
                3 => array(
                    'content' => __("Using CSV/Excel Exports", "wp-search-insights"),
                    'link' => 'https://wpsi.io/using-csv-excel-exports/',
                ),
                4 => array(
                    'content' => __("Improving your Search Result Page", "wp-search-insights"),
                    'link' => 'https://wpsi.io/improving-your-search-result-page/',
                ),
                5 => array(
                    'content' => __("The Search Filter", "wp-search-insights"),
                    'link' => 'https://wpsi.io/the-search-filter/',
                ),
                6 => array(
                    'content' => __("Positioning your search form", "wp-search-insights"),
                    'link' => 'https://wpsi.io/about-search-forms/',
                ),
            );
	        $button = '<a href="https://wpsi.io/tips-tricks/" target="_blank"><button class="button button-upsell">'.__("View all" , "wp-search-insights").'</button></a>';

	        $container = $this->get_template('tipstricks-element.php');
	        $output = "";
            foreach ($items as $item) {
	            $output .= str_replace(array(
                    '{link}',
                    '{content}',
                ), array(
                    $item['link'],
                    $item['content'],
                ), $container);
            }
            return '<div>'.$output.'</div>'.$button;
        }
    }
}
