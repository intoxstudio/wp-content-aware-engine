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
 * Transposh Module
 * 
 * Detects if current content is:
 * a) in specific language
 *
 */
class WPCAModule_transposh extends WPCAModule_Base {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('language',__('Languages',WPCACore::DOMAIN));
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
		if(function_exists('transposh_get_current_language')) {
			$data[] = transposh_get_current_language();
		}
		return $data;
	}

	/**
	 * Get content for sidebar editor
	 * 
	 * @global object $my_transposh_plugin
	 * @since  1.0
	 * @param  array $args
	 * @return array 
	 */
	protected function _get_content($args = array()) {
		global $my_transposh_plugin;
		$langs = array();

		/**
		 * isset($my_transposh_plugin->options->viewable_languages)
		 * returns false because transposh dev has not implemented __isset
		 * using get_option instead for robustness
		 */

		if(defined('TRANSPOSH_OPTIONS') && method_exists('transposh_consts', 'get_language_orig_name')) {
			$options = get_option(TRANSPOSH_OPTIONS);

			if(isset($options['viewable_languages'])) {
				foreach(explode(',',$options['viewable_languages']) as $lng) {
					$langs[$lng] = transposh_consts::get_language_orig_name($lng);
				}
			}
		}

		if(isset($args['include'])) {
			$langs = array_intersect_key($langs,array_flip($args['include']));
		}
		return $langs;
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
}
