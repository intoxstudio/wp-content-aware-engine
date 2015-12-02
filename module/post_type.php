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
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('post_type',__('Post Types',WPCACore::DOMAIN));
		$this->type_display = true;
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
			array(&$this,'post_ancestry_check'),10,3);

		foreach ($this->_post_types()->get_all() as $post_type) {
			add_action('wp_ajax_wpca/module/'.$this->id.'-'.$post_type->name,array($this,'ajax_print_content'));
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
		if($args["post_type"] == "attachment") {
			$post_status = "inherit";
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
				LIMIT 0,20
				",
				$args['post_type'],
				"%".$args['search']."%",
				"%".$args['search']."%"
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

		return $posts;
	}

	/**
	 * Get registered public post types
	 *
	 * @since   1.0
	 * @return  array
	 */
	protected function _post_types() {
		if(!$this->_post_types) {
			$this->_post_types = new WPCAPostTypeManager();
			// List public post types
			foreach (get_post_types(array('public' => true), 'objects') as $post_type) {
				$this->_post_types->add($post_type);
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
			foreach($this->_post_types()->get_all() as $post_type) {
				$data = $this->_get_content(array('include' => $ids, 'posts_per_page' => -1, 'post_type' => $post_type->name, 'orderby' => 'title', 'order' => 'ASC'));

				if($data || isset($lookup[$post_type->name]) || isset($lookup[WPCACore::PREFIX.'sub_' . $post_type->name])) {
					$group_data[$this->id."-".$post_type->name] = array(
						"label" => $post_type->label
					);

					if($data) {
						$posts = array();
						foreach ($data as $post) {
							$posts[$post->ID] = $post->post_title.$this->_post_states($post);
						}
						$group_data[$this->id."-".$post_type->name]["data"] = $posts;
					}

					if(isset($lookup[WPCACore::PREFIX.'sub_' . $post_type->name])) {
						$group_data[$this->id."-".$post_type->name]["options"] = array(
							WPCACore::PREFIX.'sub_' . $post_type->name => true
						);
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

		preg_match('/post_type-(.+)$/i', $args["item_object"], $matches);
		$args['item_object'] = isset($matches[1]) ? $matches[1] : "";

		$post_type = get_post_type_object($args['item_object']);

		if(!$post_type) {
			return false;
		}

		$posts = $this->_get_content(array(
			'post_type' => $post_type->name,
			'orderby'   => 'title',
			'order'     => 'ASC',
			'paged'     => $args['paged'],
			'search'    => $args['search']
		));

		$retval = array();
		foreach ($posts as $post) {
			$retval[$post->ID] = $post->post_title.$this->_post_states($post);
		}
		return $retval;

	}

	/**
	 * Set module info in list
	 *
	 * @since  2.0
	 * @param  array  $list
	 * @return array
	 */
	public function list_module($list) {
		foreach($this->_post_types()->get_all() as $post_type) {
			$list[$this->id."-".$post_type->name] = $post_type->label;
		}
		return $list;
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
			foreach($this->_post_types()->get_all() as $post_type) {
				if($this->type_display) {
					$placeholder = $post_type->has_archive ? "/".sprintf(__("%s Archives",WPCACore::DOMAIN),$post_type->labels->singular_name) : "";
					$placeholder = $post_type->name == "post" ? "/".__("Blog Page",WPCACore::DOMAIN) : $placeholder;
					$placeholder = $post_type->labels->all_items.$placeholder;
				}
				echo WPCAView::make("module/condition_".$this->id."_template",array(
					'id'          => $this->id,
					'placeholder' => $placeholder,
					'post_type'   => $post_type->name,
					'autoselect'  => WPCACore::PREFIX.'sub_'.$post_type->name
				))->render();
			}
		}
	}

	/**
	 * Get post states
	 *
	 * @since  1.0
	 * @see    _post_states()
	 * @param  WP_Post  $post
	 * @return string
	 */
	private function _post_states($post) {
		$post_states = array();

		if ( !empty($post->post_password) )
			$post_states['protected'] = __('Password protected');
		if ( 'private' == $post->post_status)
			$post_states['private'] = __('Private');
		if ( 'draft' == $post->post_status)
			$post_states['draft'] = __('Draft');
		if ( 'pending' == $post->post_status)
			/* translators: post state */
			$post_states['pending'] = _x('Pending', 'post state');
		if ( is_sticky($post->ID) )
			$post_states['sticky'] = __('Sticky');
		if ( 'future' === $post->post_status ) {
			$post_states['scheduled'] = __( 'Scheduled' );
		}
 
		/**
		 * Filter the default post display states used in the posts list table.
		 *
		 * @since 2.8.0
		 *
		 * @param array $post_states An array of post display states.
		 * @param int   $post        The post ID.
		 */
		$post_states = apply_filters( 'display_post_states', $post_states, $post );

		return $post_states ? " (".implode(", ", $post_states).")" : "";
	}

	
	/**
	 * Automatically select child of selected parent
	 *
	 * @since  1.0
	 * @param  string  $new_status 
	 * @param  string  $old_status 
	 * @param  WP_Post $post       
	 * @return void 
	 */
	public function post_ancestry_check($new_status, $old_status, $post) {
		
		if(!WPCACore::post_types()->has($post->post_type) && $post->post_type != WPCACore::TYPE_CONDITION_GROUP) {
			
			$status = array('publish','private','future');
			// Only new posts are relevant
			if(!in_array($old_status,$status) && in_array($new_status,$status)) {
				
				$post_type = get_post_type_object($post->post_type);
				if($post_type->hierarchical && $post_type->public && $post->post_parent) {
				
					// Get sidebars with post ancestor wanting to auto-select post
					$query = new WP_Query(array(
						'post_type'				=> WPCACore::TYPE_CONDITION_GROUP,
						'meta_query'			=> array(
							'relation'			=> 'AND',
							array(
								'key'			=> WPCACore::PREFIX . $this->id,
								'value'			=> WPCACore::PREFIX.'sub_' . $post->post_type,
								'compare'		=> '='
							),
							array(
								'key'			=> WPCACore::PREFIX . $this->id,
								'value'			=> get_ancestors($post->ID,$post->post_type),
								'type'			=> 'numeric',
								'compare'		=> 'IN'
							)
						)
					));
					if($query && $query->found_posts) {
						foreach($query->posts as $sidebar) {
							add_post_meta($sidebar->ID, WPCACore::PREFIX.$this->id, $post->ID);
						}
					}
				}
			}	
		}	
	}

}
