<?php
defined( 'ABSPATH' ) or die( "you do not have access to this page!" );

if ( ! class_exists( 'WPSI_GRID' ) ) {
	class WPSI_GRID {

		private static $_this;

		public $capability = 'activate_plugins';

		function __construct()
		{
			if (isset(self::$_this)) {
				wp_die(sprintf(__('%s is a singleton class and you cannot create a second instance.',
					'wp-search-insights'), get_class($this)));
			}

			self::$_this = $this;
			add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

		}

		static function this()
		{
			return self::$_this;
		}


		public function enqueue_assets($hook)
		{
			global $search_insights_settings_page;
			// Enqueue assest when on index.php (WP dashboard) or plugins settings page

			if ($hook == $search_insights_settings_page) {

				wp_register_style('wpsi-muuri',
					trailingslashit(wp_search_insights_url) . "grid/css/muuri.css", "",
					wp_search_insights_version);
				wp_enqueue_style('wpsi-muuri');

				wp_register_script('wpsi-muuri',
					trailingslashit(wp_search_insights_url)
					. 'grid/js/muuri.min.js', array("jquery"), wp_search_insights_version);
				wp_enqueue_script('wpsi-muuri');

			wp_register_script('wpsi-grid',
				trailingslashit(wp_search_insights_url)
				. 'grid/js/grid.js', array("jquery", "wpsi-muuri"), wp_search_insights_version);
			wp_enqueue_script('wpsi-grid');

			}
		}
	}
}//Class closure

