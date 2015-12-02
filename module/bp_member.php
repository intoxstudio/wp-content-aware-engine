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
 * BuddyPress Member Page Module
 * 
 * Detects if current content is:
 * a) a specific buddypress member page
 *
 */
class WPCAModule_bp_member extends WPCAModule_Base {

	/**
	 * Cached search string
	 * @var string
	 */
	protected $search_string;

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('bp_member',__('BuddyPress Members',WPCACore::DOMAIN));
		
		
	}

	/**
	 * Initiate module
	 *
	 * @since  2.0
	 * @return void
	 */
	public function initiate() {
		parent::initiate();
		add_filter('wpca/module/static/in-context',
			array($this,'static_is_content'));
	}

	/**
	 * Get content for sidebar editor
	 * 
	 * @global  object    $bp
	 * @since   1.0
	 * @param   array     $args
	 * @return  array
	 */
	protected function _get_content($args = array()) {
		global $bp;

		$content = array();

		if(isset($bp->loaded_components,$bp->bp_options_nav)) {
			$components = $bp->loaded_components;
			unset($components['members'],$components['xprofile']);
			$components['profile'] = 'profile';

			foreach((array)$components as $name) {
				$content[$name] = ucfirst($name);
				if(isset($bp->bp_options_nav[$name])) {
					foreach($bp->bp_options_nav[$name] as $child) {
						$content[$name."-".$child["slug"]] = $child['name'];
					}
				}
			}
		}

		if(isset($args['include'])) {
			$content = array_intersect_key($content,array_flip($args['include']));
		}
		if(isset($args["search"]) && $args["search"]) {
			$this->search_string = $args["search"];
			$content = array_filter($content,array($this,"_filter_search"));
		}
		
		return $content;
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
	 * Determine if content is relevant
	 * 
	 * @global object  $bp
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
		global $bp;
		return isset($bp->displayed_user->domain) && $bp->displayed_user->domain;
	}

	/**
	 * Get data from context
	 *
	 * @global object $bp
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {
		global $bp;
		$data = array();
		if(isset($bp->current_component)) {
			$data[] = $bp->current_component;
			if(isset($bp->current_action)) {
				$data[] = $bp->current_component."-".$bp->current_action;
			}
		}
		return $data;
	}

	/**
	 * Get content in JSON
	 *
	 * @since   2.0
	 * @param   array    $args
	 * @return  array
	 */
	public function ajax_get_content($args) {
		$args = wp_parse_args($args, array(
			'paged'          => 1,
			'search'         => ''
		));

		return $this->_get_content($args);
	}
	
	/**
	 * Avoid collision with content of static module
	 * Somehow buddypress pages pass is_404()
	 *
	 * @since  1.0
	 * @param  boolean $content 
	 * @return boolean          
	 */
	public function static_is_content($content) {
		return $content && !$this->in_context();
	}
	
}
