<?php
/*
Plugin Name: Relevanssi Search Results Cache
Description: Saves each front-end Relevanssi search results page for 1 hour. Does not modify Relevanssi. Only works in conjunction with Relevanssi.
Version: 1.0.0
Author: Reza Consulting Inc.
*/
if (!defined('ABSPATH')) exit;

function dw_search_cache_state($query, $set = null) {
	static $state = array();
	$id = spl_object_id($query);
	if ($set !== null) $state[$id] = $set;
	return isset($state[$id]) ? $state[$id] : null;
}

add_filter('posts_pre_query', function ($posts, $query) {
	if (is_admin() || is_user_logged_in() || !($query instanceof WP_Query) || !$query->is_main_query() || !$query->is_search() || !function_exists('relevanssi_query')) {
		return $posts;
	}
	// $_GET is part of the key so the Date/Relevance dropdown never shares a saved page.
	$key = 'dw_search_' . md5(serialize(array($query->query_vars, $_GET)));
	$cached = get_transient($key);
	dw_search_cache_state($query, array('key' => $key, 'cached' => is_array($cached) ? $cached : null));
	return $posts;
}, 1, 2);

add_filter('relevanssi_search_ok', function ($ok, $query = null) {
	if ($query instanceof WP_Query) {
		$state = dw_search_cache_state($query);
		if ($state && $state['cached'] !== null) return false;
	}
	return $ok;
}, 10, 2);

add_filter('posts_pre_query', function ($posts, $query) {
	if (!($query instanceof WP_Query)) return $posts;
	$state = dw_search_cache_state($query);
	if (!$state) return $posts;
	if ($state['cached'] !== null) {
		$query->found_posts   = $state['cached']['found'];
		$query->max_num_pages = $state['cached']['pages'];
		return $state['cached']['posts'];
	}
	if (is_array($posts)) {
		set_transient($state['key'], array(
			'posts' => $posts,
			'found' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		), HOUR_IN_SECONDS);
	}
	return $posts;
}, 100, 2);
