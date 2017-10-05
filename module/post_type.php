<?php
/**
 * @package WP Content Aware Engine
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 *
 * Post Type Module
 *
 * Detects if current content is:
 * a) specific post type or specific post
 * b) specific post type archive or home
 * 
 */
class WPCAModule_post_type extends WPCAModule_Base {
	
	/**
	 * Registered public post types
	 * 
	 * @var array
	 */
	private $_post_types;

	/**
	 * Conditions to inherit from post ancestors
	 * @var array
	 */
	private $_post_ancestor_conditions;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('post_type',__('Post Types',WPCA_DOMAIN));
	}

	/**
	 * Initiate module
	 *
	 * @since  2.0
	 * @return void
	 */
	public function initiate() {
		parent::initiate();

		add_action('transition_post_status',
			array($this,'post_ancestry_check'),10,3);

		if(is_admin()) {
			foreach ($this->post_types() as $post_type) {
				add_action('wp_ajax_wpca/module/'.$this->id.'-'.$post_type,
					array($this,'ajax_print_content'));
			}
		}
	}

	/**
	 * Get content for sidebar editor
	 *
	 * @since  1.0
	 * @param  array $args
	 * @return array 
	 */
	protected function _get_content($args = array()) {
		$args = wp_parse_args($args, array(
			'include'        => '',
			'post_type'      => 'post',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'paged'          => 1,
			'posts_per_page' => 20,
			'search'         => ''
		));
		extract($args);

		$exclude = array();
		if ($args['post_type'] == 'page' && 'page' == get_option('show_on_front')) {
			$exclude[] = intval(get_option('page_on_front'));
			$exclude[] = intval(get_option('page_for_posts'));
		}

		$post_status = array('publish','private','future','draft');
		if($args['post_type'] == 'attachment') {
			$post_status = 'inherit';
		}

		//WordPress searches in title and content by default
		//We want to search in title and slug
		if($args['search']) {
			$exclude_query = '';
			if(!empty($exclude)) {
				$exclude_query = " AND ID NOT IN (".implode(",", $exclude).")";
			}

			//Using unprepared (safe) exclude because WP is not good at parsing arrays
			global $wpdb;
			$posts = $wpdb->get_results($wpdb->prepare("
				SELECT ID, post_title, post_type, post_parent, post_status, post_password
				FROM $wpdb->posts
				WHERE post_type = '%s' AND (post_title LIKE '%s' OR post_name LIKE '%s') AND post_status IN('".implode("','", $post_status)."')
				".$exclude_query."
				ORDER BY post_title ASC
				LIMIT %d,20
				",
				$args['post_type'],
				"%".$args['search']."%",
				"%".$args['search']."%",
				($args['paged']-1)*$args['posts_per_page']
			));
		} else {
			$query = new WP_Query(array(
				'posts_per_page'         => $args['posts_per_page'],
				'post_type'              => $args['post_type'],
				'post_status'            => $post_status,
				'post__in'               => $args['include'],
				'post__not_in'           => $exclude,
				'orderby'                => $args['orderby'],
				'order'                  => $args['order'],
				'paged'                  => $args['paged'],
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false
			));
			$posts = $query->posts;
		}

		$retval = array();
		foreach ($posts as $post) {
			$retval[$post->ID] = $this->post_title($post);
		}

		return $retval;
	}

	/**
	 * Get registered public post types
	 *
	 * @since   4.0
	 * @return  array
	 */
	public function post_types() {
		if(!$this->_post_types) {
			// List public post types
			foreach (get_post_types(array('public' => true), 'names') as $post_type) {
				$this->_post_types[$post_type] = $post_type;
			}
		}
		return $this->_post_types;
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
		$ids = get_post_custom_values(WPCACore::PREFIX . $this->id, $post_id);
		if($ids) {
			$lookup = array_flip((array)$ids);
			foreach($this->post_types() as $post_type) {
				$post_type_obj = get_post_type_object($post_type);
				$data = $this->_get_content(array(
					'include'        => $ids,
					'posts_per_page' => -1,
					'post_type'      => $post_type
				));

				if($data || isset($lookup[$post_type])) {

					$placeholder = $post_type_obj->has_archive ? '/'.sprintf(__('%s Archives',WPCA_DOMAIN),$post_type_obj->labels->singular_name) : '';
					$placeholder = $post_type == 'post' ? '/'.__('Blog Page',WPCA_DOMAIN) : $placeholder;
					$placeholder = $post_type_obj->labels->all_items.$placeholder;

					$group_data[$this->id.'-'.$post_type] = array(
						'label' => $post_type_obj->label,
						'placeholder' => $placeholder,
						'default_value' => $post_type
					);

					if($data) {
						$group_data[$this->id.'-'.$post_type]['data'] = $data;
					}
				}
			}
		}
		return $group_data;
	}
	
	/**
	 * Determine if content is relevant
	 *
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
		return ((is_singular() || is_home()) && !is_front_page()) || is_post_type_archive();
	}

	/**
	 * Get data from context
	 *
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {
		if(is_singular()) {
			return array(
				get_post_type(),
				get_queried_object_id()
			);
		}
		global $post_type;
		// Home has post as default post type
		if(!$post_type) $post_type = 'post';
		return array(
			$post_type
		);
	}

	/**
	 * Get content in HTML
	 *
	 * @since   1.0
	 * @param   array    $args
	 * @return  string
	 */
	public function ajax_get_content($args) {
		$args = wp_parse_args($args, array(
			'item_object'    => 'post',
			'paged'          => 1,
			'search'         => ''
		));

		preg_match('/post_type-(.+)$/i', $args['item_object'], $matches);
		$args['item_object'] = isset($matches[1]) ? $matches[1] : "";

		$post_type = get_post_type_object($args['item_object']);

		if(!$post_type) {
			return false;
		}
		$args['post_type'] = $post_type->name;
		unset($args['item_object']);

		return $this->_get_content($args);

	}

	/**
	 * Set module info in list
	 *
	 * @since  2.0
	 * @param  array  $list
	 * @return array
	 */
	public function list_module($list) {
		foreach($this->post_types() as $post_type) {
			$post_type_obj = get_post_type_object($post_type);
			$placeholder = $post_type_obj->has_archive ? '/'.sprintf(__('%s Archives',WPCA_DOMAIN),$post_type_obj->labels->singular_name) : '';
			$placeholder = $post_type == 'post' ? '/'.__('Blog Page',WPCA_DOMAIN) : $placeholder;
			$placeholder = $post_type_obj->labels->all_items.$placeholder;
			$list[$this->id.'-'.$post_type] = array(
				'name' => $post_type_obj->label,
				'placeholder' => $placeholder,
				'default_value' => $post_type
			);
		}
		return $list;
	}

	/**
	 * Get post title and state
	 *
	 * @since  3.7
	 * @param  WP_Post  $post
	 * @return string
	 */
	public function post_title($post) {
		$post_states = array();

		if ( !empty($post->post_password) ) {
			$post_states['protected'] = __('Password protected');
		}

		if ( is_sticky($post->ID) ) {
			$post_states['sticky'] = __('Sticky');
		}

		switch($post->post_status) {
			case 'private':
				$post_states['private'] = __('Private');
				break;
			case 'draft':
				$post_states['draft'] = __('Draft');
				break;
			case 'pending':
				/* translators: post state */
				$post_states['pending'] = _x('Pending', 'post state');
				break;
			case 'scheduled':
				$post_states['scheduled'] = __( 'Scheduled' );
				break;
		}

		$post_title = $post->post_title ? $post->post_title : __('(no title)');
		$post_states = apply_filters( 'display_post_states', $post_states, $post );

		return $post_title . ' ' . ($post_states ? " (".implode(", ", $post_states).")" : "");
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
		$new = array();

		foreach($this->post_types() as $post_type) {
			$id = $this->id.'-'.$post_type;
			if(isset($_POST['conditions'][$id])) {
				$new = array_merge($new,$_POST['conditions'][$id]);
			}
		}

		if ($new) {
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
	 * Check if post ancestors have sidebar conditions
	 *
	 * @since  1.0
	 * @param  string  $new_status 
	 * @param  string  $old_status 
	 * @param  WP_Post $post       
	 * @return void 
	 */
	public function post_ancestry_check($new_status, $old_status, $post) {
		
		if(!WPCACore::types()->has($post->post_type) && $post->post_type != WPCACore::TYPE_CONDITION_GROUP && $post->post_parent) {

			$status = array(
				'publish' => 1,
				'private' => 1,
				'future'  => 1
			);
			// Only new posts are relevant
			if(!isset($status[$old_status]) && isset($status[$new_status])) {

				$post_type = get_post_type_object($post->post_type);
				if($post_type->hierarchical && $post_type->public) {

					// Get sidebars with post ancestor wanting to auto-select post
					$query = new WP_Query(array(
						'post_type'  => WPCACore::TYPE_CONDITION_GROUP,
						'meta_query' => array(
						'relation'   => 'AND',
							array(
								'key'     => WPCACore::PREFIX . 'autoselect',
								'value'   => 1,
								'compare' => '='
							),
							array(
								'key'     => WPCACore::PREFIX . $this->id,
								'value'   => get_ancestors($post->ID,$post->post_type),
								'type'    => 'numeric',
								'compare' => 'IN'
							)
						)
					));
					
					if($query && $query->found_posts) {
						//Add conditions after Quick Select
						//otherwise they will be removed there
						$this->_post_ancestor_conditions = $query->posts;
						add_action('save_post_'.$post->post_type,
							array($this,'post_ancestry_add'),99,2);
					}
				}
			}
		}
	}

	/**
	 * Add sidebar conditions from post ancestors
	 *
	 * @since  3.1.1
	 * @param  int      $post_id
	 * @param  WP_Post  $post
	 * @return void
	 */
	public function post_ancestry_add($post_id, $post) {
		if($this->_post_ancestor_conditions) {
			foreach($this->_post_ancestor_conditions as $condition) {
				add_post_meta($condition->ID, WPCACore::PREFIX.$this->id, $post_id);
			}
		}
	}

}
