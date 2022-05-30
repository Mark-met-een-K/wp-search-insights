<?php

defined( 'ABSPATH' ) or die( "you do not have access to this page!" );

if ( ! class_exists( 'Search' ) ) {
	class Search {

		private static $_this;
		public $filtered_terms = array(
			'{search_term_string}'
		);

		function __construct() {

			if ( isset( self::$_this ) ) {
				wp_die( sprintf( __( '%s is a singleton class and you cannot create a second instance.',
					'wp-search-insights' ), get_class( $this ) ) );
			}

			self::$_this = $this;

			//Misschien moet deze juist wel als allerlaatste, dat de code uitgevoerd wordt na pageload
			add_action( 'template_redirect', array( $this, 'get_regular_search' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'wp_ajax_nopriv_wpsi_process_search', array( $this, 'get_ajax_search') );
			add_action( 'wp_ajax_wpsi_process_search', array( $this, 'get_ajax_search') );
			add_action( 'plugins_loaded', array( $this, 'update_db' ) );
			add_action('wp_ajax_wpsi_delete_terms', array($this, 'ajax_delete_terms'));
            add_action('wp_ajax_wpsi_ignore_terms', array($this, 'ajax_ignore_terms'));
            add_action('init', array($this, 'get_custom_search'));
		}

		static function this() {
			return self::$_this;
		}

		/**
		 * Delete array of terms using ajax
		 */

		public function ajax_delete_terms()
		{
			$error = false;

			if (!current_user_can('manage_options')) {

				$error = true;
			}

			if (!isset($_POST['term_ids'])){
				$error = true;
			}

			if (!isset($_POST['token'])){
				$error = true;
			}

			if (!$error && !wp_verify_nonce(sanitize_title($_POST['token']), 'search_insights_nonce')){
				$error = true;
			}

			if (!$error){
				$term_ids = json_decode($_POST['term_ids']);
				foreach($term_ids as $term_id){
					$this->delete_term(intval($term_id));
				}
			}

			$data = array(
				'success' => !$error,
			);

			$response = json_encode($data);
			header("Content-Type: application/json");
			echo $response;
			exit;
		}

        /**
         * Ignore array of terms using ajax
         */

        public function ajax_ignore_terms()
        {
            $error = false;

            if (!current_user_can('manage_options')) {

                $error = true;
            }

            if (!isset($_POST['term_ids'])){
                $error = true;
            }

            if (!isset($_POST['token'])){
                $error = true;
            }

            if (!$error && !wp_verify_nonce(sanitize_title($_POST['token']), 'search_insights_nonce')){
                $error = true;
            }

            if (!$error){
                $term_ids = json_decode($_POST['term_ids']);
                foreach($term_ids as $term_id){
                    $this->ignore_term(intval($term_id));
                }
            }

            $data = array(
                'success' => !$error,
            );

            $response = json_encode($data);
            header("Content-Type: application/json");
            echo $response;
            exit;
        }




        /**
		 * Delete term by id
		 * @param int $term_id
		 */

		public function delete_term($term_id){

			if (!current_user_can('manage_options')) return;

			global $wpdb;
			//get the term, so we can also remove it from the single table
			$term_single = $wpdb->get_var($wpdb->prepare("select term from {$wpdb->prefix}searchinsights_single where id=%s", intval($term_id)));

			if ($term_single){
			    // Get the frequency of term
				$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}searchinsights_single WHERE term = '$term_single'");
				// Substract 1 since we deleted this entry
				$count = ( $count - 1 );
				// Delete from archive if count is 0
				if ( $count === intval(0) ) {
                    $wpdb->delete(
                        $wpdb->prefix . 'searchinsights_archive',
                        array('term' => $term_single)
                    );
                } else {
				    // Update the frequency in archive table
                    $wpdb->update(
                        $wpdb->prefix . 'searchinsights_archive',
                        array('frequency' => intval( $count ) ),
                        array('term' => $term_single)
                    );
                }
			}

            $wpdb->delete(
                $wpdb->prefix . 'searchinsights_single',
                array('id' => intval($term_id))
            );

			WPSI::$admin->clear_cache();
		}

        /**
         * Ignore term
         * Add to filter, delete all references from single/archive table
         * @since 1.3.7
         * @param int $term_id
         */

        public function ignore_term($term_id){

            if ( !current_user_can('manage_options') ) return;

            global $wpdb;
            //get the term, so we can also remove it from the single table
            $term_single = $wpdb->get_var($wpdb->prepare("select term from {$wpdb->prefix}searchinsights_single where id=%s", intval($term_id)));

            // Add to filtered terms
            $filter = get_option('wpsi_filter_textarea');

            // Do not add , when the current filter ends with it
            if ( !$filter ) {
                $filter = $term_single;
            }

            if ( strpos($filter, $term_single) === false ) {
                if ( $this->endsWith($filter, ',') ) {
                    $filter = $filter . " " . $term_single;
                } else {
                    $filter = $filter . ", " . $term_single;
                }
            }

            // Update the filter option
            update_option('wpsi_filter_textarea', $filter );

            $wpdb->delete(
                $wpdb->prefix . 'searchinsights_single',
                array('term' => $term_single)
            );

            if ($term_single){
                $wpdb->delete(
                    $wpdb->prefix . 'searchinsights_archive',
                    array( 'term' => sanitize_text_field( $term_single ) )
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

		public function enqueue_assets() {

			if (!get_option('wpsi_track_ajax_searches')) return;

			$minified = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
            wp_register_script( 'search-insights-frontend-js',
                trailingslashit( wpsi_url )
                . "assets/js/frontend$minified.js", array('jquery'), wpsi_version , true);
            wp_enqueue_script( 'search-insights-frontend-js' );
            wp_localize_script( 'search-insights-frontend-js', 'search_insights_ajax',
                array(
                    'ajaxurl' => admin_url( 'admin-ajax.php' ),
                    'token'   => wp_create_nonce( 'search_insights_nonce'),
                ) );
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

                // Get the search count. This data is displayed in the admin dashboard.
                $result_count = $wp_query->found_posts;

				// Process term and count, add additional information and write to DB
				$this->process_search_term( $search_term , $result_count );

            }
		}

		/**
		 * Get search with a custom search parameter
		 */

		public function get_custom_search() {
			//get custom search parameter
			$custom_search_parameter = sanitize_title(get_option('wpsi_custom_search_parameter'));
			$caller = $this->get_caller_by_search_parameter($custom_search_parameter);

			if (strlen($custom_search_parameter)>0 && isset($_GET[$custom_search_parameter])) {
				$result_count = 0;
				//Get the search term
				$search_term
					= sanitize_text_field( $_GET[ $custom_search_parameter ] );
				// Get the search count. This data is displayed in the admin dashboard.
				$args = array(
					'posts_per_page' => - 1,
					'post_status'    => 'publish',
					'offset'         => 0,
					's'              => $search_term,
				);
				$posts = get_posts( $args );
				if ( $posts ) {
					$result_count = count( $posts );
				}

				// Process term and count, add additional information and write to DB
				$this->process_search_term( $search_term, $result_count, $caller);
			}
		}

		/**
		 * Get caller of search term by parameter
		 * @param string $search_parameter
		 *
		 * @return mixed|void
		 */

		public function get_caller_by_search_parameter($search_parameter) {
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

			//Check and verify nonce
            if (!isset($_POST['token']) || !wp_verify_nonce($_POST['token'], 'search_insights_nonce') ) exit;

            if (isset($_POST['searchterm'])) {
                $search_term = sanitize_text_field($_POST['searchterm']);
                $args = array( 's' => $search_term );
                $search_query = new WP_Query( $args );
                $result_count = $search_query->found_posts;
                $this->process_search_term($search_term, $result_count);
            }
			$data['success'] = true;
			$response               = json_encode( $data );
			header( "Content-Type: application/json" );
			echo $response;
			exit;
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

        public function process_search_term( $search_term , $result_count , $caller = '') {
        	//Exclude empty search queries
	        if (strlen( $search_term ) === 0) {
		        return;
	        }
	        /**
	         * allow skipping this search term
	         */

	        if ( !apply_filters('wpsi_process_search_term', true, $search_term, $result_count ) ) {
	        	return;
	        }

	        /**
	         * allow manipulation of search term
	         */

	        $search_term = apply_filters('wpsi_search_term', $search_term, $result_count );

	        $filtered_terms = get_option('wpsi_filter_textarea');

            // Remove commas from option
            $filtered_terms = str_replace(',,',',', $filtered_terms);
			if (!empty($filtered_terms)){
				$filtered_terms = explode(",", $filtered_terms);

				// Check if search term should be filtered
				foreach ($filtered_terms as $term) {

                    // Trim
                    $term = trim( strtolower( $term ) );
                    $search_term = trim( strtolower( $search_term ) );

					if ( $term ===  $search_term ) {
						return;
					}
				}
			}


	        // Return if the query comes from an administrator and the exclude admin searches option is been enabled
            if ( in_array( 'administrator', wp_get_current_user()->roles ) && get_option( 'wpsi_exclude_admin' )) {
	            return;
            }

            // Get the query arg. Use esc_url_raw instead of esc_url because it doesn't decode &
            $current_url = esc_url_raw( home_url( add_query_arg( $_GET ) ) );
            // When clicking on 'see results' in dashboard, &searchinsights will be added to the url. If this is found current url, return.
            if ( strpos( $current_url, "&searchinsights" ) !== false ) {
	            return;
            }

            // Check if the term length is below minimum value option
            if ( (strlen($search_term) < (get_option('wpsi_min_term_length') ) ) && (get_option('wpsi_min_term_length') !== 0) ) {
                return;
            }

            if ( (strlen($search_term) > (get_option('wpsi_max_term_length') ) ) && (get_option('wpsi_max_term_length') !== 0 ) ) {
                return;
            }

            if ( in_array($search_term, $this->filtered_terms) ) {
            	return;
            }

            $this->write_terms_to_db($search_term, $result_count);

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

		public function write_terms_to_db( $search_term, $result_count ) {
			global $wpdb;
			//check if this search was written with five seconds ago
			$replace_search = false;
			$search_term = sanitize_text_field($search_term);
			$old_search_term = $search_term;
			$now = $this->current_time();
			$last_search = $this->get_last_search_term();

			//check if the last search is a recent search
			$ten_seconds_ago = $now-10;
			$last_search_is_recent = $last_search && $last_search->time > $ten_seconds_ago;

			//this search is the same as the previous one, which is recent, ignore.
			if ($last_search && $last_search_is_recent && $last_search->term===$search_term){
				return;
			}

			//last search is part of new search, overwrite existing entries with new search term
			if ($last_search && $last_search_is_recent && strpos( $search_term, $last_search->term)!==FALSE ){
				$replace_search = $last_search;
				$old_search_term = $last_search->term;
			}

			// Write the search term to the single database which contains each query
			// if replace_search is passed, this term will be updated
            $this->write_search_term_to_single_table( $search_term , $replace_search);

            // Check if search term exists in the archive database, if it does update the term count. Create a new entry otherwise
            $table_name_archive = $wpdb->prefix . 'searchinsights_archive';
			$old_term_in_database = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_name_archive WHERE term = %s", $old_search_term) );
			$old_term_exists = $old_term_in_database && $wpdb->num_rows > 0;
			$current_term_in_database = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_name_archive WHERE term = %s", $search_term) );
			$current_term_exists = $current_term_in_database && $wpdb->num_rows > 0;

			if ( $old_term_exists || $current_term_exists) {
				// Exists, update the count in archive
				// if it's one character different, update only term and result count, not frequency
				if ($old_search_term && ($search_term !== $old_search_term)){
					$this->replace_term( $old_search_term, $search_term, $result_count);
				} else {
					$this->update_term_count( $search_term, $result_count);
				}
			} else {
				// Doesn't exist, write a new entry to archive
				$this->write_search_term_to_archive_table( $search_term, $result_count );
			}
		}

		/**
		 * Get the last term that has been added to the list.
		 * @return object $row
		 */

		public function get_last_search_term(){
			global $wpdb;
			$table_name_archive = $wpdb->prefix . 'searchinsights_single';
			$sql = "SELECT * FROM $table_name_archive ORDER BY ID DESC LIMIT 1";
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

		public function update_term_count( $search_term, $result_count) {
			if (!get_option('wpsi_database_created')) return;
			global $wpdb;

			$table_name_archive = $wpdb->prefix . 'searchinsights_archive';
			//Have to use query on INT because $wpdb->update assumes string.
			$result_count = intval($result_count);
			$wpdb->query( $wpdb->prepare( "UPDATE $table_name_archive SET frequency = frequency +1, result_count=%s, time=%s WHERE term = %s", $result_count, $this->current_time(), sanitize_text_field($search_term)) );
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

		public function replace_term( $search_term, $new_term, $result_count) {
			if (!get_option('wpsi_database_created')) return;

			global $wpdb;
			$new_term = sanitize_text_field($new_term);
			$search_term = sanitize_text_field($search_term);

			$table_name_archive = $wpdb->prefix . 'searchinsights_archive';
			//Have to use query on INT because $wpdb->update assumes string.
			$result_count = intval($result_count);
			$time = $this->current_time();
			$wpdb->query( $wpdb->prepare( "UPDATE $table_name_archive SET term=%s, time=%s, result_count=$result_count WHERE term = %s", $new_term, $time, $search_term ) );


			//now, in case we have double terms, do some clean up
			$sql = $wpdb->prepare("select * from $table_name_archive where term = %s",$new_term);
			$results = $wpdb->get_results($sql);
			if (count($results)>1){
				//get total frequency
				$sql = $wpdb->prepare("select SUM(frequency) as frequency from $table_name_archive where term = %s",$new_term);
				$frequency = $wpdb->get_var($sql);
				$sql = $wpdb->prepare("select id from $table_name_archive where term = %s order by frequency DESC",$new_term);
				$id = $wpdb->get_var($sql);

				$IDS = wp_list_pluck($results, 'id');
				if (($key = array_search($id, $IDS)) !== false) {
					unset($IDS[$key]);
				}
				$sql = implode(' OR id =', $IDS);
				$sql = "DELETE from $table_name_archive where id=".$sql;
				$wpdb->query($sql);

				$wpdb->update(
					$table_name_archive,
					array('frequency' => $frequency),
					array('id' => $id)
				);
			}
		}

		/**
		 * Get searches
		 * @param array $args
		 * @param bool $trend
		 * @return array|int $searches
		 */

		public function get_searches($args=array(), $trend=false){
			$defaults = array(
				'orderby' => 'frequency',
                'order' => 'DESC',
                'result_count' => false,
				'number' => -1,
				'term'=> false,
				'compare' => false,
                'from' => "*",
				'range' => false,
				'count' => false,
				'date_from' => false,
				'date_to' => false,
				'include'
			);
            $args = wp_parse_args( $args,$defaults);
            if ($args['range'] && $args['range']!=='all'){
	            switch ($args['range']){
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
			$table_name_archive = $wpdb->prefix . 'searchinsights_archive';
			$table_name_single = $wpdb->prefix . 'searchinsights_single';

			$limit = '';
			if ($args['number']!=-1){
				$count = intval($args['number']);
				$limit = "LIMIT $count";
			}
			$order = $args['order']=='ASC' ? 'ASC' : 'DESC';
			$orderby = sanitize_title($args['orderby']);
			$where = '';

			if ($args['term']){
				$where .= $wpdb->prepare(' AND term = %s ',sanitize_text_field($args['term']));
			}

			//split of trend where because we don't want the range here.
			$trend_where = $where;

			if ($args['result_count']!==FALSE){
				$where .= " AND result_count ";
				if ($args['compare']) {
					$where .= $args['compare'];
				} else {
					$where .= "=";
				}
				$where .= $args['result_count'];
			}

			if ($args['date_from']){
				$from = intval($args['date_from']);
				$where .=" AND time > $from ";
			}

			if ($args['date_to']){
				$to = intval($args['date_to']);
				$where .=" AND time < $to ";
			}

			/**
			 * If $trend=true, we need two searches, to check foreach search the number of hits the previous trend month. We join these searches in one query
			 */

			$search_sql = "SELECT ".$args['from']." from $table_name_archive WHERE 1=1 $where ORDER BY $orderby $order $limit";
			if ( $trend ) {
				if ($args['date_from'] && $args['date_to'] ) {
					$period = intval( $args['date_to'] )
					          - intval( $args['date_from'] );
					$last_period_start = intval($args['date_from'])-$period;
					$last_period_end = intval($args['date_from']);
				} elseif ($args['range']) {
					$period = $args['range'];
					$last_period_start = strtotime("-2 $period");
					$last_period_end = strtotime("-1 $period");
				}

				$trend_where .= " AND time > $last_period_start AND time < $last_period_end";
				$previous_period_sql = "SELECT COUNT(*) as previous_frequency, term from $table_name_single WHERE 1=1 $trend_where GROUP BY term";
				$search_sql = "select current.*, previous.previous_frequency from ($search_sql) as current left join ($previous_period_sql) as previous ON current.term = previous.term";
			}

			if ($args['count']) {
				$search_sql = str_replace(" * ", " count(*) as count ",  $search_sql);
				$searches =$wpdb->get_var( $search_sql );
			} else {
				$searches =$wpdb->get_results( $search_sql );
			}

			//if we searched for a term, there is only one result
			if ($args['term']){
				if (isset($searches[0])){
					$searches = $searches[0];
				} else{
					$searches = false;
				}
			}
			return $searches;
		}


		/**
		 * Get popular searches
		 * @param array $args
		 *
		 * @return array|int $searches
		 */

		public function get_searches_single($args=array()){
			$defaults = array(
				'number' => -1,
				'order' => 'DESC',
				'orderby' => 'time',
				'term'=> false,
				'compare' => ">",
				'range' => false,
				'result_count' => false,
				'offset' => false,
				'count' => false,
				'date_from' => false,
				'date_to' => false,
			);
			$args = wp_parse_args( $args, $defaults);

			if ($args['range'] && $args['range']!=='all'){
				switch ($args['range']){
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
			$table_name = $wpdb->prefix . 'searchinsights_single';
			$limit = '';
			if ($args['number']!=-1){
				$count = intval($args['number']);
				$limit = "LIMIT $count";
				if ($args['offset']){
					$limit .= ' OFFSET '.intval($args['offset']);
				}
			}
			$order = $args['order']=='ASC' ? 'ASC' : 'DESC';
			$orderby = sanitize_title($args['orderby']);
			$where = '';
			if ($args['term']){
				$where .= $wpdb->prepare(' AND term = %s ', sanitize_text_field($args['term']));
			}

			if ($args['date_from']){
				$from = intval($args['date_from']);
				$where .=" AND time > $from ";
			}

			if ($args['date_to']){
				$to = intval($args['date_to']);
				$where .=" AND time < $to ";
			}

			$search_sql ="SELECT * from $table_name WHERE 1=1 $where ORDER BY $orderby $order $limit";

			/*
			 * We want to include the result count. Create an inner join with the archive.
			 */

			if ($args['result_count']){
				$search_sql = "select main.*, archive.result_count, archive.frequency from ($search_sql) as main left join {$wpdb->prefix}searchinsights_archive as archive  on main.term = archive.term ORDER BY main.$orderby $order";
			}


			if ($args['count']) {
				$search_sql = str_replace(" * ", " count(*) as count ",  $search_sql);
				$searches =$wpdb->get_var( $search_sql );
			} else {
				$searches =$wpdb->get_results( $search_sql );

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

		public function write_search_term_to_single_table( $search_term , $replace_search=false) {
			if (!get_option('wpsi_database_created')) return;

			global $wpdb;
			$table_name_single = $wpdb->prefix . 'searchinsights_single';
			$referrer = $this->get_referer();

			$update_args = array(
				'term'          => sanitize_text_field($search_term),
				'referrer'      => sanitize_text_field($referrer['url']),//we can't use esc_url, because it also may be "home"
				'referrer_id'   => intval($referrer['post_id']),
				'time'          => $this->current_time(),
			);

			if ( !$replace_search ) {
				$wpdb->insert(
					$table_name_single,
					$update_args
				);
			} else {
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

		public function current_time(){
			//store the date
			$timezone_offset = get_option('gmt_offset');
			return time() + (60 * 60 * $timezone_offset);

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

		public function write_search_term_to_archive_table( $search_term, $result_count) {
			if (!get_option('wpsi_database_created')) return;

			global $wpdb;

			$table_name_archive = $wpdb->prefix . 'searchinsights_archive';
			$wpdb->insert(
				$table_name_archive,
				array(
					'time'      => $this->current_time(),
					'term'      => sanitize_text_field($search_term),
					'result_count'      => intval($result_count),
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

		public function get_referer() {
            $referrer = wp_get_referer();
			$uri_parts = explode('?', $referrer, 2);
			if ($uri_parts && isset($uri_parts[0])) $referrer = $uri_parts[0];
			$post_id = url_to_postid($referrer);
			if ($post_id){
				$url = str_replace(site_url(), '', get_permalink($post_id));
			} elseif (trailingslashit($referrer)==trailingslashit(site_url())) {
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
		 * Check if database is still up to date. If not, update
		 */

		public function update_db() {
			if ( get_option( 'search_insights_db_version' )
			     != wpsi_version
			) {
				global $wpdb;
				require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
				$charset_collate = $wpdb->get_charset_collate();

				$table_name_single = $wpdb->prefix . 'searchinsights_single';
				$sql               = "CREATE TABLE $table_name_single (
                      `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                      `time` INT(11) NOT NULL,
                      `term` text NOT NULL,
                      `referrer` text NOT NULL,
                      `referrer_id` INT(11),
                      PRIMARY KEY (id)
                    ) $charset_collate;";
				dbDelta( $sql );

				$table_name_archive = $wpdb->prefix . 'searchinsights_archive';
				$sql                = "CREATE TABLE $table_name_archive (
                        `id` mediumint(9) NOT NULL AUTO_INCREMENT,
                        `time` INT(11) NOT NULL,
                        `term` text NOT NULL,
                        `frequency` INT(10) NOT NULL,
                        `result_count` INT(10) NOT NULL,
                        PRIMARY KEY  (id)
                      ) $charset_collate;";
				dbDelta( $sql );
				update_option( 'search_insights_db_version',
					wpsi_version );
			}
            update_option('wpsi_database_created', true);
        }

	}
}