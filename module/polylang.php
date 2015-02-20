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
 * Polylang Module
 * 
 * Detects if current content is:
 * a) in specific language
 *
 */
class WPCAModule_polylang extends WPCAModule_Base {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('language',__('Languages',WPCACore::DOMAIN));
		
		add_filter('pll_get_post_types', array(&$this,'remove_sidebar_multilingual'));
		
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
		if(function_exists('pll_current_language')) {
			$data[] = pll_current_language();
		}
		return $data;
	}

	/**
	 * Get languages
	 * 
	 * @global object $polylang
	 * @since  1.0
	 * @param  array  $args
	 * @return array 
	 */
	protected function _get_content($args = array()) {
		global $polylang;

		$langs = array();

		if(isset($polylang->model) && method_exists($polylang->model, 'get_languages_list')) {
			foreach($polylang->model->get_languages_list(array('fields'=>false)) as $lng) {
				$langs[$lng->slug] = $lng->name;
			}
		}

		if(isset($args['include'])) {
			$langs = array_intersect_key($langs,array_flip($args['include']));
		}
		return $langs;
	}
	
	/**
	 * Remove sidebars from multilingual list
	 *
	 * @since  1.0
	 * @param  array $post_types 
	 * @return array             
	 */
	public function remove_sidebar_multilingual($post_types) {
		foreach(WPCACore::post_types()->get_all() as $post_type) {
			unset($post_types[$post_type->name]);
		}
		return $post_types;
	}

}
