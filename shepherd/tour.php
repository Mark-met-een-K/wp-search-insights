<?php
if (!defined('ABSPATH')) die('you do not have access to this page!');

class wpsi_tour {

	private static $_this;

	public $capability = 'activate_plugins';
	public $url;
	public $version;

	function __construct()
	{
		if (isset(self::$_this)) {
			wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.',
				'wp-search-insights'), get_class($this)));
		}

		self::$_this = $this;

		$this->url = wpsi_url.'/shepherd';
		$this->version = wpsi_version;
		add_action( 'init', array( $this, 'listen_for_cancel_tour') );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	static function this()
	{
		return self::$_this;
	}

	public function enqueue_assets($hook) {
		if ( ! get_option( 'wpsi_tour_cancelled' ) ) {

			global $search_insights_settings_page;
			if ( $hook == 'plugins.php' || $hook == $search_insights_settings_page || $hook=='index.php' ) {
				wp_register_script( 'wpsi-tether',
					trailingslashit( $this->url )
					. '/tether/tether.min.js', "", $this->version );
				wp_enqueue_script( 'wpsi-tether' );

				wp_register_script( 'wpsi-shepherd',
					trailingslashit( $this->url )
					. '/tether-shepherd/shepherd.min.js', "", $this->version );
				wp_enqueue_script( 'wpsi-shepherd' );

				wp_register_style( 'wpsi-shepherd',
					trailingslashit( $this->url ) . "/css/shepherd-theme-arrows.min.css", "",
					$this->version );
				wp_enqueue_style( 'wpsi-shepherd' );

				wp_register_style( 'wpsi-shepherd-tour',
					trailingslashit( $this->url ) . "/css/wpsi-tour.min.css", "",
					$this->version );
				wp_enqueue_style( 'wpsi-shepherd-tour' );

				wp_register_script( 'wpsi-shepherd-tour',
					trailingslashit( $this->url )
					. '/js/wpsi-tour.js', array('jquery'), $this->version );
				wp_enqueue_script( 'wpsi-shepherd-tour' );

				$logo = '<span class="wpsi-tour-logo"><img class="wpsi-tour-logo" style="width: 70px; height: 70px;" src="' . wpsi_url . 'assets/images/logo.png"></span>';
				$html = '<div class="wpsi-tour-logo-text">'.$logo.'<span class="wpsi-tour-text">{content}</span></div>';

				wp_localize_script( 'wpsi-shepherd-tour', 'wpsi_tour',
					array(
						'ajaxurl'       => admin_url( 'admin-ajax.php' ),
						'html'          => $html,
						'token'         => wp_create_nonce( 'search_insights_nonce' ),
						'nextBtnText'   => __( "Next", "wp-search-insights" ),
						'backBtnText'   => __( "Previous", "wp-search-insights" ),
						'startTour' => __( "Start tour", "wp-search-insights" ),
						'endTour'       => __( "End tour", "wp-search-insights" ),
						'steps'         => array(
							1 => array(
								'title' => __( 'Welcome to WP Search Insights', 'wp-search-insights' ),
					            'text'  => __( 'WP Search Insights will give you insights into your visitor\'s search behavior. Let\'s take a look at the dashboard!', 'wp-search-insights' ) ,
								'link'  => admin_url( "index.php" ),
							),
							2 => array(
								'title' => __( 'Dashboard widget', 'wp-search-insights' ),
								'text'  => __( 'Your dashboard widget will show you the top 5 searches without results and top 5 searches overall. You can quickly access your dashboard from here.', 'wp-search-insights' ),
								'link'  => admin_url( "tools.php?page=wpsi-settings-page" ),
							),
                            3 => array(
                                'title' => __( "Recent searches", "wp-search-insights" ),
                                'text'  => __('The recent searches section shows all recorded searches. It also displays when the search was made and from which post or page it originated.' ,'wp-search-insights') ,
                            ),
							4 => array(
								'title' => __( "Popular searches", "wp-search-insights" ),
								'text'  => __('The most popular search terms will be displayed here.', 'wp-search-insights') ,
							),
							5 => array(
								'title' => __('Configure WP Search Insights','wp-search-insights'),
								'text'  => __('Exclude admin searches and configure character length for short and longer queries.', 'wp-search-insights') ,
							),
							6 => array(
								'title' => __('Start using WP Search Insights!','wp-search-insights'),
								'text'  => __('The tour has ended. Please come back in a few days to see the results.', 'wp-search-insights'),
							),
						),


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
