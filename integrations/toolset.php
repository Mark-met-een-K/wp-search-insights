<?php
add_action('admin_init', 'wpsi_maybe_set_custom_search_parameter');
function wpsi_maybe_set_custom_search_parameter(){
	if (defined('toolset_constant_here')) {
		update_option('wpsi_custom_search_parameter', 'wpv_post_search');
	}
}

add_filter('wpsi_get_caller_by_search_parameter', 'wpsi_add_toolset_search_parameter_caller', 10, 2);
function wpsi_add_toolset_search_parameter_caller($caller, $search_parameter){
	if ($search_parameter ===  'wpv_post_search') {
		$caller = 'toolset';
	}
	return $caller;
}