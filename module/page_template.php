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
 * Page Template Module
 * 
 * Detects if current content has:
 * a) any or specific page template
 *
 *
 */
class WPCAModule_page_template extends WPCAModule_Base {

	/**
	 * Cached search string
	 * @var string
	 */
	protected $search_string;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('page_template',__('Page Templates',WPCA_DOMAIN));
		$this->placeholder = __('All Templates',WPCA_DOMAIN);
		$this->default_value = $this->id;
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
		if(isset($args['search']) && $args['search']) {
			$this->search_string = $args['search'];
			$templates = array_filter($templates,array($this,'_filter_search'));
		}
		return $templates;
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
