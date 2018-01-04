<?php
/**
 * @package WP Content Aware Engine
 * @author Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 * @copyright 2018 by Joachim Jensen
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
			$args['search_columns'] = array( 'user_nicename', 'user_login', 'display_name' );
		}

		$user_query = new WP_User_Query(  $args );

		$author_list = array();

		if($user_query->results) {
			foreach($user_query->get_results()  as $user) {
				$author_list[] = array(
					'id'   => $user->ID,
					'text' => $user->display_name
				);
			}
		}
		return $author_list;
	}

}
