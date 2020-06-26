<?php
add_action('admin_init', 'wpsi_maybe_set_custom_search_parameter');
function wpsi_maybe_set_custom_search_parameter(){
	if (defined('toolset_constant_here')) {
		update_option('wpsi_custom_search_parameter', 'wpv_post_search');
	}
}