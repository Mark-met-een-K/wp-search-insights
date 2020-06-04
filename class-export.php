<?php
defined( 'ABSPATH' ) or die( "you do not have access to this page!" );

if ( ! class_exists( 'WPSI_EXPORT' ) ) {
	class WPSI_EXPORT {
		private static $_this;
		public $filename = "wpsi-export.csv";
		public $rows = 500;

		static function this() {
			return self::$_this;
		}

		function __construct() {
			if ( isset( self::$_this ) ) {
				wp_die( sprintf( __( '%s is a singleton class and you cannot create a second instance.',
					'wp-search-insights' ), get_class( $this ) ) );
			}

			self::$_this = $this;

			add_filter("wpsi_ajax_content_export", array($this, 'ajax_content_export') );
			add_filter('wpsi_settings_blocks', array($this, 'export_block') );

			add_action('wp_ajax_wpsi_start_export', array($this, 'ajax_start_export'));
		}

		public function export_block($blocks){
			$blocks[] = array(
				'title' => __( "Export", "wp-search-insights" ),
				'content' => '<div class="wpsi-skeleton"></div>',
				'index' => 'export',
				'type'=> 'export',
				'controls' => '',
			);
			return $blocks;

		}


		public function ajax_content_export(){
			if (!current_user_can('manage_options')) return;
			$disabled = get_transient('wpsi_export_in_progress') ? 'disabled' : '';
			$content = '<input type="text" id="jquery-datepicker" name="entry_post_date" value="">';
			$content .= '<button '.$disabled.' class="button-secondary" id="wpsi-start-export">'.__("Export", "wp-search-insights").'</button>';
			$link = '';
			if (file_exists($this->filepath() )){
				$link = '<a href="'.$this->fileurl().'">'.__("Download", "wp-search-insights").'</a></div>';
			}

			$content .= '<div class="wpsi-download-link">'.$link.'</div>';
			ob_start();
            echo $content;
			return ob_get_clean();
		}



		public function ajax_start_export()
		{
			$error = false;
			$percent = 0;

			if (!current_user_can('manage_options')) {
				$error = true;
			}

			if (!isset($_GET['token'])){
				$error = true;
			}

			if (!$error && !wp_verify_nonce(sanitize_title($_GET['token']), 'search_insights_nonce')){
				$error = true;
			}

			$data = array(
				'success' => false,
				'percent' => 0,
			);

			if (!$error){
				//first call, start generation
				if ( !get_transient('wpsi_export_in_progress') ){
					set_transient('wpsi_export_in_progress', true, 2 * DAY_IN_SECONDS);
					//cleanup old file
					$file = $this->filepath();
					if (file_exists($file)){
						unlink($file);
					}
					//get total rows
					$args = array(
						'number' => -1,
						'range' => false,
						'count' => true,
					);

					$count = WPSI::$search->get_searches_single($args);
					update_option('wpsi_export_row_count', $count);
					$progress = 0;
					update_option('wpsi_export_progress', $progress );
				} else {
					$percent = $this->process_csv_chunk();
				}

				$data = array(
					'success' => !$error,
					'percent' => round($percent, 0),
					'total' => round($percent, 0),
					'path' => $this->fileurl(),
				);

			}

			$response = json_encode($data);
			header("Content-Type: application/json");
			echo $response;
			exit;
		}


		public function process_csv_chunk(){
			if ( !get_transient('wpsi_export_in_progress') ) return;

			$progress = get_option('wpsi_export_progress') + 1;
			update_option('wpsi_export_progress', $progress);
			$offset = $progress * $this->rows;

			//if $progress * $this->rows > $count, stop.
			$count = get_option('wpsi_export_row_count');
			if ($count ==0 ){
				$percent = 100;
			} else {
				$percent = 100 * ( ($progress * $this->rows) / $count );
				if ($percent >= 100) $percent = 100;
			}

			if ($percent == 100) {
				update_option('wpsi_export_progress', 0);
				delete_transient('wpsi_export_in_progress');
			}

			$args = array(
				'number' => $this->rows,
				'range' => false,
				'result_count' => true,
				'offset' => $offset,
			);
			$searches = WPSI::$search->get_searches_single($args);

			//convert to array
			$json  = json_encode($searches);
			$searches = json_decode($json, true);
			$this->create_csv_file($this->filename, $searches);
			return $percent;
		}


		private function create_csv_file($filename, $data){
			$delimiter=";";
			require_once(ABSPATH . 'wp-admin/includes/file.php');
			$uploads = wp_upload_dir();
			$upload_dir = $uploads['basedir'];
			if (!file_exists($upload_dir)) {
				mkdir($upload_dir);
			}

			if (!file_exists($upload_dir . "/wpsi")) {
				mkdir($upload_dir . "/wpsi");
			}

			//set the path
			$file = $upload_dir . "/wpsi/".$filename;

			//'a' creates file if not existing, otherwise appends.
			$csv_handle = fopen ($file,'a');
			foreach ($data as $line) {
				fputcsv($csv_handle, $line, $delimiter);
			}
			fclose ($csv_handle);
		}


		private function filepath(){
			$uploads = wp_upload_dir();
			$upload_dir = $uploads['basedir'];
			return $upload_dir . "/wpsi/".$this->filename;
		}

		private function fileurl(){
			$uploads = wp_upload_dir();
			return $uploads['baseurl'] . "/wpsi/".$this->filename;
		}

	}
}