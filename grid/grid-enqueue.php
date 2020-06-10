<?php
defined( 'ABSPATH' ) or die( "you do not have access to this page!" );

		add_action('admin_enqueue_scripts',  'wpsi_enqueue_assets');
		function wpsi_enqueue_assets($hook)
		{
			global $search_insights_settings_page;
			// Enqueue assest when on index.php (WP dashboard) or plugins settings page

			if ($hook == $search_insights_settings_page) {

				wp_register_style('wpsi-muuri',
					trailingslashit(wpsi_url) . "grid/css/muuri.css", "",
					wpsi_version);
				wp_enqueue_style('wpsi-muuri');

				wp_register_script('wpsi-muuri',
					trailingslashit(wpsi_url)
					. 'grid/js/muuri.min.js', array("jquery"), wpsi_version);
				wp_enqueue_script('wpsi-muuri');

			wp_register_script('wpsi-grid',
				trailingslashit(wpsi_url)
				. 'grid/js/grid.js', array("jquery", "wpsi-muuri"), wpsi_version);
			wp_enqueue_script('wpsi-grid');

			}
		}


