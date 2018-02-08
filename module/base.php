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
 * All modules should extend this one.
 *
 */
abstract class WPCAModule_Base {
	
	/**
	 * Module identification
	 * 
	 * @var string
	 */
	protected $id;

	/**
	 * Module name
	 * 
	 * @var string
	 */
	protected $name;

	/**
	 * Module description
	 * 
	 * @var string
	 */
	protected $description;

	/**
	 * Placeholder label
	 * 
	 * @var string
	 */
	protected $placeholder;

	/**
	 * Default condition value
	 * Use to target any condition content
	 * 
	 * @var string
	 */
	protected $default_value = "";

	
	/**
	 * Constructor
	 *
	 * @param   string    $id
	 * @param   string    $title
	 * @param   string    $description
	 */
	public function __construct($id, $title, $description = '', $placeholder = '') {
		$this->id = $id;
		$this->name = $title;
		$this->description = $description;
		$this->placeholder = $placeholder;

		$this->initiate();
	}

	/**
	 * Initiate module
	 *
	 * @since  2.0
	 * @return void
	 */
	public function initiate() {
		if(is_admin()) {
			add_action('wp_ajax_wpca/module/'.$this->id,
				array($this,'ajax_print_content'));
		}
	}

	/**
	 * Set module info in list
	 *
	 * @since  2.0
	 * @param  array  $list
	 * @return array
	 */
	public function list_module($list) {
		//TODO: remove in favor of backbone objects
		$list[$this->id] = array(
			'name' => $this->name,
			'placeholder' => $this->placeholder,
			'default_value' => $this->default_value
		);
		return $list;
	}

	/**
	 * Default query join
	 * 
	 * @global wpdb   $wpdb
	 * @since  1.0
	 * @return string 
	 */
	public function db_join() {
		global $wpdb;
		return "LEFT JOIN $wpdb->postmeta {$this->id} ON {$this->id}.post_id = p.ID AND {$this->id}.meta_key = '".WPCACore::PREFIX.$this->id."' ";
	}
	
	/**
	 * Idenficiation getter
	 *
	 * @since  1.0
	 * @return string 
	 */
	final public function get_id() {
		return $this->id;
	}

	/**
	 * Save data on POST
	 *
	 * @since  1.0
	 * @param  int  $post_id
	 * @return void
	 */
	public function save_data($post_id) {
		$meta_key = WPCACore::PREFIX . $this->id;
		$old = array_flip(get_post_meta($post_id, $meta_key, false));
		$new = isset($_POST['conditions'][$this->id]) ? $_POST['conditions'][$this->id] : '';

		if (is_array($new)) {
			//$new = array_unique($new);
			// Skip existing data or insert new data
			foreach ($new as $new_single) {
				if (isset($old[$new_single])) {
					unset($old[$new_single]);
				} else {
					add_post_meta($post_id, $meta_key, $new_single);
				}
			}
			// Remove existing data that have not been skipped
			foreach ($old as $old_key => $old_value) {
				delete_post_meta($post_id, $meta_key, $old_key);
			}
		} elseif (!empty($old)) {
			// Remove any old values when $new is empty
			delete_post_meta($post_id, $meta_key);
		}
	}

	/**
	 * Get data for condition group
	 *
	 * @since  2.0
	 * @param  array  $group_data
	 * @param  int    $post_id
	 * @return array
	 */
	public function get_group_data($group_data,$post_id) {
		$data = get_post_custom_values(WPCACore::PREFIX . $this->id, $post_id);
		if($data) {
			$group_data[$this->id] = array(
				'label'         => $this->name,
				'placeholder'   => $this->placeholder,
				'data'          => $this->_get_content(array('include' => $data)),
				'default_value' => $this->default_value
			);
		}
		return $group_data;
	}

	/**
	 * Get content for sidebar edit screen
	 *
	 * @since   1.0
	 * @param   array     $args
	 * @return  array
	 */
	abstract protected function _get_content($args = array());

	/**
	 * Determine if current content is relevant
	 *
	 * @since  1.0
	 * @return boolean 
	 */
	abstract public function in_context();

	/**
	 * Get data from current content
	 *
	 * @since  1.0
	 * @return array|string
	 */
	abstract public function get_context_data();

	/**
	 * Remove posts if they have data from
	 * other contexts (meaning conditions arent met)
	 *
	 * @since  3.2
	 * @param  array  $posts
	 * @return array
	 */
	public function filter_excluded_context($posts) {
		foreach($posts as $id => $parent) {
			if(get_post_custom_values(WPCACore::PREFIX . $this->id, $id) !== null) {
				unset($posts[$id]);
			}
		}
		return $posts;
	}

	/**
	 * Get content for AJAX
	 *
	 * @since   1.0
	 * @param   array    $args
	 * @return  string
	 */
	public function ajax_get_content($args) {
		$args = wp_parse_args($args, array(
			'paged'          => 1,
			'search'         => ''
		));

		return $this->_get_content($args);
	}

	/**
	 * Print JSON for AJAX request
	 *
	 * @since   1.0
	 * @return  void
	 */
	final public function ajax_print_content() {

		if(!isset($_POST['sidebar_id']) || 
			!check_ajax_referer(WPCACore::PREFIX.$_POST['sidebar_id'],'nonce',false)) {
			wp_die();
		}

		$paged = isset($_POST['paged']) ? $_POST['paged'] : 1;
		$search = isset($_POST['search']) ? $_POST['search'] : false;

		$response = $this->ajax_get_content(array(
			'paged' => $paged,
			'search' => $search,
			'item_object' => $_POST['action']
		));

		//ECMAScript has no standard to guarantee
		//prop order in an object, send array instead
		//todo: fix in each module
		$fix_response = array();
		foreach ($response as $id => $title) {
			if(!is_array($title)) {
				$fix_response[] = array(
					'id'   => $id,
					'text' => $title
				);
			} else {
				$fix_response[] = $title;
			}
		}

		wp_send_json($fix_response);
	}

	/**
	 * Destructor
	 *
	 * @since 4.0
	 */
	public function __destruct() {
		if(is_admin()) {
			remove_action('wp_ajax_wpca/module/'.$this->id,
				array($this,'ajax_print_content'));
		}
	}
}

//eol