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


			add_action('wp_ajax_wpsi_delete_terms', array($this, 'ajax_delete_terms'));

		}

		static function this() {
			return self::$_this;
		}

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
		 * Delete term by id
		 * @param $term_id
		 */
		public function delete_term($term_id){

			if (!current_user_can('manage_options')) return;

			global $wpdb;
			//get the term, so we can also remove it from the single table
			$term = $wpdb->get_var($wpdb->prepare("select term from {$wpdb->prefix}searchinsights_archive where id=%s", intval($term_id)));
			$wpdb->delete(
				$wpdb->prefix . 'searchinsights_archive',
				array('id' => intval($term_id))
			);

			if ($term){
				$wpdb->delete(
					$wpdb->prefix . 'searchinsights_single',
					array('term' => sanitize_text_field($term))
				);
			}

            WP_Search_insights()->admin->clear_cache();
		}


		public function enqueue_assets() {
			$minified = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
            wp_register_script( 'search-insights-frontend-js',
                trailingslashit( wp_search_insights_url )
                . "assets/js/frontend$minified.js", array('jquery'), wp_search_insights_version , true);
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
            exit;
        }



        /**
         * @param $search_term
         * @param $result_count
         *
         * Check if conditions are met, if so write the term to DB
         *
         * @since 1.0
         *
         */

        public function process_search_term( $search_term , $result_count ) {

        	//Exclude empty search queries
	        if (strlen( $search_term ) === 0) {
		        return;
	        }

	        $filtered_terms = get_option('wpsi_filter_textarea');

	        // Remove commas from option
	        $filtered_terms = str_replace( ',' , '', $filtered_terms);
	        $filtered_terms = explode(" ", $filtered_terms);

	        // Check if search term should be filtered
	        foreach ($filtered_terms as $term) {
				if ($term === $search_term) {
					return;
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
		 * @param $search_term
         * @param $result_count
		 *
		 *  Write search term to both tables
		 *
		 * @since 1.0
		 *
		 */

		public function write_terms_to_db( $search_term, $result_count ) {

			global $wpdb;
			//check if this search was written with five seconds ago
			$replace_search_term=false;
			$search_term = sanitize_text_field($search_term);
			$old_search_term = $search_term;
			$now = $this->current_time();
			$five_seconds_ago = $now-10;
			$last_search = $this->get_last_search_term();

			//exact match, ignore.
			if ($last_search && $last_search->time > $five_seconds_ago && $last_search->term===$search_term){
				return;
			}

			//differs only one character, overwrite existing entries with new search term
			if ($last_search && $last_search->time > $five_seconds_ago && strpos($search_term, $last_search->term)!==FALSE ){
				$replace_search_term = $last_search;
				$old_search_term = $last_search->term;
			}

			// Write the search term to the single database which contains each query
			// if it's only one char different, it will replace the previous one.
            $this->write_search_term_to_single_table( $search_term , $replace_search_term);

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
				// Doesn't exists, write a new entry to archive
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

		public function get_duplicate_and_fuzzy($term){

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
		 * @param string $trendperiod
		 * @return array $searches
		 */

		public function get_searches($args=array(), $trend=false, $trendperiod='MONTH'){
			$defaults = array(
				'orderby' => 'frequency',
                'order' => 'DESC',
                'result_count' => false,
				'number' => -1,
				'term'=> false,
				'time' => false,
				'compare' => ">",
                'from' => "*",
			);

            $args = wp_parse_args( $args,$defaults);
			global $wpdb;
			$table_name_archive = $wpdb->prefix . 'searchinsights_archive';
			$limit = '';
			if ($args['number']!=-1){
				$count = intval($args['number']);
				$limit = "LIMIT $count";
			}
			$order = $args['order']=='ASC' ? 'ASC' : 'DESC';
			$orderby = sanitize_title($args['orderby']);
			$where = '';
			if ($args['result_count']!==FALSE){
                $where .= " AND result_count ";
                if ($args['compare']) {
                    $where .= $args['compare'];
                }
                $where .= "=" .$args['result_count'];
			}
			if ($args['term']){
				$where .= $wpdb->prepare(' AND term = %s ',sanitize_text_field($args['term']));
			}

			if ($args['time']){
				$compare = $args['compare']=='>' ? '>' : '<';
				$time = intval($args['time']);
				$where .=" AND time $compare $time ";
			}

			/**
			 * If $trend=true, we need two searches, to check foreach search the number of hits the previous trend month. We join these searches in one query
			 */
			$search_sql = "SELECT ".$args['from']." from $table_name_archive WHERE 1=1 $where ORDER BY $orderby $order $limit";

			if ($trend){
			    error_log("trend");
				switch ($trendperiod) {
					case 'YEAR':
						$period = 'years';
						break;
					case 'DAY':
						$period = 'days';
						break;
					default:
						$period = 'months';
				}
				$last_period_start = strtotime("-2 $period");
				$last_period_end = strtotime("-1 $period");
				$where .= " AND time > $last_period_start AND time < $last_period_end";
				$previous_period_sql = "SELECT frequency as previous_frequency, id from $table_name_archive WHERE 1=1 $where ORDER BY $orderby $order $limit";

				$search_sql = "select current.*, previous.previous_frequency from ($search_sql) as current left join ($previous_period_sql) as previous ON current.id = previous.id";
			}

            $searches =$wpdb->get_results( $search_sql );

			return $searches;
		}


		/**
		 * Get popular searches
		 * @param array $args
		 * @return array $searches
		 */

		public function get_searches_single($args=array(), $trend=false, $trendperiod='MONTH'){
			$defaults = array(
				'number' => -1,
				'order' => 'DESC',
				'orderby' => 'term',
				'term'=> false,
				'time' => false,
				'compare' => ">",
			);
			$args = wp_parse_args( $args,$defaults);
			global $wpdb;
			$table_name = $wpdb->prefix . 'searchinsights_single';
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
			if ($args['time']){
				$compare = $args['compare']=='>' ? '>' : '<';
				$time = intval($args['time']);
				$where .=" AND time $compare $time ";
			}
			$searches =$wpdb->get_results( "SELECT * from $table_name WHERE 1=1 $where ORDER BY $orderby $order $limit" );

            if ($trend){
                switch ($trendperiod) {
                    case 'YEAR':
                        $period = 'years';
                        break;
                    case 'DAY':
                        $period = 'days';
                        break;
                    default:
                        $period = 'months';
                }
                $last_period_start = strtotime("-2 $period");
                $last_period_end = strtotime("-1 $period");
                $where .= " AND time > $last_period_start AND time < $last_period_end";
                $previous_period_sql = "SELECT frequency as previous_frequency, id from $table_name_archive WHERE 1=1 $where ORDER BY $orderby $order $limit";

                $search_sql = "select current.*, previous.previous_frequency from ($search_sql) as current left join ($previous_period_sql) as previous ON current.id = previous.id";
            }

			return $searches;
		}

		/**
		 * @param $search_term
         * @param $replace_search_term
		 *
		 * Write search term to single table. Any additional information such as time and referer is added here too
		 *
		 * @since 1.0
		 *
		 */

		public function write_search_term_to_single_table( $search_term , $replace_search_term=false) {
			global $wpdb;

			$table_name_single = $wpdb->prefix . 'searchinsights_single';
			if (!$replace_search_term) {
				$wpdb->insert(
					$table_name_single,
					array(
						'time'     => $this->current_time(),
						'term'     => sanitize_text_field($search_term),
						'referrer' => $this->get_referer(),
					)
				);
			} else {
				$wpdb->update(
					$table_name_single,
					array(
						'term'     => sanitize_text_field($search_term),
						'referrer' => $this->get_referer(),
						'time'     => $this->current_time(),
					),
					array(
						'id' => intval($replace_search_term->id),
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
		 * @param $search_term
		 *
		 * Write search term to archive table Any additional information such as time and referer is added here too
		 *
		 * @since 1.0
		 *
		 */

		public function write_search_term_to_archive_table( $search_term, $result_count) {
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
		 * @return string
		 *
		 * Get the post title of the referer
		 *
		 */

		public function get_referer() {
            $referrer = esc_url_raw("http://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

			$uri_parts = explode('?', $referrer, 2);
			if ($uri_parts && isset($uri_parts[0])) $referrer = $uri_parts[0];
			$post_id = url_to_postid($referrer);
			if ($post_id){
				return get_the_title($post_id);
			} elseif (trailingslashit($referrer)==trailingslashit(site_url())) {
				return __('Home','wp-search-insights');
			} else {
				return str_replace(site_url(), '', $referrer);
			}
		}

	}//Class closure
} //if class exists closure

