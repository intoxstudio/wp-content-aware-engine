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
 * Page Template Module
 * 
 * Detects if current content has:
 * a) any or specific page template
 *
 *
 */
class WPCAModule_page_template extends WPCAModule_Base {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('page_template',__('Page Templates',WPCACore::DOMAIN));

		$this->type_display = true;
	}
	
	/**
	 * Determine if content is relevant
	 *
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
		if(is_singular() && !('page' == get_option( 'show_on_front') && get_option('page_on_front') == get_the_ID())) {
			$template = get_post_meta(get_the_ID(),'_wp_page_template',true);
			return ($template && $template != 'default');
		}
		return false;
	}

	/**
	 * Get data from context
	 * 
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {
		return array(
			$this->id,
			get_post_meta(get_the_ID(),'_wp_page_template',true)
		);
	}

	/**
	 * Get page templates
	 *
	 * @since  1.0
	 * @param  array $args
	 * @return array 
	 */
	protected function _get_content($args = array()) {
		$templates = array_flip(get_page_templates());
		if(isset($args['include'])) {
			$templates = array_intersect_key($templates,array_flip($args['include']));
		}
		return $templates;
	}
	
}
