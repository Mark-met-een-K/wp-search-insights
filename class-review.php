<?php
defined('ABSPATH') or die("you do not have acces to this page!");

if (!class_exists("wpsi_review")) {
	class wpsi_review
	{
		private static $_this;
		public $searchcount;
		public $minimum_count=100;

		function __construct()
		{
			if (isset(self::$_this))
				wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.', 'complianz-gdpr'), get_class($this)));

			self::$_this = $this;

			register_activation_hook(__FILE__, array($this, 'set_activation_time_stamp'));
			//show review notice, only to free users
			if (!defined("wpsi_premium") && !is_multisite()) {
				$this->searchcount = get_transient('wpsi_total_searchcount');
				if (!$this->searchcount){
					$items = WP_SEARCH_INSIGHTS()->WP_Search_Insights_Search->get_searches_single();
					$this->searchcount = count($items);
					set_transient('wpsi_total_searchcount', $this->searchcount, 'DAY_IN_SECONDS');
				}
				$notice_has_been_shown= get_option('wpsi_review_notice_shown');
				$over_one_month_old = get_option('wpsi_activation_time') && (get_option('wpsi_activation_time') < strtotime("-1 month"));
				if (!$notice_has_been_shown && $over_one_month_old ){
					add_action('wp_ajax_dismiss_review_notice', array($this, 'dismiss_review_notice_callback'));
					add_action('admin_notices', array($this, 'show_leave_review_notice'));
					add_action('admin_print_footer_scripts', array($this, 'insert_dismiss_review'));
				}


			}

		}


		/**
         * Set activation time of plugin
		 * @param $networkwide
		 */
		public function set_activation_time_stamp($networkwide)
		{
			update_option('wpsi_activation_time', time());
		}


		static function this()
		{
			return self::$_this;
		}

		public function show_leave_review_notice()
		{

			/*
			 * Prevent notice from being shown on Gutenberg page, as it strips off the class we need for the ajax callback.
			 *
			 * */
			$screen = get_current_screen();
			if ( $screen->parent_base === 'edit' ) return;

			?>
			<div id="message" class="updated fade notice is-dismissible wpsi-review really-simple-plugins">
                <?php if ($this->searchcount>$this->minimum_count){?>
				<p><?php printf(__('Hi, WP Search Insights has given you insights on over %s searches on your site already, awesome! If you have a moment, please consider leaving a review on WordPress.org to spread the word. We greatly appreciate it! If you have any questions or feedback, leave us a %smessage%s.', 'wp-search-insights'), $this->searchcount, '<a href="https://complianz.io/contact" target="_blank">', '</a>'); ?></p>
				<?php } else { ?>
                    <p><?php printf(__('Hi, you have been using WP Search Insights for a month now, awesome! If you have a moment, please consider leaving a review on WordPress.org to spread the word. We greatly appreciate it! If you have any questions or feedback, leave us a %smessage%s.', 'wp-search-insights'), '<a href="https://complianz.io/contact" target="_blank">', '</a>'); ?></p>
                <?php } ?>
                <i>- Mark</i>
				<ul style="margin-left: 30px; list-style: square;">
					<li><p style="margin-top: -5px;"><a target="_blank"
					                                    href="https://wordpress.org/support/plugin/wp-search-insights/reviews/#new-post"><?php _e('Leave a review', 'wp-search-insights'); ?></a>
						</p></li>
					<li><p style="margin-top: -5px;"><a href="#"
					                                    id="maybe-later"><?php _e('Maybe later', 'wp-search-insights'); ?></a>
						</p></li>
					<li><p style="margin-top: -5px;"><a href="#"
					                                    class="review-dismiss"><?php _e('I already placed a review', 'wp-search-insights'); ?></a>
						</p></li>
				</ul>
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
			$ajax_nonce = wp_create_nonce("wpsi_dismiss_review");
			?>s
			<script type='text/javascript'>
                jQuery(document).ready(function ($) {
                    $(".wpsi-review.notice.is-dismissible").on("click", ".notice-dismiss", function (event) {
                        rsssl_dismiss_review('dismiss');
                    });
                    $(".wpsi-review.notice.is-dismissible").on("click", "#maybe-later", function (event) {
                        rsssl_dismiss_review('later');
                        $(this).closest('.wpsi-review').remove();
                    });
                    $(".wpsi-review.notice.is-dismissible").on("click", ".review-dismiss", function (event) {
                        rsssl_dismiss_review('dismiss');
                        $(this).closest('.wpsi-review').remove();
                    });

                    function rsssl_dismiss_review(type) {
                        var data = {
                            'action': 'dismiss_review_notice',
                            'type': type,
                            'token': '<?php echo $ajax_nonce; ?>'
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
			check_ajax_referer('wpsi_dismiss_review', 'token');

			$type = isset($_POST['type']) ? $_POST['type'] : false;

			if ($type === 'dismiss') {
				update_option('wpsi_review_notice_shown',true);
			}
			if ($type === 'later') {
				update_option('wpsi_activation_time', time());
			}

			wp_die(); // this is required to terminate immediately and return a proper response
		}
	}
}