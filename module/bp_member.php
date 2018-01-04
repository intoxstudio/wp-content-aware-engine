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
 * BuddyPress Member Page Module
 * Requires BuddyPress 2.6+
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
		parent::__construct('bp_member',__('BuddyPress Profiles',WPCA_DOMAIN));
		$this->default_value = 0;
		$this->placeholder = __('All Sections',WPCA_DOMAIN);
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

		if(isset($args['paged']) && $args['paged'] > 1) {
			return array();
		}

		$content = array();
		$is_search = isset($args["search"]) && $args["search"];

		if(isset($bp->members->nav)) {

			foreach($bp->members->nav->get_item_nav() as $item) {
				$content[$item->slug] = array(
					'id'   => $item->slug,
					'text' => $item->name
				);
				if($item->children) {
					$level = $is_search ? 0 : 1;
					foreach ($item->children as $child_item) {
						$content[$item->slug."-".$child_item->slug] = array(
							'text'  => $child_item->name,
							'id'    => $item->slug."-".$child_item->slug,
							'level' => $level
						);
					}
				}
			}
		}

		if(isset($args['include'])) {
			$content = array_intersect_key($content,array_flip($args['include']));
		}
		if($is_search) {
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
		return mb_stripos($value['text'], $this->search_string) !== false;
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
		$data = array($this->default_value);
		if(isset($bp->current_component)) {
			$data[] = $bp->current_component;
			if(isset($bp->current_action)) {
				$data[] = $bp->current_component."-".$bp->current_action;
			}
		}
		return $data;
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
		//TODO: test if deprecated
		return $content && !$this->in_context();
	}
	
}
