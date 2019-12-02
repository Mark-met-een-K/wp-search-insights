<?php

defined( 'ABSPATH' ) or die( "you do not have access to this page!" );

if ( ! class_exists( 'WP_Search_Insights_Search' ) ) {
	class WP_Search_Insights_Search {

		private static $_this;

		function __construct() {

			if ( isset( self::$_this ) ) {
				wp_die( sprintf( __( '%s is a singleton class and you cannot create a second instance.',
					'wp-search-insights' ), get_class( $this ) ) );
			}

			self::$_this = $this;

			//Misschien moet deze juist wel als allerlaatste, dat de code uitgevoerd wordt na pageload
			add_action( 'template_redirect', array( $this, 'get_regular_search' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
			add_action( 'init', array( $this, 'get_ajax_search') );
		}

		static function this() {
			return self::$_this;
		}

		public function enqueue_assets() {

            wp_register_script( 'search-insights-frontend-js',
                trailingslashit( wp_search_insights_url )
                . 'assets/js/frontend.js', array('jquery'), wp_search_insights_version );

            wp_enqueue_script( 'search-insights-frontend-js' );

            wp_localize_script( 'search-insights-frontend-js', 'search_insights_ajax',
                array(
                    'ajaxurl' => admin_url( 'admin-ajax.php' ),
                    'token'   => wp_create_nonce( 'search_insights_nonce', 'token' ),
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
            if (!isset($_POST['token']) || !wp_verify_nonce($_POST['token'], 'search_insights_nonce') ) return;

            if (isset($_POST['searchterm'])) {
                $search_term = sanitize_text_field($_POST['searchterm']);
                $args = array( 's' => $search_term );
                $search_query = new WP_Query( $args );
                $result_count = $search_query->found_posts;
                $this->process_search_term($search_term, $result_count);
            }
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
			$old_search_term = $search_term;
			$now = time();
			$five_seconds_ago = $now-5;
			$last_search = $this->get_last_search_term();

			//exact match, ignore.
			if ($last_search && $last_search->time > $five_seconds_ago && $last_search->term===$search_term){
				return;
			}

			//differs only one character, overwrite existing entries with new search term
			if ($last_search && $last_search->time > $five_seconds_ago && substr($search_term, 0, -1)===$last_search->term){
				$replace_search_term = $last_search;
				$old_search_term = $last_search->term;
			}

			// Write the search term to the single database which contains each query
			// if it's only one char different, it will replace the previous one.
            $this->write_search_term_to_single_table( $search_term , $replace_search_term);

            // Check if search term exists in the archive database, if it does update the term count. Create a new entry otherwise
            $table_name_archive = $wpdb->prefix . 'searchinsights_archive';
			$term_in_database = $wpdb->get_results( $wpdb->prepare("SELECT * FROM $table_name_archive WHERE term = %s", $old_search_term) );
			if ( $term_in_database && $wpdb->num_rows > 0 ) {
				// Exists, update the count in archive
				// if it's one character different, update only term and result count, not frequency
				if ($search_term !== $old_search_term){
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
			$wpdb->query( $wpdb->prepare( "UPDATE $table_name_archive SET frequency = frequency +1, result_count=$result_count WHERE term = %s", $search_term ) );
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

			$table_name_archive = $wpdb->prefix . 'searchinsights_archive';
			//Have to use query on INT because $wpdb->update assumes string.
			$result_count = intval($result_count);
			$time = time();
			$wpdb->query( $wpdb->prepare( "UPDATE $table_name_archive SET term=%s, time=%s, result_count=$result_count WHERE term = %s", $new_term, $time, $search_term ) );
		}

		/**
		 * Get searches
		 * @param array $args
		 * @return array $searches
		 */

		public function get_searches($args=array()){
			$defaults = array(
				'orderby' => 'frequency',
                'order' => 'DESC',
                'result_count' => false,
				'number' => -1,
				'term'=> false,
				'time' => false,
				'compare' => ">",
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
				$where .= " AND result_count = ".intval($args['result_count']);
			}
			if ($args['term']){
				$where .= $wpdb->prepare(' AND term = %s ',sanitize_text_field($args['term']));
			}

			if ($args['time']){
				$compare = $args['compare']=='>' ? '>' : '<';
				$time = intval($args['time']);
				$where .=" AND time $compare $time ";
			}
			$searches =$wpdb->get_results( "SELECT * from $table_name_archive WHERE 1=1 $where ORDER BY $orderby $order $limit" );

			return $searches;
		}


		/**
		 * Get popular searches
		 * @param array $args
		 * @return array $searches
		 */

		public function get_searches_single($args=array()){
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
						'time'     => time(),
						'term'     => $search_term,
						'referrer' => $this->get_referer(),
					)
				);
			} else {
				error_log("update single");
				error_log(print_r($replace_search_term,true));
				error_log("new term $search_term");
				$wpdb->update(
					$table_name_single,
					array(
						'term'     => $search_term,
						'referrer' => $this->get_referer(),
						'time'     => time(),
					),
					array(
						'id' => $replace_search_term->id,
					)
				);
			}
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
					'time'      => time(),
					'term'      => $search_term,
					'result_count'      => $result_count,
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
			return get_the_title(url_to_postid(wp_get_referer()));
		}

	}//Class closure
} //if class exists closure

