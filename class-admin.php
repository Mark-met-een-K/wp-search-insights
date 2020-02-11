<?php
defined( 'ABSPATH' ) or die( "you do not have access to this page!" );

if ( ! class_exists( 'WPSI_Admin' ) ) {
    class WPSI_Admin{

        private static $_this;
        public $grid_items;
        public $capability = 'activate_plugins';

        function __construct()
        {
            if (isset(self::$_this)) {
                wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.',
                    'wp-search-insights'), get_class($this)));
            }

            self::$_this = $this;

	        $this->grid_items = array(
                1 => array(
                    'title' => __("All Searches", "wp-search-insights"),
                    'content' => $this->recent_table(),
                    'class' => '',
                    'type' => 'all',
                    'can_hide' => true,

                ),
                2 => array(
                    'title' => __("No Results", "wp-search-insights"),
                    'content' => $this->generate_no_results_overview(),
                    'class' => 'small',
                    'type' => 'no-results',
                    'can_hide' => true,

                ),
                3 => array(
                    'title' => __("Most Popular Searches", "wp-search-insights"),
                    'content' => $this->generate_dashboard_widget($on_grid=true),
                    'class' => 'small',
                    'type' => 'popular',
                    'can_hide' => true,

                ),
                4 => array(
                    'title' => __("Tips & Tricks", "wp-search-insights"),
                    'content' => $this->generate_tips_tricks(),
                    'type' => 'tasks',
                    'class' => 'half-height',
                    'can_hide' => true,

                ),
                5 => array(
                    'title' => 'Other',
                    'content' => $this->generate_other_plugins(),
                    'class' => 'half-height no-border',
                    'type' => 'plugins',
                    'can_hide' => false,
                ),
            );
        }

        static function this()
        {
            return self::$_this;
        }

        /**
         * Initializes the admin class
         *
         * @since  1.0
         *
         * @access public
         *
         */

        public function init()
        {
            $this->capability = get_option('wpsi_select_dashboard_capability');

            if (!current_user_can($this->capability)) {
                return;
            }
	        add_action('wp_ajax_wpsi_get_datatable', array($this, 'ajax_get_datatable'));

            add_action('admin_init', array($this, 'wpsi_settings_section_and_fields'));
            add_action('admin_menu', array($this, 'add_settings_page'), 40);

            add_action('admin_init', array($this, 'add_privacy_info'));

            $plugin = wp_search_insights_plugin;

            add_filter("plugin_action_links_$plugin", array($this, 'plugin_settings_link'));

            add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('admin_head', array($this, 'inline_styles'));

            if (current_user_can('manage_options')) {
                add_action('update_option_wpsi_exclude_admin', array($this, 'redirect_to_settings_tab'));
                add_action('update_option_wpsi_min_term_length', array($this, 'redirect_to_settings_tab'));
                add_action('update_option_wpsi_max_term_length', array($this, 'redirect_to_settings_tab'));
                add_action('update_option_wpsi_select_dashboard_capability', array($this, 'redirect_to_settings_tab'));

                add_action('admin_init', array($this, 'listen_for_clear_database'), 40);
            }

            add_action('wp_dashboard_setup', array($this, 'add_wpsi_dashboard_widget'));

        }

        public function inline_styles()
        {
            ?>
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

                wp_register_style('search-insights',
                    trailingslashit(wp_search_insights_url) . "assets/css/style.min.css", "",
                    wp_search_insights_version);
                wp_enqueue_style('search-insights');

                wp_register_script('search-insights',
                    trailingslashit(wp_search_insights_url)
                    . 'assets/js/scripts.js', array("jquery"), wp_search_insights_version);
                wp_enqueue_script('search-insights');
                wp_localize_script('search-insights', 'wpsi',
                    array(
		                'ajaxurl' => admin_url( 'admin-ajax.php' ),
		                'token'   => wp_create_nonce( 'search_insights_nonce'),
		                'dateFilter'   => '<select class="wpsi-date-filter">
                                                <option value="all">'.__("All time", "wp-search-insights").'</option>
                                                <option value="year">'.__("Year", "wp-search-insights").'</option>
                                                <option value="week">'.__("Week", "wp-search-insights").'</option>
                                                <option value="day">'.__("Day", "wp-search-insights").'</option>
                                            </select>',
	                )
                );

                //Datatables javascript for interactive tables
                wp_register_script('datatables',
                    trailingslashit(wp_search_insights_url)
                    . 'assets/js/datatables.min.js', array("jquery"), wp_search_insights_version);
                wp_enqueue_script('datatables');

                // The dashboard widget doesn't use fontello or pagination, return here if we're on the WP dashboard.
                if ($hook == 'index.php') return;

                wp_register_style('fontello',
                    trailingslashit(wp_search_insights_url) . 'assets/font-icons/css/fontello.css', "",
                    wp_search_insights_version);
                wp_enqueue_style('fontello');

                //Datatables plugin to hide pagination when it isn't needed
                wp_register_script('datatables-pagination',
                    trailingslashit(wp_search_insights_url)
                    . 'assets/js/dataTables.conditionalPaging.js', array("jquery"), wp_search_insights_version);
                wp_enqueue_script('datatables-pagination');
            }
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
                = '<a target="_blank" href=" https://wpsearchinsights.com/documentation/">'
                . __('Docs', 'wp-search-insights') . '</a>';
            array_unshift($links, $faq_link);

            return $links;
        }

        /**
         *
         * Add a settings page
         *
         * @since 1.0
         *
         */

        public function add_settings_page()
        {
            if (!current_user_can($this->capability)) {
                return;
            }

            global $search_insights_settings_page;

            $search_insights_settings_page = add_submenu_page(
                'tools.php',
                "WP Search Insights",
                "Search Insights",
                $this->capability, //capability
                'wpsi-settings-page', //url
                array($this, 'settings_page')); //function
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
                __("", "wpsi-search-insights"),
                array($this, 'wpsi_settings_tab_intro'),
                'wpsi-settings'
            );

            // Add the field with the names and function to use for our new
            // settings, put it in our new section
            add_settings_field(
                'exclude_admin_searches',
                __("Exclude admin searches", 'wp-search-insights'),
                array($this, 'option_wpsi_exclude_admin'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );


            add_settings_field(
                'min_search_length',
                __("Exclude searches shorter than characters", 'wp-search-insights'),
                array($this, 'option_min_term_length'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'max_search_length',
                __("Exclude searches longer than characters", 'wp-search-insights'),
                array($this, 'option_max_term_length'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'wpsi_select_dashboard_capability',
                __("Who can view the dashboard", 'wp-search-insights'),
                array($this, 'option_wpsi_select_dashboard_capability'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'wpsi_filter_textarea',
                __("Search term filter", 'wp-search-insights'),
                array($this, 'option_textarea_filter'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'clear_database',
                __("Clear database", 'wp-search-insights'),
                array($this, 'option_wpsi_clear_database'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'wpsi_cleardatabase',
                __("Clear data on plugin uninstall", 'wp-search-insights'),
                array($this, 'option_clear_database_on_uninstall'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            // Register our setting so that $_POST handling is done for us and
            // our callback function just has to echo the <input>
            register_setting('wpsi-settings-tab', 'wpsi_exclude_admin');
            register_setting('wpsi-settings-tab', 'wpsi_cleardatabase');
            register_setting('wpsi-settings-tab', 'wpsi_min_term_length');
            register_setting('wpsi-settings-tab', 'wpsi_max_term_length');
            register_setting('wpsi-settings-tab', 'wpsi_select_dashboard_capability');
            register_setting('wpsi-settings-tab', 'wpsi_filter_textarea');

        }

        public function add_privacy_info()
        {
            if (!function_exists('wp_add_privacy_policy_content')) {
                return;
            }

            $content = sprintf(
                __('WP Search Insights does not process any personal identifiable information, so the GDPR does not apply to these plugins or usage of these plugins on your website. You can find our privacy policy <a href="%s" target="_blank">here</a>.', 'wp-search-insights'),
                'https://wpsearchinsights.com/privacy-statement/'
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

        public function wpsi_settings_tab_intro()
        {
            ?>
            <div class="wpsi-settings-intro">
                <span class="wpsi-settings-logo"><i class="icon-cog-alt"></i></span>
                <span class="wpsi-settings-intro-text"><?php _e('WP Search Insights settings', 'wp-search-insights'); ?></span>
            </div>
            <?php
        }

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

                <?php
                WP_Search_insights()->wpsi_help->get_help_tip(__("With this option enabled all searches of logged in administrators will be ignored", "wp-search-insights"));
                ?>
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
            WP_Search_insights()->wpsi_help->get_help_tip(__("Select who can view the dashboard. Choose between administrators and all users", "wp-search-insights"));
            ?>
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

                <?php
                WP_Search_insights()->wpsi_help->get_help_tip(__("Enable this option if you want to delete the WP Search Insights database tables when you uninstall the plugin.", "wp-search-insights"));
                ?>
            </div>
            <?php
        }

        public function option_min_term_length()
        {
            ?>
            <input id="wpsi_min_term_length" class="wpsi_term_length" name="wpsi_min_term_length" size="40" min="0"
                   max="24" value="<?php echo intval(get_option('wpsi_min_term_length')) ?>"
                   type="number" <?php checked(1, intval(get_option('wpsi_min_term_length'), true)) ?> </input>
            <?php
            WP_Search_insights()->wpsi_help->get_help_tip(__("All searches with a count below this value will be ignored. Set to 0 for no limitations.", "wp-search-insights"));
            ?>
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
                   type="number" <?php checked(1, intval(get_option('wpsi_max_term_length'), true)) ?> </input>
            <?php
            WP_Search_insights()->wpsi_help->get_help_tip(__("All searches with a count above this value will be ignored. Set to 0 for no limitations.", "wp-search-insights"));
            ?>
            <?php
        }

        public function option_wpsi_clear_database()
        {

            $args = array(
                'action_label' => __("Clear database", "wp-search-insights"),
                'title' => __("Are you sure?", "wp-search-insights"),
                'description' => __("Clearing the database deletes all recorded searches. You can create a backup by exporting the tables to either .csv or .xlsx format by pressing the download button beneath the tables.", "wp-search-insights"),
                'action' => 'clear_database',
            );
            $this->add_thickbox_button($args);
            WP_Search_insights()->wpsi_help->get_help_tip(__("Pressing this button will delete all recorded searches from your database", "wp-search-insights"));
            ?>
            <?php
        }


        /**
         * Create button with popup using WP core thickbox
         * @param $args
         */

        public function add_thickbox_button($args)
        {
            add_thickbox();

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
                       value="<?php echo __('Clear database', 'wp-search-insights'); ?>"/>
            </div>
            <div id="wpsi_<?php echo esc_attr($args['action']) ?>" style="display: none;">

                <h1 style="padding-top: 5px;"><?php echo $args["title"] ?></h1>
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
            <textarea name="wpsi_filter_textarea" rows="3" cols="40" id="wpsi_filter_textarea">
            <?php
            echo
            esc_html(get_option('wpsi_filter_textarea'));
            ?>
        </textarea>
            <?php
            WP_Search_insights()->wpsi_help->get_help_tip(__("Exclude words, sentences or URL's. Seperate each search term with whitespace or a comma", "wp-search-insights"));
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
            if (!isset($_GET['token']) || (!wp_verify_nonce($_GET['token'], 'wpsi_clear_database'))) {
                return;
            }
            //check for action
            if (isset($_GET["action"]) && $_GET["action"] == 'clear_database') {
                $this->clear_database_tables();
                $this->clear_cache();
            }
            wp_redirect(admin_url('tools.php?page=wpsi-settings-page'));
            exit;
        }

        /**
         * Clear the transient caches
         */

        public function clear_cache()
        {
            delete_transient('wpsi_popular_searches');
            delete_transient('wpsi_top_searches');
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

            ?>
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
                                    <input class="wpsi-toggle-items" name="toggle_data_id_<?= $index ?>" type="checkbox"
                                           id="toggle_data_id_<?= $index ?>" value="data_id_<?= $index ?>">
                                    <?= $grid_item['title'] ?>
                                </label>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                    <div id="wpsi-toggle-options">
                        <div id="wpsi-toggle-link-wrap">
                            <button type="button" id="wpsi-show-toggles" class="button show-settings"
                                    aria-controls="screen-options-wrap"><?php _e("Screen options", "wp-search-insights"); ?>
                                <span id="wpsi-toggle-arrows" class="dashicons dashicons-arrow-down"></span></button>
                        </div>
                    </div>
                </div>
                <div id="wpsi-dashboard">

                    <!--    Navigation-->
                    <div class="wp-search-insights-container">
                        <ul class="tabs">
                            <li class="tab-link current" data-tab="dashboard"><a class="tab-text tab-dashboard"
                                                                                 href="#dashboard#top">Dashboard</a>
                            </li>
                            <?php if (current_user_can('manage_options')) { ?>
                                <li class="tab-link" data-tab="settings"><a class="tab-text tab-settings"
                                                                            href="#settings#top">Settings</a></li>
                            <?php } ?>
                            <!--						--><?php //echo "<img class='rsp-image' src='" . trailingslashit( wp_search_insights_url ) . "assets/images/really-simple-plugins.png' alt='Really Simple plugins'>"; ?>
                        </ul>
                    </div>
                    <div class="wp-search-insights-main">
                        <!--    Dashboard tab   -->
                        <div id="dashboard" class="tab-content current">
                            <div class="wpsi-settings-intro">
                                <img class="wpsi-settings-logo"><?php echo "<img class='wpsi-image' src='" . trailingslashit(wp_search_insights_url) . "assets/images/noname_logo.png' alt='WP Search Insights logo'>"; ?></img></span>
                                <span class="wpsi-settings-intro-text"><?php _e('WP Search Insights', 'wp-search-insights') ?></span>
                            </div>
                            <button class="button"
                                    id="wpsi-delete-selected"><?php _e("Delete selected terms", "wp-search-insights") ?></button>

                            <?php
                            //get html of block
                            $grid_items = $this->grid_items;
                            $container = $this->get_template('grid-container.php', wp_search_insights_path . '/grid');
                            $element = $this->get_template('grid-element.php', wp_search_insights_path . '/grid');
                            $output = '';
                            foreach ($grid_items as $index => $grid_item) {
                                $output .= str_replace(array('{class}', '{content}', '{index}', '{type}'), array($grid_item['class'], $grid_item['content'], $index, $grid_item['type']), $element);
                            }
                            echo str_replace('{content}', $output, $container);
                            ?>

                        </div>
                        <!--    Settings tab    -->
                        <?php if (current_user_can('manage_options')) { ?>
                            <div id="settings" class="tab-content">
                                <div>
                                    <form action="options.php" method="post">
                                        <?php
                                        settings_fields('wpsi-settings-tab');
                                        do_settings_sections('wpsi-settings');
                                        ?>

                                        <input class="button button-primary wpsi-save-button" name="Submit"
                                               type="submit"
                                               value="<?php echo __("Save",
                                                   "wp-search-insights"); ?>"/>
                                    </form>
                                </div>
                            </div>
                        <?php } ?>
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
            $url = add_query_arg(array(
                "page" => "wpsi-settings-page#settings#top",
            ), admin_url("tools.php"));
            wp_safe_redirect($url);
            exit;
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


        public function get_template($file, $path = wp_search_insights_path, $args = array())
        {

            $file = trailingslashit($path) . 'templates/' . $file;
            $theme_file = trailingslashit(get_stylesheet_directory()) . dirname(wp_search_insights_path) . $file;

            if (file_exists($theme_file)) {
                $file = $theme_file;
            }

            if (strpos($file, '.php') !== false) {
                ob_start();
                require $file;
                $contents = ob_get_clean();
            } else {
                $contents = file_get_contents($file);
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
         * @param bool $echo true if on wp dashboard
         * @param bool $on_grid true if on grid
         * @param string $range date range to view
         * @return false|string
         */
        public function generate_dashboard_widget($on_grid = false, $range = 'all')
        {
            ob_start();

            if (!$on_grid) {
                $widget = $this->get_template('dashboard-widget.php');
            } else {
                $widget = $this->get_template('grid-dashboard-widget.php');
            }

            $html = "";

            //only use cached data on dash
            $popular_searches = get_transient("wpsi_popular_searches_$range");
            if ($on_grid) $popular_searches = false;
            if (!$popular_searches) {
                $args = array(
                    'orderby' => 'frequency',
                    'order' => 'DESC',
                    'result_count' => 0,
                    'number' => 5,
                    'range' => $range,
                );
                $popular_searches = WP_SEARCH_INSIGHTS()->Search->get_searches($args, $trend = true, 'MONTH');
                set_transient("wpsi_popular_searches_$range", $popular_searches, HOUR_IN_SECONDS);
            }

            if (!$on_grid) {
                $tmpl = $this->get_template('dashboard-row.php');
            } else {
                $tmpl = $this->get_template('grid-dashboard-row.php');
            }
            if (count($popular_searches) == 0) {
                $html .= str_replace(array("{icon}", "{link}", "{searches}", "{time}"), array(
                    'dashicons-no-alt',
                    __("No recorded searches yet", "wp-search-insights"),
                    '',
                    ''
                ), $tmpl);
            }
            foreach ($popular_searches as $search) {
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
                    $this->get_term_link($search->term),
                    $searches,
                    $time
                ), $tmpl);
            }

            $widget = str_replace('{popular_searches}', $html, $widget);

            $html = "";
            $top_searches = get_transient("wpsi_top_searches_$range");
	        if ($on_grid) $top_searches = false;

	        if (!$top_searches) {
                $args = array(
                    'orderby' => 'frequency',
                    'order' => 'DESC',
                    'number' => 5,
                    'range' => $range,
                );
                $top_searches = WP_SEARCH_INSIGHTS()->Search->get_searches($args, $trend = true, 'MONTH');
                set_transient("wpsi_top_searches_$range", $top_searches, HOUR_IN_SECONDS);
            }
            if (count($top_searches) == 0) {
                $html .= str_replace(array("{icon}", "{link}", "{searches}", "{time}"), array(
                    'dashicons-no-alt',
                    __("No recorded searches yet", "wp-search-insights"),
                    '',
                    ''
                ), $tmpl);
            }
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
                    $this->get_term_link($search->term),
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
		    $html  = __("No data found", "wp-search-insights");
		    if (!current_user_can('manage_options')) {
			    $error = true;
		    }

		    if (!isset($_GET['range'])){
			    $error = true;
		    }

		    if (!isset($_GET['type'])){
			    $error = true;
		    }

		    if (!isset($_GET['token'])){
			    $error = true;
		    }

		    if (!$error && !wp_verify_nonce(sanitize_title($_GET['token']), 'search_insights_nonce')){
			    $error = true;
		    }

		    if (!$error){
			    $range = sanitize_title($_GET['range']);
			    $type = sanitize_title($_GET['type']);
			    switch ($type){
                    case 'all':
	                    $html = $this->recent_table($range);
	                    break;
                    case 'popular':
	                    $html = $this->generate_dashboard_widget(true, $range);
	                    break;
                    default:
                        $html = __('Invalid command','wp-search-insights');
                        break;
			    }
		    }

		    $data = array(
			    'success' => !$error,
			    'html' => $html,
		    );

		    $response = json_encode($data);
		    header("Content-Type: application/json");
		    echo $response;
		    exit;
	    }

        /**
         * @param bool $dashboard_widget
         *
         * Generate the recent searches table in dashboard
         *
         * @return string
         * @since 1.0
         */

        public function recent_table($range = 'all')
        {
            ob_start();

            $args = array(
                'number' => 2000,
                'range' => $range,
            );
            $recent_searches = WP_SEARCH_INSIGHTS()->Search->get_searches_single($args);
            ?>
            <table id="wpsi-recent-table" class="wpsi-table">
                <caption><?php _e("All Searches", "wp-search-insights"); ?></caption>
                <thead>
                <tr class="wpsi-thead-th">
                    <th scope='col' style="width: 15%;"><?php _e("Search term", "wp-search-insights"); ?> </th>
                    <th scope='col' style="width: 10%;"><?php _e("Results", "wp-search-insights"); ?> </th>
                    <th scope="col" style="width: 13%;" class="dashboard-tooltip-hits">
                        ' <?php _e("When", "wp-search-insights"); ?> </th>
                        <th scope='col' style="width: 10%;" class="dashboard-tooltip-from"><?php _e("From", "wp-search-insights") ?> </th>
                </tr>
                </thead>

                <tbody>
                <?php
                // Start generating rows
                foreach ($recent_searches as $search) {
	                $args = array(
		                'term'  => $search->term,
	                );
	                $result = WP_SEARCH_INSIGHTS()->Search->get_searches($args);
	                if ($result) {
		                ?>
                        <tr>
                            <td data-label="Term" class="wpsi-term"
                                data-term_id="<?php echo $search->id ?>"><?php echo $this->get_term_link( $search->term ) ?></td>
                            <td><?php echo $result->result_count ?></td>
                            <td data-label='When'><?php $this->get_date( $search->time ) ?></td>
                            <td><?php echo $search->referrer ?></td>
                        </tr>
		                <?php
	                }
                }
                ?>
                </tbody>
            </table>
            <?php
            return  ob_get_clean();
        }

        /**
         * Create a link which isn't included in the search results
         *
         * @param $term
         *
         * @return string
         */

        public function get_term_link($term)
        {
            $search_url = home_url() . "?s=" . $term . "&searchinsights";
            return '<a href="' . $search_url . '" target="_blank">' . $term . '</a>';
        }


        public function get_date($unix)
        {

            $date = date(get_option('date_format'), $unix);
            $date = $this->localize_date($date);
            $time = date(get_option('time_format'), $unix);
            $date = sprintf(__("%s at %s", 'wp-search-insights'), $date, $time);

            return $date;
        }

        /**
         * Get translated date
         *
         * @param $date
         *
         * @return mixed
         */
        public function localize_date($date)
        {
            $month = date('F', strtotime($date)); //june
            $month_localized = __($month); //juni
            $date = str_replace($month, $month_localized, $date);
            $weekday = date('l', strtotime($date)); //wednesday
            $weekday_localized = __($weekday); //woensdag
            $date = str_replace($weekday, $weekday_localized, $date);

            return $date;
        }

        /**
         *
         * Generate the no results overview in dashboard
         *
         * @return string
         * @since 1.2
         */

        public function generate_no_results_overview()
        {
            ob_start();
            ?>
            <div class="wpsi-nr-overview">
                <div class="wpsi-nr-header">
                    <div class="wpsi-nr-header-items">
                        <div class="wpsi-no-results">
                            <span class="wpsi-nr-title"><?php _e("No Results", "wp-search-insights"); ?></span>
                        </div>
                        <div class="wpsi-total-searches">
                            <span class="wpsi-nr-title"><?php _e("Total Searches", "wp-search-insights"); ?></span>
                            <span class="wpsi-search-count wpsi-header-right">
                            <?php
                            echo count(WP_SEARCH_INSIGHTS()->Search->get_searches_single());
                            ?>
                        </span>
                        </div>
                    </div>
                </div>
                <div class="wpsi-nr-content">
                    <div class="wpsi-nr-progress">

                    </div>
                    <div class="wpsi-nr-has-result">
                        <div class="has-result-title">
                            <?php _e("Have results", "wp-search-insights"); ?>
                        </div>
                        <div class="wpsi-result-count">
                            <?php echo $this->get_result_count() . "%"; ?>
                        </div>
                    </div>
                    <div class="wpsi-nr-no-result">
                        <div class="no-result-title">
                            <?php _e("No results", "wp-search-insights"); ?>
                        </div>
                        <div class="wpsi-result-count">
                            <?php echo $this->get_result_count($without_results = true) . "%"; ?>
                        </div>
                    </div>
                </div>
                <div class="wpsi-nr-footer">
                    <span class="wpsi-export-searches"></span>
                </div>
            </div>
            <?php
            $contents = ob_get_clean();
            return $contents;
        }

        /**
         * @param bool $without_results
         * @return float|int
         *
         * Get the result count for a period
         */

        public function get_result_count($without_results = false)
        {

            // Get the count of all searches made in period
            $nr_of_terms = count(WP_SEARCH_INSIGHTS()->Search->get_searches_single());

            // Set args for query
            $args = array(
                'compare' => '>',
                'from' => 'result_count',
                'result_count' => 0,
            );

            // Get terms with more than one result
            //
//        $result_count = $wpdb->get_results("SELECT frequency FROM $table_name_archive WHERE result_count >= 1");

            $frequency = WP_SEARCH_INSIGHTS()->Search->get_searches();
//        SELECT * from wp_searchinsights_archive WHERE 1=1 AND result_count >=1 AND frequency >=1
            $term_more_than_1_result = count(WP_SEARCH_INSIGHTS()->Search->get_searches($args));

            $percentage_results = $term_more_than_1_result / $nr_of_terms * 100;
            $percentage_no_results = 100 - $percentage_results;
//
            if ($without_results) {
                return $percentage_no_results;
            } else {
                return $percentage_results;
            }
        }


        /**
         *
         * Generate the popular searches table in
         *
         * @return string
         * @since 1.0
         */

        public function generate_popular_table()
        {
            ob_start();

            $args = array(
                'orderby' => 'frequency',
                'order' => 'DESC',
                'number' => 1000,
            );
            $popular_searches = WP_SEARCH_INSIGHTS()->Search->get_searches($args);
            ?>
            <table id="wpsi-popular-table" class="wpsi-table"><span class="wpsi-tour-hook wpsi-tour-popular"></span>
                <div class="wpsi-caption">
                    <caption><?php _e('Popular searches', 'wp-search-insights'); ?></caption>
                </div>

                <thead>
                <tr class="wpsi-thead-th">
                    <?php
                    echo "<th scope='col' style='width: 20%;'>" . __("Term", "wp-search-insights")
                        . "</th>";
                    echo "<th scope='col' style='width: 10%;'>" . __("Count", "wp-search-insights")
                        . "</th>";
                    ?>
                </tr>
                </thead>
                <tbody>
                <?php

                foreach ($popular_searches as $search) {
                    echo "<tr>" . '<td class="wpsi-term" data-label="Term" data-term_id="' . $search->id . '">' . $this->get_term_link($search->term)
                        . "</td>" . "<td data-label='Count'>" . $search->frequency
                        . "</td>" . "</tr>";
                }

                ?>
                </tbody>
            </table>
            <?php

            $contents = ob_get_clean();
            return $contents;
        }

        public function generate_other_plugins()
        {
            $items = array(
                1 => array(
                    'title' => __("Really Simple SSL", "wp-search-insights"),
                    'content' => __("Get Really Simple SSL Now", "wp-search-insights"),
                    'link' => admin_url() . "plugin-install.php?s=really+simple+ssl&tab=search&type=term"
                ),
                2 => array(
                    'title' => __("CMPLZ", "wp-search-insights"),
                    'content' => __("get Complianz now", "wp-search-insights"),
                    'link' => admin_url() . "plugin-install.php?s=complianz&tab=search&type=term"
                ),
            );

            $container = $this->get_template('upsell-container.php');
            $element = $this->get_template('upsell-element.php');
            $output = '';
            foreach ($items as $item) {
                $output .= str_replace(array(
                    '{title}',
                    '{content}',
                    '{link}',
                ), array(
                    $item['title'],
                    $item['content'],
                    $item['link'],
                ), $element);

            }
            return str_replace('{content}', $output, $container);
        }

        public function generate_tips_tricks()
        {
            $items = array(
                1 => array(
                    'title' => __("How to use wpsi", "wp-search-insights"),
                    'content' => __("lorem", "wp-search-insights"),
                ),
                2 => array(
                    'title' => __("From page", "wp-search-insights"),
                    'content' => __("lorem", "wp-search-insights"),
                ),
            );
            $container = $this->get_template('tipstricks-container.php');
            $element = $this->get_template('tipstricks-element.php');
            $output = '';
            foreach ($items as $item) {
                $output .= str_replace(array(
                    '{title}',
                    '{content}'
                ), array(
                    $item['title'],
                    $item['content'],
                ), $element);
            }
            return str_replace('{content}', $output, $container);
        }
    }
}