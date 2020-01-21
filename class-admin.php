<?php

defined( 'ABSPATH' ) or die( "you do not have access to this page!" );

if ( ! class_exists( 'WP_Search_Insights_Admin' ) ) {
    class WP_Search_Insights_Admin {

    private static $_this;

    public $capability;

    function __construct()
    {

	    if (isset(self::$_this)) {
            wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.',
                'wp-search-insights'), get_class($this)));
        }

        self::$_this = $this;

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

	    $this->capability = 'manage_options';//get_option('wpsi_select_dashboard_capability');

	    if (!current_user_can($this->capability)) {
            return;
        }

        add_action('admin_init', array($this, 'wpsi_settings_section_and_fields'));
        add_action('admin_menu', array($this, 'add_settings_page'), 40);

	    add_action('admin_init', array($this, 'add_privacy_info'));

	    $plugin = wp_search_insights_plugin;

        add_filter("plugin_action_links_$plugin", array($this, 'plugin_settings_link'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('admin_head', array($this, 'inline_styles'));

        if (current_user_can('manage_options')) {
	        add_action( 'update_option_wpsi_exclude_admin', array( $this, 'redirect_to_settings_tab' ) );
	        add_action( 'update_option_wpsi_min_term_length', array( $this, 'redirect_to_settings_tab' ) );
	        add_action( 'update_option_wpsi_max_term_length', array( $this, 'redirect_to_settings_tab' ) );
	        add_action( 'update_option_wpsi_select_dashboard_capability', array( $this, 'redirect_to_settings_tab' ) );

	        add_action('admin_init', array($this, 'listen_for_clear_database'), 40);
        }

	    add_action('wp_dashboard_setup', array($this, 'add_wpsi_dashboard_widget') );

    }

    public function inline_styles(){
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
	        wp_localize_script( 'search-insights', 'wpsi',
		        array(
			        'ajaxurl' => admin_url( 'admin-ajax.php' ),
			        'token'   => wp_create_nonce( 'search_insights_nonce'),
		        )
            );

            //Datatables javascript for interactive tables
            wp_register_script('datatables',
                trailingslashit(wp_search_insights_url)
                . 'assets/js/datatables.min.js',  array("jquery"), wp_search_insights_version);
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
                . 'assets/js/dataTables.conditionalPaging.js',  array("jquery"), wp_search_insights_version);
            wp_enqueue_script('datatables-pagination');
        }
    }

    /**
     * @param $links
     *
     * Create a settings link to show in plugins overview
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
//	    if (!defined("wpsi_pro_version")) {
//		    if (!class_exists('RSSSL_PRO')) {
//			    $premium_link = '<a target="_blank" href="https://wpsearchinsights.com/downloads/wp-searchinsights-pro/">' . __('Premium Support', 'really-simple-ssl') . '</a>';
//			    array_unshift($links, $premium_link);
//		    }
//	    }
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
        if (!current_user_can('manage_options')) return;

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
            <span class="wpsi-settings-intro-text"><?php  _e('WP Search Insights settings', 'wp-search-insights'); ?></span>
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
                       value="1"  <?php checked(1, get_option('wpsi_exclude_admin'), true) ?> />
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
                    <option value="activate_plugins" <?php if ( get_option('wpsi_select_dashboard_capability') == 'activate_plugins')  echo 'selected="selected"'; ?>><?php _e('Administrators', 'wp-search-insights');?></option>
                    <option value="read" <?php if ( get_option('wpsi_select_dashboard_capability') == 'read') echo 'selected="selected"'; ?>><?php _e('All Users', 'wp-search-insights');?></option>
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
                       value="1"  <?php checked(1, get_option('wpsi_cleardatabase'), true) ?> />
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
            <input id="wpsi_min_term_length" class="wpsi_term_length" name="wpsi_min_term_length" size="40" min="0" max ="24" value="<?php echo intval(get_option('wpsi_min_term_length')) ?>"
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
        <input id="wpsi_max_term_length" class="wpsi_term_length" name="wpsi_max_term_length" size="40" min="0" max ="255" value="<?php echo intval(get_option('wpsi_max_term_length')) ?>"
               type="number" <?php checked(1, intval(get_option('wpsi_max_term_length'), true)) ?> </input>
        <?php
        WP_Search_insights()->wpsi_help->get_help_tip(__("All searches with a count above this value will be ignored. Set to 0 for no limitations.", "wp-search-insights"));
        ?>
        <?php
    }

    public function option_wpsi_clear_database() {

        $args = array(
                'action_label' =>  __( "Clear database", "wp-search-insights" ),
                'title' => __( "Are you sure?", "wp-search-insights" ),
                'description' => __( "Clearing the database deletes all recorded searches. You can create a backup by exporting the tables to either .csv or .xlsx format by pressing the download button beneath the tables.", "wp-search-insights" ),
                'action' => 'clear_database',
        );
        $this->add_thickbox_button($args);
        WP_Search_insights()->wpsi_help->get_help_tip( __( "Pressing this button will delete all recorded searches from your database", "wp-search-insights" ) );
        ?>
        <?php
    }

	    /**
         * Create button with popup using WP core thickbox
	     * @param $args
	     */

    public function add_thickbox_button($args){
	    add_thickbox();

	    $default_args = array(
                "title" => '',
                "action_label" => '',
                "action" => '',
                "description" => '',
        );
        $args = wp_parse_args($args, $default_args);
	    $token      = wp_create_nonce( 'wpsi_thickbox_nonce' );
	    $action_url = add_query_arg(array('page'=>'wpsi-settings-page', 'action'=>$args['action'], 'token'=> $token), admin_url( "tools.php"));

	    ?>
            <div>
                <input class="thickbox button"
                       title="<?php _e( "You're about to clear your database!", "wp-search-insights" ); ?>"
                       type="button" style="display: block;" alt="#TB_inline?height=260&width=450&inlineId=wpsi_<?php echo esc_attr($args['action'])?>"
                       value="<?php echo __( 'Clear database', 'wp-search-insights' ); ?>"/>
            </div>
            <div id="wpsi_<?php echo esc_attr($args['action'])?>" style="display: none;">

            <h1 style="padding-top: 5px;"><?php echo $args["title"]?></h1>
                <p><?php echo $args['description']?></p>
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

            <a class="button" style="height: 25px; line-height: 25px;" href="#" id="wpsi_cancel_<?php echo esc_attr($args['action'])?>">
                <?php _e( "Cancel", "wp-search-insights" ) ?>
            </a>

        </div>
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
        if (!isset($_GET['token']) || (!wp_verify_nonce($_GET['token'], 'wpsi_thickbox_nonce'))) return;
        //check for action
        if (isset($_GET["action"]) && $_GET["action"] == 'clear_database') {
            $this->clear_database_tables();
	        delete_transient('wpsi_popular_searches');
        }
        wp_redirect(admin_url('tools.php?page=wpsi-settings-page'));exit;
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
    <div id="wpsi-dashboard">
    <!--    Navigation-->
    <div class="wp-search-insights-container">
        <ul class="tabs">
            <li class="tab-link current" data-tab="dashboard"><a class="tab-text tab-dashboard" href="#dashboard#top">Dashboard</a></li>
            <?php if (current_user_can('manage_options') ) { ?>
            <li class="tab-link" data-tab="settings"><a class="tab-text tab-settings" href="#settings#top">Settings</a></li>
            <?php } ?>
            <?php echo "<img class='rsp-image' src='" . trailingslashit(wp_search_insights_url) . "assets/images/really-simple-plugins.png' alt='Really Simple plugins'>"; ?>
        </ul>
    </div>
    <div class="wp-search-insights-main">
    <!--    Dashboard tab   -->
        <div id="dashboard" class="tab-content current">
            <div class="search-insights-dashboard">
                <div class="search-insights-most-popular-searches search-insights-table">
                    <div class="search-insights-most-popular search-insights-table">
                       <?php $this->generate_popular_table(); ?>
                    </div>
                </div>

                <div class="search-insights-recent-searches search-insights-table">
		            <?php $this->generate_recent_table(); ?>
                </div>
            </div>
        </div>
<!--    Settings tab    -->
	    <?php if (current_user_can('manage_options') ) { ?>
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
        if (!current_user_can($this->capability)) return;

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

    public function add_wpsi_dashboard_widget() {
        wp_add_dashboard_widget('dashboard_widget_wpsi', 'WP Search Insights', array($this, 'generate_dashboard_widget') ) ;
    }



    public function get_template($file)
    {

        $file = trailingslashit(wp_search_insights_path) . 'templates/' . $file;
        $theme_file = trailingslashit(get_stylesheet_directory()) . dirname(wp_search_insights_path) . $file;

        if (file_exists($theme_file)) {
            $file = $theme_file;
        }

        if (strpos($file, '.php') !== FALSE) {
            ob_start();
            require $file;
            $contents = ob_get_clean();
        } else {
            $contents = file_get_contents($file);
        }

        return $contents;
    }


	public function dashboard_row(){

    }

    public function generate_dashboard_widget( )
    {
        $widget = $this->get_template('dashboard-widget.php');

        $html = "";

        $popular_searches = get_transient('wpsi_popular_searches');
        if (!$popular_searches) {
	        $args = array(
		        'orderby' => 'frequency',
		        'order' => 'DESC',
		        'result_count' => 0,
		        'number' => 5,

	        );
	        $popular_searches = WP_SEARCH_INSIGHTS()->WP_Search_Insights_Search->get_searches( $args, $trend = true, 'MONTH' );
	        set_transient('wpsi_popular_searches', $popular_searches, HOUR_IN_SECONDS);
        }
	    $tmpl = $this->get_template('dashboard-row.html');

	    if (count($popular_searches)==0){
		    $html .= str_replace(array("{icon}", "{link}", "{searches}", "{time}"), array('dashicons-no-alt',__("No recorded searches yet","wp-search-insights"), '', ''), $tmpl);
	    }
        foreach ($popular_searches as $search ){
            if ($search->frequency==$search->previous_frequency){
                $icon = 'dashicons-minus';
            } elseif ($search->frequency>$search->previous_frequency){
                $icon = 'dashicons-arrow-up-alt';
            } else {
                $icon = 'dashicons-arrow-down-alt';
            }
            $time = sprintf(__("%s ago","wp-search-insights"), human_time_diff($search->time, current_time('timestamp')));
            $searches = sprintf( _n( '%s search', '%s searches', $search->frequency, 'wpsi-search-insights' ), number_format_i18n( $search->frequency ) );
            $html .= str_replace(array("{icon}", "{link}", "{searches}", "{time}"), array($icon,$this->get_term_link($search->term), $searches, $time), $tmpl);
        }

        $widget = str_replace('{popular_searches}', $html, $widget);

	    $html = "";
	    $top_searches = get_transient('wpsi_top_searches');
	    if (!$top_searches) {
		    $args = array(
			    'orderby' => 'frequency',
			    'order' => 'DESC',
			    'number' => 5,
		    );
		    $top_searches = WP_SEARCH_INSIGHTS()->WP_Search_Insights_Search->get_searches( $args, $trend = true, 'MONTH' );
		    set_transient('wpsi_top_searches', $top_searches, HOUR_IN_SECONDS);
	    }
	    if (count($top_searches)==0){
		    $html .= str_replace(array("{icon}", "{link}", "{searches}", "{time}"), array('dashicons-no-alt',__("No recorded searches yet","wp-search-insights"), '', ''), $tmpl);
	    }
        foreach ($top_searches as $search ){
            if ($search->frequency==$search->previous_frequency){
                $icon = 'dashicons-minus';
            } elseif ($search->frequency>$search->previous_frequency){
                $icon = 'dashicons-arrow-up-alt';
            } else {
                $icon = 'dashicons-arrow-down-alt';
            }
            $time = sprintf(__("%s ago","wp-search-insights"), human_time_diff($search->time, current_time('timestamp')));

            $searches = sprintf( _n( '%s search', '%s searches', $search->frequency, 'wpsi-search-insights' ), number_format_i18n( $search->frequency ) );
            $html .= str_replace(array("{icon}", "{link}", "{searches}","{time}"), array($icon,$this->get_term_link($search->term), $searches, $time ), $tmpl);
        }

	    $widget = str_replace('{top_searches}', $html, $widget);
        echo $widget;

    }

    /**
    * @param bool $dashboard_widget
    *
    * Generate the recent searches table in dashboard
    *
    * @since 1.0
     *
    */

     public function generate_recent_table($dashboard_widget = false)
     {

         global $wpdb;
         $table_name_single = $wpdb->prefix . 'searchinsights_single';
         $recent_searches = $wpdb->get_results("SELECT * FROM $table_name_single ORDER BY time DESC LIMIT 2000");

         ?>
         <table id="wpsi-recent-table">
         <?php if (!$dashboard_widget) { ?>
         <caption><?php _e("Recent Searches", "wp-search-insights"); } ?>
         </caption>
            <thead>
                <tr class="wpsi-thead-th">
                 <th scope='col' style="width: 25%;"><?php _e("Search term", "wp-search-insights");?> </th>
                 <th scope='col' style="width: 15%;"><?php _e("When", "wp-search-insights");?> </th>
                 <?php if (!$dashboard_widget) { ?>
                 <th scope='col' style="width: 20%;" class="dashboard-tooltip-from"><?php _e("From post/page", "wp-search-insights")?> </th>
                <?php } ?>
                </tr>
            </thead>
            <tbody>
            <?php
            // Start generating rows
            foreach ($recent_searches as $search) {

                // Show the full time on dashboard, shorten the time on the dashboard widget.
                $search_time_td = "<td data-label='When'>".$this->get_date($search->time)."</td>";

	            //Add &searchinsights to 'see results' link to prevent it from counting as search;
	            $link = $this->get_term_link($search->term);
	            $search_term_td = '<td data-label="Term" class="wpsi-term" data-term_id="'.$search->id.'">'.$link.'</td>';
                $referrer_td = "<td>$search->referrer</td>";

                //Generate the row with or without hits and referer, depending on where the table is generated
                echo "<tr>" . $search_term_td . $search_time_td . $referrer_td . "</tr>";

            }
            ?>
            </tbody>
         </table>
        <?php
 }

	    /**
         * Create a link which isn't included in the search results
	     * @param $term
	     *
	     * @return string
	     */

        public function get_term_link($term){
	        $search_url = home_url() . "?s=" . $term . "&searchinsights";
	        return '<a href="'.$search_url.'" target="_blank">'.$term.'</a>';
        }




	    public function get_date($unix)
	    {

            $date = date(get_option('date_format'), $unix);
            $date = $this->localize_date($date);
            $time = date(get_option('time_format'), $unix);
            $date = sprintf(__("%s at %s", 'complianz-gdpr'), $date, $time);

		    return $date;
	    }

	    /**
         * Get translated date
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
    * Generate the popular searches table in
    *
    * @since 1.0
    *
    */

     public function generate_popular_table() {

	     $args = array(
		     'orderby' => 'frequency',
		     'order' => 'DESC',
		     'number' =>1000,
	     );
	     $popular_searches = WP_SEARCH_INSIGHTS()->WP_Search_Insights_Search->get_searches($args);
         ?>
         <button class="button" id="wpsi-delete-selected"><?php _e("Delete selected terms", "wp-search-insights")?></button>
         <table id="wpsi-popular-table"><span class="wpsi-tour-hook wpsi-tour-popular"></span>
             <caption><?php _e('Popular searches', 'wp-search-insights'); ?></caption>

             <thead>
             <tr class="wpsi-thead-th">
                 <?php
                 echo "<th scope='col' style='width: 20%;'>" . __("Term", "wp-search-insights")
                     . "</th>";
                 echo "<th scope='col' style='width: 10%;'>" . __("Count", "wp-search-insights")
                     . "</th>";
                  echo '<th scope="col" style="width: 10%;" class="dashboard-tooltip-hits">'. __("Results", "wp-search-insights").'</th>';

                 ?>
             </tr>
             </thead>
             <tbody>
             <?php

             foreach ($popular_searches as $search) {
	             if ($search->result_count == 0) {
		             // No hits, show an error icon
		             $results = "<i class='hit-icon icon-cancel'></i>";
	             } else {
		             // There are hits, show an checkmark icon. Also make the term clickable to show results
		             $results = "<i class='hit-icon icon-ok'></i>$search->result_count";
	             }

                 echo "<tr>" . '<td class="wpsi-term" data-label="Term" data-term_id="'.$search->id.'">'. $this->get_term_link($search->term)
                     . "</td>" . "<td data-label='Count'>" . $search->frequency
                     . "<td>$results</td>"
                     . "</td>" . "</tr>";
             }

             ?>
             </tbody>
         </table>
        <?php
     }
 }
}//Class closure