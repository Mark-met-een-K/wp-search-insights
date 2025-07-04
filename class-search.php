<?php

defined('ABSPATH') or die("you do not have access to this page!");

if (!class_exists('Search')) {
    class Search
    {

        private static $_this;
        public $filtered_terms = array(
            '{search_term_string}'
        );

        private $allowed_orderby = array(
            'id',
            'term',
            'time',
            'referrer',
            'referrer_id',
            'landing_page',
            'landing_time'
        );

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

            add_action('template_redirect', array($this, 'get_regular_search'));

            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('wp_ajax_nopriv_wpsi_process_search', array($this, 'get_ajax_search'));
            add_action('wp_ajax_wpsi_process_search', array($this, 'get_ajax_search'));
            add_action('wp_ajax_wpsi_delete_terms', array($this, 'ajax_delete_terms'));
            add_action('wp_ajax_wpsi_ignore_terms', array($this, 'ajax_ignore_terms'));
            add_action('init', array($this, 'get_custom_search'));

            // Store landing page
            add_action('wp_ajax_wpsi_store_landing_page', array($this, 'ajax_store_landing_page'));
            add_action('wp_ajax_nopriv_wpsi_store_landing_page', array($this, 'ajax_store_landing_page'));

        }

        static function this()
        {
            return self::$_this;
        }

        /**
         * Delete array of terms using ajax
         */

        public function ajax_delete_terms()
        {
            $error = false;

            if (!$error && (!isset($_POST['token']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['token'])), 'wpsi_delete_terms'))) {
                $error = true;
            }

            if (!current_user_can('manage_options')) {
                $error = true;
            }

            if (!isset($_POST['term_ids'])) {
                $error = true;
            }

            if (!isset($_POST['token'])) {
                $error = true;
            }

            if (!$error) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Proper sanitization handled in sanitize_term_ids() function
                $term_ids = WPSI::$admin->sanitize_term_ids($_POST['term_ids']);
                if ($term_ids === false) {
                    $error = true;
                } else {
                    foreach ($term_ids as $term_id) {
                        $this->delete_term($term_id);
                    }
                }
            }

            $data = array(
                'success' => !$error,
            );

            $response = json_encode($data, JSON_THROW_ON_ERROR);
            header("Content-Type: application/json");
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $response;
            exit;
        }

        /**
         * Ignore array of terms using ajax
         */

        public function ajax_ignore_terms()
        {
            $error = false;

            if (!$error && (!isset($_POST['token']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['token'])), 'wpsi_ignore_terms'))) {
                $error = true;
            }

            if (!current_user_can('manage_options')) {
                $error = true;
            }

            if (!isset($_POST['term_ids'])) {
                $error = true;
            }

            if (!isset($_POST['token'])) {
                $error = true;
            }

            // Process term IDs if no error so far
            if (!$error) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Proper sanitization handled in sanitize_term_ids() function
                $term_ids = WPSI::$admin->sanitize_term_ids($_POST['term_ids']);
                if ($term_ids === false) {
                    $error = true;
                } else {
                    foreach ($term_ids as $term_id) {
                        $this->ignore_term($term_id);
                    }
                }
            }

            $data = array(
                'success' => !$error,
            );

            $response = json_encode($data);
            header("Content-Type: application/json");
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $response;
            exit;
        }


        /**
         * Delete term by id
         * @param int $term_id
         */

        public function delete_term($term_id)
        {

            if (!current_user_can('manage_options')) {
                return;
            }

            global $wpdb;

            //get the term, so we can also remove it from the single table
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables require direct queries. Caching unnecessary for deletion operations that modify data immediately.
            $term_single = $wpdb->get_var($wpdb->prepare("select term from {$wpdb->prefix}searchinsights_single where id=%s", intval($term_id)));

            if ($term_single) {
                // Get the frequency of term
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables require direct queries. Count needed for deletion logic.
                $count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}searchinsights_single WHERE term = %s",
                        $term_single
                    )
                );
                // Substract 1 since we deleted this entry
                $count -= 1;
                // Delete from archive if count is 0
                if ($count === intval(0)) {
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables require direct queries. Deletion operations don't benefit from caching.
                    $wpdb->delete(
                        $wpdb->prefix . 'searchinsights_archive',
                        array('term' => $term_single)
                    );
                } else {
                    // Update the frequency in archive table
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables require direct queries. Update operations don't benefit from caching.
                    $wpdb->update(
                        $wpdb->prefix . 'searchinsights_archive',
                        array('frequency' => intval($count)),
                        array('term' => $term_single)
                    );
                }
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables require direct queries. Deletion operations don't benefit from caching.
            $wpdb->delete(
                $wpdb->prefix . 'searchinsights_single',
                array('id' => intval($term_id))
            );

            WPSI::$admin->clear_cache();
        }

        /**
         * Ignore term
         * Add to filter, delete all references from single/archive table
         * @param int $term_id
         * @since 1.3.7
         */

        public function ignore_term($term_id)
        {

            if (!current_user_can('manage_options')) return;

            global $wpdb;
            //get the term, so we can also remove it from the single table
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables require direct queries. One-time lookup for term doesn't benefit from caching.
            $term_single = $wpdb->get_var($wpdb->prepare("select term from {$wpdb->prefix}searchinsights_single where id=%s", intval($term_id)));

            // Add to filtered terms
            $filter = get_option('wpsi_filter_textarea');

            // Do not add , when the current filter ends with it
            if (!$filter) {
                $filter = $term_single;
            }

            if (strpos($filter, $term_single) === false) {
                if ($this->endsWith($filter, ',')) {
                    $filter = $filter . " " . $term_single;
                } else {
                    $filter = $filter . ", " . $term_single;
                }
            }

            // Update the filter option
            update_option('wpsi_filter_textarea', $filter);

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables require direct queries. Deletion operations don't benefit from caching.
            $wpdb->delete(
                $wpdb->prefix . 'searchinsights_single',
                array('term' => $term_single)
            );

            if ($term_single) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom plugin tables require direct queries. Deletion operations don't benefit from caching.
                $wpdb->delete(
                    $wpdb->prefix . 'searchinsights_archive',
                    array('term' => sanitize_text_field($term_single))
                );
            }

            WPSI::$admin->clear_cache();
        }

        public function endsWith($haystack, $needle)
        {
            $length = strlen($needle);
            if ($length == 0) {
                return true;
            }

            return (substr($haystack, -$length) === $needle);
        }

        public function startsWith($haystack, $needle)
        {
            $length = strlen($needle);
            return (substr($haystack, 0, $length) === $needle);
        }

        public function enqueue_assets()
        {
            // AJAX search tracking (only loaded when option is enabled)
            if (get_option('wpsi_track_ajax_searches')) {
                wp_register_script('search-insights-frontend-js',
                    trailingslashit(wpsi_url) . "assets/js/frontend-v2.js",
                    array('jquery'),
                    wpsi_version,
                    true);
                wp_enqueue_script('search-insights-frontend-js');
                wp_localize_script('search-insights-frontend-js', 'search_insights_ajax',
                    array(
                        'ajaxurl' => admin_url('admin-ajax.php'),
                        'token' => wp_create_nonce('wpsi_process_search'),
                    )
                );
            }

            // Landing page tracking (always loaded)
            wp_register_script('wpsi-search-navigation',
                trailingslashit(wpsi_url) . 'assets/js/search-navigation.js',
                array('jquery'),
                wpsi_version,
                true
            );
            wp_enqueue_script('wpsi-search-navigation');
            wp_localize_script('wpsi-search-navigation', 'wpsi_search_navigation', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'token' => wp_create_nonce('wpsi_store_landing_page'),
            ));
        }

        /**
         *
         * Get the search term from query
         *
         * @since 1.0
         *
         */

        public function get_regular_search() {
            global $wp_query;

            if ( is_search() ) {
                //Get the search term
                $search_term = get_search_query();

                // Generate search id
                $search_id = $this->generate_uuid();

                // Get result count and permalinks
                $result_count = $wp_query->found_posts;
                $permalinks = $this->get_search_result_permalinks($wp_query);

                // Set cookies
                $this->set_search_cookies($search_term, $result_count, $search_id, $permalinks);

                // Process term and count, add additional information and write to DB
                $this->process_search_term($search_term, $result_count, '', $search_id);
            }
        }

        /**
         * Get search with a custom search parameter
         */

        public function get_custom_search() {
            // Get custom search parameter
            $custom_search_parameter = sanitize_title(get_option('wpsi_custom_search_parameter'));

            // Skip if custom param is 's' as it's handled by get_regular_search
            if ($custom_search_parameter === 's') {
                return;
            }

            $caller = $this->get_caller_by_search_parameter($custom_search_parameter);

            // Get search ID from cookie or generate new one
            $search_id = isset($_COOKIE['wpsi_search_id'])
                ? sanitize_text_field(wp_unslash($_COOKIE['wpsi_search_id']))
                : $this->generate_uuid();

            // Check if the parameter exists in GET request
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simply checking URL parameters, not processing form data
            if (strlen($custom_search_parameter) > 0 && isset($_GET[$custom_search_parameter])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simply checking URL parameters, not processing form data
                $search_term = sanitize_text_field(wp_unslash($_GET[$custom_search_parameter]));

                // Get result count
                $args = array(
                    'posts_per_page' => -1,
                    'post_status' => 'publish',
                    'offset' => 0,
                    's' => $search_term,
                );
                $posts = get_posts($args);
                $result_count = count($posts);

                // Get permalinks (empty array for custom searches)
                $permalinks = array();

                // Set cookies
                $this->set_search_cookies($search_term, $result_count, $search_id, $permalinks);

                // Process term
                $this->process_search_term($search_term, $result_count, $caller, $search_id);
            }
        }

        /**
         * Get caller of search term by parameter
         * @param string $search_parameter
         *
         * @return mixed|void
         */

        public function get_caller_by_search_parameter($search_parameter)
        {
            return apply_filters('wpsi_get_caller_by_search_parameter', 'wordpress', $search_parameter);
        }

        /**
         *
         * Listen for an AJAX post containing a search term
         *
         * @since 1.0
         *
         */

        public function get_ajax_search() {

            // Check and verify nonce
            if (!isset($_POST['token']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['token'])), 'wpsi_process_search')) {
                wp_send_json_error(array('message' => __('Invalid request', 'wp-search-insights')));
            }

            if (isset($_POST['searchterm'])) {
                // Properly unslash before sanitizing
                $search_term = sanitize_text_field(wp_unslash($_POST['searchterm']));

                // Create or use provided search ID
                $search_id = '';
                if (isset($_POST['search_id']) && !empty($_POST['search_id'])) {
                    $search_id = sanitize_text_field(wp_unslash($_POST['search_id']));
                } else {
                    $search_id = $this->generate_uuid();
                }

                // Get result count
                $args = array('s' => $search_term);
                $search_query = new WP_Query($args);
                $result_count = $search_query->found_posts;
                $permalinks = $this->get_search_result_permalinks($search_query);

                // Set cookies
                $this->set_search_cookies($search_term, $result_count, $search_id, $permalinks);

                // Process the search term
                $this->process_search_term($search_term, $result_count, '', $search_id);

                // Send response
                wp_send_json_success(array(
                    'success' => true,
                    'result_count' => $result_count
                ));
            }
        }


        /**
         * @param string $search_term
         * @param int $result_count
         * @param string $caller
         *
         * Check if conditions are met, if so write the term to DB
         *
         * @since 1.0
         *
         */

        public function process_search_term($search_term, $result_count, $caller = '', $search_id = '')
        {
            //Exclude empty search queries
            if (strlen($search_term) === 0) {
                return;
            }

            // Skip terms exceeding configured limit
            if (strlen($search_term) > 255) {
                return;
            }

            /**
             * allow skipping this search term
             */

            if (!apply_filters('wpsi_process_search_term', true, $search_term, $result_count)) {
                return;
            }

            /**
             * allow manipulation of search term
             */

            $search_term = apply_filters('wpsi_search_term', $search_term, $result_count);

            $filtered_terms = get_option('wpsi_filter_textarea');

            // Remove commas from option
            $filtered_terms = str_replace(',,', ',', $filtered_terms);
            if (!empty($filtered_terms)) {
                $filtered_terms = explode(",", $filtered_terms);

                // Check if search term should be filtered
                foreach ($filtered_terms as $term) {

                    // Trim
                    $term = trim(strtolower($term));
                    $search_term = trim(strtolower($search_term));

                    if ($term === $search_term) {
                        return;
                    }
                }
            }

            // Return if the query comes from an administrator and the exclude admin searches option is been enabled
            if (in_array('administrator', wp_get_current_user()->roles) && get_option('wpsi_exclude_admin')) {
                return;
            }

            // Get the query arg. Use esc_url_raw instead of esc_url because it doesn't decode &
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simply checking URL parameters, not processing form data
            $current_url = esc_url_raw(home_url(add_query_arg($_GET)));
            // When clicking on 'see results' in dashboard, &searchinsights will be added to the url. If this is found current url, return.
            if (strpos($current_url, "&searchinsights") !== false) {
                return;
            }

            // Check if the term length is below minimum value option
            if ((strlen($search_term) < ((int)get_option('wpsi_min_term_length'))) && ((int)get_option('wpsi_min_term_length') !== 0)) {
                return;
            }

            if (strlen($search_term) > (int)get_option('wpsi_max_term_length') && (int)get_option('wpsi_max_term_length') !== 0) {
                return;
            }

            if (in_array($search_term, $this->filtered_terms)) {
                return;
            }

            do_action('wpsi_after_search_tracking', $search_term);

            // Generate a UUID if none provided
            if (empty($search_id)) {
                $search_id = $this->generate_uuid();
            }

            $this->write_terms_to_db($search_term, $result_count, $search_id);

        }

        /**
         * @param string $search_term
         * @param int $result_count
         *
         *  Write search term to both tables
         *
         * @since 1.0
         *
         */

        public function write_terms_to_db($search_term, $result_count, $search_id = '')
        {

            global $wpdb;
            //check if this search was written with five seconds ago
            $replace_search = false;

            $search_term = $this->sanitize_search_term($search_term);

            // Don't process empty terms (after sanitization)
            if (empty($search_term)) {
                return;
            }

            $old_search_term = $search_term;
            $now = $this->get_utc_timestamp();
            $last_search = $this->get_last_search_term();

            //check if the last search is a recent search
            $ten_seconds_ago = $now - 10;
            $last_search_is_recent = $last_search && $last_search->time > $ten_seconds_ago;

            //this search is the same as the previous one, which is recent, ignore.
            if ($last_search && $last_search_is_recent && $last_search->term === $search_term) {
                return;
            }

            //last search is part of new search, overwrite existing entries with new search term
            if ($last_search && $last_search_is_recent && strpos($search_term, $last_search->term) !== FALSE) {
                $replace_search = $last_search;
                $old_search_term = $last_search->term;
            }

            // Write the search term to the single database which contains each query
            // if replace_search is passed, this term will be updated
            $this->write_search_term_to_single_table($search_term, $replace_search, $search_id);

            // Check if search term exists in the archive database, if it does update the term count. Create a new entry otherwise
            $table_name_archive = esc_sql($wpdb->prefix . 'searchinsights_archive');
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery -- Table name is properly escaped with esc_sql(). Custom plugin tables require direct queries. These existence checks are used immediately in conditional logic and don't benefit from caching.
            $old_term_in_database = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name_archive WHERE term = %s", $old_search_term));
            $old_term_exists = $old_term_in_database && $wpdb->num_rows > 0;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery -- Table name is properly escaped with esc_sql(). Custom plugin tables require direct queries. These existence checks are used immediately in conditional logic and don't benefit from caching.
            $current_term_in_database = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name_archive WHERE term = %s", $search_term));
            $current_term_exists = $current_term_in_database && $wpdb->num_rows > 0;

            if ($old_term_exists || $current_term_exists) {
                // Exists, update the count in archive
                // if it's one character different, update only term and result count, not frequency
                if ($old_search_term && ($search_term !== $old_search_term)) {
                    $this->replace_term($old_search_term, $search_term, $result_count);
                } else {
                    $this->update_term_count($search_term, $result_count);
                }
            } else {
                // Doesn't exist, write a new entry to archive
                $this->write_search_term_to_archive_table($search_term, $result_count);
            }
        }


        /**
         * Sanitize search term while preserving legitimate searches
         *
         * @param string $search_term The search term to sanitize
         * @return string Sanitized search term
         *
         * All queries use ->prepare already, added for defense in depth
         */
        private function sanitize_search_term($search_term)
        {
            // First apply WordPress sanitization
            $search_term = sanitize_text_field($search_term);

            // Don't process empty terms
            if (empty($search_term) || trim($search_term) === '') {
                return '';
            }

            // Store the sanitized term before SQL pattern matching
            $before_sql_sanitization = $search_term;

            // Apply SQL injection pattern matching

            // Pattern 1: Quote followed by semicolon - common SQL injection technique
            $search_term = preg_replace("/['\"`];/", "", $search_term);
            // Pattern 2: Quote/semicolon followed by SQL keywords with flexible whitespace
            $search_term = preg_replace("/['\"`;]\s*(DROP|DELETE|UPDATE|INSERT|SELECT|UNION|ALTER)/i", "", $search_term);
            // Pattern 3: Common SQL comment markers used to terminate queries
            $search_term = preg_replace("/(--|\/\*|\*\/)/", "", $search_term);
            // Pattern 4: Only remove # if it's at the beginning of a statement or after whitespace (likely SQL comment)
            $search_term = preg_replace("/(^|\\s)#/", "", $search_term);

            // If SQL pattern matching changed the term, it likely contained malicious patterns
            // In this case, we exclude it entirely
            if ($search_term !== $before_sql_sanitization) {
                return '';
            }

            return $search_term;
        }

        /**
         * Get the last term that has been added to the list.
         * @return object $row
         */

        public function get_last_search_term()
        {
            global $wpdb;
            $table_name_single = esc_sql($wpdb->prefix . 'searchinsights_single');
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is properly escaped with esc_sql()
            $sql = $wpdb->prepare("SELECT * FROM {$table_name_single} ORDER BY ID DESC LIMIT %d", 1);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- SQL is prepared above, direct query required for custom plugin table, caching unnecessary for latest search term which changes frequently
            $row = $wpdb->get_row($sql);
            if (!$row) return false;
            return $row;
        }

        /**
         * @param $search_term
         *
         * Update term count in archive table
         *
         * @since 1.0
         *
         */

        public function update_term_count($search_term, $result_count)
        {
            if (!get_option('wpsi_database_created')) return;
            global $wpdb;

            $table_name_archive = esc_sql($wpdb->prefix . 'searchinsights_archive');
            //Have to use query on INT because $wpdb->update assumes string.
            $result_count = (int)$result_count;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching  -- Table name is properly escaped with esc_sql()
            $wpdb->query($wpdb->prepare("UPDATE $table_name_archive SET frequency = frequency +1, result_count=%s, time=%s WHERE term = %s", $result_count, $this->get_utc_timestamp(), sanitize_text_field($search_term)));
        }

        /**
         * @param $search_term
         * @param $new_term
         * @param $result_count
         *
         * Update term count in archive table
         *
         * @since 1.0
         *
         */

        public function replace_term($search_term, $new_term, $result_count)
        {
            if (!get_option('wpsi_database_created')) return;

            global $wpdb;
            $new_term = sanitize_text_field($new_term);
            $search_term = sanitize_text_field($search_term);
            $table_name_archive = esc_sql($wpdb->prefix . 'searchinsights_archive');

            // First, decrease the count for the old term
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required for custom plugin table, caching unnecessary for update operations
            $wpdb->query($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is properly escaped with esc_sql()
            "UPDATE $table_name_archive SET frequency = frequency - 1 WHERE term = %s",
                $search_term
            ));

            // Remove the old term if its frequency is now 0
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required for custom plugin table, caching unnecessary for deletion operations
            $wpdb->query($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is properly escaped with esc_sql()
            "DELETE FROM $table_name_archive WHERE term = %s AND frequency <= 0",
                $search_term
            ));

            // Now increment or create the new term
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required for custom plugin table, result used immediately for conditional logic
            $current_term_in_database = $wpdb->get_results($wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is properly escaped with esc_sql()
            "SELECT * FROM $table_name_archive WHERE term = %s",
                $new_term
            ));

            if ($current_term_in_database && $wpdb->num_rows > 0) {
                // Term exists, update it
                $this->update_term_count($new_term, $result_count);
            } else {
                // Term doesn't exist, create it
                $this->write_search_term_to_archive_table($new_term, $result_count);
            }
        }

        /**
         * @param $args
         * @param $trend
         *
         * Get searches
         */
        public function get_searches($args = array(), $trend = false)
        {

            $defaults = array(
                'orderby' => 'frequency',
                'order' => 'DESC',
                'result_count' => false,
                'number' => -1,
                'offset' => 0,
                'term' => false,
                'compare' => false,
                'from' => "*",
                'range' => false,
                'count' => false,
                'date_from' => false,
                'date_to' => false,
            );
            $args = wp_parse_args($args, $defaults);

            // Handle range parameter by converting it to date_from/date_to
            if ($args['range'] && $args['range'] !== 'all') {
                switch ($args['range']) {
                    case 'day':
                        $range = time() - DAY_IN_SECONDS;
                        break;
                    case 'week':
                        $range = time() - WEEK_IN_SECONDS;
                        break;
                    case 'year':
                        $range = time() - YEAR_IN_SECONDS;
                        break;
                    case 'month':
                        $range = time() - MONTH_IN_SECONDS;
                        break;
                    default:
                        $range = time() - MONTH_IN_SECONDS;
                }
                $args['date_from'] = $range;
                $args['date_to'] = time();
            }

            global $wpdb;
            $table_name_archive = esc_sql($wpdb->prefix . 'searchinsights_archive');
            $table_name_single = esc_sql($wpdb->prefix . 'searchinsights_single');

            $limit = '';
            if ($args['number'] != -1) {
                $count = absint($args['number']);
                if ($args['offset'] > 0) {
                    // Use both LIMIT and OFFSET when offset is specified
                    $limit = $wpdb->prepare("LIMIT %d OFFSET %d", $count, absint($args['offset']));
                } else {
                    // Just use LIMIT when no offset
                    $limit = $wpdb->prepare("LIMIT %d", $count);
                }
            }

            // Validate order parameter
            $order = $args['order'] === 'ASC' ? 'ASC' : 'DESC';

            // Validate orderby against whitelist of allowed columns
            $orderby = in_array($args['orderby'], $this->allowed_orderby) ? $args['orderby'] : 'frequency';

            $where = '';

            if ($args['term']) {
                $where .= $wpdb->prepare(' AND term = %s ', sanitize_text_field($args['term']));
            }

            if ($args['result_count'] !== FALSE) {
                $where .= " AND result_count ";

                // Validate compare operator against whitelist
                $allowed_compare = array('=', '>', '<', '>=', '<=', '<>');
                if ($args['compare'] && in_array($args['compare'], $allowed_compare)) {
                    $where .= $args['compare'];
                } else {
                    $where .= "=";
                }

                $where .= $wpdb->prepare('%d', intval($args['result_count']));
            }

            // Check if we need to filter by date range
            $use_single_table = ($args['date_from'] && $args['date_to']);

            // Track whether we need to apply trend calculation to results
            $apply_trend = ($trend === true);

            // Set up trend calculation period if needed (do this before any queries)
            $last_period_start = null;
            $last_period_end = null;

            if ($apply_trend) {
                if ($args['date_from'] && $args['date_to']) {
                    $period = absint($args['date_to']) - absint($args['date_from']);
                    $last_period_start = absint($args['date_from']) - $period;
                    $last_period_end = absint($args['date_from']);
                } else if ($args['range']) {
                    $period = sanitize_text_field($args['range']);
                    $last_period_start = strtotime("-2 $period");
                    $last_period_end = strtotime("-1 $period");
                }
            }

            // Now process queries based on which table to use
            if ($use_single_table) {
                // For date-filtered searches, we'll use a different approach
                // First, get the terms and their counts from the single table
                $date_where = '';
                if ($args['date_from']) {
                    $from = absint($args['date_from']);
                    $date_where .= $wpdb->prepare(" AND s.time > %d ", $from);  // Add 's.' prefix
                }
                if ($args['date_to']) {
                    $to = absint($args['date_to']);
                    $date_where .= $wpdb->prepare(" AND s.time < %d ", $to);   // Add 's.' prefix
                }

                // Build the archive join query - we need to join on the archive table
                $archive_join = '';
                $archive_where = '';

                if ($args['result_count'] !== FALSE) {
                    $archive_join = " LEFT JOIN $table_name_archive ar ON s.term = ar.term ";
                    $archive_where = " AND ar.result_count ";

                    // Validate compare operator against whitelist
                    $allowed_compare = array('=', '>', '<', '>=', '<=', '<>');
                    if ($args['compare'] && in_array($args['compare'], $allowed_compare)) {
                        $archive_where .= $args['compare'];
                    } else {
                        $archive_where .= "=";
                    }

                    $archive_where .= $wpdb->prepare('%d', intval($args['result_count']));
                }

                // Get terms with their frequency in the date range, with result_count filter
                $term_counts_sql = "SELECT s.term, COUNT(*) as date_frequency
           FROM $table_name_single s
           $archive_join
           WHERE 1=1 $date_where $archive_where
           GROUP BY s.term
           ORDER BY date_frequency $order
           $limit";

                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- search part of $term_counts_sql has been sanitized and prepared, direct query required for search analytics on custom tables, results used immediately for display
                $term_counts = $wpdb->get_results($term_counts_sql);

                if (empty($term_counts)) {
                    return [];
                }

                // Extract terms to fetch their archive data
                $terms = [];
                foreach ($term_counts as $row) {
                    $terms[] = $row->term;
                }

                if (empty($terms)) {
                    return [];
                }

                $placeholders = implode(',', array_fill(0, count($terms), '%s'));

                // Build query components with specific comments for each part
                $select_part = "SELECT a.*";
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is properly escaped with esc_sql()
                $from_part = " FROM {$table_name_archive} a";
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated list of %s tokens
                $where_part = " WHERE a.term IN ($placeholders)";
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated list of %s tokens
                $order_part = " ORDER BY FIELD(a.term, $placeholders)";

                // Combine all parts into the final query
                $search_sql = $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- query parts are prepared above
                    $select_part . $from_part . $where_part . $order_part,
                    // Merge the two arrays of arguments - one set for IN clause, one set for FIELD function
                    array_merge($terms, $terms)
                );

                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prepared above, direct query required for search analytics on custom tables, results used immediately for user display
                $results = $wpdb->get_results($search_sql);

                // Map the date-specific frequency to the results
                $frequency_map = [];
                foreach ($term_counts as $term_count) {
                    $frequency_map[$term_count->term] = $term_count->date_frequency;
                }

                // Replace the frequency with the date-specific one
                foreach ($results as $result) {
                    if (isset($frequency_map[$result->term])) {
                        $result->frequency = $frequency_map[$result->term];
                    }
                }

                // Sort by the new frequency
                usort($results, function ($a, $b) use ($order) {
                    if ($order === 'ASC') {
                        return $a->frequency - $b->frequency;
                    }

                    return $b->frequency - $a->frequency;
                });

                // Limit results if needed
                if ($args['number'] !== -1 && count($results) > $args['number']) {
                    $results = array_slice($results, 0, $args['number']);
                }

                $searches = $results;
            } else {
                // For non-date-filtered searches, use the original query
                $date_where = '';
                if ($args['date_from']) {
                    $from = absint($args['date_from']);
                    $date_where .= $wpdb->prepare(" AND time > %d ", $from);
                }
                if ($args['date_to']) {
                    $to = absint($args['date_to']);
                    $date_where .= $wpdb->prepare(" AND time < %d ", $to);
                }

                $where .= $date_where;

                // Validate 'from' parameter against SQL injection
                $from_clause = '*'; // Default
                if ($args['from'] !== '*') {
                    // Whitelist approach for columns that can be selected
                    $allowed_columns = array('id', 'term', 'result_count', 'time', 'frequency');
                    $requested_columns = explode(',', $args['from']);
                    $safe_columns = array();

                    foreach ($requested_columns as $column) {
                        $column = trim($column);
                        if (in_array($column, $allowed_columns)) {
                            $safe_columns[] = "`$column`";
                        }
                    }

                    if (!empty($safe_columns)) {
                        $from_clause = implode(', ', $safe_columns);
                    }
                }

                $search_sql = "SELECT $from_clause from $table_name_archive WHERE 1=1 $where ORDER BY $orderby $order $limit";

                if ($args['count']) {
                    $search_sql = str_replace(" $from_clause ", " count(*) as count ", $search_sql);
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prepared above, direct query required for custom analytics tables, count result used immediately
                    $searches = $wpdb->get_var($search_sql);
                } else {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prepared above, direct query required for custom analytics tables, results used immediately for display
                    $searches = $wpdb->get_results($search_sql);
                }
            }

            // Apply trend calculation to results AFTER we've retrieved them,
            // regardless of which table path we took
            if ($apply_trend && !$args['count'] && is_array($searches)) {
                // Only apply trend if we have actual search results that are an array

                // Get terms for previous period
                $trend_terms = [];
                foreach ($searches as $search) {
                    if (isset($search->term)) {
                        $trend_terms[] = $search->term;
                    }
                }

                if (!empty($trend_terms)) {
                    // Get the previous frequencies for these terms
                    $placeholders = implode(',', array_fill(0, count($trend_terms), '%s'));
                    $trend_where = $wpdb->prepare(" time > %d AND time < %d ", $last_period_start, $last_period_end);

                    // Create the SQL query parameters
                    $trend_params = array_merge($trend_terms);

                    // Build query components with specific comments for each part
                    $query_select = "SELECT term, COUNT(*) as previous_frequency";
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is properly escaped with esc_sql()
                    $query_from = " FROM {$table_name_single}";
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders is a generated list of %s tokens, $trend_where is prepared above
                    $query_where = " WHERE term IN ($placeholders) AND $trend_where";
                    $query_group = " GROUP BY term";

                    // Combine all parts into the final query
                    $previous_period_sql = $wpdb->prepare(
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- query parts are prepared above, direct query required for trend analysis on custom tables, results used immediately for comparison
                        $query_select . $query_from . $query_where . $query_group,
                        $trend_params
                    );

                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- prepared above, direct query required for trend analysis on custom tables, results used immediately for calculations
                    $previous_frequencies = $wpdb->get_results($previous_period_sql);

                    // Create lookup array
                    $prev_freq_map = [];
                    foreach ($previous_frequencies as $prev) {
                        $prev_freq_map[$prev->term] = $prev->previous_frequency;
                    }

                    // Add previous_frequency to each search result
                    foreach ($searches as $search) {
                        if (isset($search->term)) {
                            $search->previous_frequency = isset($prev_freq_map[$search->term]) ?
                                $prev_freq_map[$search->term] : 0;
                        }
                    }
                }
            }

            // If we searched for a term, there is only one result
            if ($args['term']) {
                if (isset($searches[0])) {
                    $searches = $searches[0];
                } else {
                    $searches = false;
                }
            }

            return $searches;
        }

        /**
         * Get searches from single table with optimized queries
         * @param array $args
         * @return array|int $searches
         */
        public function get_searches_single($args = array())
        {
            $defaults = array(
                'number' => -1,
                'order' => 'DESC',
                'orderby' => 'time',
                'term' => false,
                'compare' => ">",
                'range' => false,
                'result_count' => false,
                'offset' => 0,
                'count' => false,
                'date_from' => false,
                'date_to' => false,
            );
            $args = wp_parse_args($args, $defaults);

            // Handle range conversion
            if ($args['range'] && $args['range'] !== 'all') {
                // Range conversion code remains unchanged
                switch ($args['range']) {
                    case 'day':
                        $range = time() - DAY_IN_SECONDS;
                        break;
                    case 'week':
                        $range = time() - WEEK_IN_SECONDS;
                        break;
                    case 'year':
                        $range = time() - YEAR_IN_SECONDS;
                        break;
                    case 'month':
                        $range = time() - MONTH_IN_SECONDS;
                        break;
                    default:
                        $range = time() - MONTH_IN_SECONDS;
                }
                $args['date_from'] = $range;
                $args['date_to'] = time();
            }

            global $wpdb;
            $table_name = esc_sql($wpdb->prefix . 'searchinsights_single');
            $archive_table = esc_sql($wpdb->prefix . 'searchinsights_archive');

            // Pagination parameters
            $limit = '';
            if ($args['number'] != -1) {
                $count = absint($args['number']); // Use absint instead of intval for positive integers
                if ($count > 0) {
                    if ($args['offset'] > 0) {
                        // Include offset when specified
                        $limit = $wpdb->prepare("LIMIT %d OFFSET %d", $count, absint($args['offset']));
                    } else {
                        // Just use LIMIT when no offset
                        $limit = $wpdb->prepare("LIMIT %d", $count);
                    }
                }
            }

            // Order parameters - validate against whitelist of allowed columns
            $orderby = in_array($args['orderby'], $this->allowed_orderby) ? $args['orderby'] : 'time';
            $order = $args['order'] === 'ASC' ? 'ASC' : 'DESC';

            // WHERE clause conditions with proper preparation
            $where = '';
            if ($args['term']) {
                $where .= $wpdb->prepare(' AND term = %s ', sanitize_text_field($args['term']));
            }

            if ($args['date_from']) {
                $from = absint($args['date_from']);
                $where .= $wpdb->prepare(" AND time > %d ", $from);
            }

            if ($args['date_to']) {
                $to = absint($args['date_to']);
                $where .= $wpdb->prepare(" AND time < %d ", $to);
            }

            // Column selection with whitelist validation
            $allowed_columns = array('id', 'time', 'term', 'referrer', 'referrer_id', 'landing_page', 'is_conversion', 'landing_time', 'is_internal', 'search_id');

            // Count-only query
            if ($args['count']) {
                $count_sql = "SELECT COUNT(*) FROM $table_name WHERE 1=1" . $where;

                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $where is already properly prepared above, direct query required for search analytics count, result used immediately
                $count = $wpdb->get_var($count_sql);
                return $count;
            }

            $columns_string = implode(', ', $allowed_columns);
            // Build and prepare the main query
            $search_sql = "SELECT $columns_string FROM $table_name WHERE 1=1 $where ORDER BY $orderby $order $limit";

            // Handle result_count parameter - optimize JOIN
            if ($args['result_count']) {
                // More efficient JOIN syntax with proper escaping
                $search_sql = "SELECT s.*, a.result_count, a.frequency
              FROM ($search_sql) as s
              LEFT JOIN $archive_table as a
              ON s.term = a.term
              ORDER BY s.$orderby $order";
            }

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- at this point the entire query has been sanitized and prepared, direct query required for custom search analytics tables, results used immediately for display
            $searches = $wpdb->get_results($search_sql);

            // If we're looking for a specific term, return just that result
            if ($args['term'] && isset($searches[0])) {
                $searches = $searches[0];
            }

            return $searches;
        }

        /**
         * @param $search_term
         * @param $replace_search
         *
         * Write search term to single table. Any additional information such as time and referer is added here too
         *
         * @since 1.0
         *
         */

        public function write_search_term_to_single_table($search_term, $replace_search = false, $search_id = '')
        {
            if (!get_option('wpsi_database_created')) return;

            global $wpdb;
            $table_name_single = esc_sql($wpdb->prefix . 'searchinsights_single');
            $referrer = $this->get_referer();

            $update_args = array(
                'term' => sanitize_text_field($search_term),
                'referrer' => sanitize_text_field($referrer['url']),//we can't use esc_url, because it also may be "home"
                'referrer_id' => intval($referrer['post_id']),
                'time' => $this->get_utc_timestamp(),
                'search_id' => sanitize_text_field($search_id) ?? ''
            );

            if (!$replace_search) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required for custom plugin tables, caching unnecessary for insert operations which modify data
                $wpdb->insert(
                    $table_name_single,
                    $update_args
                );
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required for custom plugin tables, caching unnecessary for update operations which modify data
                $wpdb->update(
                    $table_name_single,
                    $update_args,
                    array(
                        'id' => intval($replace_search->id),
                    )
                );
            }
        }

        /**
         * Get current time
         * @return float|int
         */

        public function get_utc_timestamp()
        {
            return time();
        }

        /**
         * @param string $search_term
         * @param int $result_count
         *
         * Write search term to archive table Any additional information such as time and referer is added here too
         *
         * @since 1.0
         *
         */

        public function write_search_term_to_archive_table($search_term, $result_count)
        {
            if (!get_option('wpsi_database_created')) return;

            global $wpdb;

            $table_name_archive = esc_sql($wpdb->prefix . 'searchinsights_archive');
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required for custom plugin tables, caching unnecessary for insert operations which modify data
            $wpdb->insert(
                $table_name_archive,
                array(
                    'time' => $this->get_utc_timestamp(),
                    'term' => sanitize_text_field($search_term),
                    'result_count' => (int)$result_count,
                    //First occurance, set frequency to 1 so count can be updated when term is searched again
                    'frequency' => '1',
                )
            );
        }

        /**
         * Get the post title of the referer
         * @return array
         *
         *
         */

        public function get_referer()
        {
            $referrer = wp_get_referer();
            $uri_parts = explode('?', $referrer, 2);
            if ($uri_parts && isset($uri_parts[0])) $referrer = $uri_parts[0];
            $post_id = url_to_postid($referrer);
            if ($post_id) {
                $url = str_replace(site_url(), '', get_permalink($post_id));
            } elseif (trailingslashit($referrer) == trailingslashit(site_url())) {
                $url = 'home';
            } else {
                $url = str_replace(site_url(), '', $referrer);
            }

            return array(
                'url' => $url,
                'post_id' => $post_id,
            );
        }

        /**
         * Generate an RFC 4122 Version 4 compliant UUID
         *
         * @return string UUID v4
         * @throws Exception
         */
        private function generate_uuid() {
            // Generate 16 bytes of random data
            $data = random_bytes(16);

            // Set version to 4 (0100 in binary)
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);

            // Set variant to RFC 4122 (10xx in binary)
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

            // Format the UUID in the standard 8-4-4-4-12 format
            $hex = bin2hex($data);
            return sprintf(
                '%s-%s-%s-%s-%s',
                substr($hex, 0, 8),
                substr($hex, 8, 4),
                substr($hex, 12, 4),
                substr($hex, 16, 4),
                substr($hex, 20, 12)
            );
        }

        /**
         * Handle AJAX request to store landing page
         */
        public function ajax_store_landing_page()
        {

            // Verify nonce with proper unslashing and sanitization
            if (!isset($_POST['token']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['token'])), 'wpsi_store_landing_page')) {
                wp_send_json_error(array('message' => __('Invalid request', 'wp-search-insights')));
                return;
            }

            // Validate required fields
            if (empty($_POST['search_term']) || empty($_POST['landing_page']) ||
                empty($_POST['search_timestamp']) || !isset($_POST['is_conversion'])) {
                wp_send_json_error(array('message' => __('Invalid request', 'wp-search-insights')));
                return;
            }

            // Validate the timestamp is recent
            $search_timestamp = (int)$_POST['search_timestamp'];
            $search_timestamp_seconds = $search_timestamp / 1000; // Convert from JS milliseconds if needed
            $current_time = time();
            $max_age = 5 * 60; // 5 minutes in seconds

            if ($current_time - $search_timestamp_seconds > $max_age) {
                wp_send_json_error(array('message' => __('Invalid request', 'wp-search-insights')));
                return;
            }

            global $wpdb;
            // Properly unslash and sanitize all input
            $search_term = sanitize_text_field(wp_unslash($_POST['search_term']));
            $landing_page = esc_url_raw(wp_unslash($_POST['landing_page']));
            $is_conversion = (bool)$_POST['is_conversion'];
            $is_internal = isset($_POST['is_internal']) ? (bool)$_POST['is_internal'] : false;
            $landing_time = isset($_POST['landing_time']) ? (int)$_POST['landing_time'] : time();
            $search_id = isset($_POST['search_id']) ? sanitize_text_field(wp_unslash($_POST['search_id'])) : '';

            // Properly escape table name
            $table_name = esc_sql($wpdb->prefix . 'searchinsights_single');

            // First try to find by search_id (most specific match)
            if (!empty($search_id)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Complex custom table update query, cannot use using default WordPress database functions or caching
                $result = $wpdb->query($wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared  -- Table name escaped above using esc_sql()
                "UPDATE {$table_name}
            SET landing_page = %s,
                is_conversion = %d,
                is_internal = %d,
                landing_time = %d
            WHERE search_id = %s",
                    $landing_page,
                    $is_conversion ? 1 : 0,
                    $is_internal ? 1 : 0,
                    $landing_time,
                    $search_id
                ));

                wp_send_json_success(array('method' => 'search_id', 'rows_affected' => $result));
            } else {
                    // If we reached here, search_id lookup failed, try time-based fallback
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $fallback_result = $wpdb->query($wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name escaped above
                        "UPDATE {$table_name}
                            SET landing_page = %s,
                                is_conversion = %d,
                                is_internal = %d,
                                landing_time = %d
                            WHERE term = %s
                            AND time >= %d
                            ORDER BY ID DESC
                            LIMIT 1",
                        $landing_page,
                        $is_conversion ? 1 : 0,
                        $is_internal ? 1 : 0,
                        $landing_time,
                        $search_term,
                        $search_timestamp_seconds - 300 // Allowing 5 minutes variance
                    ));

                    wp_send_json_success(array(
                        'method' => 'fallback',
                        'rows_affected' => $fallback_result
                    ));
                }
            }

        /**
         * Get permalinks for search results from a query
         *
         * @param WP_Query $query The query containing search results
         * @return array Array of permalink URLs
         */
        public function get_search_result_permalinks($query)
        {
            $permalinks = array();

            // Only proceed if we have results
            if ($query->found_posts > 0 && $query->have_posts()) {
                // Save the original query position
                $original_position = $query->current_post;

                // Reset to beginning to get all permalinks
                $query->rewind_posts();

                while ($query->have_posts()) {
                    $query->the_post();
                    $permalinks[] = get_permalink();
                }

                // Restore the original query position
                $query->current_post = $original_position;
                $query->rewind_posts();
            }

            return $permalinks;
        }

        /**
         * Set tracking cookies for a search
         *
         * @param string $search_term The search term
         * @param int $result_count Number of results found
         * @param string $search_id UUID for this search
         * @param array $permalinks Optional array of result permalinks
         * @return void
         */
        private function set_search_cookies($search_term, $result_count, $search_id, $permalinks = []) {
            // Set the search ID cookie
            setcookie('wpsi_search_id', $search_id, [
                'expires' => time() + 300, // 5 minutes
                'path' => '/',
                'samesite' => 'Lax',
                'secure' => is_ssl(),
                'httponly' => false // Need JavaScript access
            ]);

            // Create search data JSON
            $search_data = json_encode([
                'term' => $search_term,
                'timestamp' => time() * 1000, // Convert to JS milliseconds
                'results' => $permalinks,
                'result_count' => $result_count,
                'search_id' => $search_id
            ]);

            // Set the JSON search data cookie
            setcookie('wpsi_last_search', $search_data, [
                'expires' => time() + 300, // 5 minutes
                'path' => '/',
                'samesite' => 'Lax',
                'secure' => is_ssl(),
                'httponly' => false // Need JavaScript access
            ]);
        }
    }
}
