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
	 * Enable AJAX search in editor
	 * 
	 * @var boolean
	 */
	protected $searchable = false;

	/**
	 * Enable display for all content of type
	 * 
	 * @var boolean
	 */
	protected $type_display = false;

	protected $pagination = array(
		'per_page' => 20,
		'total_pages' => 1,
		'total_items' => 0 
	);

	protected $ajax = false;
	
	/**
	 * Constructor
	 *
	 * @param   string    $id
	 * @param   string    $title
	 * @param   boolean   $ajax
	 * @param   string    $description
	 */
	public function __construct($id, $title, $ajax = false, $description = "") {
		$this->id = $id;
		$this->name = $title;
		$this->ajax = $ajax;
		$this->description = $description;

		if(is_admin()) {

			add_action('wpca/modules/admin-box',
				array(&$this,'meta_box_content'));
			add_action('wpca/modules/save-data',
				array(&$this,'save_data'));

			add_filter('wpca/modules/print-data',
				array(&$this,'print_group_data'),10,2);

			foreach(WPCACore::post_types()->get_all() as $post_type) {
				add_filter('manage_'.$post_type->name.'_columns',
					array(&$this,'metabox_preferences'));
			}
			
			if($this->ajax) {
				add_action('wp_ajax_wpca/module/'.$this->id,array(&$this,'ajax_print_content'));
			}
		}
		
		add_filter('wpca/modules/context-data',
			array(&$this,'parse_context_data'));

	}

	/**
	 * Display module in Screen Settings
	 *
	 * @since   1.0
	 * @param   array    $columns
	 * @return  array
	 */
	public function metabox_preferences($columns) {
		$columns['box-'.$this->id] = $this->name;
		return $columns;
	}
	
	/**
	 * Default meta box content
	 * 
	 * @global object $post
	 * @since  1.0
	 * @return void 
	 */
	public function meta_box_content() {

		$data = $this->_get_content();
		
		if(!$data && !$this->type_display)
			return;

		$screen = get_current_screen();

		$panels = "";
		if($this->type_display) {
			$panels .= '<ul><li><label><input class="cas-chk-all" type="checkbox" name="cas_condition['.$this->id.'][]" value="'.$this->id.'" /> '.sprintf(__('Display with All %s',WPCACore::DOMAIN),$this->name).'</label></li></ul>'."\n";
		}
		
		if($data) {

			$tabs = array();
			$tabs['all'] = array(
				'title' => __('View All'),
				'status' => true,
				'content' => $this->_get_checkboxes($data)
			);

			if($this->searchable) {
				$tabs['search'] = array(
					'title' => __('Search'),
					'status' => false,
					'content' => '',
					'content_before' => '<p><input class="cas-autocomplete-' . $this->id . ' cas-autocomplete quick-search" id="cas-autocomplete-' . $this->id . '" type="search" name="cas-autocomplete" value="" placeholder="'.__('Search').'" autocomplete="off" /><span class="spinner"></span></p>'
				);
			}

			$panels .= $this->create_tab_panels($this->id,$tabs);
		}

		WPCAView::make("module.meta_box",array(
			'hidden'       => in_array("box-".$this->id, get_hidden_columns( $screen->id )) ? ' hide-if-js' : '',
			'id'           => $this->id,
			'module'       => $this->id,
			'description'  => $this->description,
			'name'         => $this->name,
			'panels'       => $panels
		))->render();
		
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
	 * Print saved condition data for a group
	 *
	 * @since  1.0
	 * @param  int    $post_id
	 * @return void
	 */
	public function print_group_data($post_id) {
		$data = get_post_custom_values(WPCACore::PREFIX . $this->id, $post_id);
		if($data) {
			WPCAView::make('module.group',array(
				'id' => $this->id,
				'name' => $this->name,
				'data' => $data,
				'checkboxes' => $this->_get_checkboxes($this->_get_content(array('include' => $data)),false,true)
			))->render();
		}
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
	 * Get checkboxes for sidebar edit screen
	 *
	 * @since   1.0
	 * @param   array           $data
	 * @param   boolean         $pagination
	 * @param   array|boolean   $selected_content
	 * @return  string
	 */
	protected function _get_checkboxes($data, $pagination = false, $selected_data = array()) {
		$content = '';
		foreach($data as $id => $name) {
			if(is_array($selected_data)) {
				$selected = checked(in_array($id,$selected_data),true,false);
			} else {
				$selected = checked($selected_data,true,false);
			}
			$content .= '<li class="cas-'.$this->id.'-'.$id.'"><label><input class="cas-' . $this->id . '" type="checkbox" name="cas_condition['.$this->id.'][]" title="'.$name.'" value="'.$id.'"'.$selected.'/> '.$name.'</label></li>'."\n";
		}
		return $content;
	}

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
	 * Create tab panels for administrative meta boxes
	 *
	 * @since  1.0
	 * @param  string    $id
	 * @param  array     $args
	 * @return string
	 */
	final protected function create_tab_panels($id, $args) {
		$return = '<div id="'.$id.'" class="posttypediv">';
		
		$content = '';
		$tabs = '';
		
		$count = count($args);
		foreach($args as $key => $tab) {
			if($count > 1) {
				$tabs .= '<li'.($tab['status'] ? ' class="tabs"' : '').'>';
				$tabs .= '<a class="nav-tab-link" href="#tabs-panel-' . $id . '-'.$key.'" data-type="tabs-panel-' . $id . '-'.$key.'"> '.$tab['title'].' </a>';
				$tabs .= '</li>';				
			}
			$content .= '<div id="tabs-panel-' . $id . '-'.$key.'" class="tabs-panel'.($tab['status'] ? ' tabs-panel-active' : ' tabs-panel-inactive').'">';
			if(isset($tab['content_before'])) {
				$content .= $tab['content_before'];
			}
			$content .= '<ul id="cas-list-' . $id . '" class="cas-contentlist categorychecklist form-no-clear">'."\n";
			$content .= $tab['content'];
			$content .= '</ul>'."\n";
			$content .= '</div>';
		}

		if($tabs) {
			$return .= '<ul class="category-tabs">'.$tabs.'</ul>';
		}
		$return .= $content;

		$return .'</div>';

		return $return;
	}

	/**
	 * Get content in HTML
	 *
	 * @since   1.0
	 * @param   array    $args
	 * @return  string
	 */
	public function ajax_get_content($args) {
		return '';
	}

	/**
	 * Print HTML content for AJAX request
	 *
	 * @since   1.0
	 * @return  void
	 */
	final public function ajax_print_content() {

		if(!isset($_POST['sidebar_id']) || 
			!check_ajax_referer(WPCACore::PREFIX.$_POST['sidebar_id'],'nonce',false)) {
			die();
		}

		$paged = isset($_POST['paged']) ? $_POST['paged'] : 1;
		$search = isset($_POST['search']) ? $_POST['search'] : false;
		$item_object = isset($_POST['item_object']) ? $_POST['item_object'] : '';

		$response = $this->ajax_get_content(array(
			'paged' => $paged,
			'search' => $search,
			'item_object' => $item_object
		));

		echo json_encode($response);
		die();
	}
	
}
