<?php
defined('ABSPATH') or die("you do not have access to this page!");

if (!class_exists('WPSI_EXPORT')) {
    class WPSI_EXPORT
    {
        private static $_this;
        public $rows = 500;

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

            add_filter("wpsi_ajax_content_export", array($this, 'ajax_content_export'));
            add_filter('wpsi_settings_blocks', array($this, 'export_block'));
            add_action('wp_ajax_wpsi_start_export', array($this, 'ajax_start_export'));

        }

        /**
         * Add settings block for the export option
         * @param array $blocks
         *
         * @return array
         */
        public function export_block($blocks)
        {
            $blocks[] = array(
                'title' => __("Data", "wp-search-insights"),
                'content' => $this->content_export(),
                'index' => 'settings',
                'class' => 'wpsi-export-grid',
                'type' => 'settings',
                'controls' => '',
            );
            return $blocks;
        }

        /**
         * Content of export block
         * @return string
         */

        public function content_export()
        {
            if (!current_user_can('manage_options')) return;

            ob_start();

            do_settings_sections('wpsi-data');
            settings_fields('wpsi-data-tab');

            wpsi_save_button();

            return ob_get_clean();
        }

        /**
         * Start export with ajax call
         */

        public function ajax_start_export()
        {
            $error = false;
            $percent = 0;

            if (!current_user_can('manage_options')) {
                $error = true;
            }

            if (!$error && (!isset($_GET['token']) || !wp_verify_nonce(sanitize_key(wp_unslash($_GET['token'])), 'wpsi_start_export'))) {
                $error = true;
            }

            if (!isset($_GET['token'])) {
                $error = true;
            }

            if (!isset($_GET['date_from']) || !isset($_GET['date_to'])) {
                $error = true;
            }

            $data = array(
                'success' => false,
                'percent' => 0,
            );

            if (!$error) {
                $date_from = intval($_GET['date_from']);
                $date_to = intval($_GET['date_to']);
                //first call, start generation
                if (!get_transient('wpsi_export_in_progress')) {
                    set_transient('wpsi_export_in_progress', true, 2 * DAY_IN_SECONDS);
                    //cleanup old file
                    $file = $this->filepath();
                    if (file_exists($file)) {
                        wp_delete_file($file);
                    }
                    //get total rows
                    $args = array(
                        'number' => -1,
                        'count' => true,
                        'date_from' => $date_from,
                        'date_to' => $date_to,
                    );

                    $count = WPSI::$search->get_searches_single($args);
                    update_option('wpsi_export_row_count', $count);
                    $progress = 0;
                    update_option('wpsi_export_progress', $progress);
                } else {
                    $args = array(
                        'date_from' => $date_from,
                        'date_to' => $date_to,
                    );
                    $percent = $this->process_csv_chunk($args);
                }

                $data = array(
                    'success' => !$error,
                    'percent' => round($percent, 0),
                    'total' => round($percent, 0),
                );

                if ($percent >= 100) {
                    $data['path'] = $this->fileurl() . "?token=" . time(); // Add timestamp to prevent caching
                }

            }

            $response = json_encode($data);
            header("Content-Type: application/json");
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo $response;
            exit;
        }

        /**
         * process a csv chunk
         * @param array $args
         *
         * @return float
         * @throws Exception
         */

        public function process_csv_chunk($args = array())
        {
            if (!get_transient('wpsi_export_in_progress')) return;

            $progress = get_option('wpsi_export_progress') + 1;
            update_option('wpsi_export_progress', $progress);
            $offset = ($progress - 1) * $this->rows;

            //if ($progress * $this->rows > $count), stop.
            $count = get_option('wpsi_export_row_count');

            if ($count == 0) {
                $percent = 100;
            } else {
                $percent = 100 * (($progress * $this->rows) / $count);
                if ($percent >= 100) $percent = 100;
            }
            if ($percent >= 100) {
                update_option('wpsi_export_progress', 0);
                delete_transient('wpsi_export_in_progress');
            }

            $args['number'] = $this->rows;
            $args['result_count'] = true;
            $args['offset'] = $offset;
            $searches = WPSI::$search->get_searches_single($args);

            //convert to array
            $json = json_encode($searches);
            $searches = json_decode($json, true);

            $this->create_csv_file($searches);
            return $percent;
        }

        /**
         * Get headers from an array
         * @param array $array
         *
         * @return array|bool
         */

        private function parse_headers_from_array($array)
        {
            if (!isset($array[0])) return array();
            $array = $array[0];
            return array_keys($array);
        }

        /**
         * create csv file from array
         *
         * @param $data
         *
         * @throws Exception
         */

        private function create_csv_file($data)
        {
            $delimiter = ",";

            // Initialize WP_Filesystem
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
            global $wp_filesystem;

            if (!$wp_filesystem) {
                return false; // WP_Filesystem unavailable
            }

            $uploads = wp_upload_dir();
            $upload_dir = $uploads['basedir'];
            $wpsi_dir = $upload_dir . "/wpsi";

            // Create directories if they don't exist
            if (!$wp_filesystem->exists($upload_dir)) {
                $wp_filesystem->mkdir($upload_dir);
            }

            if (!$wp_filesystem->exists($wpsi_dir)) {
                $wp_filesystem->mkdir($wpsi_dir);
            }

            // Generate random filename for storage if not exists
            if (!get_option('wpsi_file_name')) {
                $token = str_shuffle(time());
                update_option('wpsi_file_name', $token);
            }

            $filename = get_option('wpsi_file_name');
            $file = $wpsi_dir . "/" . $filename . ".csv";

            // Build CSV content in memory
            $csv_content = '';

            // Create a line with headers
            $headers = $this->parse_headers_from_array($data);
            $has_time_column = array_search('time', $headers);
            if ($has_time_column !== FALSE) {
                $headers[] = __("date", "wp-search-insights");
            }

            // Add headers to CSV content
            $csv_content .= $this->build_csv_line($headers, $delimiter);

            // Process data rows
            $data = array_map(array($this, 'localize_date'), $data);
            foreach ($data as $line) {
                $line = array_map('sanitize_text_field', $line);
                $csv_content .= $this->build_csv_line($line, $delimiter);
            }

            // Write the complete CSV content to the file
            // Use 'a' mode (append) by checking if file exists first
            if ($wp_filesystem->exists($file)) {
                $existing_content = $wp_filesystem->get_contents($file);
                $csv_content = $existing_content . $csv_content;
            }

            $wp_filesystem->put_contents($file, $csv_content, FS_CHMOD_FILE);

            return $file;
        }

        /**
         * Helper method to build a CSV line with proper escaping
         *
         * @param array $fields Array of field values
         * @param string $delimiter The field delimiter
         * @return string The CSV line with a trailing newline
         */
        private function build_csv_line($fields, $delimiter = ',')
        {
            $enclosure = '"';
            $escaped_fields = array();

            foreach ($fields as $field) {
                // Escape the enclosure character with another enclosure character
                $escaped_field = str_replace($enclosure, $enclosure . $enclosure, $field);

                // Enclose fields that contain the delimiter, enclosure, or newlines
                if (
                    strpos($field, $delimiter) !== false ||
                    strpos($field, $enclosure) !== false ||
                    strpos($field, "\n") !== false ||
                    strpos($field, "\r") !== false
                ) {
                    $escaped_field = $enclosure . $escaped_field . $enclosure;
                }

                $escaped_fields[] = $escaped_field;
            }

            return implode($delimiter, $escaped_fields) . "\n";
        }

        /**
         * Get a localized date for this row
         * @param $row
         *
         * @return mixed
         */
        public function localize_date($row)
        {
            if (isset($row['time'])) {
                $this->add_nice_time_header = true;
                $row['nice_time'] = WPSI::$admin->localize_date($row['time']);
            }
            return $row;
        }

        /**
         * Get a filepath
         * @return string
         */

        private function filepath()
        {
            $uploads = wp_upload_dir();
            $upload_dir = $uploads['basedir'];
            return $upload_dir . "/wpsi/" . get_option('wpsi_file_name') . ".csv";
        }

        /**
         * Get a file URL
         * @return string
         */
        private function fileurl()
        {
            $uploads = wp_upload_dir();
            return $uploads['baseurl'] . "/wpsi/" . get_option('wpsi_file_name') . ".csv";
        }

    }
}
