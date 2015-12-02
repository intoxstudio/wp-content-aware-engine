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
 * All modules should extend this one.
 *
 */
abstract class WPCAModule_Base {
	
	/**
	 * Module idenfification
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
	 * @var string
	 */
	protected $default_value = "";

	/**
	 * Enable display for all content of type
	 * 
	 * @var boolean
	 */
	protected $type_display = false;
	
	/**
	 * Constructor
	 *
	 * @param   string    $id
	 * @param   string    $title
	 * @param   string    $description
	 */
	public function __construct($id, $title, $description = "", $placeholder = "") {
		$this->id = $id;
		$this->name = $title;
		$this->description = $description;
		$this->placeholder = $placeholder;
	}

	/**
	 * Initiate module
	 *
	 * @since  2.0
	 * @return void
	 */
	public function initiate() {
		if(is_admin()) {
			add_action('wpca/modules/save-data',
				array($this,'save_data'));
			add_action('admin_footer-post.php',
				array($this,'template_condition'),1);
			add_action('admin_footer-post-new.php',
				array($this,'template_condition'),1);
			add_action('wp_ajax_wpca/module/'.$this->id,
				array($this,'ajax_print_content'));

			add_filter('wpca/modules/list',
				array($this,'list_module'));
			add_filter('wpca/modules/group-data',
				array($this,'get_group_data'),10,2);
		}
		
		add_filter('wpca/modules/context-data',
			array($this,'parse_context_data'));
	}

	/**
	 * Set module info in list
	 *
	 * @since  2.0
	 * @param  array  $list
	 * @return array
	 */
	public function list_module($list) {
		$list[$this->id] = $this->name;
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
		return "LEFT JOIN $wpdb->postmeta {$this->id} ON {$this->id}.post_id = posts.ID AND {$this->id}.meta_key = '".WPCACore::PREFIX.$this->id."' ";
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
		$new = isset($_POST['cas_condition'][$this->id]) ? $_POST['cas_condition'][$this->id] : '';
		$old = array_flip(get_post_meta($post_id, $meta_key, false));

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
				"label" => $this->name,
				"data" => $this->_get_content(array('include' => $data))
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
	 * Parse context data to sql query
	 *
	 * @since   1.0
	 * @param   array|string    $data
	 * @return  array
	 */
	final public function parse_context_data($data) {
		if(apply_filters("wpca/module/{$this->id}/in-context", $this->in_context())) {
			$data['JOIN'][$this->id] = apply_filters("wpca/module/{$this->id}/db-join", $this->db_join());

			$context_data = $this->get_context_data();

			if(is_array($context_data)) {
				$context_data = "({$this->id}.meta_value IS NULL OR {$this->id}.meta_value IN ('".implode("','",$context_data) ."'))";
			}
			$data['WHERE'][$this->id] = apply_filters("wpca/module/{$this->id}/db-where", $context_data);

			
		} else {
			$data['EXCLUDE'][] = $this->id;
		}
		return $data;
	}

	/**
	 * Get content for AJAX
	 *
	 * @since   1.0
	 * @param   array    $args
	 * @return  string
	 */
	public function ajax_get_content($args) {
		return '';
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
			'item_object' => $_POST["action"]
		));

		wp_send_json($response);
	}

	/**
	 * Create module Backbone template
	 * for administration
	 *
	 * @since  2.0
	 * @return void
	 */
	public function template_condition() {
		if(WPCACore::post_types()->has(get_post_type())) {
			echo WPCAView::make("module/condition_template",array(
				'id'          => $this->id,
				'placeholder' => $this->placeholder,
				'default'     => $this->default_value
			))->render();
		}
	}
	
}

//eol