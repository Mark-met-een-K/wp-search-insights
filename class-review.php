<?php
defined('ABSPATH') or die("you do not have access to this page!");

if (!class_exists("wpsi_review")) {
    class wpsi_review
    {
        private static $_this;
        public $searchcount;
        public $minimum_count = 25;

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

            add_action('init', array($this, 'maybe_show_review_notice'), 15);
            add_action('admin_init', array($this, 'process_get_review_dismiss'));
        }

        static function this()
        {
            return self::$_this;
        }

        /**
         * Set up review notice if conditions are met
         */
        public function maybe_show_review_notice() {
            // Only for free users
            if (defined("WPSI_PRO") || is_multisite()) {
                return;
            }

            if (!get_option('wpsi_activation_time')) {
                update_option('wpsi_activation_time', time());
            }

            $this->searchcount = get_transient('wpsi_total_searchcount');
            if (!$this->searchcount) {
                // Safety check - skip if database tables aren't created yet
                if (!get_option('wpsi_database_created')) return;

                $items = WPSI::$search->get_searches_single();
                $this->searchcount = count($items);
                set_transient('wpsi_total_searchcount', $this->searchcount, DAY_IN_SECONDS);
            }

            $notice_has_been_shown = get_option('wpsi_review_notice_shown');
            $over_one_month_old = get_option('wpsi_activation_time') &&
                (get_option('wpsi_activation_time') < strtotime("-1 month"));

            if (!$notice_has_been_shown && $over_one_month_old) {
                add_action('wp_ajax_wpsi_dismiss_review_notice', array($this, 'dismiss_review_notice_callback'));
                add_action('admin_notices', array($this, 'show_leave_review_notice'));
                add_action('admin_print_footer_scripts', array($this, 'insert_dismiss_review'));
            }
        }

        /**
         * Show a notice to the user
         */
        public function show_leave_review_notice()
        {
            if (isset($_GET['wpsi_dismiss_review'], $_GET['wpsi_review_nonce'])
                && wp_verify_nonce(sanitize_key($_GET['wpsi_review_nonce']), 'wpsi_dismiss_review_nonce')) {
                return;
            }

            // Prevent notice from being shown on Gutenberg page, as it strips off the class we need for the ajax callback.
            $screen = get_current_screen();
            if ($screen->parent_base === 'edit') return;

            ?>
            <style>
                .wpsi-container {
                    display: flex;
                    padding: 12px;
                }

                .wpsi-container .dashicons {
                    margin-left: 10px;
                    margin-right: 5px;
                }

                .wpsi-review-image img {
                    margin-top: 0.5em;
                }

                .wpsi-buttons-row {
                    margin-top: 10px;
                    display: flex;
                    align-items: center;
                }
            </style>
            <div id="message" class="updated fade notice is-dismissible wpsi-review really-simple-plugins"
                 style="border-left:4px solid #333">
                <div class="wpsi-container">
                    <div class="wpsi-review-image"><img width=80px"
                                                        src="<?php echo esc_url(wpsi_url) ?>/assets/images/logo.png"
                                                        alt="review-logo"></div>
                    <div style="margin-left:30px">
                        <?php if ($this->searchcount > $this->minimum_count) { ?>
                            <p>
                                <?php
                                printf(
                                /* translators: %1$s: number of searches, %2$s: opening link tag, %3$s: closing link tag */
                                    esc_html__('Hi, Search Insights has given you insights on over %1$s searches on your site already, awesome! If you have a moment, please consider leaving a review on WordPress.org to spread the word. We greatly appreciate it! If you have any questions or feedback, leave us a %2$smessage%3$s.', 'wp-search-insights'),
                                    absint($this->searchcount),
                                    '<a href="' . esc_url('https://wpsi.io/contact') . '" target="_blank">',
                                    '</a>'
                                );
                                ?>
                            </p>
                        <?php } else { ?>
                            <p>
                                <?php
                                printf(
                                /* translators: %1$s: opening link tag, %2$s: closing link tag */
                                    esc_html__('Hi, you have been using Search Insights for a month now, awesome! If you have a moment, please consider leaving a review on WordPress.org to spread the word. We greatly appreciate it! If you have any questions or feedback, leave us a %1$smessage%2$s.', 'wp-search-insights'),
                                    '<a href="' . esc_url('https://wpsi.io/contact') . '" target="_blank">',
                                    '</a>'
                                );
                                ?>
                            </p>
                        <?php } ?>
                        <i class="wpsi-signature">- Mark</i>

                        <div class="wpsi-buttons-row">
                            <a class="button button-primary" target="_blank"
                               href="https://wordpress.org/support/plugin/wp-search-insights/reviews/#new-post"><?php esc_html_e('Leave a review', 'wp-search-insights'); ?></a>

                            <div class="dashicons dashicons-calendar"></div>
                            <a href="#" id="maybe-later"><?php esc_html_e('Maybe later', 'wp-search-insights'); ?></a>

                            <div class="dashicons dashicons-no-alt"></div>
                            <a href="<?php echo esc_url(add_query_arg(array(
                                'page' => 'wpsi-settings-page',
                                'wpsi_dismiss_review' => 1,
                                'wpsi_review_nonce' => wp_create_nonce('wpsi_dismiss_review_nonce')
                            ), admin_url('tools.php'))); ?>" class="review-dismiss">
                                <?php esc_html_e('Don\'t show again', 'wp-search-insights'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php

        }

        /**
         * Insert some ajax script to dismiss the review notice, and stop nagging about it
         *
         * @since  2.0
         *
         * @access public
         *
         * type: dismiss, later
         *
         */

        public function insert_dismiss_review()
        {
            $ajax_nonce = wp_create_nonce("wpsi_dismiss_review_nonce");
            ?>
            <script type='text/javascript'>
                jQuery(document).ready(function ($) {

                    $(".wpsi-review.notice.is-dismissible").on("click", ".notice-dismiss", function (event) {
                        wpsi_dismiss_review('dismiss');
                    });

                    $(".wpsi-review.notice.is-dismissible").on("click", "#maybe-later", function (event) {
                        wpsi_dismiss_review('later');
                        $(this).closest('.wpsi-review').remove();
                    });

                    $(".wpsi-review.notice.is-dismissible").on("click", ".review-dismiss", function (event) {
                        wpsi_dismiss_review('dismiss');
                        $(this).closest('.wpsi-review').remove();
                    });

                    function wpsi_dismiss_review(type) {
                        var data = {
                            'action': 'wpsi_dismiss_review_notice',
                            'type': type,
                            'security': '<?php echo esc_js($ajax_nonce); ?>'
                        };
                        $.post(ajaxurl, data, function (response) {
                        });
                    }
                });
            </script>
            <?php
        }

        /**
         * Process the ajax dismissal of the review message.
         *
         * @since  2.1
         *
         * @access public
         *
         */

        public function dismiss_review_notice_callback()
        {
            // First verify the nonce
            if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_key($_POST['security']), 'wpsi_dismiss_review_nonce')) {
                wp_die();
            }

            if (isset($_POST['type'])) {
                $type = sanitize_title(wp_unslash($_POST['type']));
            } else {
                $type = false;
            }

            if ($type === 'dismiss') {
                update_option('wpsi_review_notice_shown', true);
            }
            if ($type === 'later') {
                update_option('wpsi_activation_time', time());
            }

            wp_die();
        }

        /**
         * Dismiss review notice with get, which is more stable
         */

        public function process_get_review_dismiss()
        {
            // Verify the nonce
            if (isset($_GET['wpsi_dismiss_review'], $_GET['wpsi_review_nonce']) && wp_verify_nonce(sanitize_key($_GET['wpsi_review_nonce']), 'wpsi_dismiss_review_nonce')) {
                update_option('wpsi_review_notice_shown', true);
            }
        }
    }
}
