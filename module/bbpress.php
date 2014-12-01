<?php
/**
 * @package WP Content Aware Engine
 * @version 1.0
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */

if (!defined('WPCACore::VERSION')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit;
}

/**
 *
 * bbPress Module
 * 
 * Detects if current content is:
 * a) any or specific bbpress user profile
 *
 */
class WPCAModule_bbpress extends WPCAModule_author {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->id = 'bb_profile';
		$this->name = __('bbPress User Profiles',WPCACore::DOMAIN);
		
		add_filter('cas-db-where-post_types', array(&$this,'add_forum_dependency'));

		if(is_admin()) {
			add_action('wp_ajax_cas-autocomplete-'.$this->id, array(&$this,'ajax_content_search'));
		}
		
	}
	
	/**
	 * Determine if content is relevant
	 * @return boolean 
	 */
	public function in_context() {
		return function_exists('bbp_is_single_user') ? bbp_is_single_user() : false;
	}

	/**
	 * Get data from context
	 * @author Joachim Jensen <jv@intox.dk>
	 * @since  2.0
	 * @return array
	 */
	public function get_context_data() {
		$data = array($this->id);
		if(function_exists('bbp_get_displayed_user_id')) {
			$data[] = bbp_get_displayed_user_id();
		}
		return $data;
	}
	
	/**
	 * Sidebars to be displayed with forums will also 
	 * be dislpayed with respective topics and replies
	 * @param  string $where 
	 * @return string 
	 */
	public function add_forum_dependency($where) {
		if(is_singular(array('topic','reply'))) {
			$data = array(
				get_post_type(),
				get_the_ID(),
				'forum'
			);
			if(function_exists('bbp_get_forum_id')) {
				$data[] = bbp_get_forum_id();
			}
			$where = "(post_types.meta_value IS NULL OR post_types.meta_value IN('".implode("','", $data)."'))";
		}
		return $where;
	}
	
}
