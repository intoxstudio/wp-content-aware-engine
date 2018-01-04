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
 * Static Pages Module
 * 
 * Detects if current content is:
 * a) front page
 * b) search results
 * c) 404 page
 *
 */
class WPCAModule_static extends WPCAModule_Base {

	/**
	 * Cached search string
	 * @var string
	 */
	protected $search_string;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('static',__('Static Pages',WPCA_DOMAIN));
	}
	
	/**
	 * Get static content
	 *
	 * @since  1.0
	 * @param  array $args
	 * @return array 
	 */
	protected function _get_content($args = array()) {
		$static = array(
			'front-page' => __('Front Page', WPCA_DOMAIN),
			'search'     => __('Search Results', WPCA_DOMAIN),
			'404'        => __('404 Page', WPCA_DOMAIN)
		);

		if(isset($args['include'])) {
			$static = array_intersect_key($static, array_flip($args['include']));
		}
		if(isset($args['search']) && $args['search']) {
			$this->search_string = $args['search'];
			$static = array_filter($static,array($this,'_filter_search'));
		}
		return $static;
	}

	/**
	 * Filter content based on search
	 *
	 * @since  2.0
	 * @param  string  $value
	 * @return boolean
	 */
	protected function _filter_search($value) {
		return mb_stripos($value, $this->search_string) !== false;
	}

	/**
	 * Determine if content is relevant
	 *
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
		return is_front_page() || is_search() || is_404();
	}
	
	/**
	 * Get data from context
	 * 
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {
		if(is_front_page()) {
			$val = 'front-page';
		} else if(is_search()) {
			$val = 'search';
		} else {
			$val = '404';
		}
		return array(
			$val
		);
	}

}
