<?php
defined( 'ABSPATH' ) or die( "you do not have access to this page!" );

if ( ! class_exists( 'WPSI_ADMIN' ) ) {
    class WPSI_ADMIN{

        private static $_this;
        public $grid_items;
        public $capability = 'activate_plugins';
        public $tabs;


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

            $is_wpsi_page = isset($_GET['page']) && $_GET['page'] === 'wpsi-settings-page' ? true : false;

            if ($is_wpsi_page) {
                add_action('admin_init', array($this, 'init_grid') );
                add_action('admin_head', array($this, 'inline_styles'));
            }

            add_action('admin_init', array($this, 'add_privacy_info'));

            $plugin = wpsi_plugin;

            add_filter("plugin_action_links_$plugin", array($this, 'plugin_settings_link'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('wp_ajax_wpsi_get_datatable', array($this, 'ajax_get_datatable'));


            if (current_user_can('manage_options')) {
                add_action('update_option_wpsi_exclude_admin', array($this, 'redirect_to_settings_tab'));
                add_action('update_option_wpsi_min_term_length', array($this, 'redirect_to_settings_tab'));
                add_action('update_option_wpsi_max_term_length', array($this, 'redirect_to_settings_tab'));
                add_action('update_option_wpsi_select_dashboard_capability', array($this, 'redirect_to_settings_tab'));
                add_action('admin_init', array($this, 'listen_for_clear_database'), 40);
            }

            add_action('wp_dashboard_setup', array($this, 'add_wpsi_dashboard_widget'));
			add_action('admin_menu', array($this, 'maybe_add_plus_one') );

			add_action('wpsi_on_settings_page', array($this, 'reset_plus_one_ten_searches') );

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
                    'content' => '<div class="wpsi-skeleton"></div>',//$this->recent_table('week'),
                    'class' => 'table-overview',
                    'type' => 'all',
                    'controls' => '<div class="wpsi-date-container"></div>',
                    'can_hide' => true,

                ),
                2 => array(
                    'title' => __("Results", "wp-search-insights"),
                    'content' => '<div class="wpsi-skeleton"></div>',//$this->results_table('week'),
                    'class' => 'small',
                    'type' => 'results',
                    'controls' => '<div class="wpsi-date-container"></div>',
                    'can_hide' => true,

                ),
                3 => array(
                    'title' => __("Most Popular Searches", "wp-search-insights"),
                    'content' => '<div class="wpsi-skeleton"></div>',//$this->generate_dashboard_widget($on_grid=true),
                    'class' => 'small',
                    'type' => 'popular',
                    'controls' => '<div class="wpsi-date-container"></div>',
                    'can_hide' => true,

                ),
                4 => array(
                    'title' => __("Tips & Tricks", "wp-search-insights"),
                    'content' => $this->generate_tips_tricks(),
                    'type' => 'tasks',
                    'class' => 'half-height wpsi-tips-tricks',
                    'can_hide' => true,
                    'controls' => '',
                ),
            5 => array(
                    'title' => $this->get_other_plugins_title(),
                    'content' => $this->generate_other_plugins(),
                    'class' => 'half-height no-border no-background upsell-grid-container ',
                    'type' => 'plugins',
                    'can_hide' => false,
                    'controls' => '',
                ),
            );
        }

        public function get_other_plugins_title() {
            $logo = trailingslashit(wpsi_url) . "assets/images/really-simple-plugins.png";
            $title = "Our Plugins <div class='rsp-logo'><img src='$logo'/></div>";
            return $title;
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
                    trailingslashit(wpsi_url) . "assets/css/style.min.css", "",
                    wp_search_insights_version);
                wp_enqueue_style('search-insights');

                wp_register_script('search-insights',
                    trailingslashit(wpsi_url)
                    . 'assets/js/scripts.js', array("jquery"), wp_search_insights_version);
                wp_enqueue_script('search-insights');
                wp_localize_script('search-insights', 'wpsi',
                    array(
		                'ajaxurl' => admin_url( 'admin-ajax.php' ),
		                'token'   => wp_create_nonce( 'search_insights_nonce'),
		                'dateFilter'   => '<select class="wpsi-date-filter">
                                                <option value="month">'.__("Month", "wp-search-insights").'</option>
                                                <option value="week" selected="selected">'.__("Week", "wp-search-insights").'</option>
                                                <option value="day">'.__("Day", "wp-search-insights").'</option>
                                            </select>',
	                )
                );

                //Datatables javascript for interactive tables
                wp_register_script('datatables',
                    trailingslashit(wpsi_url)
                    . 'assets/js/datatables.min.js', array("jquery"), wp_search_insights_version);
                wp_enqueue_script('datatables');

                // The dashboard widget doesn't use fontello or pagination, return here if we're on the WP dashboard.
                if ($hook == 'index.php') return;

                wp_register_style('fontello',
                    trailingslashit(wpsi_url) . 'assets/font-icons/css/fontello.css', "",
                    wp_search_insights_version);
                wp_enqueue_style('fontello');

                //Datatables plugin to hide pagination when it isn't needed
                wp_register_script('datatables-pagination',
                    trailingslashit(wpsi_url)
                    . 'assets/js/dataTables.conditionalPaging.js', array("jquery"), wp_search_insights_version);
                wp_enqueue_script('datatables-pagination');
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
                = '<a href="https://wpsearchinsights.com/documentation/" target="_blank">'
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
                'wpsi_cleardatabase',
                __("Clear data on plugin uninstall", 'wp-search-insights'),
                array($this, 'option_clear_database_on_uninstall'),
                'wpsi-settings',
                'wpsi-settings-tab'
            );

            add_settings_field(
                'wpsi_clear_database',
                __("Clear database", 'wp-search-insights'),
                array($this, 'option_wpsi_clear_database'),
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


	        add_settings_section(
		        'wpsi-filter-tab',
		        __("", "wpsi-search-insights"),
		        array($this, 'wpsi_settings_tab_intro'),
		        'wpsi-filter'
	        );

	        add_settings_field(
		        'wpsi_filter_textarea',
		        __("Search Filter","wp-search-insights"),
		        array($this, 'option_textarea_filter'),
		        'wpsi-filter',
		        'wpsi-filter-tab'
	        );
            register_setting('wpsi-filter-tab', 'wpsi_filter_textarea');

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

                <?php
                WPSI::$help->get_help_tip(__("With this option enabled all searches of logged in administrators will be ignored", "wp-search-insights"));
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
	        WPSI::$help->get_help_tip(__("Select who can view the dashboard. Choose between administrators and all users", "wp-search-insights"));
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
                WPSI::$help->get_help_tip(__("Enable this option if you want to delete the WP Search Insights database tables when you uninstall the plugin.", "wp-search-insights"));
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
	        WPSI::$help->get_help_tip(__("All searches with a count below this value will be ignored. Set to 0 for no limitations.", "wp-search-insights"));
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
            WPSI::$help->get_help_tip(__("All searches with a count above this value will be ignored. Set to 0 for no limitations.", "wp-search-insights"));
            ?>
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
//	        WPSI::$help->get_help_tip(__("Pressing this button will delete all recorded searches from your database", "wp-search-insights"));
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

        /*





         */

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
         * Clear the transient caches
         */

        public function clear_cache(){
            delete_transient( 'wpsi_popular_searches' );
            delete_transient( 'wpsi_top_searches' );
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
                                    <input class="wpsi-toggle-items" name="toggle_data_id_<?= $index ?>" type="checkbox"
                                           id="toggle_data_id_<?= $index ?>" value="data_id_<?= $index ?>">
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
                                <img class="wpsi-settings-logo wpsi-image" src="<?=trailingslashit(wpsi_url)?>assets/images/logo.png" alt="WP Search Insights logo">
                                 <div class="header-links">
                                    <div class="tab-links">
                                        <?php foreach ($this->tabs as $key => $tab) {
                                            if (isset($tab['capability']) && !current_user_can($tab['capability'])) continue;
	                                        $current = $key=='dashboard' ? 'current' : '';
                                            ?>
                                            <li class="tab-link <?=$current?>" data-tab="<?=$key?>"><a class="tab-text tab-<?=$key?>" href="#<?=$key?>#top"><?=$tab['title']?></a></li>
                                        <?php }?>
                                    </div>
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
	     * Save button for the forms
	     */

        public function save_button($add_border=false) {
            if ($add_border){
	            ?> <div id="clear-searches-btn-border"></div> <?php
            }
            ?>
            <input class="button wpsi-save-button" name="Submit"
                   type="submit"
                   value="<?php echo __("Save",
                       "wp-search-insights"); ?>"/>
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


        public function get_template($file, $path = wpsi_path, $args = array())
        {

            $file = trailingslashit($path) . 'templates/' . $file;
            $theme_file = trailingslashit(get_stylesheet_directory()) . dirname(wpsi_path) . $file;

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
         * @param string $range date range to view
         * @return false|string
         */
        public function generate_dashboard_widget($on_grid = false, $range = 'month')
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

                $popular_searches = WPSI::$search->get_searches($args, $trend = true, 'MONTH');
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
                $top_searches = WPSI::$search->get_searches($args, $trend = true, 'MONTH');
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
				    case 'results':
					    $html = $this->results_table($range);
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

            ?>
            <table id="wpsi-recent-table" class="wpsi-table">

                <thead>
                <tr class="wpsi-thead-th">
                    <th scope='col' style="width: 15%;"><?php _e("Search term", "wp-search-insights"); ?> </th>
                    <th scope='col' style="width: 5%;"><?php _e("Results", "wp-search-insights"); ?> </th>
                    <th scope="col" style="width: 12%;" class="dashboard-tooltip-hits">
                        <?php _e("When", "wp-search-insights"); ?> </th>
                        <th scope='col' style="width: 15%;" class="dashboard-tooltip-from"><?php _e("From", "wp-search-insights") ?> </th>
                </tr>
                </thead>

                <tbody>
                <?php
                // Start generating rows
                $args = array(
                    'number' => 1000,
                    'range' => $range,
                    'result_count' => true,
                );
                $recent_searches = WPSI::$search->get_searches_single($args);
                foreach ($recent_searches as $search) {
                    ?>
                    <tr>
                        <td data-label="Term" class="wpsi-term"
                            data-term_id="<?php echo $search->id ?>"><?php echo $this->get_term_link( $search->term ) ?></td>
                        <td><?php echo $search->result_count ?></td>
                        <td data-label='When'><?php echo $this->get_date( $search->time ) ?></td>
                        <td><?php echo $this->get_referrer_link($search->referrer) ?></td>
                    </tr>
                    <?php

                }
                ?>
                <?php
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

        /**
        * Get referrer link
        * @param $referrer
        *
        * @return string
         */

        public function get_referrer_link($referrer){
            //legacy title search
            $post_id = $this->get_post_by_title($referrer);
            if ($post_id){
                $url = get_permalink($post_id);
                $referrer = get_the_title($post_id);
            } elseif ($referrer === 'home' || $referrer === '' || $referrer === '/') {
                $url = site_url();
                $referrer = __('Home','wp-search-insights');
            } elseif (strpos($referrer, site_url()) === FALSE) {
                $url = site_url( $referrer );
            } else {
                $url = $referrer;
            }

            //make sure the link is not too long
            if (strlen($referrer)>25){
                $referrer = substr($referrer, 0, 22).'...';
            }
            return '<a target="_blank" href="' . esc_url_raw($url) . '" target="_blank">' . esc_html($referrer) . '</a>';
        }

        private function get_post_by_title($title){
			global $wpdb;

			$query = $wpdb->prepare(
				'SELECT ID FROM ' . $wpdb->posts . " WHERE post_title = '%s'",
				sanitize_text_field($title)
			);

			return $wpdb->get_var( $query );
		}


        public function get_date($unix)
        {
            $date = date('Y-m-d', $unix);
            $date = $this->localize_date($date);
            $time = date('H:i', $unix);
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

        public function results_table($range='week')
        {
            ob_start();
	        $results = $this->get_results($range)
            ?>
            <div class="wpsi-nr-overview">

                <div class="wpsi-nr-content">
                    <div class="progress-bar-container">
                        <div class="progress">
                            <div class="bar" style="width:<?php echo $results['results']['percentage']?>%"></div>
                        </div>
                    </div>
                    <div class="progress-text">
                    <?php if ($results['results']['count'] ==! 0) { ?>
                        <span class="percentage"><?php echo $results['results']['percentage'] . "% " ?></span>
                        <span class="percentage-text"><?php _e("of searches have results", "wp-search-insights");?></span>
                    </div>
                    <div class="wpsi-total-searches">
                        <span class="wpsi-nr-title-in-widget"><?php _e("Total Searches", "wp-search-insights"); ?></span>
                        <span class="wpsi-search-count">
                            <?php
                            $args = array(
                                'range'=>$range,
                            );
                            echo count(WPSI::$search->get_searches_single($args));
                            ?>
                        <?php } else { ?>
                            <span class="percentage-text">
                                <?php _e("No searches in selected period", "wp-search-insights");
                                ?>
                            </span>
                            <?php } ?>
                        </span>
                    </div>
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
                                <?php echo  $results['results']['count'] . " ". __("searches", "wp-search-insights"); ?>
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
                                <?php echo  $results['no-results']['count'] . " ". __("searches", "wp-search-insights"); ?>
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
                                    'range' => $range,
                                    'orderby' => 'frequency',
                                    'result_count' => 0,
                                    'compare' => '>',
                                    'number' => 1,
                                    );

                                $top_search = WPSI::$search->get_searches($args);
                                if (!empty($top_search)) {
                                echo $top_search[0]->term;
                                } else {
                                    _e("No result", "wp-search-insights");
                                }
                                ?>
                            </div>
                        </div>
                        <div class="wpsi-result-count">
                        <?php
                            if (!empty($top_search)) {
                            echo  $top_search[0]->frequency . " ". __("searches", "wp-search-insights");
                             } else {
                                 echo "0 ".__("searches", "wp-search-insights");
                             }
                       ?>
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
                                    'range' => $range,
                                    'result_count' => 0,
                                    'orderby' => 'frequency',
                                    'order' => 'DESC',
                                    'number' => 1,
                                );

                                $top_search_no_result = WPSI::$search->get_searches($args);
                                if (!empty($top_search_no_result)) {
                                    echo $top_search_no_result[0]->term;
                                } else {
                                    _e("No result", "wp-search-insights");
                                }
                                ?>
                            </div>
                        </div>
                        <div class="wpsi-result-count">
                            <?php
                             if (!empty($top_search_no_result)) {
                                echo  $top_search_no_result[0]->frequency . " ". __("searches", "wp-search-insights");
                             } else {
                                 echo "0 ".__("searches", "wp-search-insights");
                             }
                             ?>

                        </div>
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
         * @param string $range
         * @return array
         *
         * Get the result count for a period
         */

        public function get_results($range)
        {

            // Get the count of all searches made in period
	        $args = array(
		        'range' => $range,
	        );
            $nr_of_terms = count(WPSI::$search->get_searches_single($args));

            // Set args for query
            $args = array(
	            'range' => $range,
                'result_count' => 0,
                'compare' => '>',
            );

            // Get terms with more than one result
            $have_results = count(WPSI::$search->get_searches($args));
            $no_results = $nr_of_terms - $have_results;
            if ( $have_results == 0 ) {
                $percentage_results = 0;
            } else {
                $percentage_results = $have_results / $nr_of_terms * 100;
            }

            $percentage_no_results = 100 - $percentage_results;

            return array(
                    'results' => array(
                            'percentage' => round($percentage_results,0),
                            'count' => $have_results,
                    ),
                    'no-results' => array(
	                    'percentage' => round($percentage_no_results,0),
	                    'count' => $no_results,
                    ),
                    'total' => $nr_of_terms
            );
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
            $popular_searches = WPSI::$search->get_searches($args);
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

            $plugin_url = trailingslashit(wpsi_url);
            $items = array(
                1 => array(
                    'title' => '<div class="rsssl-yellow upsell-round"></div>',
                    'content' => __("Really Simple SSL - Easily migrate your website to SSL"),
                    'status' => $this->get_rsssl_status(),
                    'class' => 'rsssl',
                    'controls' => '',
                ),
                2 => array(
                    'title' => '<div class="cmplz-blue upsell-round"></div>',
                    'content' => __("Complianz Privacy Suite - Consent Management as it should be ", "wp-search-insights"),
                    'status' => $this->get_cmplz_status(),
                    'class' => 'cmplz',
                    'controls' => '',
                ),
                3 => array(
                    'title' => '<div class="zip-pink upsell-round"></div>',
                    'content' => __("Zip Recipes - Beautiful recipes optimized for Google ", "wp-search-insights"),
                    'status' => $this->get_zip_status(),
                    'class' => 'zip',
                    'controls' => '',
                ),
            );
	        $container = $this->get_template('grid-container.php', wpsi_path . '/grid');

            $element = $this->get_template('upsell-element.php');
            $output = '';
            foreach ($items as $item) {
                $output .= str_replace(array(
                    '{title}',
                    '{content}',
                    '{status}',
                    '{class}',
                    '{controls}',
                ), array(
                    $item['title'],
                    $item['content'],
                    $item['status'],
                    $item['class'],
                    $item['controls'],
                ), $element);
            }
            return str_replace('{content}', '<div class="wpsi-other-plugins-container">'.$output.'</div>', $container);
        }

        public function get_rsssl_status() {
            if (defined('rsssl_plugin') && defined('rsssl_pro_plugin')) {
                $status = "Installed";
            } elseif (defined('rsssl_plugin') && !defined('rsssl_pro_plugin')) {
                $link = "https://really-simple-ssl.com/pro";
                $text = __('Upgrade to pro', 'wp-search-insights');
                $status = "<a href=$link>$text</a>";
            }
            else {
                $link = admin_url() . "plugin-install.php?s=Really+Simple+SSL+Mark+Wolters&tab=search&type=term";
                $text = __('Install', 'wp-search-insights');
                $status = "<a href=$link>$text</a>";
            }
            return $status;
        }

        public function get_cmplz_status() {
            if (defined('cmplz_plugin') && defined('cmplz_premium')) {
                $status = "Installed";
            } elseif (defined('cmplz_plugin') && !defined('cmplz_premium')) {
                $link = "https://complianz.io/pricing";
                $text = __('Upgrade to pro', 'wp-search-insights');
                $status = "<a href=$link>$text</a>";
            } else {
                $link = admin_url() . "plugin-install.php?s=complianz&tab=search&type=term";
                $text = __('Install', 'wp-search-insights');
                $status = "<a href=$link>$text</a>";
            }
            return $status;
        }

        public function get_zip_status() {
            if (defined('ZRDN_PLUGIN_BASENAME')) {
                $status = "Installed";
            } else {
                $link = admin_url() . "plugin-install.php?s=zip+recipes&tab=search&type=term";
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
                    'link'    => 'https://wpsearchinsights.com/writing-content-for-google/',
                ),
                2 => array(
                    'content' => __("WP Search Insights Beginner's Guide", "wp-search-insights"),
                    'link' => 'https://wpsearchinsights.com/wp-search-insights-beginners-guide/',
                ),
                3 => array(
                    'content' => __("Using CSV/Excel Exports", "wp-search-insights"),
                    'link' => 'https://wpsearchinsights.com/using-csv-excel-exports/',
                ),
                4 => array(
                    'content' => __("Improving your Search Result Page", "wp-search-insights"),
                    'link' => 'https://wpsearchinsights.com/improving-your-search-result-page/',
                ),
                5 => array(
                    'content' => __("The Search Filter", "wp-search-insights"),
                    'link' => 'https://wpsearchinsights.com/the-search-filter/',
                ),
                6 => array(
                    'content' => __("Positioning your search form", "wp-search-insights"),
                    'link' => 'https://wpsearchinsights.com/about-search-forms/',
                ),

            );
            $button_link = "https://wpsearchinsights.com/tips-tricks/";
            //$container = $this->get_template('tipstricks-container.php');
	        $container = $this->get_template('grid-container.php', wpsi_path . '/grid');
	        $button =     '<a href="{button_link}" target="_blank"><button class="button button-upsell">'.__("View all" , "wp-search-insights").'</button></a>';
	        $container .= $button;

	        $element = $this->get_template('tipstricks-element.php');
            $output = '';
            foreach ($items as $item) {
                $output .= str_replace(array(
                    '{link}',
                    '{content}',
                ), array(
                    $item['link'],
                    $item['content'],
                ), $element);
            }
            return str_replace(array('{content}' , '{button_link}'), array('<div class="wpsi-tips-tricks-container">'.$output.'</div>', $button_link), $container);
        }
    }
}
