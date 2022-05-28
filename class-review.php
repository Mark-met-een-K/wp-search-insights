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
				wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.', 'wpsi-search-insights'), get_class($this)));

			self::$_this = $this;
			//show review notice, only to free users
			if (!defined("wpsi_premium") && !is_multisite()) {
			    if (!get_option('wpsi_activation_time')){
				    update_option('wpsi_activation_time', time());
			    }
				$this->searchcount = get_transient('wpsi_total_searchcount');
				if (!$this->searchcount){
				    if (!get_option('wpsi_database_created') ) return;
					$items = WPSI::$search->get_searches_single();
					$this->searchcount = count($items);
					set_transient('wpsi_total_searchcount', $this->searchcount, 'DAY_IN_SECONDS');
				}
				$notice_has_been_shown= get_option('wpsi_review_notice_shown');
				$over_one_month_old = get_option('wpsi_activation_time') && (get_option('wpsi_activation_time') < strtotime("-1 month"));
				if (!$notice_has_been_shown && $over_one_month_old ){
					add_action('wp_ajax_wpsi_dismiss_review_notice', array($this, 'dismiss_review_notice_callback'));
					add_action('admin_notices', array($this, 'show_leave_review_notice'));
					add_action('admin_print_footer_scripts', array($this, 'insert_dismiss_review'));
				}
			}

			add_action('admin_init', array($this, 'process_get_review_dismiss' ));


		}


		static function this()
		{
			return self::$_this;
		}

		/**
		 * Show a notice to the user
		 */
		public function show_leave_review_notice()
		{
			if (isset( $_GET['wpsi_dismiss_review'] ) ) return;

			/**
			 * Prevent notice from being shown on Gutenberg page, as it strips off the class we need for the ajax callback.
			 *
			 * */
			$screen = get_current_screen();
			if ( $screen->parent_base === 'edit' ) return;

			?>
            <style>
                .wpsi-container {
                    display: flex;
                    padding:12px;
                }
                .wpsi-container .dashicons {
                    margin-left:10px;
                    margin-right:5px;
                }
                .wpsi-review-image img{
                   margin-top:0.5em;
                }
                .wpsi-buttons-row {
                    margin-top:10px;
                    display: flex;
                    align-items: center;
                }
            </style>
			<div id="message" class="updated fade notice is-dismissible wpsi-review really-simple-plugins" style="border-left:4px solid #333">
                <div class="wpsi-container">
                    <div class="wpsi-review-image"><img width=80px" src="<?php echo wpsi_url?>/assets/images/logo.png" alt="review-logo"></div>
                <div style="margin-left:30px">
                <?php if ($this->searchcount>$this->minimum_count){?>
				<p><?php printf(__('Hi, WP Search Insights has given you insights on over %s searches on your site already, awesome! If you have a moment, please consider leaving a review on WordPress.org to spread the word. We greatly appreciate it! If you have any questions or feedback, leave us a %smessage%s.', 'wp-search-insights'), $this->searchcount, '<a href="wpsi.io/contact" target="_blank">', '</a>'); ?></p>
				<?php } else { ?>
                    <p><?php printf(__('Hi, you have been using WP Search Insights for a month now, awesome! If you have a moment, please consider leaving a review on WordPress.org to spread the word. We greatly appreciate it! If you have any questions or feedback, leave us a %smessage%s.', 'wp-search-insights'), '<a href="https://wpsi.io/contact " target="_blank">', '</a>'); ?></p>
                <?php } ?>
                <i class="wpsi-signature">- Mark</i>

                <div class="wpsi-buttons-row">
                    <a class="button button-primary" target="_blank"
                       href="https://wordpress.org/support/plugin/wp-search-insights/reviews/#new-post"><?php _e('Leave a review', 'wp-search-insights'); ?></a>

                    <div class="dashicons dashicons-calendar"></div><a href="#" id="maybe-later"><?php _e('Maybe later', 'wp-search-insights'); ?></a>

                    <div class="dashicons dashicons-no-alt"></div><a href="<?php echo add_query_arg(array('page'=>'wpsi-settings-page', 'wpsi_dismiss_review'=>1), admin_url('tools.php') )?>" class="review-dismiss"><?php _e('Don\'t show again', 'wp-search-insights'); ?></a>
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
			$ajax_nonce = wp_create_nonce("wpsi_dismiss_review");
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
			if (isset($_POST['type'])) {
				$type = sanitize_title( $_POST['type'] );
            } else {
			    $type = false;
            }

			if ($type === 'dismiss') {
				update_option('wpsi_review_notice_shown',true);
			}
			if ($type === 'later') {
				update_option('wpsi_activation_time', time());
			}

			wp_die(); // this is required to terminate immediately and return a proper response
		}

		/**
		 * Dismiss review notice with get, which is more stable
		 */

		public function process_get_review_dismiss(){
			if (isset( $_GET['wpsi_dismiss_review'] ) ){
				update_option( 'wpsi_review_notice_shown', true );
			}
		}
	}
}