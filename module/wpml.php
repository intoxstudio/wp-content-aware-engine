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
 * WPML Module
 * Requires version 2.4.3+
 * 
 * Detects if current content is:
 * a) in specific language
 *
 */
class WPCAModule_wpml extends WPCAModule_Base {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('language',__('Languages',WPCA_DOMAIN));
	}
	
	/**
	 * Determine if content is relevant
	 *
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
		return true;
	}

	/**
	 * Get data from context
	 * 
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {
		$data = array($this->id);
		if(defined('ICL_LANGUAGE_CODE')) {
			$data[] = ICL_LANGUAGE_CODE;
		}
		return $data;
	}

	/**
	 * Get languages
	 *
	 * @since  1.0
	 * @param  array $args
	 * @return array 
	 */
	protected function _get_content($args = array()) {
		$langs = array();

		if(function_exists('icl_get_languages')) {
			foreach(icl_get_languages('skip_missing=N') as $lng) {
				$langs[$lng['language_code']] = $lng['native_name'];
			}
		}

		if(isset($args['include'])) {
			$langs = array_intersect_key($langs,array_flip($args['include']));
		}
		return $langs;
	}

}
