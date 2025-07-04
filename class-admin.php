<?php
defined('ABSPATH') or die("you do not have access to this page!");

if (!class_exists('WPSI_ADMIN')) {
    class WPSI_ADMIN
    {

        private static $_this;
        public $grid_items;
        public $capability = 'activate_plugins';
        public $tabs;
        public $rows = 8; // Amount of rows to display in popular searches table

        static function this()
        {
            return self::$_this;
        }

        function __construct()
        {
            if (isset(self::$_this)) {
                wp_die(
                    esc_html(
                        sprintf(
                        /* translators: %s: class name */
                            __('%s is a singleton class and you cannot create a second instance.', 'wp-search-insights'),
                            get_class($this)
                        )
                    )
                );
            }

            self::$_this = $this;

            $this->capability = get_option('wpsi_select_dashboard_capability');
            add_action('admin_menu', array($this, 'add_menu_pages'), 40);
            add_action('admin_init', array($this, 'wpsi_settings_section_and_fields'));
            add_action('admin_init', array($this, 'maybe_enable_ajax_tracking'));
            add_action('admin_init', array($this, 'check_upgrade'), 10, 2);
            add_action('wp_ajax_wpsi_store_date_range', array($this, 'ajax_store_date_range'));
            add_action('wp_ajax_wpsi_save_settings', array($this, 'ajax_save_settings'));

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple admin page check, not processing form data
            $is_wpsi_page = isset($_GET['page']) && $_GET['page'] === 'wpsi-settings-page';

            if ($is_wpsi_page) {
                add_action('admin_init', array($this, 'init_grid'));
                add_action('admin_head', array($this, 'inline_styles'));
                add_action('admin_head', array($this, 'add_visibility_script'));

                // Dot not add action to clear entries from db when the option is set to never
                if (get_option('wpsi_select_term_deletion_period') && get_option('wpsi_select_term_deletion_period') !== 'never') {
                    add_action('admin_init', array($this, 'clear_entries_from_database'));
                }
            }

            add_action('admin_init', array($this, 'add_privacy_info'));

            $plugin = wpsi_plugin;

            add_filter("plugin_action_links_$plugin", array($this, 'plugin_settings_link'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('wp_ajax_wpsi_get_datatable', array($this, 'ajax_get_content'));

            if ( current_user_can('manage_options' ) ) {
                add_action('admin_init', array($this, 'listen_for_clear_database'), 40);
                add_filter('wp_redirect', array($this, 'modify_settings_redirect'), 10, 2);
            }

            add_action('wp_dashboard_setup', array($this, 'add_wpsi_dashboard_widget'));

            add_filter('wpsi_popular_search_row_args', array($this, 'add_trend_information'), 10, 4);

            add_action('wp_ajax_wpsi_save_filter_preference', array($this, 'ajax_save_filter_preference'));

        }

        /**
         * Modify the redirect after saving options to keep the user on the correct tab
         *
         * @param string $location The redirect location
         * @param int $status The redirect status code
         * @return string Modified redirect location
         */
        public function modify_settings_redirect($location, $status)
        {
            // Check if this is a redirect from options.php after saving WPSI settings
            if (strpos($location, 'settings-updated=true') !== false &&
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified by options.php
                isset($_POST['option_page']) &&
                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified by options.php
                isset($_POST['wpsi_active_tab'])) {

                // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified by options.php
                $option_page = sanitize_text_field(wp_unslash($_POST['option_page']));

                // Check if this is our settings page update
                if (strpos($option_page, 'wpsi-') === 0) {
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified by options.php
                    $active_tab = sanitize_title(wp_unslash($_POST['wpsi_active_tab']));

                    // Create the redirect URL with proper query args
                    $new_location = add_query_arg(
                        array(
                            'page' => 'wpsi-settings-page',
                            'settings-updated' => 'true'
                        ),
                        admin_url('admin.php')
                    );

                    // Add the hash fragment with the #top suffix, properly escaped
                    $new_location .= '#' . esc_html($active_tab) . '#top';

                    // Validate the redirect to prevent open redirects
                    return wp_validate_redirect($new_location, admin_url('admin.php?page=wpsi-settings-page'));
                }
            }

            // Return original location if not a WPSI settings update
            return $location;
        }

        /**
         *
         * Stuff to update after plugin upgrade
         *
         * @since 1.3
         *
         */

        public function check_upgrade()
        {

            $prev_version = get_option('wpsi-current-version', false);

            if ($prev_version && version_compare($prev_version, '1.3.7', '<')) {
                update_option('wpsi_select_term_deletion_period', 'never');
            }

            // Only set this once, the first time the plugin upgrades to 2.0
            if ( ! get_option('wpsi_version_two_installation_time' ) &&
                version_compare(get_option('wpsi-current-version', '0'), '2.0', '<')) {
                update_option('wpsi_version_two_installation_time', time());
            }

            update_option('wpsi-current-version', wpsi_version);

        }

        /**
         * Do a one time check if a known ajax plugin is installed. If so, activate Ajax tracking.
         */

        public function maybe_enable_ajax_tracking()
        {

            if (!get_option('wpsi_checked_ajax_plugins')) {
                $ajax_plugin_active =
                    function_exists('searchwp_live_search_request_handler') //SearchWP Live Ajax Search
                    || defined('ASL_CURRENT_VERSION') //ajax search lite
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

        public function init_grid()
        {
            $this->tabs = apply_filters('wpsi_tabs', array(
                'dashboard' => array(
                    'title' => __("General", "wp-search-insights"),
                ),
                'settings' => array(
                    'title' => __("Settings", "wp-search-insights"),
                    'capability' => 'manage_options',
                ),
            ));

            $this->grid_items = array(
                1 => array(
                    'title' => __("All Searches", "wp-search-insights") . ' <span id="wpsi-total-count" class="wpsi-total-search-count"></span>',
                    'display_option_title' => __("All Searches", "wp-search-insights"), // New property without span
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
                    'title' => __("Popular Searches", "wp-search-insights"),
                    'content' => '<div class="wpsi-skeleton"></div>',
                    'class' => 'small wpsi-load-ajax',
                    'type' => 'popular',
                    'controls' => $this->get_popular_filter_controls(),
                    'can_hide' => true,
                    'ajax_load' => true,
                ),
            );

            $this->grid_items = apply_filters('wpsi_dashboard_grid_items', $this->grid_items);

            // Add tips & tricks as the last item
            $next_id = max(array_keys($this->grid_items)) + 1;
            $this->grid_items[$next_id] = array(
                'title' => __("Tips & Tricks", "wp-search-insights"),
                'content' => $this->generate_tips_tricks(),
                'type' => 'tasks',
                'class' => 'half-height wpsi-tips-tricks',
                'can_hide' => true,
                'controls' => '',
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
                    var rangeType = localStorage.getItem('wpsi_range_type');

                    if (unixStart === null || unixEnd === null) {
                        unixStart = moment().startOf('day').unix();
                        unixEnd = moment().endOf('day').unix();
                        localStorage.setItem('wpsi_range_start', unixStart);
                        localStorage.setItem('wpsi_range_end', unixEnd);
                    }

                    // Function to handle relative date ranges
                    function updateRelativeDateRanges() {
                        if (!rangeType) {
                            return;
                        }

                        var shouldUpdate = false;
                        var start, end;

                        // Define date ranges based on current time
                        var todayStart = moment().startOf('day');
                        var todayEnd = moment().endOf('day');

                        // Important: Case-insensitive comparison for more reliable matching
                        var normalizedRangeType = rangeType.toLowerCase();

                        switch(normalizedRangeType) {
                            case 'today':
                                start = todayStart;
                                end = todayEnd;
                                shouldUpdate = true;
                                break;
                            case 'yesterday':
                                start = moment().subtract(1, 'days').startOf('day');
                                end = moment().subtract(1, 'days').endOf('day');
                                shouldUpdate = true;
                                break;
                            case 'last 7 days':
                                start = moment().subtract(6, 'days').startOf('day');
                                end = todayEnd;
                                shouldUpdate = true;
                                break;
                            case 'last 30 days':
                                start = moment().subtract(29, 'days').startOf('day');
                                end = todayEnd;
                                shouldUpdate = true;
                                break;
                            case 'this month':
                                start = moment().startOf('month');
                                end = moment().endOf('month');
                                shouldUpdate = true;
                                break;
                            case 'last month':
                                start = moment().subtract(1, 'month').startOf('month');
                                end = moment().subtract(1, 'month').endOf('month');
                                shouldUpdate = true;
                                break;
                            default:

                        }

                        // Update the date range if we have a relative range
                        if (shouldUpdate) {
                            unixStart = start.unix();
                            unixEnd = end.unix();
                            localStorage.setItem('wpsi_range_start', unixStart);
                            localStorage.setItem('wpsi_range_end', unixEnd);

                            // Update display
                            $('.wpsi-date-container span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));

                            // Also update on server
                            $.ajax({
                                type: "POST",
                                url: wpsi.ajaxurl,
                                data: {
                                    action: 'wpsi_store_date_range',
                                    start: unixStart,
                                    end: unixEnd,
                                    range_type: rangeType,
                                    token: wpsi.tokens.store_date_range
                                }
                            });
                        }
                    }

                    // Run the update function on page load
                    updateRelativeDateRanges();

                    // Convert to integers
                    unixStart = parseInt(unixStart);
                    unixEnd = parseInt(unixEnd);

                    // Create moment objects without adding utcOffset (this is the key fix)
                    var startMoment = moment.unix(unixStart);
                    var endMoment = moment.unix(unixEnd);

                    // Update the display with these moment objects
                    $('.wpsi-date-container span').html(startMoment.format('MMMM D, YYYY') + ' - ' + endMoment.format('MMMM D, YYYY'));

                    function wpsiUpdateDate(start, end, label) {
                        $('.wpsi-date-container span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));

                        // Store the unix timestamps directly without adding offsets
                        localStorage.setItem('wpsi_range_start', start.unix());
                        localStorage.setItem('wpsi_range_end', end.unix());

                        // Store the range label if provided
                        if (label && typeof label === 'string') {
                            localStorage.setItem('wpsi_range_type', label);
                        } else {
                            // For custom ranges, clear the type
                            localStorage.setItem('wpsi_range_type', '');
                        }

                        // Add AJAX call here
                        $.ajax({
                            type: "POST",
                            url: wpsi.ajaxurl,
                            data: {
                                action: 'wpsi_store_date_range',
                                start: start.unix(),
                                end: end.unix(),
                                range_type: label || '',
                                token: wpsi.tokens.store_date_range
                            }
                        });

                        // Detect range type based on start and end dates
                        var rangeType = detectRangeType(start, end);

                        // Trigger event with both date objects and detected range type
                        $(document).trigger('wpsiDateRangeChanged', [start, end, rangeType]);
                    }

                    // Helper function to detect range type from start/end dates
                    function detectRangeType(start, end) {
                        var diff = end.diff(start, 'days');

                        // Detect common ranges
                        if (diff <= 1) return 'today';
                        if (diff <= 7) return 'week';
                        if (diff <= 31) return 'month';
                        if (diff <= 366) return 'year';
                        return 'all';
                    }

                    var todayStart = moment().endOf('day').subtract(1, 'days').add(1, 'minutes');
                    var todayEnd = moment().endOf('day');
                    var yesterdayStart = moment().endOf('day').subtract(2, 'days').add(1, 'minutes');

                    var yesterdayEnd = moment().endOf('day').subtract(1, 'days');
                    var lastWeekStart = moment().startOf('day').subtract(6, 'days');
                    var lastWeekEnd = moment().endOf('day');

                    var wpsiPluginActivated = '<?php echo esc_js(get_option('wpsi_activation_time'))?>';

                    $('.wpsi-date-container.wpsi-table-range').daterangepicker(
                        {
                            ranges: {
                                'Today': [todayStart, todayEnd],
                                'Yesterday': [yesterdayStart, yesterdayEnd],
                                'Last 7 Days': [lastWeekStart, lastWeekEnd],
                                'Last 30 Days': [moment().subtract(29, 'days').startOf('day'), moment().endOf('day')],
                                'This Month': [moment().startOf('month'), moment().endOf('month')],
                                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                                'All time': [moment.unix(wpsiPluginActivated), moment()]
                            },
                            "locale": {
                                "format": "<?php echo esc_js(__('MM/DD/YYYY', 'wp-search-insights'));?>",
                                "separator": " - ",
                                "applyLabel": "<?php echo esc_js(__("Apply", "wp-search-insights"));?>",
                                "cancelLabel": "<?php echo esc_js(__("Cancel", "wp-search-insights"));?>",
                                "fromLabel": "<?php echo esc_js(__("From", "wp-search-insights"));?>",
                                "toLabel": "<?php echo esc_js(__("To", "wp-search-insights"));?>",
                                "customRangeLabel": "<?php echo esc_js(__("Custom", "wp-search-insights"));?>",
                                "weekLabel": "<?php echo esc_js(__("W", "wp-search-insights"));?>",
                                "daysOfWeek": [
                                    /* translators: Abbreviation for Monday */
                                    "<?php echo esc_js(__("Mo", "wp-search-insights")); ?>",
                                    /* translators: Abbreviation for Tuesday */
                                    "<?php echo esc_js(__("Tu", "wp-search-insights")); ?>",
                                    /* translators: Abbreviation for Wednesday */
                                    "<?php echo esc_js(__("We", "wp-search-insights")); ?>",
                                    /* translators: Abbreviation for Thursday */
                                    "<?php echo esc_js(__("Th", "wp-search-insights")); ?>",
                                    /* translators: Abbreviation for Friday */
                                    "<?php echo esc_js(__("Fr", "wp-search-insights")); ?>",
                                    /* translators: Abbreviation for Saturday */
                                    "<?php echo esc_js(__("Sa", "wp-search-insights")); ?>",
                                    /* translators: Abbreviation for Sunday */
                                    "<?php echo esc_js(__("Su", "wp-search-insights")); ?>"
                                ],

                                "monthNames": [
                                    "<?php echo esc_js(__("January", "wp-search-insights"));?>",
                                    "<?php echo esc_js(__("February", "wp-search-insights"));?>",
                                    "<?php echo esc_js(__("March", "wp-search-insights"));?>",
                                    "<?php echo esc_js(__("April", "wp-search-insights"));?>",
                                    "<?php echo esc_js(__("May", "wp-search-insights"));?>",
                                    "<?php echo esc_js(__("June", "wp-search-insights"));?>",
                                    "<?php echo esc_js(__("July", "wp-search-insights"));?>",
                                    "<?php echo esc_js(__("August", "wp-search-insights"));?>",
                                    "<?php echo esc_js(__("September", "wp-search-insights"));?>",
                                    "<?php echo esc_js(__("October", "wp-search-insights"));?>",
                                    "<?php echo esc_js(__("November", "wp-search-insights"));?>",
                                    "<?php echo esc_js(__("December", "wp-search-insights"));?>"
                                ],
                                "firstDay": 1
                            },
                            "alwaysShowCalendars": true,
                            startDate: moment.unix(unixStart),
                            endDate: moment.unix(unixEnd),
                            "opens": "left",
                        }, function (start, end, label) {
                            wpsiUpdateDate(start, end, label);
                            window.wpsiLoadAjaxTables();
                        });

                    // Rest of your function (export date range picker, etc.)
                    // ...
                });
            </script>
            <?php
        }

        /**
         * @return void
         *
         * Hide hidden blocks
         */
        public function add_visibility_script() {
            ?>
            <script type="text/javascript">
                // Run immediately to prevent FOUC (Flash of Unstyled Content)
                (function() {
                    // Add a class to body to indicate JS is active
                    document.documentElement.classList.add('wpsi-js-active');

                    // Create a style element
                    var style = document.createElement('style');
                    style.type = 'text/css';
                    style.id = 'wpsi-initial-visibility';

                    // Hide elements initially until JS initializes them properly
                    var css = '.wpsi-js-active .wpsi-item { opacity: 0; }\n';
                    css += '.wpsi-js-active.wpsi-grid-loaded .wpsi-item { opacity: 1; transition: opacity 0.3s ease; }\n';

                    style.appendChild(document.createTextNode(css));
                    document.head.appendChild(style);
                })();
            </script>
            <?php
        }

        public function enqueue_assets($hook)
        {
            global $search_insights_settings_page;
            // Enqueue assest when on index.php (WP dashboard) or plugins settings page

            if ($hook === 'index.php' || $hook === $search_insights_settings_page) {
                // Datatables javascript for interactive tables
                wp_register_script('wpsi-datatables',
                    trailingslashit(wpsi_url)
                    . 'assets/js/datatables.min.js', array("jquery"), wpsi_version, false);
                wp_enqueue_script('wpsi-datatables');

                // Datepicker
                wp_enqueue_style('wpsi-datepicker', trailingslashit(wpsi_url) . 'assets/datepicker/datepicker.css', "",
                    wpsi_version);

                wp_register_script('wpsi-datepicker',
                    trailingslashit(wpsi_url)
                    . 'assets/datepicker/datepicker.js', array("jquery", "moment"), wpsi_version, false);

                wp_register_style(
                    'wpsi',
                    trailingslashit(wpsi_url) . "assets/css/style.css",
                    "",
                    wpsi_version
                );
                wp_enqueue_style('wpsi');

                wp_register_script(
                    'wpsi',
                    trailingslashit(wpsi_url) . 'assets/js/scripts-v2.js',
                    array("jquery", "wpsi-datepicker", "wpsi-datatables"),
                    wpsi_version, false
                );
                wp_enqueue_script('wpsi');

                wp_localize_script('wpsi', 'wpsi',
                    array(
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'activation_time' => get_option('wpsi_activation_time', 0),
                        'skeleton' => '<div class="wpsi-skeleton"></div>',
                        'batch' => (int)$this->get_batch_size(),
                        'strings' => array(
                            'download' => __("Download", 'wp-search-insights'),
                            'previous' => __('Previous', 'wp-search-insights'),
                            'next' => __('Next', 'wp-search-insights'),
                            'no_searches' => __('No searches in selected period', 'wp-search-insights'),
                            /* translators: %1$d: current page number, %2$d: total pages count */
                            'page_of' => __('Page %1$d of %2$d', 'wp-search-insights'),
                            'loading_text' => __('Loading data', 'wp-search-insights'),
                        ),
                        'export_in_progress' => get_transient('wpsi_export_in_progress'),
                        'tokens' => array(
                            'store_date_range' => wp_create_nonce('wpsi_store_date_range'),
                            'save_settings' => wp_create_nonce('wpsi_save_settings'),
                            'get_datatable' => wp_create_nonce('wpsi_get_datatable'),
                            'delete_terms' => wp_create_nonce('wpsi_delete_terms'),
                            'ignore_terms' => wp_create_nonce('wpsi_ignore_terms'),
                            'clear_database' => wp_create_nonce('wpsi_thickbox_nonce'),
                            'start_export' => wp_create_nonce('wpsi_start_export'),
                            'save_filter_preference' => wp_create_nonce('wpsi_save_filter_preference'),
                        ),
                        'localize' => array(
                            'search' => __('Search', 'wp-search-insights'),
                            'previous' => __('Previous', 'wp-search-insights'),
                            'next' => __('Next', 'wp-search-insights'),
                            'no-searches' => __('No searches in selected period', 'wp-search-insights'),
                            /* translators: %1$d: current page number, %2$d: total pages count */
                            'page_of' => __('Page %1$d of %2$d', 'wp-search-insights'),
                            'date_format' => __('MM/DD/YYYY', 'wp-search-insights'),
                            'separator' => __(' - ', 'wp-search-insights'),
                            'apply_label' => __("Apply", "wp-search-insights"),
                            'cancel_label' => __("Cancel", "wp-search-insights"),
                            'from_label' => __("From", "wp-search-insights"),
                            'to_label' => __("To", "wp-search-insights"),
                            'custom_label' => __("Custom", "wp-search-insights"),
                            'week_label' => __("W", "wp-search-insights"),
                            'days_of_week' => array(
                                /* translators: Abbreviation for Monday */
                                __("Mo", "wp-search-insights"),
                                /* translators: Abbreviation for Tuesday */
                                __("Tu", "wp-search-insights"),
                                /* translators: Abbreviation for Wednesday */
                                __("We", "wp-search-insights"),
                                /* translators: Abbreviation for Thursday */
                                __("Th", "wp-search-insights"),
                                /* translators: Abbreviation for Friday */
                                __("Fr", "wp-search-insights"),
                                /* translators: Abbreviation for Saturday */
                                __("Sa", "wp-search-insights"),
                                /* translators: Abbreviation for Sunday */
                                __("Su", "wp-search-insights")
                            ),
                            'month_names' => array(
                                __("January", "wp-search-insights"),
                                __("February", "wp-search-insights"),
                                __("March", "wp-search-insights"),
                                __("April", "wp-search-insights"),
                                __("May", "wp-search-insights"),
                                __("June", "wp-search-insights"),
                                __("July", "wp-search-insights"),
                                __("August", "wp-search-insights"),
                                __("September", "wp-search-insights"),
                                __("October", "wp-search-insights"),
                                __("November", "wp-search-insights"),
                                __("December", "wp-search-insights")
                            ),
                        ),
                    )
                );

                // The dashboard widget doesn't use fontello or pagination, return here if we're on the WP dashboard.
                if ($hook == 'index.php') return;

                //Datatables plugin to hide pagination when it isn't needed
                wp_register_script('wpsi-datatables-pagination',
                    trailingslashit(wpsi_url)
                    . 'assets/js/dataTables.conditionalPaging.js', array("jquery"), wpsi_version, false);
                wp_enqueue_script('wpsi-datatables-pagination');

                wp_register_script(
                    'wpsi-modal',
                    trailingslashit(wpsi_url) . 'assets/js/modal.js',
                    array('jquery'),
                    wpsi_version, true,
                );
                wp_enqueue_script('wpsi-modal');
            }
        }

        /**
         * Get number of plus ones
         * @return int
         */

//        public function count_plusones()
//        {
//            $plus_ones = get_transient('wpsi_plus_ones');
//            if (!$plus_ones) {
//                $plus_ones = 0;
//
//                if (!get_option('wpsi_ten_searches_viewed_settings_page')) {
//                    $items = WPSI::$search->get_searches_single();
//                    $search_count = count($items);
//                    if ($search_count > 10) {
//                        $plus_ones++;
//                    }
//                }
//
//                set_transient('wpsi_plus_ones', $plus_ones, DAY_IN_SECONDS);
//            }
//
//            return $plus_ones;
//        }

        /**
         * Add a main menu item for Search Insights
         *
         * @since 1.4.0
         */
        public function add_menu_pages()
        {
            if (!current_user_can($this->capability)) {
                return;
            }

            global $search_insights_settings_page;

            // Count for notification badge
//            $count = $this->count_plusones();
//            $update_count = $count > 0 ? "<span class='update-plugins wpsi-update-count'><span class='update-count'>$count</span></span>" : "";

            // Add main menu item with notification
            $main_page = add_menu_page(
                "Search Insights",
//                "Search Insights" . $update_count,
                "Search Insights",
                $this->capability,
                'wpsi-settings-page',
                array($this, 'settings_page'),
                'dashicons-search',
                30
            );

            // Store the hook name for the main page
            $search_insights_settings_page = $main_page;

            // Add duplicate submenu under Tools with a JS redirect callback
            add_submenu_page(
                'tools.php',
                "WP Search Insights",
//                "WP Search Insights" . $update_count,
                "WP Search Insights",
                $this->capability,
                'wpsi-tools-redirect',
                array($this, 'js_redirect_to_main_page')
            );
        }

        /**
         * JavaScript redirect to main plugin page
         * This avoids header issues entirely
         */
        public function js_redirect_to_main_page()
        {
            $url = admin_url('admin.php?page=wpsi-settings-page');
            ?>
            <script>
                window.location.href = "<?php echo esc_url($url); ?>";
            </script>
            <p><?php esc_html_e('Redirecting to Search Insights...', 'wp-search-insights'); ?></p>
            <p>
                <a href="<?php echo esc_url($url); ?>"><?php esc_html_e('Click here if you are not redirected automatically.', 'wp-search-insights'); ?></a>
            </p>            <?php
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
            $settings_link = '<span class="wpsi-settings-link"></span><a href="admin.php?page=wpsi-settings-page">'
                . __("Settings", "wp-search-insights") . '</a></span>';
            array_unshift($links, $settings_link);

            $faq_link
                = '<a href="https://wpsi.io/docs/" target="_blank">'
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
            // Add a settings section to the 'Settings' tab
            add_settings_section(
                'wpsi-settings-tab',
                "",
                array($this, 'wpsi_settings_tab_intro'),
                'wpsi-settings'
            );

            // Add the field with the names and function to use for our new
            // settings, put it in our new section

            add_settings_field(
                'exclude_admin_searches',
                __("Exclude admin searches", 'wp-search-insights') . WPSI::$help->get_help_tip(__("Stops counting your own searches when you're logged in as admin. Turn this ON if you test searches yourself and don't want to mess up your stats.", "wp-search-insights")),
                array($this, 'option_wpsi_exclude_admin'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'min_search_length',
                __("Exclude searches shorter than characters", 'wp-search-insights') . WPSI::$help->get_help_tip(__("Ignores super short searches. Example: Set to '3' to ignore searches like 'a' or 'hi' that are probably typos. Set to '0' to track everything.", "wp-search-insights")),
                array($this, 'option_min_term_length'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'max_search_length',
                __("Exclude searches longer than characters", 'wp-search-insights') . WPSI::$help->get_help_tip(__("Ignores really long searches. Example: Set to '50' to ignore when someone pastes a paragraph. These are usually not real searches. Set to '0' to track everything.", "wp-search-insights")),
                array($this, 'option_max_term_length'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'wpsi_select_dashboard_capability',
                __("Who can view the dashboard", 'wp-search-insights') . WPSI::$help->get_help_tip(__("Controls who can see search statistics. 'Administrators only' keeps stats private to you. 'All Users' lets anyone with a login see what people are searching for.", "wp-search-insights")),
                array($this, 'option_wpsi_select_dashboard_capability'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'wpsi_track_ajax_searches',
                __("Track Ajax searches", 'wp-search-insights') . WPSI::$help->get_help_tip(__("Catches searches from fancy search boxes that show results while typing. Turn this ON if your search box shows results instantly without page reload.", "wp-search-insights")),
                array($this, 'option_wpsi_track_ajax_searches'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'wpsi_delete_terms_after_period',
                __("Automatically delete terms from your database after this period", 'wp-search-insights') . WPSI::$help->get_help_tip(__("Cleans up old search data automatically. 'Never' keeps everything forever (uses more database space). 'Week/Month/Year' deletes old searches to keep your site running smoothly.", "wp-search-insights")),
                array($this, 'option_wpsi_delete_terms_after_period'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'wpsi_custom_search_parameter',
                __("Custom search parameter", 'wp-search-insights') . WPSI::$help->get_help_tip(__("Usually leave this as 's'. Only change if your search page URL looks different than normal. For example, if your search URL is '?query=keyword' instead of '?s=keyword', you'd enter 'query' here.", "wp-search-insights")),
                array($this, 'option_wpsi_custom_search_parameter'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            // Register our setting so that $_POST handling is done for us and
            // our callback function just has to echo the <input>
            register_setting('wpsi-settings-tab', 'wpsi_exclude_admin', array(
                'sanitize_callback' => 'absint',
                'default' => 0,
            ));

            register_setting('wpsi-settings-tab', 'wpsi_min_term_length', array(
                'sanitize_callback' => array($this, 'sanitize_min_term_length'),
                'default' => 0,
            ));

            register_setting('wpsi-settings-tab', 'wpsi_max_term_length', array(
                'sanitize_callback' => array($this, 'sanitize_max_term_length'),
                'default' => 50,
            ));

            register_setting('wpsi-settings-tab', 'wpsi_select_dashboard_capability', array(
                'sanitize_callback' => array($this, 'sanitize_capability'),
                'default' => 'activate_plugins',
            ));

            register_setting('wpsi-settings-tab', 'wpsi_track_ajax_searches', array(
                'sanitize_callback' => 'absint',
                'default' => 0,
            ));

            register_setting('wpsi-settings-tab', 'wpsi_select_term_deletion_period', array(
                'sanitize_callback' => array($this, 'sanitize_term_deletion_period'),
                'default' => 'never',
            ));

            register_setting('wpsi-settings-tab', 'wpsi_custom_search_parameter', array(
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            ));


            add_settings_section(
                'wpsi-settings-tab',
                "",
                array($this, 'wpsi_settings_tab_intro'),
                'wpsi-filter'
            );

            /**
             * filter grid
             */

            add_settings_field(
                'wpsi_filter_textarea',
                __("Search Filter", "wp-search-insights"),
                array($this, 'option_textarea_filter'),
                'wpsi-filter',
                'wpsi-settings-tab'
            );

            register_setting('wpsi-filter-tab', 'wpsi_filter_textarea', array(
                'sanitize_callback' => 'sanitize_textarea_field',
                'default' => '',
            ));

            /**
             * data grid
             */

            add_settings_section(
                'wpsi-data-tab',
                "",
                array($this, 'wpsi_settings_tab_intro'),
                'wpsi-data'
            );

            add_settings_field(
                'wpsi_export_database',
                __("Export database", 'wp-search-insights') . WPSI::$help->get_help_tip(__("Export the contents of your database for backup or analysis", "wp-search-insights")),
                array($this, 'option_wpsi_export_database'),
                'wpsi-data',
                'wpsi-data-tab'
            );

            add_settings_field(
                'wpsi_cleardatabase',
                __("Clear data on uninstall", 'wp-search-insights') . WPSI::$help->get_help_tip(__("When ON, deletes ALL your collected search data if you remove the plugin. Turn OFF if you might reinstall later and want to keep your stats.", "wp-search-insights")),
                array($this, 'option_clear_database_on_uninstall'),
                'wpsi-data',
                'wpsi-data-tab'
            );

            add_settings_field(
                'wpsi_clear_database',
                __("Clear database", 'wp-search-insights') . WPSI::$help->get_help_tip(__("This will permanently delete all search data in your database. Make sure to export your data first if you want to keep a backup.", "wp-search-insights")),
                array($this, 'option_wpsi_clear_database'),
                'wpsi-data',
                'wpsi-data-tab'
            );

            register_setting('wpsi-data-tab', 'wpsi_cleardatabase', array(
                'sanitize_callback' => 'absint',
                'default' => 0,
            ));

        }

        /**
         * Sanitize the minimum term length
         *
         * @param mixed $input The input value to sanitize
         * @return int The sanitized value
         */
        public function sanitize_min_term_length($input)
        {
            $input = absint($input);

            // Min term length should be between 0 and 24
            if ($input > 24) {
                return 24;
            }

            return $input;
        }

        /**
         * Sanitize the maximum term length
         *
         * @param mixed $input The input value to sanitize
         * @return int The sanitized value
         */
        public function sanitize_max_term_length($input)
        {
            $input = absint($input);

            // Max term length should be between 0 and 255
            if ($input > 255) {
                return 255;
            }

            return $input;
        }

        /**
         * Sanitize the dashboard capability setting
         *
         * @param mixed $input The input value to sanitize
         * @return string The sanitized value
         */
        public function sanitize_capability($input)
        {
            $allowed_values = array('activate_plugins', 'read');

            if (in_array($input, $allowed_values, true)) {
                return $input;
            }

            // Default to administrator capability if invalid
            return 'activate_plugins';
        }

        /**
         * Sanitize the term deletion period
         *
         * @param mixed $input The input value to sanitize
         * @return string The sanitized value
         */
        public function sanitize_term_deletion_period($input)
        {
            $allowed_values = array('never', 'week', 'month', 'year');

            if (in_array($input, $allowed_values, true)) {
                return $input;
            }

            // Default to 'never' if invalid
            return 'never';
        }

        public function add_privacy_info()
        {
            if (!function_exists('wp_add_privacy_policy_content')) {
                return;
            }

            $content = sprintf(
            /* translators: %1$s: Opening anchor tag with URL to privacy policy, %2$s: Closing anchor tag */
                __('Search Insights does not process any personal identifiable information, so the GDPR does not apply to these plugins or usage of these plugins on your website. You can find our privacy policy %1$s here %2$s.', 'wp-search-insights'),
                '<a href="' . esc_url('https://wpsi.io/privacy-statement/') . '" target="_blank">',
                '</a>'
            );

            wp_add_privacy_policy_content(
                'Search Insights',
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

        public function wpsi_settings_tab_intro()
        {
        }

        public function option_wpsi_exclude_admin()
        {
            ?>
            <div class="wpsi-checkbox-item">
                <label class="wpsi-switch" id="wpsi-exclude-admin-switch">
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
                    <option value="activate_plugins" <?php selected(get_option('wpsi_select_dashboard_capability'), 'activate_plugins'); ?>><?php esc_html_e('Administrators', 'wp-search-insights'); ?></option>
                    <option value="read" <?php selected(get_option('wpsi_select_dashboard_capability'), 'read'); ?>><?php esc_html_e('All Users', 'wp-search-insights'); ?></option>
                </select>
            </label>
            <?php
        }

        public function option_wpsi_export_database()
        {
            ?>
            <button class="button-secondary wpsi-modal-trigger" data-target="wpsi_export_modal">
                <?php esc_html_e("Export data", "wp-search-insights") ?>
            </button>

            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output escaped in export_modal() function
            echo WPSI_Modal::export_modal();
        }

        public function option_clear_database_on_uninstall()
        {
            ?>
            <div class="wpsi-checkbox-item">
                <label id="wpsi_cleardatabase" class="wpsi-switch">
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
            <div class="wpsi-checkbox-item">
                <label class="wpsi-switch" id="wpsi-track-ajax-searches-switch">
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
                    <option value="never" <?php selected(get_option('wpsi_select_term_deletion_period'), 'never'); ?>><?php esc_html_e('Never', 'wp-search-insights'); ?></option>
                    <option value="week" <?php selected(get_option('wpsi_select_term_deletion_period'), 'week'); ?>><?php esc_html_e('Week', 'wp-search-insights'); ?></option>
                    <option value="month" <?php selected(get_option('wpsi_select_term_deletion_period'), 'month'); ?>><?php esc_html_e('Month', 'wp-search-insights'); ?></option>
                    <option value="year" <?php selected(get_option('wpsi_select_term_deletion_period'), 'year'); ?>><?php esc_html_e('Year', 'wp-search-insights'); ?></option>
                </select>
            </label>
            <?php
            WPSI::$help->get_help_tip(__("When to delete terms from your database after this time period", "wp-search-insights"));
            ?>
            <?php
        }

        public function option_wpsi_custom_search_parameter()
        {
            ?>
            <input id="wpsi_custom_search_parameter" class="wpsi_custom_search_parameter"
                   name="wpsi_custom_search_parameter" size="40"
                   value="<?php echo esc_html(get_option('wpsi_custom_search_parameter')) ?>"
                   type="text">
            <?php
        }

        public function option_min_term_length()
        {
            ?>
            <input id="wpsi_min_term_length" class="wpsi_term_length" name="wpsi_min_term_length" size="40" min="0"
                   max="24" value="<?php echo (int)get_option('wpsi_min_term_length') ?>"
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
                   max="255" value="<?php echo (int)get_option('wpsi_max_term_length') ?>"
                   type="number">
            <?php
        }

        public function option_wpsi_clear_database()
        {
            $token = wp_create_nonce('wpsi_thickbox_nonce');
            $action_url = add_query_arg(
                array(
                    'page' => 'wpsi-settings-page',
                    'action' => 'wpsi_clear_database',
                    'token' => $token
                ),
                admin_url("admin.php")
            );

            ?>
            <button type="button" class="wpsi-modal-trigger button" data-target="wpsi_clear_searches_modal">
                <?php echo esc_html__('Clear all searches', 'wp-search-insights'); ?>
            </button>

            <?php

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- output escaped in clear_searches_modal() function
            echo WPSI_Modal::clear_searches_modal(array(
                'action_url' => esc_url($action_url),
                'action_token' => esc_attr($token),
            ));
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
            if (!isset($_GET['token']) || (!wp_verify_nonce(sanitize_key($_GET['token']), 'wpsi_thickbox_nonce'))) {
                return;
            }
            //check for action
            if (isset($_GET["action"]) && $_GET["action"] == 'wpsi_clear_database') {
                $this->clear_database_tables();
                $this->clear_cache();
            }
            wp_redirect(admin_url("admin.php?page=wpsi-settings-page"));
            exit;
        }

        /**
         * Delete entries from database after certain period
         *
         * @since 1.3.8
         *
         */

        public function clear_entries_from_database()
        {
            // Nonce is already verified before calling this function
            if (!current_user_can($this->capability)) {
                return;
            }

            $period = get_option('wpsi_select_term_deletion_period');

            $past_date = '';

            if ($period === 'week') {
                $past_date = strtotime("-1 week");
            }

            if ($period === 'month') {
                $past_date = strtotime("-1 month");
            }

            if ($period === 'year') {
                $past_date = strtotime("-1 year");
            }

            $this->delete_from_tables_after_period($past_date);
        }

        public function delete_from_tables_after_period($past_date)
        {

            if (!current_user_can($this->capability)) {
                return;
            }

            // Don't proceed if past_date is empty or not a valid timestamp
            if (empty($past_date) || !is_numeric($past_date)) {
                return;
            }

            global $wpdb;

            $table_name_single = esc_sql($wpdb->prefix . 'searchinsights_single');
            $table_name_archive = esc_sql($wpdb->prefix . 'searchinsights_archive');
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is properly escaped with esc_sql(), and caching would be counterproductive for deletion operations
            $wpdb->query($wpdb->prepare("DELETE FROM {$table_name_single} WHERE (time < %d)", $past_date));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is properly escaped with esc_sql(), and caching would be counterproductive for deletion operations
            $wpdb->query($wpdb->prepare("DELETE FROM {$table_name_archive} WHERE (time < %d)", $past_date));
        }

        /**
         * Clear the transient caches
         */

        public function clear_cache()
        {
            delete_transient('wpsi_popular_searches');
            delete_transient('wpsi_top_searches');
            delete_transient('wpsi_top_searches_week');
            delete_transient('wpsi_popular_searches_week');
            delete_transient('wpsi_plus_ones');
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
            do_action('wpsi_on_settings_page');
            ?>
            <style>
                #wpcontent {padding-left: 0 !important;}
            </style>
            <div class="wrap">
                <div id="wpsi-toggle-wrap">
                    <div id="wpsi-toggle-dashboard">
                        <div id="wpsi-toggle-dashboard-text">
                            <?php esc_html_e("Select which dashboard items should be displayed", "wp-search-insights") ?>
                        </div>
                        <div id="wpsi-checkboxes">
                            <?php
                            $grid_items = $this->grid_items;
                            foreach ($grid_items as $index => $grid_item) {
                                $style = "";
                                if (!$grid_item['can_hide']) {
                                    $style = 'style="display:none"';
                                }
                                // Use option_title if available, otherwise fall back to title
                                $checkbox_label = isset($grid_item['display_option_title']) ? $grid_item['display_option_title'] : wp_strip_all_tags($grid_item['title']);
                                ?>
                                <label for="wpsi_toggle_data_id_<?php echo esc_attr($index); ?>" <?php echo esc_attr($style); ?>>
                                    <input class="wpsi-toggle-items"
                                           name="wpsi_toggle_data_id_<?php echo esc_attr($index); ?>" type="checkbox"
                                           id="wpsi_toggle_data_id_<?php echo esc_attr($index); ?>"
                                           value="data_id_<?php echo esc_attr($index); ?>">
                                    <?php echo esc_html($checkbox_label); ?>
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
                                <img class="wpsi-settings-logo"
                                     src="<?php echo esc_url(trailingslashit(wpsi_url)); ?>assets/images/logo.png"
                                     alt="Search Insights logo">
                                <div class="header-links">
                                    <ul class="tab-links">
                                        <?php foreach ($this->tabs as $key => $tab) {
                                            if (isset($tab['capability']) && !current_user_can($tab['capability'])) continue;
                                            $current = $key == 'dashboard' ? 'current' : '';
                                            ?>
                                            <li class="tab-link <?php echo esc_attr($current); ?>"
                                                data-tab="<?php echo esc_attr($key); ?>"><a
                                                        class="tab-text tab-<?php echo esc_attr($key); ?>"
                                                        href="#<?php echo esc_attr($key); ?>#top"><?php echo esc_html($tab['title']); ?></a>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                    <?php do_action('wpsi_tab_options') ?>
                                </div>
                            </div>
                        </ul>

                        <div class="wp-search-insights-main">
                            <?php foreach ($this->tabs as $key => $tab) {
                                if (isset($tab['capability']) && !current_user_can($tab['capability'])) continue;
                                $current = $key == 'dashboard' ? 'current' : '';
                                ?>
                                <div id="<?php echo esc_attr($key); ?>"
                                     class="tab-content <?php echo esc_attr($current); ?>">
                                    <?php do_action("wpsi_tab_content_$key"); ?>
                                </div>
                            <?php } ?>
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
            if (!current_user_can('manage_options')) {
                return;
            }

            global $wpdb;

            $table_name_single = esc_sql($wpdb->prefix . 'searchinsights_single');
            $table_name_archive = esc_sql($wpdb->prefix . 'searchinsights_archive');

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is properly escaped with esc_sql()
            $wpdb->query("TRUNCATE TABLE {$table_name_single}");
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is properly escaped with esc_sql()
            $wpdb->query("TRUNCATE TABLE {$table_name_archive}");

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
            wp_add_dashboard_widget('dashboard_widget_wpsi', 'Search Insights', array(
                $this,
                'generate_dashboard_widget_wrapper'
            ));
        }

        /**
         * Wrapper function for dashboard widget so params can be sent along
         */

        public function generate_dashboard_widget_wrapper()
        {
            // All content is escaped within generate_dashboard_widget()
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
                $form_open = '<form action="' . esc_url(admin_url('options.php')) . '" method="post" class="wpsi-settings-form">';
                // Add hidden field to store the current tab
                $form_open .= '<input type="hidden" name="wpsi_active_tab" value="' . sanitize_title($args['type']) . '" />';
                $form_close = '</form>';
                $button = wpsi_save_button();
                $contents = str_replace('{content}', $form_open . '{content}' . $button . $form_close, $contents);
            }

            foreach ($args as $key => $value) {
                $contents = str_replace('{' . $key . '}', $value, $contents);
            }

            return $contents;
        }


        /**
         * Format trend percentage with K abbreviation for large values
         * @param float $percent Percentage value
         * @return string Formatted percentage
         */
        function wpsi_format_trend_percentage($percent)
        {
            $abs_percent = abs($percent);

            if ($abs_percent >= 1000) {
                // For percentages 1000+, use K notation with 1 decimal place
                return round($abs_percent / 1000, 1) . 'K%';
            } else {
                // For smaller percentages, round to 1 decimal place
                return round($abs_percent, 1) . '%';
            }
        }

        /**
         * Generate popular searches block for the main plugin dashboard
         *
         * @param bool $start Timestamp for start date
         * @param bool $end Timestamp for end date
         * @param string $filter Filter type (all, with_results, without_results)
         * @param int $page Current page for pagination
         * @return string HTML content
         */
        public function generate_popular_searches_block($start = false, $end = false, $filter = 'with_results', $page = 1)
        {
            // Set default limit for free version
            $limit = 6;

            // Get top searches based on filter
            $args = array(
                'orderby' => 'frequency',
                'order' => 'DESC',
                'number' => $limit,
            );

            // Apply filter criteria
            if ($filter === 'with_results') {
                $args['result_count'] = 0;
                $args['compare'] = '>'; // Greater than 0 results
            } elseif ($filter === 'without_results') {
                $args['result_count'] = 0;
                $args['compare'] = '='; // Exactly 0 results
            }

            // Apply date range
            $args['date_from'] = $start;
            $args['date_to'] = $end;

            // Allow Pro version to modify the arguments
            $args = apply_filters('wpsi_popular_searches_args', $args, $page);

            // Fetch the search data
            $top_searches = WPSI::$search->get_searches($args, $trend = true);

            // Generate rows for top searches
            $top_html = '';
            $home_url = home_url();

            if (empty($top_searches) || count($top_searches) == 0) {
                $args = array(
                    'type' => 'dashboard',
                    'term_link' => __("No searches in selected period", "wp-search-insights"),
                );
                $top_html .= $this->render_search_row($args);
            } else {
                foreach ($top_searches as $search) {
                    $has_results = (int)$search->result_count > 0;

                    // Set appropriate icon and status class
                    if ($has_results) {
                        $icon = 'dashicons-yes-alt';
                    } else {
                        $icon = 'dashicons-no-alt';
                    }

                    /* translators: %s: number of searches formatted according to locale */
                    $searches = sprintf(_n('%s search', '%s searches',
                        $search->frequency, 'wp-search-insights'),
                        number_format_i18n($search->frequency));

                    $args = array(
                        'type' => 'dashboard',
                        'icon' => $icon,
                        'term' => $search->term,
                        'term_link' => $this->get_term_link($search->term, $home_url),
                        'count' => $searches,
                    );

                    // Allow Pro version to add trend information
                    $args = apply_filters('wpsi_popular_search_row_args', $args, $search, $start, $end);

                    $top_html .= $this->render_search_row($args);
                }
            }

            // Upsell message
//            if (!defined('WPSI_PRO') && count($top_searches) == $limit) {
//                $top_html .= '<div class="wpsi-pro-upsell">';
//                $top_html .= '<p>' . sprintf(__('Showing %d results. Get Pro to see all searches and unlock pagination.', 'wp-search-insights'), $limit) . '</p>';
//                $top_html .= '<a href="https://wpsi.io/pricing/" target="_blank" class="button button-primary">' . __('Upgrade to Pro', 'wp-search-insights') . '</a>';
//                $top_html .= '</div>';
//            }

            $top_html = apply_filters('wpsi_popular_searches_html', $top_html, $top_searches, $page, $limit, $filter);

            // Set title based on filter
            $title = __("Popular searches", "wp-search-insights");
            if ($filter === 'with_results') {
                $title = __("Popular searches with results", "wp-search-insights");
            } elseif ($filter === 'without_results') {
                $title = __("Popular searches without results", "wp-search-insights");
            }

            return $this->render_row_container(array(
                'container_type' => 'standard',
                'container_class' => 'wpsi-dashboard-widget-grid',
                'title' => $title,
                'content' => $top_html,
                'help_text' => __("Shows what people search for most on your site. Use the dropdown to filter searches with or without results. Green checkmarks (✓) mean visitors found something. Red X marks (✗) mean they found nothing and likely left disappointed. Focus on searches without results to identify content gaps.", "wp-search-insights"),
            ));
        }

        /**
         * Add trend information to search row arguments
         *
         * @param array $args Current row arguments
         * @param object $search Search data
         * @param int $start Start timestamp
         * @param int $end End timestamp
         * @return array Modified arguments
         */
        public function add_trend_information($args, $search, $start, $end)
        {
            // For all time filter, don't add trend information
            $activation_time = get_option('wpsi_activation_time', 0);
            $is_all_time = ($start && $activation_time && ($start <= $activation_time + DAY_IN_SECONDS));

            // For "today" detection
            $is_today = $this->is_today_range($start, $end);

            // Only calculate and display trends if not "all time" or "today"
            if (!$is_all_time && !$is_today) {
                $prev_frequency = property_exists($search, 'previous_frequency') ? (int)$search->previous_frequency : 0;
                $curr_frequency = (int)$search->frequency;

                // Get term's first occurrence date
                $first_occurrence = $this->get_term_first_occurrence($search->term);
                $one_week_ago = time() - (7 * DAY_IN_SECONDS);

                $truly_new = $first_occurrence && ($first_occurrence >= $one_week_ago);

                if ($truly_new) {
                    // Only mark as "New" if it's genuinely new (within last 7 days)
                    $args['extra_stats'] = array(
                        'trend' => '<span class="wpsi-trend wpsi-trend-new">New</span>'
                    );
                } else {
                    // For all other terms (including returning ones), show percent change
                    if ($prev_frequency > 0) {
                        // Standard percent change calculation
                        $percent_change = round((($curr_frequency - $prev_frequency) / $prev_frequency) * 100, 1);
                    } else {
                        // If previous frequency was 0, the change is technically infinite
                        // but we'll cap it at 100% for display purposes
                        $percent_change = 100;
                    }

                    if ($percent_change > 0) {
                        $args['extra_stats'] = array(
                            'trend' => '<span class="wpsi-trend wpsi-trend-up">↑ ' . $this->wpsi_format_trend_percentage(abs($percent_change)) . '</span>'
                        );
                    } elseif ($percent_change < 0) {
                        $args['extra_stats'] = array(
                            'trend' => '<span class="wpsi-trend wpsi-trend-down">↓ ' . $this->wpsi_format_trend_percentage(abs($percent_change)) . '</span>'
                        );
                    } else {
                        $args['extra_stats'] = array(
                            'trend' => '<span class="wpsi-trend wpsi-trend-neutral">⟷</span>'
                        );
                    }
                }
            }

            return $args;
        }

        /**
         * Get the first occurrence date of a search term
         * @param string $term The search term
         * @return int|false Timestamp of first occurrence or false if not found
         */
        public function get_term_first_occurrence($term)
        {
            global $wpdb;
            $table_name = esc_sql($wpdb->prefix . 'searchinsights_single');

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query necessary as WordPress core doesn't provide an API for this specific data lookup. Caching intentionally omitted: this is a simple query called infrequently with thousands of potential unique terms, making caching inefficient
            $first_time = $wpdb->get_var($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is properly escaped with esc_sql()
            "SELECT MIN(time) FROM $table_name WHERE term = %s",
                $term
            ));

            return $first_time ? (int)$first_time : false;
        }

        /**
         * Generate the dashboard widget for WordPress admin dashboard
         *
         * @param bool $on_grid Whether this is being used on the grid
         * @param bool $start Timestamp for start date
         * @param bool $end Timestamp for end date
         * @param string $filter Filter type (all, with_results, without_results)
         * @param int $page Current page for pagination
         * @return string HTML content
         */
        public function generate_dashboard_widget($on_grid = false, $start = false, $end = false, $filter = 'all', $page = 1)
        {
            // If this is for the grid, use the dedicated function
            if ($on_grid) {
                return $this->generate_popular_searches_block($start, $end, $filter, $page);
            }

            // Get popular searches WITH NO RESULTS
            $popular_searches_no_results = get_transient("wpsi_popular_searches_week");
            if (!$popular_searches_no_results) {
                $args = array(
                    'orderby' => 'frequency',
                    'order' => 'DESC',
                    'result_count' => 0,
                    'compare' => '=',
                    'number' => 5,
                    'range' => 'month',
                );
                $popular_searches_no_results = WPSI::$search->get_searches($args, $trend = false);
                if (!is_array($popular_searches_no_results)) {
                    $popular_searches_no_results = array();
                }
                set_transient("wpsi_popular_searches_week", $popular_searches_no_results, $this->get_dashboard_cache_time());
            }

            // Get TOP SEARCHES (all searches, sorted by frequency)
            $top_searches = get_transient("wpsi_top_searches_week");
            if (!$top_searches) {
                $args = array(
                    'orderby' => 'frequency',
                    'order' => 'DESC',
                    'result_count' => 0,
                    'compare' => '>',
                    'number' => 5,
                    'range' => 'month',
                );
                $top_searches = WPSI::$search->get_searches($args, $trend = false);
                if (!is_array($top_searches)) {
                    $top_searches = array();
                }

                set_transient("wpsi_top_searches_week", $top_searches, $this->get_dashboard_cache_time());
            }

            // Generate popular searches with no results section
            $popular_html = '';
            $home_url = home_url();

            if (count($popular_searches_no_results) == 0) {
                $args = array(
                    'type' => 'dashboard',
                    'term_link' => __("No searches in selected period", "wp-search-insights"),
                );
                $popular_html .= $this->render_search_row($args);
            } else {
                foreach ($popular_searches_no_results as $search) {
                    // Has no result, use X icon
                    $icon = 'dashicons-no-alt';

                    /* translators: %s: human-readable time difference */
                    $time = sprintf(__("%s ago", "wp-search-insights"),
                        human_time_diff($search->time, current_time('timestamp')));
                    /* translators: %s: number of searches formatted according to locale */
                    $searches = sprintf(_n('%s search', '%s searches',
                        $search->frequency, 'wp-search-insights'),
                        number_format_i18n($search->frequency));

                    $args = array(
                        'type' => 'dashboard',
                        'icon' => $icon,
                        'term' => $search->term,
                        'term_link' => $this->get_term_link($search->term, $home_url),
                        'count' => $searches,
                        'time' => $time,
                    );
                    $popular_html .= $this->render_search_row($args);
                }
            }

            // Render the no results container
            $popular_container = $this->render_row_container(array(
                'container_type' => 'standard',
                'container_class' => 'wpsi-dashboard-widget-grid',
                'title' => __("Popular searches without results", "wp-search-insights"),
                'content' => $popular_html,
                'help_text' => __("Shows searches where people found ZERO results. These are your biggest missed opportunities! Create content for these topics to immediately help visitors find what they're looking for.", "wp-search-insights"),
                'empty_text' => __("No searches without results in selected period", "wp-search-insights"),
                'is_dashboard_widget' => true,
            ));

            // Generate top searches section
            $top_html = '';

            if (count($top_searches) == 0) {
                $args = array(
                    'type' => 'dashboard',
                    'term_link' => __("No searches in selected period", "wp-search-insights"),
                );
                $top_html .= $this->render_search_row($args);
            } else {
                foreach ($top_searches as $search) {
                    $has_results = (int)$search->result_count > 0;

                    // Set appropriate icon
                    if ($has_results) {
                        $icon = 'dashicons-yes-alt';
                    } else {
                        $icon = 'dashicons-no-alt';
                    }

                    /* translators: %s: number of searches formatted according to locale */
                    $searches = sprintf(_n('%s search', '%s searches',
                        $search->frequency, 'wp-search-insights'),
                        number_format_i18n($search->frequency));

                    // For WP dashboard widget, show time
                    /* translators: %s: human-readable time difference */
                    $time = sprintf(__("%s ago", "wp-search-insights"), human_time_diff($search->time, current_time('timestamp')));

                    $args = array(
                        'type' => 'dashboard',
                        'icon' => $icon,
                        'term' => $search->term,
                        'term_link' => $this->get_term_link($search->term, $home_url),
                        'count' => $searches,
                        'time' => $time,
                    );

                    $top_html .= $this->render_search_row($args);
                }
            }

            $top_container = $this->render_row_container(array(
                'container_type' => 'standard',
                'container_class' => 'wpsi-dashboard-widget-grid',
                'title' => __("Top searches", "wp-search-insights"),
                'content' => $top_html,
                'help_text' => __("Shows what people search for most on your site. Use the dropdown to filter searches with or without results. Green checkmarks (✓) mean visitors found something. Red X marks (✗) mean they found nothing and likely left disappointed. Focus on searches without results to identify content gaps.", "wp-search-insights"), 'is_dashboard_widget' => true,
            ));

            // Build the complete widget
            $widget = '<div class="inside">';
            $widget .= '<div id="wpsi-dashboard-widget">';
            // phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage -- Plugin asset image, not from media library
            $widget .= '<div class="wpsi-widget-logo"><img width=35px" height="35px" src="' . esc_url( wpsi_url ) . '/assets/images/noname_logo.png" alt="logo">';
            $widget .= '<span>' . sprintf("Search Insights %s", wpsi_version) . '</span></div>';
            $widget .= $popular_container;
            $widget .= $top_container;
            $widget .= '<div id="wpsi-dashboard-widget-footer">';
            $admin_url = admin_url("admin.php?page=wpsi-settings-page");
            /* translators: %1$s: opening dashboard link tag, %2$s: closing dashboard link tag with icon */
            $widget .= wp_kses_post(
                    sprintf(
                        __("%1\$sDashboard%2\$s ", "wp-search-insights"),
                        '<a href="' . esc_url($admin_url) . '">',
                        '<div class="dashicons dashicons-external"></div></a>'
                    )
                ) . ' | ';
            $help_url = "https://wpsi.io";
            /* translators: %1$s: opening help link tag, %2$s: closing help link tag with icon */
            $widget .= wp_kses_post(
                sprintf(
                    __("%1\$sHelp%2\$s ", "wp-search-insights"),
                    '<a target="_blank" href="' . esc_url($help_url) . '">',
                    '<div class="dashicons dashicons-external"></div></a>'
                )
            );
            $widget .= '</div></div></div>';

            return $widget;
        }

        /**
         * Render a search row using the unified template
         *
         * @param array $args Array of template parameters
         * @return string      HTML output of the rendered template
         */
        public function render_search_row($args)
        {
            ob_start();
            include(wpsi_path . 'templates/search-row.php');
            return ob_get_clean();
        }

        /**
         * Render a row container with its content
         *
         * @param array $args Container template parameters
         * @return string HTML output of the rendered template
         */
        public function render_row_container($args)
        {
            // If column size is specified, add the appropriate class
            if (isset($args['column_size'])) {
                $column_class = 'wpsi-col-' . $args['column_size'];
                if (isset($args['container_class'])) {
                    $args['container_class'] .= ' ' . $column_class;
                } else {
                    $args['container_class'] = $column_class;
                }
            }

            // Determine if this is for the dashboard widget
            $is_dashboard_widget = isset($args['is_dashboard_widget']) ? $args['is_dashboard_widget'] : false;

            // If it's a dashboard widget, we'll skip help tooltips
            if ($is_dashboard_widget) {
                $args['help_text'] = '';
            }

            ob_start();
            include(wpsi_path . 'templates/row-container.php');
            return ob_get_clean();
        }

        public function ajax_get_content()
        {
            $error = false;
            $total = 0;
            $html = __("No data found", "wp-search-insights");
            if (!current_user_can($this->capability)) {
                $error = true;
            }

            if (!$error && isset($_GET['token']) && !wp_verify_nonce(sanitize_key(wp_unslash($_GET['token'])), 'wpsi_get_datatable')) {
                $error = true;
            }

            if (!isset($_GET['start'])) {
                $error = true;
            }

            if (!isset($_GET['end'])) {
                $error = true;
            }

            if (!isset($_GET['type'])) {
                $error = true;
            }

            if (!isset($_GET['token'])) {
                $error = true;
            }

            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $filter = isset($_GET['filter']) ? sanitize_text_field(wp_unslash($_GET['filter'])) : 'with_results';

            if (!$error) {
                $start = intval( $_GET['start'] ) ?? 0;
                $end = intval( $_GET['end'] ) ?? time();
                $type = sanitize_title(wp_unslash($_GET['type']));
                $total = $this->get_results_count($type, $start, $end);
                switch ($type) {
                    case 'all':
                        $html = $this->recent_table($start, $end, $page);
                        break;
                    case 'popular':
                        $html = $this->generate_popular_searches_block($start, $end, $filter, $page);
                        break;
                    case 'results':
                        $html = $this->results_table($start, $end);
                        break;
                    default:
                        // Allow filtering of content for all types
                        $html = apply_filters("wpsi_ajax_content_$type", $html, $start, $end, $page, $filter);
                        break;
                }

            }

            $data = array(
                'success' => !$error,
                'html' => $html,
                'total_rows' => (int)$total,
                'batch' => (int)$this->get_batch_size(),
            );

            $response = json_encode($data);
            header("Content-Type: application/json");
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

        public function get_results_count($type, $start, $end)
        {
            $count = 0;
            if ($type === 'all') {
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

            // Define base columns for the filter
            $base_columns = array(
                array(
                    'name' => __("Search term", "wp-search-insights"),
                    'key' => 'term',
                    'width' => '15%'
                ),
                array(
                    'name' => __("Results", "wp-search-insights"),
                    'key' => 'results',
                    'width' => '5%'
                ),
                array(
                    'name' => __("When", "wp-search-insights"),
                    'key' => 'when',
                    'width' => '12%',
                    'class' => 'dashboard-tooltip-hits'
                ),
                array(
                    'name' => __("From", "wp-search-insights"),
                    'key' => 'from',
                    'width' => '15%',
                    'class' => 'dashboard-tooltip-from'
                )
            );

            // Apply the filter - this will add Pro columns
            $columns = apply_filters('wpsi_datatable_columns', $base_columns);

            // Add timestamp column AFTER filters
            $columns[] = array(
                'name' => '',  // Hidden unix timestamp
                'key' => 'unix',
                'width' => '',
                'hidden' => true
            );

            // Get the data
            $args = array(
                'offset' => $this->get_batch_size() * ($page - 1),
                'number' => $this->get_batch_size(),
                'date_from' => $start,
                'date_to' => $end,
                'result_count' => true,
            );

            $recent_searches = WPSI::$search->get_searches_single($args);

            // For pagination, return only row data
            if ($page > 1) {
                $output = array();
                foreach ($recent_searches as $search) {
                    $row_data = $this->get_row_data($search, $home_url);

                    $row_html = '<tr>';
                    foreach ($columns as $column) {
                        if (!isset($column['hidden']) || !$column['hidden']) {
                            $key = $column['key'];
                            $row_html .= isset($row_data[$key]) ? $row_data[$key] : '<td></td>';
                        }
                    }
                    $row_html .= '</tr>';
                    $output[] = $row_html;
                }
                return $output;
            }

            // First page, return complete table with headers
            $output = '<table id="wpsi-recent-table" class="wpsi-table"><thead><tr class="wpsi-thead-th">';

            // Add headers
            foreach ($columns as $column) {
                if (!isset($column['hidden']) || !$column['hidden']) {
                    $class = isset($column['class']) ? ' class="' . $column['class'] . '"' : '';
                    $output .= '<th scope="col"' . $class . '>' . $column['name'] . '</th>';
                }
            }
            $output .= '</tr></thead><tbody>';

            // Add rows
            foreach ($recent_searches as $search) {
                $row_data = $this->get_row_data($search, $home_url);
                $output .= '<tr>';
                foreach ($columns as $column) {
                    if (!isset($column['hidden']) || !$column['hidden']) {
                        $key = $column['key'];
                        $output .= isset($row_data[$key]) ? $row_data[$key] : '<td></td>';
                    }
                }
                $output .= '</tr>';
            }

            $output .= '</tbody></table>';
            return $output;
        }

        /**
         * Get row data in a filterable format
         *
         * @param object $search
         * @param string $home_url
         * @return array
         */
        private function get_row_data($search, $home_url)
        {
            // Base row data with default columns
            $row_data = array(
                'term' => '<td data-label="Term" class="wpsi-term" data-term_id="' . $search->id . '">'
                    . $this->get_term_link($search->term, $home_url) . '</td>',
                'results' => '<td data-label="Result-count">' . $search->result_count . '</td>',
                'when' => '<td data-label="When" data-order="' . $search->time . '">' . $this->localize_date($search->time) . '</td>', 'from' => '<td data-label="From">' . $this->get_referrer_link($search) . '</td>',
                // Unix timestamp is already present as the last element
                'unix' => '<td data-order="' . $search->time . '"></td>'
            );

            // Apply the filter to allow plugins to add their data
            $row_data = apply_filters('wpsi_table_row_data', $row_data, $search);

            return $row_data;
        }

        public function localize_date($unix)
        {
            // Convert timestamp to account for WordPress timezone setting
            $date = new DateTime("@$unix"); // Create DateTime object from timestamp (UTC)
            $timezone = new DateTimeZone(wp_timezone_string()); // Get WP timezone
            $date->setTimezone($timezone); // Set the timezone for display

            // Format according to WordPress date/time settings
            $date_format = str_replace('F', 'M', get_option('date_format'));
            $time_format = get_option('time_format');

            return sprintf("%s at %s",
                $date->format($date_format),
                $date->format($time_format)
            );
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

            // URL encode the term when building the URL
            $search_url = $home_url . "?$search_parameter=" . urlencode($term) . '&searchinsights';

            // Add wpsi-ellipsis class to show long texts on hover
            if (strlen($term) > 38) {
                // Truncate to 37 chars plus ellipsis
                $display_term = substr($term, 0, 35) . '...';
                $class = 'wpsi-ellipsis';
                return '<span class="' . esc_attr($class) . '" data-text="' . esc_attr($term) . '"><a href="' . esc_url($search_url) . '" target="_blank">' . esc_html($display_term) . '</a></span>';
            }

            return '<span class="' . esc_attr($class) . '" data-text="' . esc_attr($term) . '"><a href="' . esc_url($search_url) . '" target="_blank">' . esc_html($term) . '</a></span>';
        }

        /**
         * Get referrer link
         * @param $search
         *
         * @return string
         */

        public function get_referrer_link($search)
        {
            //legacy title search
            $post_id = $search->referrer_id;
            if ($post_id != 0) {
                $url = get_permalink($post_id);
                $referrer = get_the_title($post_id);
            } elseif ($search->referrer === 'home' || $search->referrer === '' || $search->referrer === '/') {
                $url = site_url();
                $referrer = __('Home', 'wp-search-insights');
            } elseif (strpos($search->referrer, site_url()) === FALSE) {
                $url = site_url($search->referrer);
                $referrer = $search->referrer;
            } else {
                $url = $search->referrer;
                $referrer = $search->referrer;
            }
            //make sure the link is not too long
            if (strlen($referrer) > 25) {
                $referrer = mb_strcut($referrer, 0, 22) . '...';
            }
            return '<a target="_blank" href="' . esc_url_raw($url) . '">' . sanitize_text_field($referrer) . '</a>';
        }

        /**
         * Get post id from a string
         * @param string $title
         *
         * @return string|null
         */

        public function get_post_by_title($title)
        {
            global $wpdb;

            // WordPress does have get_page_by_title(), but it returns the full post object and runs additional queries.
            $query = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s",
                sanitize_text_field($title)
            );

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Query prepared above. Direct query is more efficient than get_page_by_title() when we only need the ID. Caching would be beneficial if this function is called repeatedly for the same title, but it's typically used for one-off lookups in referrer tracking.
            return $wpdb->get_var($query);
        }

        /**
         * Generate the no results overview in dashboard
         *
         * @param int $start
         * @param int $end
         *
         * @return string
         */
        public function results_table($start, $end)
        {
            global $wpdb;

            // Get data from the single table with a join to archive for result_count
            $single_table = esc_sql($wpdb->prefix . 'searchinsights_single');
            $archive_table = esc_sql($wpdb->prefix . 'searchinsights_archive');

            // Table names are escaped elsewhere
            $query_select = "SELECT single.*, archive.result_count as archive_result_count";
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names have been properly escaped
            $query_from = " FROM {$single_table} as single";
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names have been properly escaped
            $query_join = " LEFT JOIN {$archive_table} as archive ON single.term = archive.term";
            $query_where = " WHERE single.time BETWEEN %d AND %d";

            $query = $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query parts are prepared above
                $query_select . $query_from . $query_join . $query_where,
                $start,
                $end
            );

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Query prepared above. Complex custom query for search analytics that can't be efficiently replaced with WP core functions. No caching as this represents point-in-time analytics data that needs to be fresh.
            $all_searches = $wpdb->get_results($query);

            // Count searches with and without results
            $searches_with_results = 0;
            $searches_without_results = 0;
            $unique_terms = array();

            foreach ($all_searches as $search) {
                // Track unique terms
                $unique_terms[$search->term] = true;

                // Determine if search had results
                $has_results = false;

                // Get result_count from the archive table
                if (isset($search->archive_result_count) && $search->archive_result_count > 0) {
                    $has_results = true;
                }

                if ($has_results) {
                    $searches_with_results++;
                } else {
                    $searches_without_results++;
                }
            }

            // Get count of unique terms
            $unique_terms_count = count($unique_terms);
            $total_searches = count($all_searches);

            // Calculate percentages for current period - using float for precision
            if ($total_searches == 0) {
                $percentage_results_float = 0;
                $percentage_no_results_float = 0;
            } else {
                $percentage_results_float = ($searches_with_results / $total_searches) * 100;
                $percentage_no_results_float = 100 - $percentage_results_float;
            }

            // Rounded percentages for display
            $percentage_results = round($percentage_results_float, 1);
            $percentage_no_results = round($percentage_no_results_float, 1);

            // Calculate the EXACT previous period
            $period_length = $end - $start;
            $prev_start = $start - $period_length;
            $prev_end = $start - 1; // Ensure no overlap with current period

            $prev_query = $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query parts are prepared above
                $query_select . $query_from . $query_join . $query_where,
                $prev_start,
                $prev_end
            );

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Query prepared above. Complex custom query for search analytics that can't be efficiently replaced with WP core functions. No caching as this represents point-in-time analytics data that needs to be fresh.
            $prev_searches = $wpdb->get_results($prev_query);

            // Count previous period searches
            $prev_with_results = 0;

            foreach ($prev_searches as $search) {
                $has_results = false;
                if (isset($search->archive_result_count) && $search->archive_result_count > 0) {
                    $has_results = true;
                }

                if ($has_results) {
                    $prev_with_results++;
                }
            }

            $prev_total = count($prev_searches);

            // Calculate previous period percentages - using float for precision
            if ($prev_total == 0) {
                $prev_percentage_results_float = 0;
                $prev_percentage_no_results_float = 0;
            } else {
                $prev_percentage_results_float = ($prev_with_results / $prev_total) * 100;
                $prev_percentage_no_results_float = 100 - $prev_percentage_results_float;
            }

            // Rounded percentages for previous period
            $prev_percentage_results = round($prev_percentage_results_float, 1);
            $prev_percentage_no_results = round($prev_percentage_no_results_float, 1);

            // Calculate trend differences with higher precision
            $results_trend_float = $prev_total > 0 ? $percentage_results_float - $prev_percentage_results_float : 0;
            $no_results_trend_float = $prev_total > 0 ? $prev_percentage_no_results_float - $percentage_no_results_float : 0;

            // Round trend to 1 decimal place
            $results_trend = round($results_trend_float, 1);
            $no_results_trend = round($no_results_trend_float, 1);

            // Check for both "all time" and "today" selections
            $activation_time = get_option('wpsi_activation_time', 0);
            $is_all_time = ($start && $activation_time && ($start <= $activation_time + DAY_IN_SECONDS));

            $is_today = $this->is_today_range($start, $end);
            // Only show trends if not viewing "all time" or "today" AND we have enough data
            $show_trends = !($is_all_time || $is_today) && ($prev_total >= 3);

            ob_start();
            ?>
            <div class="wpsi-data-container">
                <div class="wpsi-data-cards">
                    <?php
                    // "Searches with results" card WITH TREND
                    $title = __("Searches with results", "wp-search-insights");
                    $number = $percentage_results . '%';

                    $footer = sprintf(
                    /* translators: %s: number of searches with results */
                        esc_html__("%s searches", "wp-search-insights"),
                        esc_html($searches_with_results)
                    );

                    // Add trend indicator if we have sufficient previous data
                    if ($show_trends) {
                        if ($results_trend > 0) {
                            // HIGHER percentage with results is GOOD
                            $number .= ' <span class="wpsi-trend wpsi-trend-up">↑ ' . $this->wpsi_format_trend_percentage($results_trend) . '</span> <span class="wpsi-trend-context">' . __("(prev period)", "wp-search-insights") . '</span>';
                        } elseif ($results_trend < 0) {
                            // LOWER percentage with results is BAD
                            $number .= ' <span class="wpsi-trend wpsi-trend-down">↓ ' . $this->wpsi_format_trend_percentage($results_trend) . '</span> <span class="wpsi-trend-context">' . __("(prev period)", "wp-search-insights") . '</span>';
                        } else {
                            // No change
                            $number .= ' <span class="wpsi-trend wpsi-trend-neutral">⟷</span> <span class="wpsi-trend-context">' . __("(prev period)", "wp-search-insights") . '</span>';
                        }
                    }

                    $card_class = 'success';
                    $help_text = __("Shows what percentage of searches displayed at least one result. This is your site's basic search success rate. If this number is low (below 80%), your visitors are regularly hitting dead ends.", "wp-search-insights");
                    include(wpsi_path . 'templates/shared-card.php');

                    // "Searches without results" card WITH TREND
                    $title = __("Searches without results", "wp-search-insights");
                    $number = $percentage_no_results . '%';

                    $footer = sprintf(
                    /* translators: %s: number of searches without results */
                        esc_html__("%s searches", "wp-search-insights"),
                        esc_html($searches_without_results)
                    );

                    // Add trend indicator if we have sufficient previous data
                    if ($show_trends) {
                        if ($no_results_trend > 0) {
                            // LOWER percentage without results is GOOD (reversed logic!)
                            $number .= ' <span class="wpsi-trend wpsi-trend-up">↓ ' . $this->wpsi_format_trend_percentage($no_results_trend) . '</span> <span class="wpsi-trend-context">' . __("(prev period)", "wp-search-insights") . '</span>';
                        } elseif ($no_results_trend < 0) {
                            // HIGHER percentage without results is BAD (reversed logic!)
                            $number .= ' <span class="wpsi-trend wpsi-trend-down">↑ ' . $this->wpsi_format_trend_percentage($no_results_trend) . '</span> <span class="wpsi-trend-context">' . __("(prev period)", "wp-search-insights") . '</span>';
                        } else {
                            // No change
                            $number .= ' <span class="wpsi-trend wpsi-trend-neutral">⟷</span> <span class="wpsi-trend-context">' . __("(prev period)", "wp-search-insights") . '</span>';
                        }
                    }

                    $card_class = 'warning';
                    $help_text = __("Shows how often people search and find absolutely nothing. These are frustrated visitors who likely leave your site immediately. The higher this percentage, the more urgent it is to add new content or improve your search system. Aim to keep this below 20%.", "wp-search-insights");
                    include(wpsi_path . 'templates/shared-card.php');
                    ?>
                </div>

                <?php if ($total_searches > 0): ?>
                    <div class="wpsi-totals-container">
                        <span class="wpsi-totals-label"><?php esc_html_e("Total unique search terms in period", "wp-search-insights"); ?></span>
                        <span class="wpsi-totals-value"><?php echo esc_html($unique_terms_count); ?></span>
                    </div>
                <?php else: ?>
                    <div class="wpsi-no-data">
                        <?php esc_html_e("No searches in selected period", "wp-search-insights"); ?>
                    </div>
                <?php endif; ?>
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

        public function get_status_link($item)
        {
            if (is_multisite()) {
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
                $link = $install_url . $item['search'] . "&tab=search&type=term";
                $text = __('Install', 'wp-search-insights');
                $status = "<a href=$link>$text</a>";
            }
            return $status;
        }

        public function generate_tips_tricks()
        {
            $items = array(
                1 => array(
                    'content' => __("Introducing Search Insights 2.0", "wp-search-insights"),
                    'link'    => 'https://wpsi.io/introducing-search-insights-2-0-faster-smarter-search-analytics/',
                ),
                2 => array(
                    'content' => __("Search Insights Beginner's Guide", "wp-search-insights"),
                    'link' => 'https://wpsi.io/search-insights-beginners-guide/',
                ),
                3 => array(
                    'content' => __("Using CSV/Excel Exports", "wp-search-insights"),
                    'link' => 'https://wpsi.io/using-csv-excel-exports/',
                ),
                4 => array(
                    'content' => __("Understanding the Search Insights dashboard", "wp-search-insights"),
                    'link' => 'https://wpsi.io/docs/understanding-the-search-analytics-dashboard/',
                ),
                5 => array(
                    'content' => __("The Search Filter", "wp-search-insights"),
                    'link' => 'https://wpsi.io/the-search-filter/',
                ),
                6 => array(
                    'content' => __("Privacy and GDPR compliance", "wp-search-insights"),
                    'link' => 'https://wpsi.io/docs/privacy-gdpr-compliance-protecting-user-data-while-gaining-insights/',
                ),
            );
            $button = '<a href="https://wpsi.io/tips-tricks/" target="_blank"><button class="button button-upsell">' . __("View all", "wp-search-insights") . '</button></a>';

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
            return '<div>' . $output . '</div>' . $button;
        }

        /**
         * Store date range as transient for server-side access
         */
        public function ajax_store_date_range()
        {
            // Verify nonce
            if (!isset($_POST['token']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['token'])), 'wpsi_store_date_range')) {
                wp_die();
            }

            if (isset($_POST['start']) && isset($_POST['end'])) {
                set_transient('wpsi_range_start', (int)$_POST['start'], DAY_IN_SECONDS * 30);
                set_transient('wpsi_range_end', (int)$_POST['end'], DAY_IN_SECONDS * 30);

                // Store range type if provided
                if (isset($_POST['range_type'])) {
                    set_transient('wpsi_range_type', sanitize_text_field(wp_unslash($_POST['range_type'])), DAY_IN_SECONDS * 30);
                }
            }

            wp_die();
        }

        /**
         * Get the cache time for dashboard widget
         *
         * @return int Cache time in seconds
         * @since 1.5.1
         */
        private function get_dashboard_cache_time()
        {
            return apply_filters('wpsi_dashboard_widget_cache_time', HOUR_IN_SECONDS);
        }

        /**
         * Save settings via AJAX
         */
        public function ajax_save_settings() {
            // Check security nonce
            if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['security'])), 'wpsi_save_settings')) {
                wp_send_json_error(array('message' => __('Invalid request', 'wp-search-insights')));
            }

            // Check user capabilities
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array('message' => __('Invalid request', 'wp-search-insights')));
            }

            // Get form data with proper unslashing
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each item sanitized individually based on type below
            $form_data = isset($_POST['form_data']) ? wp_unslash($_POST['form_data']) : array();

            // Process each setting
            foreach ($form_data as $key => $value) {
                // Only process WPSI settings
                $sanitized_key = sanitize_key($key);
                if (strpos($sanitized_key, 'wpsi_') === 0) {
                    // Apply the appropriate sanitization based on the setting type
                    switch ($sanitized_key) {
                        case 'wpsi_filter_textarea':
                            $sanitized_value = sanitize_textarea_field($value);
                            break;

                        case 'wpsi_min_term_length':
                            $sanitized_value = $this->sanitize_min_term_length($value);
                            break;

                        case 'wpsi_max_term_length':
                            $sanitized_value = $this->sanitize_max_term_length($value);
                            break;

                        case 'wpsi_select_dashboard_capability':
                            $sanitized_value = $this->sanitize_capability($value);
                            break;

                        case 'wpsi_select_term_deletion_period':
                            $sanitized_value = $this->sanitize_term_deletion_period($value);
                            break;

                        case 'wpsi_exclude_admin':
                        case 'wpsi_track_ajax_searches':
                        case 'wpsi_cleardatabase':
                            $sanitized_value = absint($value);
                            break;

                        default:
                            // Default to text field sanitization for other fields
                            $sanitized_value = sanitize_text_field($value);
                            break;
                    }

                    // Save the option with the properly sanitized value
                    update_option($sanitized_key, $sanitized_value);
                }
            }

            // Return success response
            wp_send_json_success(array(
                'message' => __('Settings saved successfully', 'wp-search-insights')
            ));
        }

        // Detect "today" consistently with UTC timestamps
        function is_today_range($start, $end)
        {
            // Get WordPress timezone
            $wp_timezone = wp_timezone(); // Built-in WP function that returns the site's timezone

            // Create DateTime objects with the UTC timestamps
            $start_dt = new DateTime('@' . $start);
            $end_dt = new DateTime('@' . $end);
            $now_dt = new DateTime('now', $wp_timezone);

            // Set the timezone to WordPress timezone
            $start_dt->setTimezone($wp_timezone);
            $end_dt->setTimezone($wp_timezone);

            // Get today's date in WP timezone
            $today = new DateTime('today', $wp_timezone);

            // Extract dates for comparison
            $start_date = $start_dt->format('Y-m-d');
            $end_date = $end_dt->format('Y-m-d');
            $today_date = $today->format('Y-m-d');

            // Check if this is a full day (approximately 24 hours)
            $range_duration = $end - $start;
            $is_full_day = abs($range_duration - 86400) < 300; // Within 5 minutes of 24 hours

            // A date range is "today" if:
            // 1. It's approximately a full day (24 hours)
            // 2. When converted to WP timezone, the START date is today
            return $is_full_day && ($start_date == $today_date);
        }

        /**
         * Sanitize term IDs from JSON string
         *
         * @param string $json_string The JSON string containing term IDs
         * @return array|false Array of sanitized term IDs or false on error
         */
        public function sanitize_term_ids($json_string)
        {

            if (empty($json_string)) {
                return false;
            }

            try {
                // Unslash the JSON string before decoding
                $unslashed_json = wp_unslash($json_string);

                // Decode JSON
                $decoded_ids = json_decode($unslashed_json, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return false;
                }

                // Validate we have an array and sanitize to integers
                if (!is_array($decoded_ids)) {
                    return false;
                }

                $sanitized_ids = array();
                foreach ($decoded_ids as $id) {
                    if (is_numeric($id)) {
                        $sanitized_ids[] = intval($id);
                    }
                }

                return !empty($sanitized_ids) ? $sanitized_ids : false;
            } catch (Exception $e) {
                return false;
            }
        }

        /**
         * Save the popular searches filter preference
         */
        public function ajax_save_filter_preference()
        {
            // Verify nonce
            if (!isset($_POST['token']) || !wp_verify_nonce(sanitize_key($_POST['token']), 'wpsi_save_filter_preference')) {
                wp_send_json_error(array('message' => __('Invalid request', 'wp-search-insights')));
                return;
            }

            // Sanitize the filter value
            $filter = isset($_POST['filter']) ? sanitize_text_field( wp_unslash( $_POST['filter'] ) ) : 'with_results';

            // Only allow valid values
            if (!in_array($filter, array('with_results', 'without_results'), true)) {
                $filter = 'with_results';
            }

            // Save as user meta for the current user (better than a global option)
            $user_id = get_current_user_id();
            if ($user_id) {
                update_user_meta($user_id, 'wpsi_popular_filter_preference', $filter);
                wp_send_json_success(array('saved' => true, 'filter' => $filter));
            } else {
                wp_send_json_error(array('message' => __('Invalid request', 'wp-search-insights')));
            }
        }

        /**
         * Generate HTML for popular searches filter controls
         *
         * @return string HTML for filter controls
         */
        public function get_popular_filter_controls()
        {
            // Get the user's preference
            $current_filter = $this->get_popular_filter_preference();

            $controls = '<div class="wpsi-popular-filter">';
            $controls .= '<select id="wpsi-popular-filter-select">';

            // Add options with the correct one selected based on preference
            $controls .= '<option value="with_results"' . selected($current_filter, 'with_results', false) . '>' .
                __("With results", "wp-search-insights") . '</option>';
            $controls .= '<option value="without_results"' . selected($current_filter, 'without_results', false) . '>' .
                __("Without results", "wp-search-insights") . '</option>';

            $controls .= '</select>';
            $controls .= '</div>';

            return $controls;
        }

        /**
         * Get the user's preferred filter for popular searches
         * @return string Filter value ('with_results' or 'without_results')
         */
        public function get_popular_filter_preference()
        {
            $user_id = get_current_user_id();
            if ($user_id) {
                $preference = get_user_meta($user_id, 'wpsi_popular_filter_preference', true);
                if (in_array($preference, array('with_results', 'without_results'), true)) {
                    return $preference;
                }
            }
            return 'with_results'; // Default
        }

        /**
         * @return mixed|null
         *
         * Get optimal batch size
         */
        public function get_batch_size()
        {
            return apply_filters('wpsi_batch_size', 200);
        }

    }
}
