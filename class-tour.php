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

			if ( $hook == 'plugins.php' || $hook == $search_insights_settings_page ) {
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
					. 'assets/js/wpsi-tour.js', "", wp_search_insights_version );
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
						'po_title'      => __( 'Welcome to Search Insights', 'wp-search-insights' ),
						'po_text'       => "<div class='wpsi-tour-logo-text'>$logo<span class='wpsi-tour-text'><b>"
						                   . __( 'Welcome to Search Insights', 'wp-search-insights' ) . "</b>" .  __( 'get insight in what your visitors are looking for!', 'wp-search-insights' ) . "</span></div>",
						'startTourtext' => __( "Start tour", "wp-search-insights" ),
						'linkTo'        => admin_url( "tools.php?page=wpsi-settings-page" ),
						// Dashboard step 1
						'dashboard_title' => __( "Find out what your visitors are looking for", "wp-search-insights" ),
						'dashboard_text'=> "<div class='wpsi-tour-logo-text'>$logo<span class='wpsi-tour-text'><p>" . __('Search Insights is now tracking search activity on your site. The most popular search terms can be found in the sortable table above.', 'wp-search-insights') . "</p></span></div>",
						// Main tour step 2
						'recent_searches_title' => __( "Never miss a search", "wp-search-insights" ),
						'recent_searches_text'  => "<div class='wpsi-tour-logo-text'>$logo<span class='wpsi-tour-text'>" . __('The recent searches section shows all recorder searches. It also displays when the search was made and from which post or page it originated.' ,'wp-search-insights') . "</span></div>",
						// Main tour step 3
						'settings_title' => 'Configure Search Insights',
						'settings_text'=> "<div class='wpsi-tour-logo-text'>$logo<span class='wpsi-tour-text'>" . __('The plugin can be configured to your liking in the settings menu. Exclude searches made by administrators, short or long terms and more.', 'wp-search-insights') . "</span></div>",
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
