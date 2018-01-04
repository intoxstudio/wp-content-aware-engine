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
 * qTranslate X Module
 * Requires version v3.4.6.4+
 * 
 * Detects if current content is:
 * a) in specific language
 *
 */
class WPCAModule_qtranslate extends WPCAModule_Base {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('language',__('Languages',WPCA_DOMAIN));
	}

	public function initiate() {
		parent::initiate();
		if(is_admin()) {
			global $q_config;
			//Disable multilanguage
			if(is_array($q_config['post_type_excluded'])) {
				foreach (WPCACore::types() as $name => $modules) {
					$q_config['post_type_excluded'][] = $name;
				}
				$q_config['post_type_excluded'][] = WPCACore::TYPE_CONDITION_GROUP;
			}
		}
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
		if(function_exists('qtranxf_getLanguage')) {
			$data[] = qtranxf_getLanguage();
		}
		return $data;
	}

	/**
	 * Get content for sidebar edit screen
	 * 
	 * @global  array     $q_config
	 * @since   1.0
	 * @param   array     $args
	 * @return  array
	 */
	protected function _get_content($args = array()) {
		global $q_config;

		$langs = array();

		if(isset($q_config['language_name'])) {
			foreach((array)get_option('qtranslate_enabled_languages') as $lng) {
				if(isset($q_config['language_name'][$lng])) {
					$langs[$lng] = $q_config['language_name'][$lng];
				}
			}
		}

		if(isset($args['include'])) {
			$langs = array_intersect_key($langs,array_flip($args['include']));
		}
		return $langs;
	}

}
