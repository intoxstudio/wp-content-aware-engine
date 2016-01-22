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
 * Pods Module
 * 
 * Detects if current content is:
 * a) any or specific Pods Page
 *
 */
class WPCAModule_Pods extends WPCAModule_author {
	
	/**
	 * Constructor
	 */
	public function __construct() {
	
		parent::__construct();
		
		$this->id = 'pods_pages';
		$this->name = __('Pods Pages',WPCACore::DOMAIN);
		
	}
	
	/**
	 * Determine if content is relevant
	 *
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
	
		$in_context = false;
		
		if ( function_exists( 'is_pod_page' ) && is_pod_page() ) {
			$in_context = true;
		}
	
		return $in_context;
		
	}

	/**
	 * Get data from context
	 * 
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {
	
		$data = array(
			$this->id,
		);
		
		return $data;
		
	}
	
}
