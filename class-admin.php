<?php

defined( 'ABSPATH' ) or die( "you do not have access to this page!" );

if ( ! class_exists( 'WP_Search_Insights_Admin' ) ) {
    class WP_Search_Insights_Admin {

    private static $_this;

    public $capability = 'activate_plugins';

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
        if (!current_user_can($this->capability)) {
            return;
        }

        add_action('admin_init', array($this, 'wpsi_settings_section_and_fields'));
        add_action('admin_menu', array($this, 'add_settings_page'), 40);

        $plugin = "wp_search_insights";
        add_filter("plugin_action_links_$plugin", array($this, 'plugin_settings_link'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        add_action('admin_init', array($this, 'listen_for_clear_database'), 40);

        add_action('update_option_wpsi_exclude_admin', array($this, 'redirect_to_settings_tab'));
        add_action('update_option_wpsi_min_term_length', array($this, 'redirect_to_settings_tab'));
        add_action('update_option_wpsi_max_term_length', array($this, 'redirect_to_settings_tab'));

        add_action('wp_dashboard_setup', array($this, 'add_wpsi_dashboard_widget') );

    }

    public function enqueue_assets($hook)
    {
        global $search_insights_settings_page;
        // Enqueue assest when on index.php (WP dashboard) or plugins settings page
        if ($hook == 'index.php' || $hook == $search_insights_settings_page) {

            wp_register_style('search-insights',
                trailingslashit(wp_search_insights_url) . 'assets/css/style.css', "",
                wp_search_insights_version);
            wp_enqueue_style('search-insights');

            wp_register_script('search-insights',
                trailingslashit(wp_search_insights_url)
                . 'assets/js/scripts.js', array("jquery"), wp_search_insights_version);
            wp_enqueue_script('search-insights');

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
     *
     * @since 1.0
     */

    public function plugin_settings_link($links)
    {
        $settings_link = '<a href="tools.php?page=wpsi-settings-page">'
            . _e("Settings", "wp-search-insights") . '</a>';
        array_unshift($links, $settings_link);

        $faq_link
            = '<a target="_blank" href="https://wp-search-insights.com/knowledge-base/">'
            . _e('Docs', 'wp-search-insights') . '</a>';
        array_unshift($links, $faq_link);
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
            __("Search Insights", "wp-search-insights"), //page title
            __("Search Insights", "wp-search-insights"), //submenu title
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
        if (!current_user_can($this->capability)) return;

        // Add a settings section to the 'Settings' tab
        add_settings_section(
            'wpsi-settings-tab',
            __("Settings", "wpsi-search-insights"),
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
            'clear_database',
            __("Clear database", 'wp-search-insights'),
            array($this, 'option_wpsi_clear_database'),
            'wpsi-settings',
            'wpsi-settings-tab'
        );

        // Register our setting so that $_POST handling is done for us and
        // our callback function just has to echo the <input>
        register_setting('wpsi-settings-tab', 'wpsi_exclude_admin');
        register_setting('wpsi-settings-tab', 'wpsi_min_term_length');
        register_setting('wpsi-settings-tab', 'wpsi_max_term_length');

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
        echo "<p>" . __('Configure Search Insights here', 'wp-search-insights')
            . "</p>";
    }

    public function option_wpsi_exclude_admin()
    {
    ?>
        <div class="tg-list-item">
            <input class="tgl tgl-skewed" id="wpsi_exclude_admin" name="wpsi_exclude_admin" size="40" value="1"
                   type="checkbox" <?php checked(1, get_option('wpsi_exclude_admin'), true) ?> </input>
            <label class="tgl-btn" data-tg-off="OFF" data-tg-on="ON" for="wpsi_exclude_admin"></label>
            <?php
            WP_Search_insights()->wpsi_help->get_help_tip(__("With this option enabled all searches of logged in administrators will be ignored", "wp-search-insights"));
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

    public function option_wpsi_clear_database()
    {
    ?>
        <div><input class="thickbox button" title="" type="button" style="display: block; float: left;" alt="#TB_inline?
        height=270&width=400&inlineId=wpsi_clear_database" value="<?php echo __('Clear database', 'wp-search-insights'); ?>"/></div>
        <div id="wpsi_clear_database" style="display: none;">

            <h1 style="margin: 10px 0; text-align: center;"><?php _e("Are you sure?", "wp-search-insights") ?></h1>
            <p><?php _e("Clearing the database deletes all recorded searches. You can create a backup by exporting the tables to either .csv or .xlsx format by pressing the download button beneath the tables." , "wp-search-insights"); ?>"</p>

            <?php
            $token = wp_create_nonce('wpsi_clear_database');
            $clear_db_link = admin_url("tools.php?page=wpsi-settings-page&action=clear_database&token=" . $token);

            ?>
            <a class="button" href="<?php echo $clear_db_link ?>">
                <?php _e("I'm sure I want to clear the database", "wp-search-insights") ?>
            </a>

            <a class="button" href="#" id="wpsi_cancel_database_clearing">
                <?php _e("Cancel", "wp-search-insights") ?>
            </a>

        </div>
        <?php
        WP_Search_insights()->wpsi_help->get_help_tip(__("Pressing this button will delete all recorded searches from your database", "wp-search-insights"));
        ?>
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

        if (!current_user_can($this->capability)) return;

        //check nonce
        if (!isset($_GET['token']) || (!wp_verify_nonce($_GET['token'], 'wpsi_clear_database'))) return;
        //check for action
        if (isset($_GET["action"]) && $_GET["action"] == 'clear_database') {
            $this->clear_database_tables();
        }
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
    <!--    Navigation-->
    <a class="wp-search-insights-container">
    <ul class="tabs">
        <li class="tab-link current" data-tab="dashboard"><a class="tab-text" href="#dashboard">Dashboard</li>
        <li class="tab-link" data-tab="settings"><a class="tab-text tab-settings" href="#settings">Settings</li>
    </ul>
    </a>
    <div class="wp-search-insights-main">
    <!--    Dashboard tab   -->
        <div id="dashboard" class="tab-content current">
            <div class="search-insights-dashboard">
                <div class="search-insights-recent-searches search-insights-table">
                    <?php $this->generate_recent_table(); ?>
                </div>

                <div class="search-insights-most-popular-searches search-insights-table">
                    <div class="search-insights-most-popular search-insights-table">
                       <?php $this->generate_popular_table(); ?>
                    </div>
                </div>
            </div>
        </div>
<!--    Settings tab    -->
        <div id="settings" class="tab-content">
            <form action="options.php" method="post">
                <?php
                settings_fields('wpsi-settings-tab');
                do_settings_sections('wpsi-settings');
                ?>

                <input class="button button-primary" name="Submit"
                       type="submit"
                       value="<?php echo __("Save",
                           "wp-search-insights"); ?>"/>
            </form>
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
            "page" => "wpsi-settings-page#settings",
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
        wp_add_dashboard_widget('dashboard_widget_wpsi', 'Recent Searches', array($this, 'generate_dashboard_widget') ) ;
    }

    public function generate_dashboard_widget( )
    {
    ?>
    <div id="wpsi-dashboard-widget">
    <?php $this->generate_recent_table($dashboard_widget = true); ?>
        <div id="wpsi-dashboard-widget-footer">
            <?php
            $admin_url = admin_url("tools.php?page=wpsi-settings-page");
            echo sprintf(__("%sGo to dashboard%s ", "wp-search-insights"), "<a target='_blank' href='$admin_url'>", '</a>');
            ?>
        </div>
    </div>
    <?php
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
         <table id="search-insights-recent-table">
         <?php if (!$dashboard_widget) { ?>
         <caption><?php _e("Recent Searches", "wp-search-insights"); } ?>
         </caption>
            <thead>
                <tr class="wpsi-thead-th">
                 <th scope='col'><?php _e("Search term", "wp-search-insights");?> </th>
                 <th scope='col'><?php _e("When", "wp-search-insights");?> </th>
                 <?php if (!$dashboard_widget) { ?>
                 <th scope='col' class="dashboard-tooltip-hits"><?php _e("Results", "wp-search-insights")?> </th>
                 <th scope='col' class="dashboard-tooltip-from"><?php _e("From post/page", "wp-search-insights")?> </th>
                <?php } ?>
                </tr>
            </thead>
            <tbody>
            <?php
            // Start generating rows
            foreach ($recent_searches as $search) {

                // Show the full time on dashboard, shorthen the time on the dashboard widget.
                if (!$dashboard_widget) {
                    $search_time_td = "<td data-label='When'>$search->time</td>";
                } else {
                    //Convert SQL timestamp to Unix time
                    $unix_timestamp = strtotime($search->time);
                    //Create a human readable timestamp
                    $time_diff = human_time_diff($unix_timestamp, current_time('timestamp'));
                    $search_time_td = "<td data-label='When'>$time_diff ago</td>";
                }

                if ($search->result_count == 0) {
                    // No hits, show an error icon
                    $results = "<i class='hit-icon icon-cancel'></i>";
                    $search_term_td = "<td data-label='Term'><b> $search->term </b></td>";
                } else {
                    // There are hits, show an checkmark icon. Also make the term clickable to show results
                    $results = "<i class='hit-icon icon-ok'></i>$search->result_count";
                    // Add &searchinsights to 'see results' link to prevent it from counting as search;
                    $search_url = home_url() . "?s=" . $search->term . "&searchinsights";
                if (!$dashboard_widget) {
                     $search_term_td = "<td data-label='Term'><a href='$search_url' id='term-link' target='_blank'><b> $search->term </b></a></td>";
                    } else {
                     $search_term_td = "<td data-label='Term'><b> $search->term </b></td>";
                    }
                }

                // Do not generate the hits and referer in the dashboard widget.
                if(!$dashboard_widget) {
                    $hits_td = "<td>$results</td>";
                    $referer_td = "<td>$search->referer</td>";
                }

                //Generate the row with or without hits and referer, depending on where the table is generated
                if (!$dashboard_widget) {
                    echo "<tr>" . $search_term_td . $search_time_td . $hits_td . $referer_td . "</tr>";
                } else {
                    echo "<tr>" . $search_term_td . $search_time_td . "</tr>";
                }
            }
            ?>
            </tbody>
         </table>
        <?php
 }

    /**
    *
    * Generate the popular searches table in
    *
    * @since 1.0
    *
    */

     public function generate_popular_table() {

        global $wpdb;
        $table_name_archive = $wpdb->prefix . 'searchinsights_archive';
        $popular_searches = $wpdb->get_results("SELECT * FROM $table_name_archive ORDER BY frequency DESC LIMIT 1000");
         ?>

         <table id="search-insights-most-popular-table">
             <caption><?php _e('Popular searches', 'wp-search-insights'); ?></caption>
             <thead>
             <tr class="wpsi-thead-th">
                 <?php
                 echo "<th scope='col'>" . __("Term", "wp-search-insights")
                     . "</th>";
                 echo "<th scope='col'>" . __("Count", "wp-search-insights")
                     . "</th>";
                 ?>
             </tr>
             </thead>
             <tbody>
             <?php

             foreach ($popular_searches as $search) {
                 echo "<tr>" . "<td data-label='Term'>" . "<b>" . $search->term . "</b>"
                     . "</td>" . "<td data-label='Count'>" . $search->frequency
                     . "</td>" . "</tr>";
             }

             ?>
             </tbody>
         </table>
        <?php
     }
 }
}//Class closure