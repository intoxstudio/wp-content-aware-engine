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
 * Pods Module
 * Requires version 2.6+
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
		parent::__construct('pods',__('Pods Pages',WPCA_DOMAIN));
		$this->placeholder = __('All Pods Pages',WPCA_DOMAIN);
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
		if(function_exists('pod_page_exists')) {
			$pod_page = pod_page_exists();
			$data[] = $pod_page['id'];
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
		$args['ids'] = $args['include'];
		unset($args['include']);

		$pods = array();
		if(function_exists('pods_api')) {
			$results = pods_api()->load_pages($args);
			foreach ($results as $result) {
				$pods[$result['id']] = $result['name'];
			}
		}
		if($args['search']) {
			$this->search_string = $args['search'];
			$pods = array_filter($pods,array($this,'_filter_search'));
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

}
