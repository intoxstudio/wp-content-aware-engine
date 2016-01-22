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
 * Pods Module
 * 
 * Detects if current content is:
 * a) any or specific Pods Page
 *
 */
class WPCAModule_pods extends WPCAModule_Base {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('pods',__('Pods Pages',WPCACore::DOMAIN));

		$this->type_display = true;
		$this->placeholder = __("All Pods Pages",WPCACore::DOMAIN);
		$this->default_value = $this->id;
	}
	
	/**
	 * Determine if content is relevant
	 *
	 * @since  2.0
	 * @return boolean 
	 */
	public function in_context() {
		return function_exists( 'is_pod_page' ) && is_pod_page();
	}

	/**
	 * Get data from context
	 * 
	 * @since  2.0
	 * @return array
	 */
	public function get_context_data() {
		$data = array(
			$this->id
		);
		if(function_exists("pod_page_exists")) {
			$pod_page = pod_page_exists();
			$data[] = $pod_page["id"];
		}
		return $data;
	}

	/**
	 * Get Pod Pages
	 *
	 * @since  2.0
	 * @param  array $args
	 * @return array 
	 */
	protected function _get_content($args = array()) {
		$args = wp_parse_args($args, array(
			'include'        => false,
			'where'          => '',
			'limit'          => -1,
			'search'         => ''
		));
		$args["ids"] = $args["include"];
		unset($args["include"]);

		$pods = array();
		if(function_exists("pods_api")) {
			$results = pods_api()->load_pages($args);
			foreach ($results as $result) {
				$pods[$result["id"]] = $result["name"];
			}
		}
		if(isset($args["search"]) && $args["search"]) {
			$this->search_string = $args["search"];
			$pods = array_filter($pods,array($this,"_filter_search"));
		}

		return $pods;
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
			'search'         => $args['search']
		));

		return $this->_get_content($args);
	}

}
