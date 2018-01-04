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
 * URL Module
 * 
 * Detects if current content is:
 * a) matching a URL or URL pattern
 *
 */
class WPCAModule_date extends WPCAModule_Base {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'date',
			__('Dates',WPCA_DOMAIN)
		);
		$this->placeholder = __('Date Archives',WPCA_DOMAIN);
		$this->default_value = '0000-00-00';
	}

	/**
	 * Determine if content is relevant
	 *
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
		return is_date();
	}

	/**
	 * Get data from context
	 *
	 * @global object $wpdb
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {
		global $wpdb;
		return $wpdb->prepare(
			"(date.meta_value IS NULL OR '%s' = date.meta_value)",
			'0000-00-00'
		);
	}

	/**
	 * Get content
	 * 
	 * @since  1.0
	 * @return array 
	 */
	protected function _get_content($args = array()) {
		$data = array();
		if(isset($args['include'])) {
			$data = array_intersect_key($data, array_flip($args['include']));
		}
		return $data;

	}

}
