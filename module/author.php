<?php
/**
 * @package WP Content Aware Engine
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 *
 * Author Module
 * 
 * Detects if current content is:
 * a) post type written by any or specific author
 * b) any or specific author archive
 *
 */
class WPCAModule_author extends WPCAModule_Base {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('author',__('Authors',WPCA_DOMAIN));
		$this->placeholder = __('All Authors',WPCA_DOMAIN);
		$this->default_value = $this->id;
	}
	
	/**
	 * Determine if content is relevant
	 *
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
		return (is_singular() && !is_front_page()) || is_author();
	}

	/**
	 * Get data from context
	 *
	 * @global WP_Post $post
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {
		global $post;
		return array(
			$this->id,
			(string)(is_singular() ? $post->post_author : get_query_var('author'))
		);			
	}

	/**
	 * Get authors
	 *
	 * @since  1.0
	 * @param  array     $args
	 * @return array
	 */
	protected function _get_content($args = array()) {
		$args = wp_parse_args($args, array(
			'number'  => 20,
			'fields'  => array('ID','display_name'),
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'paged'   => 1,
			'search'  => '',
			'include' => ''
		));
		$args['offset'] = ($args['paged']-1)*$args['number'];
		unset($args['paged']);

		if($args['search']) {
			$args['search'] = '*'.$args['search'].'*';
			//display_name does not seem to be recognized, add it anyway
			$args['search_columns'] = array( 'user_nicename', 'user_login', 'display_name' );
			add_filter( 'user_search_columns',
				array($this,'filter_search_column'), 10, 3 );
		}

		$user_query = new WP_User_Query(  $args );

		$author_list = array();
		if($user_query->results) {
			foreach($user_query->get_results()  as $user) {
				$author_list[$user->ID] = $user->display_name;
			}
		}
		return $author_list;
	}

	/**
	 * Filter to definitely add display_name to search_columns
	 * WP3.6+
	 *
	 * @since 1.0
	 * @param   array      $search_columns
	 * @param   string     $search
	 * @param   WP_User    $user
	 * @return  array
	 */
	function filter_search_column($search_columns, $search, $user) {
		$search_columns[] = 'display_name';
		return $search_columns;
	}

}
