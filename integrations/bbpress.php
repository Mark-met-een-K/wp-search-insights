<?php
defined('ABSPATH') or die("you do not have access to this page!");


/**
 * Process BBPRess searches
 * @param $posts
 * @param $query
 *
 * @return mixed
 */

function wpsi_track_bbpress($posts, $query){
	$result_count = empty($posts) ? 0 : count($posts);

	if (isset($query->query_vars['s'])){
		$search_terms = $query->query_vars['s'];
		WPSI::$search->process_search_term( $search_terms , $result_count, 'bbpress');
	}
	return $posts;
}
add_filter( 'bbp_has_search_results', 'wpsi_track_bbpress', 10, 2);
