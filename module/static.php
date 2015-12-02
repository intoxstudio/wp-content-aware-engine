<?php
/**
 * @package WP Content Aware Engine
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
		parent::__construct('static',__('Static Pages',WPCACore::DOMAIN));
		$this->type_display = false;
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
			'front-page' => __('Front Page', WPCACore::DOMAIN),
			'search'     => __('Search Results', WPCACore::DOMAIN),
			'404'        => __('404 Page', WPCACore::DOMAIN)
		);

		if(isset($args['include'])) {
			$static = array_intersect_key($static, array_flip($args['include']));
		}
		if(isset($args["search"]) && $args["search"]) {
			$this->search_string = $args["search"];
			$static = array_filter($static,array($this,"_filter_search"));
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
	 * Get content in JSON
	 *
	 * @since  2.0
	 * @param  array  $args
	 * @return array
	 */
	public function ajax_get_content($args) {
		$args = wp_parse_args($args, array(
			'paged'          => 1,
			'search'         => ''
		));

		return $this->_get_content($args);
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
