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

if(!class_exists('WPCACore')) {

	$domain = explode('/',plugin_basename( __FILE__ ));
	define('WPCA_DOMAIN',$domain[0]);
	define('WPCA_PATH',plugin_dir_path(__FILE__));

	/**
	 * Core for WordPress Content Aware Engine
	 */
	final class WPCACore {

		/**
		 * Using class prefix instead of namespace
		 * for PHP5.2 compatibility
		 */
		const CLASS_PREFIX         = 'WPCA';

		/**
		 * Prefix for data (keys) stored in database
		 */
		const PREFIX               = '_ca_';

		/**
		 * Post Type for condition groups
		 */
		const TYPE_CONDITION_GROUP = 'condition_group';

		/**
		 * Post Statuses for condition groups
		 */
		const STATUS_NEGATED       = 'negated';
		const STATUS_PUBLISHED     = 'publish';

		/**
		 * Exposures for condition groups
		 */
		const EXP_SINGULAR         = 0;
		const EXP_SINGULAR_ARCHIVE = 1;
		const EXP_ARCHIVE          = 2;

		/**
		 * Capability to manage sidebars
		 */
		const CAPABILITY           = 'edit_theme_options';

		/**
		 * Name for generated nonces
		 */
		const NONCE                = '_ca_nonce';

		/**
		 * Post Types that use the engine
		 * @var WPCAPostTypeManager
		 */
		private static $type_manager;

		/**
		 * Conditions retrieved from database
		 * @var array
		 */
		private static $condition_cache = array();

		/**
		 * Sidebars retrieved from database
		 * @var array
		 */
		private static $post_cache  = array();

		/**
		 * Constructor
		 */
		public static function init() {

			spl_autoload_register(array(__CLASS__,'_autoload_class_files'));

			self::$type_manager = new WPCATypeManager();

			if(is_admin()) {

				add_action('admin_enqueue_scripts',
					array(__CLASS__,'add_group_script_styles'),9);
				add_action('delete_post',
					array(__CLASS__,'sync_group_deletion'));
				add_action('trashed_post',
					array(__CLASS__,'sync_group_trashed'));
				add_action('untrashed_post',
					array(__CLASS__,'sync_group_untrashed'));
				add_action('add_meta_boxes',
					array(__CLASS__,'add_group_meta_box'),10,2);
				add_action('wpca/modules/save-data',
					array(__CLASS__,'save_condition_options'),10,3);
			
				add_action('wp_ajax_wpca/add-rule',
					array(__CLASS__,'ajax_update_group'));

			}

			add_action('init',
				array(__CLASS__,'add_group_post_type'),99);
			
		}

		/**
		 * Get post type manager
		 *
		 * @deprecated 4.0
		 * @since   1.0
		 * @return  WPCAPostTypeManager
		 */
		public static function post_types() {
			return self::$type_manager;
		}

		/**
		 * Get type manager
		 * 
		 * @since   4.0
		 * @return  WPCAPostTypeManager
		 */
		public static function types() {
			return self::$type_manager;
		}

		/**
		 * Register group post type
		 *
		 * @since   1.0
		 * @return  void
		 */
		public static function add_group_post_type() {

			$capabilities = array(
				'edit_post'          => self::CAPABILITY,
				'read_post'          => self::CAPABILITY,
				'delete_post'        => self::CAPABILITY,
				'edit_posts'         => self::CAPABILITY,
				'delete_posts'       => self::CAPABILITY,
				'edit_others_posts'  => self::CAPABILITY,
				'publish_posts'      => self::CAPABILITY,
				'read_private_posts' => self::CAPABILITY
			);
			
			register_post_type(self::TYPE_CONDITION_GROUP,array(
				'labels'       => array(
					'name'               => __('Condition Groups', WPCA_DOMAIN),
					'singular_name'      => __('Condition Group', WPCA_DOMAIN),
				),
				'capabilities' => $capabilities,
				'public'              => false,
				'hierarchical'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array('author'),
				'can_export'          => false,
				'delete_with_user'    => false
			));

			register_post_status( self::STATUS_NEGATED, array(
				'label'                     => _x( 'Negated', 'condition status', WPCA_DOMAIN ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
			));
		}

		/**
		 * Get group IDs by their parent ID
		 * 
		 * @since   1.0
		 * @param   int    $parent_id
		 * @return  array
		 */
		private static function get_group_ids_by_parent($parent_id) {
			if (!self::$type_manager->has(get_post_type($parent_id)))
				return array();

			global $wpdb;
			return (array)$wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_parent = '%d'", $parent_id));

		}

		/**
		 * Delete groups from database when their parent is deleted 
		 *
		 * @since  1.0
		 * @param  int    $post_id
		 * @return void
		 */
		public static function sync_group_deletion($post_id) {

			if (!current_user_can(self::CAPABILITY))
				return;

			$groups = self::get_group_ids_by_parent($post_id);
			if($groups) {
				foreach($groups as $group_id) {
					//Takes care of metadata and terms too
					wp_delete_post($group_id,true);
				}
			}
		}

		/**
		 * Trash groups when their parent is trashed
		 *
		 * @since   1.0
		 * @param   int    $post_id
		 * @return  void
		 */
		public static function sync_group_trashed($post_id) {

			$groups = self::get_group_ids_by_parent($post_id);
			if($groups) {
				foreach($groups as $group_id) {
					wp_trash_post($group_id);
				}
			}
		}

		/**
		 * Untrash groups when their parent is untrashed
		 *
		 * @since   1.0
		 * @param   int    $post_id
		 * @return  void
		 */
		public static function sync_group_untrashed($post_id) {

			$groups = self::get_group_ids_by_parent($post_id);
			if($groups) {
				foreach($groups as $group_id) {
					wp_untrash_post($group_id);
				}
			}
		}

		/**
		 * Get filtered condition groups
		 *
		 * @since  2.0
		 * @return array
		 */
		public static function get_conditions($post_type) {
			global $wpdb, $wp_query, $post;
			
			if((!$wp_query->query && !$post) || is_admin())
				return array();
			
			// Return cache if present
			if(isset(self::$condition_cache[$post_type])) {
				return self::$condition_cache[$post_type];
			}

			$excluded = array();
			$where = array();
			$join = array();

			$cache = array(
				$post_type
			);

			$modules = self::$type_manager->get($post_type)->get_all();

			foreach (self::$type_manager as $other_type => $other_modules) {
				if($other_type == $post_type) {
					continue;
				}
				if($other_modules->get_all() === $modules) {
					$cache[] = $other_type;
				}
			}

			foreach ($modules as $module) {
				$id = $module->get_id();
				if(apply_filters("wpca/module/{$id}/in-context", $module->in_context())) {
					$join[$id] = apply_filters("wpca/module/{$id}/db-join", $module->db_join());
					$data = $module->get_context_data();
					if(is_array($data)) {
						$data = "({$id}.meta_value IS NULL OR {$id}.meta_value IN ('".implode("','",$data) ."'))";
					}
					$where[$id] = apply_filters("wpca/module/{$id}/db-where", $data);
				} else {
					$excluded[] = $module;
				}
			}

			// Check if there are any conditions for current content
			if(empty($where)) {
				return array();
			}

			$post_status = array(
				self::STATUS_PUBLISHED,
				self::STATUS_NEGATED
			);

			$where[] = "p.post_type = '".self::TYPE_CONDITION_GROUP."'";
			$where[] = "p.post_status IN ('".implode("','", $post_status)."')";
			//exposure
			$where[] = "p.menu_order ".(is_archive() || is_home() ? '>=' : '<=')." 1";
				
			//Syntax changed in MySQL 5.5 and MariaDB 10.0 (reports as version 5.5)
			//todo: this might not be needed anymore?
			$wpdb->query('SET'.(version_compare($wpdb->db_version(), '5.5', '>=') ? ' SESSION' : ' OPTION').' SQL_BIG_SELECTS = 1');

			$groups_in_context = $wpdb->get_results(
				"SELECT p.ID, p.post_parent ".
				"FROM $wpdb->posts p ".
				implode(' ',$join)."
				WHERE
				".implode(' AND ',$where)."
			",OBJECT_K);

			$groups_negated = $wpdb->get_results($wpdb->prepare(
				"SELECT p.ID, p.post_parent ".
				"FROM $wpdb->posts p ".
				"WHERE p.post_type = '%s' ".
				"AND p.post_status = '%s' ",
				self::TYPE_CONDITION_GROUP,
				self::STATUS_NEGATED
			),OBJECT_K);

			//Force update of meta cache to prevent lazy loading
			update_meta_cache('post',array_keys($groups_in_context+$groups_negated));
			
			//condition group => type
			$valid = array();
			foreach($groups_in_context as $group) {
				$valid[$group->ID] = $group->post_parent;
			}

			//Exclude types that have unrelated content in same group
			foreach ($excluded as $module) {
				$valid = $module->filter_excluded_context($valid);
			}

			//Filter negated groups
			//type => group
			$handled_already = array_flip($valid);
			foreach($groups_negated as $group) {
				if(isset($valid[$group->ID])) {
					unset($valid[$group->ID]);
				} else {
					$valid[$group->ID] = $group->post_parent;
				}
				if(isset($handled_already[$group->post_parent])) {
					unset($valid[$group->ID]);
				}
				$handled_already[$group->post_parent] = 1;
			}

			foreach ($cache as $cache_type) {
				self::$condition_cache[$cache_type] = $valid;
			}

			return self::$condition_cache[$post_type];
		}

		/**
		 * Get filtered posts from a post type
		 *
		 * @since  1.0
		 * @global type     $wpdb
		 * @global WP_Query $wp_query
		 * @global WP_Post  $post
		 * @return array 
		 */
		public static function get_posts($post_type) {
			global $wpdb, $wp_query, $post;

			// Return cache if present
			if(isset(self::$post_cache[$post_type])) {
				return self::$post_cache[$post_type];
			}

			if(!self::$type_manager->has($post_type) || (!$wp_query->query && !$post) || is_admin()) {
				return false;
			}

			$valid = self::get_conditions($post_type);

			self::$post_cache[$post_type] = array();

			if($valid) {
				$results = $wpdb->get_results("
					SELECT
						p.ID,
						p.post_type,
						h.meta_value handle
					FROM $wpdb->posts p
					INNER JOIN $wpdb->postmeta h ON h.post_id = p.ID AND h.meta_key = '".self::PREFIX."handle' 
					WHERE
						p.post_type = '".$post_type."' AND 
						p.post_status = 'publish' AND 
						p.ID IN(".implode(',',$valid).") 
					ORDER BY p.menu_order ASC, h.meta_value DESC, p.post_date DESC
				",OBJECT_K);

				self::$post_cache[$post_type] = apply_filters("wpca/posts/{$post_type}",$results);
			}
			return self::$post_cache[$post_type];
		}
		
		/**
		 * Add meta box to manage condition groups
		 * 
		 * @since   1.0
		 * @param   string    $post_type
		 * @param   WP_Post   $post
		 */
		public static function add_group_meta_box($post_type,$post) {
			self::render_group_meta_box($post,$post_type,'normal','default');
		}

		public static function render_group_meta_box($post,$screen,$context = 'normal',$priority = 'default') {
			
			if(self::$type_manager->has($post->post_type)) {

				$options = array();
				foreach (self::$type_manager->get($post->post_type)->get_all() as $module) {
					$options = $module->list_module($options);
				}
				$options = apply_filters('wpca/modules/list',$options);
				$post_type_obj = get_post_type_object($post->post_type);

				$template = WPCAView::make('condition_options');
				add_action('wpca/group/settings',array($template,'render'),-1,2);

				$template = WPCAView::make('group_template',array(
					'post_type'=> $post->post_type,
					'options'  => $options
				));
				add_action('admin_footer',array($template,'render'));

				$template = WPCAView::make('condition_template');
				add_action('admin_footer',array($template,'render'));

				$view = WPCAView::make('meta_box',array(
					'post_type'=> $post->post_type,
					'nonce'    => wp_nonce_field(self::PREFIX.$post->ID, self::NONCE, true, false),
					'options'  => $options
				));

				$title = isset($post_type_obj->labels->ca_title) ? $post_type_obj->labels->ca_title : __('Conditional Logic', WPCA_DOMAIN);

				add_meta_box(
					'cas-rules',
					$title,
					array($view,'render'),
					$screen,
					$context,
					$priority
				);

			}
		}

		/**
		 * Insert new condition group for a post type
		 * Uses current post per default
		 *
		 * @since  1.0
		 * @param  WP_Post|int    $post
		 * @return int
		 */
		public static function add_condition_group($post_id = null) {
			$post = get_post($post_id);

			//Make sure to go from auto-draft to draft
			if($post->post_status == 'auto-draft') {
				wp_update_post( array(
					'ID'          => $post->ID,
					'post_title'  => '',
					'post_status' => 'draft'
				));
			}

			return wp_insert_post(array(
				'post_status' => self::STATUS_PUBLISHED, 
				'menu_order'  => self::EXP_SINGULAR_ARCHIVE,
				'post_type'   => self::TYPE_CONDITION_GROUP,
				'post_author' => $post->post_author,
				'post_parent' => $post->ID,
			));
		}

		/**
		 * Get condition groups for a post type
		 * Uses current post per default
		 * 
		 * @since  1.0
		 * @param  WP_Post|int    $post_id
		 * @return array
		 */
		private static function _get_condition_groups($post_id = null) {
			$post = get_post($post_id);
			$groups = array();

			if($post) {
				$groups = get_posts(array(
					'posts_per_page'   => -1,
					'post_type'        => self::TYPE_CONDITION_GROUP,
					'post_parent'      => $post->ID,
					'post_status'      => array(self::STATUS_PUBLISHED,self::STATUS_NEGATED),
					'order'            => 'ASC'
				));
			}
			return $groups;

		}

		/**
		 * AJAX callback to update a condition group
		 * 
		 * @since 1.0
		 * @return  void
		 */
		public static function ajax_update_group() {

			$response = array();

			try {
				if(!isset($_POST['current_id']) || 
					!check_ajax_referer(self::PREFIX.$_POST['current_id'],'token',false)) {
					$response = __('Unauthorized request',WPCA_DOMAIN);
					throw new Exception('Forbidden',403);
				}

				//Make sure some rules are sent
				if(!isset($_POST['conditions'])) {
					//Otherwise we delete group
					if($_POST['id'] && wp_delete_post(intval($_POST['id']), true) === false) {
						$response = __('Could not delete conditions',WPCA_DOMAIN);
						throw new Exception('Internal Server Error',500);
					}
					$response['removed'] = true;
				}
				if(!isset($response['removed'])) {
					//If ID was not sent at this point, it is a new group
					if(!$_POST['id']) {
						$post_id = self::add_condition_group(intval($_POST['current_id']));
						$response['new_post_id'] = $post_id;
					} else {
						$post_id = intval($_POST['id']);
					}

					wp_update_post(array(
						'ID' => $post_id,
						'post_status' => $_POST['status'] == self::STATUS_NEGATED ? self::STATUS_NEGATED : self::STATUS_PUBLISHED,
						'menu_order' => (int)$_POST['exposure']
					));

					foreach (self::$type_manager->get($_POST['post_type'])->get_all() as $module) {
						//send $_POST here
						$module->save_data($post_id);
					}

					do_action('wpca/modules/save-data',$post_id,$_POST['post_type']);
				}

				$response['message'] = __('Conditions updated',WPCA_DOMAIN);
				
				wp_send_json($response);
				
			} catch(Exception $e) {
				header('HTTP/1.1 '.$e->getCode().' '.$e->getMessage());
				echo $response;
				wp_die();
			}
			
		}

		/**
		 * Save registered meta for condition group
		 *
		 * @since  3.2
		 * @param  int  $group_id
		 * @return void
		 */
		public static function save_condition_options($group_id,$post_type) {
			$meta_keys = self::get_condition_meta_keys($post_type);
			foreach ($meta_keys as $key => $default_value) {
				$value = isset($_POST[$key]) ? $_POST[$key] : false;
				if($value) {
					update_post_meta($group_id,$key,$value);
				} else if(get_post_meta($group_id,$key,true)) {
					delete_post_meta($group_id,$key);
				}
			}
		}

		public static function add_group_script_styles($hook) {
			$current_screen = get_current_screen();

			wp_register_style(
				self::PREFIX.'condition-groups',
				plugins_url('/assets/css/condition_groups.css', __FILE__),
				array(),
				WPCA_VERSION
			);

			if(self::$type_manager->has($current_screen->post_type) && $current_screen->base == 'post') {
				self::enqueue_scripts_styles($hook);
			}
		}

		/**
		 * Get condition option defaults
		 *
		 * @since  3.2
		 * @param  string  $post_type
		 * @return array
		 */
		public static function get_condition_meta_keys($post_type) {
			$group_meta = array(
				'_ca_autoselect' => 0
			);
			return apply_filters('wpca/condition/meta',$group_meta,$post_type);
		}

		/**
		 * Register and enqueue scripts and styles
		 * for post edit screen
		 *
		 * @since 1.0
		 * @param   string    $hook
		 * @return  void
		 */
		public static function enqueue_scripts_styles($hook) {

			$post_type = get_post_type();

			$group_meta = self::get_condition_meta_keys($post_type);

			$groups = self::_get_condition_groups();
			$data = array();
			$i = 0;
			foreach ($groups as $group) {
				$data[$i] = array(
					'id'         => $group->ID,
					'status'     => $group->post_status,
					'exposure'   => $group->menu_order,
					'conditions' => array()
				);

				foreach (self::$type_manager->get($post_type)->get_all() as $module) {
					$data[$i]['conditions'] = $module->get_group_data($data[$i]['conditions'],$group->ID);
				}
				// $meta = get_post_custom($group->ID);
				// foreach ($group_meta as $meta_key => $default_value) {
				// 	$value = $default_value;
				// 	if(isset($meta[$meta_key])) {
				// 		$value = $meta[$meta_key];
				// 	}
				// 	$data[$i][$meta_key] = $value;
				// }
				foreach ($group_meta as $meta_key => $default_value) {
					$value = get_post_meta($group->ID,$meta_key,true);
					if($value === false) {
						$value = $default_value;
					}
					$data[$i][$meta_key] = $value;
				}
				$i++;
			}

			//Make sure to use packaged version
			if(wp_script_is('select2','registered')) {
				wp_deregister_script('select2');
				wp_deregister_style('select2');
			}

			//Add to head to take priority
			//if being added under other name
			wp_register_script(
				'select2',
				plugins_url('/assets/js/select2.min.js', __FILE__),
				array('jquery'),
				'4.0.3',
				false
			);

			wp_register_script(
				'backbone.trackit',
				plugins_url('/assets/js/backbone.trackit.min.js', __FILE__),
				array('backbone'),
				'0.1.0',
				true
			);

			wp_register_script(
				'backbone.epoxy',
				plugins_url('/assets/js/backbone.epoxy.min.js', __FILE__),
				array('backbone'),
				'1.3.3',
				true
			);

			wp_register_script(
				self::PREFIX.'condition-groups',
				plugins_url('/assets/js/condition_groups.min.js', __FILE__),
				array('jquery','select2','backbone','backbone.trackit','backbone.epoxy'),
				WPCA_VERSION,
				true
			);

			wp_enqueue_script(self::PREFIX.'condition-groups');
			wp_localize_script(self::PREFIX.'condition-groups', 'WPCA', array(
				'searching'     => __('Searching',WPCA_DOMAIN),
				'noResults'     => __('No results found.',WPCA_DOMAIN),
				'loadingMore'   => __('Loading more results',WPCA_DOMAIN),
				'unsaved'       => __('Conditions have unsaved changes. Do you want to continue and discard these changes?',WPCA_DOMAIN),
				'groups'        => $data,
				'meta_default'  => $group_meta,
				'post_type'     => $post_type,
				'text_direction' => is_rtl() ? 'rtl' : 'ltr'
			));
			wp_enqueue_style(self::PREFIX.'condition-groups');

		}

		/**
		 * Autoload class files
		 * 
		 * @since 1.0
		 * @param   string    $class
		 * @return  void
		 */
		private static function _autoload_class_files($class) {
			if(strpos($class, self::CLASS_PREFIX) === 0) {
				$class = str_replace(self::CLASS_PREFIX, '', $class);
				$class = self::str_replace_first('_', '/', $class);
				$class = strtolower($class);
				$file = WPCA_PATH . $class . '.php';
				if(file_exists($file)) {
					include($file);
				}
			}
		}

		/**
		 * Helper function to replace first
		 * occurence of substring
		 *
		 * @since 1.0
		 * @param   string    $search
		 * @param   string    $replace
		 * @param   string    $subject
		 * @return  string
		 */
		private static function str_replace_first($search, $replace, $subject) {
			$pos = strpos($subject, $search);
			if ($pos !== false) {
				$subject = substr_replace($subject, $replace, $pos, strlen($search));
			}
			return $subject;
		}
	}
}

//eol
