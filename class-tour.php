<?php
if (!defined('ABSPATH')) die('you do not have access to this page!');

class wpsi_tour {

	private static $_this;

	public $capability = 'activate_plugins';

	function __construct()
	{

		if (isset(self::$_this)) {
			wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.',
				'wp-search-insights'), get_class($this)));
		}

		self::$_this = $this;

		add_action( 'init', array( $this, 'listen_for_cancel_tour') );

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

	public function init() {
		if ( ! current_user_can( $this->capability ) ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

	}

	public function enqueue_assets($hook) {

		if ( ! get_option( 'wpsi_tour_cancelled' ) ) {

			global $search_insights_settings_page;
			if ( $hook == 'plugins.php' || $hook == $search_insights_settings_page || $hook=='index.php' ) {
				wp_register_script( 'wpsi-tether',
					trailingslashit( wp_search_insights_url )
					. 'assets/includes/tether/tether.min.js', "", wp_search_insights_version );
				wp_enqueue_script( 'wpsi-tether' );

				wp_register_script( 'wpsi-shepherd',
					trailingslashit( wp_search_insights_url )
					. 'assets/includes/tether-shepherd/shepherd.min.js', "", wp_search_insights_version );
				wp_enqueue_script( 'wpsi-shepherd' );

				wp_register_style( 'wpsi-shepherd',
					trailingslashit( wp_search_insights_url ) . "assets/css/tether-shepherd/shepherd-theme-arrows.min.css", "",
					wp_search_insights_version );
				wp_enqueue_style( 'wpsi-shepherd' );

				wp_register_style( 'wpsi-shepherd-tour',
					trailingslashit( wp_search_insights_url ) . "assets/css/wpsi-tour.min.css", "",
					wp_search_insights_version );
				wp_enqueue_style( 'wpsi-shepherd-tour' );

				wp_register_script( 'wpsi-shepherd-tour',
					trailingslashit( wp_search_insights_url )
					. 'assets/js/wpsi-tour.js', array('jquery'), wp_search_insights_version );
				wp_enqueue_script( 'wpsi-shepherd-tour' );

				$logo = '<span class="wpsi-tour-logo"><img class="wpsi-tour-logo" style="width: 70px; height: 70px;" src="' . wp_search_insights_url . 'assets/images/logo.png"></span>';

				wp_localize_script( 'wpsi-shepherd-tour', 'search_insights_tour_ajax',
					array(
						// General
						'ajaxurl'       => admin_url( 'admin-ajax.php' ),
						'token'         => wp_create_nonce( 'search_insights_nonce' ),
						'nextBtnText'   => __( "Next", "wp-search-insights" ),
						'endTour'       => __( "End tour", "wp-search-insights" ),
						'backBtnText'   => __( "Previous", "wp-search-insights" ),
						// Plugins overview tour start
						'po_title'      => __( 'Welcome to WP Search Insights', 'wp-search-insights' ),
						'po_text'       => "<div class='wpsi-tour-logo-text'>$logo<span class='wpsi-tour-text'>"
						                    .  __( 'WP Search Insights will give you insights into your visitor\'s search behaviour. Let\'s take a look at the dashboard!', 'wp-search-insights' ) . "</span></div>",
						'startTourtext' => __( "Start tour", "wp-search-insights" ),
						'linkToDashboard'  => admin_url( "index.php" ),

						//show dashboard widget
						'widget_title'      => __( 'Dashboard widget', 'wp-search-insights' ),
						'widget_text'       => "<div class='wpsi-tour-logo-text'>$logo<span class='wpsi-tour-text'>"
						                    .  __( 'Your dashboard widget will show you the top 5 searches without results and top 5 searches overall. You can quickly access your dashboard from here.', 'wp-search-insights' ) . "</span></div>",
						'linkToSettings'        => admin_url( "tools.php?page=wpsi-settings-page" ),
						// Dashboard step 2
						'dashboard_title' => __( "Popular searches", "wp-search-insights" ),
						'dashboard_text'=> "<div class='wpsi-tour-logo-text'>$logo<span class='wpsi-tour-text'><p>" . __('WP Search Insights is recording your searches. The most popular search terms will be displayed here.', 'wp-search-insights') . "</p></span></div>",
						// Main tour step 3
						'recent_searches_title' => __( "Recent searches", "wp-search-insights" ),
						'recent_searches_text'  => "<div class='wpsi-tour-logo-text'>$logo<span class='wpsi-tour-text'>" . __('The recent searches section shows all recorded searches. It also displays when the search was made and from which post or page it originated.' ,'wp-search-insights') . "</span></div>",
						// Main tour step 4
						'settings_title' => __('Configure WP Search Insights','wp-search-insights'),
						'settings_text'=> "<div class='wpsi-tour-logo-text'>$logo<span class='wpsi-tour-text'>" . __('Exclude admin searches and configure character length for short and longer queries.', 'wp-search-insights') . "</span></div>",
						// Main tour step 5
						'finish_title' => __('Start using WP Search Insights!','wp-search-insights'),
						'finish_text'=> "<div class='wpsi-tour-logo-text'>$logo<span class='wpsi-tour-text'>" . __('The tour has ended. Please come back in a few days to see the results.', 'wp-search-insights') . "</span></div>",

					) );
			}
		}
	}

	/**
	 *
	 * @since 1.0
	 *
	 * When the tour is cancelled, a post will be sent. Listen for post and update tour cancelled option.
	 *
	 */

	public function listen_for_cancel_tour() {

		if (!isset($_POST['wpsi_cancel_tour']) || !wp_verify_nonce($_POST['token'], 'search_insights_nonce') ) return;

		update_option('wpsi_tour_cancelled', true);

	}
}
