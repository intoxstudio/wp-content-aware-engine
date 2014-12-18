<?php
/**
 * @package WP Content Aware Engine
 * @version 1.0
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */

if (!defined('ABSPATH')) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit;
}

if(!class_exists("WPCACore")) {
	/**
	 * Core for WordPress Content Aware Engine
	 */
	final class WPCACore {

		/**
		 * Using class prefix instead of namespace
		 * for PHP5.2 compatibility
		 */
		const CLASS_PREFIX         = "WPCA";
		
		/**
		 * Engine version
		 */
		const VERSION              = '1.0';

		/**
		 * Prefix for data (keys) stored in database
		 * Backwards compatible
		 */
		const PREFIX               = '_cas_';

		/**
		 * Post Type for condition groups
		 * Backwards compatible
		 */
		const TYPE_CONDITION_GROUP = 'sidebar_group';

		/**
		 * Post Status for negated condition groups
		 */
		const STATUS_NEGATED       = 'negated';

		/**
		 * Language domain
		 */
		const DOMAIN               = 'wp-content-aware-engine';

		/**
		 * Capability to manage sidebars
		 */
		const CAPABILITY           = 'edit_theme_options';

		/**
		 * Post Types that use the engine
		 * @var WPCAPostTypeManager
		 */
		private static $post_type_manager;

		/**
		 * Sidebars retrieved from database
		 * @var array
		 */
		private static $post_cache  = array();

		/**
		 * Modules for specific content or cases
		 * @var WPCAModuleManager
		 */
		private static $module_manager;

		/**
		 * Constructor
		 */
		public static function init() {

			spl_autoload_register(array(__CLASS__,"_autoload_class_files"));

			if(is_admin()) {
				
				add_action('admin_enqueue_scripts',
					array(__CLASS__,'enqueue_scripts_styles'));
				add_action('delete_post',
					array(__CLASS__,'sync_group_deletion'));
				add_action('trashed_post',
					array(__CLASS__,'sync_group_trashed'));
				add_action('untrashed_post',
					array(__CLASS__,'sync_group_untrashed'));
				add_action('add_meta_boxes',
					array(__CLASS__,'add_group_meta_box'),10,3);
			
				add_action('wp_ajax_cas_add_rule',
					array(__CLASS__,'ajax_update_group'));
				add_action('wp_ajax_cas_remove_group',
					array(__CLASS__,'ajax_remove_group'));

			}

			add_action('init',
				array(__CLASS__,'load_textdomain'),9);
			add_action('init',
				array(__CLASS__,'set_modules'),9);
			add_action('init',
				array(__CLASS__,'add_group_post_type'),99);
			
		}

		/**
		 * Get post type manager
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @return  WPCAPostTypeManager
		 */
		public static function post_types() {
			if(!self::$post_type_manager) {
				self::$post_type_manager = new WPCAPostTypeManager();
			}
			return self::$post_type_manager;
		}

		/**
		 * Get module manager
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @return  WPCAModuleManager
		 */
		public static function modules() {
			if(!self::$module_manager) {
				self::$module_manager = new WPCAModuleManager();
			}
			return self::$module_manager;
		}

		/**
		 * Set initial modules
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @return  void
		 */
		public static function set_modules() {
			$modules = array(
				'static'        => true,
				'post_types'    => true,
				'authors'       => true,
				'page_template' => true,
				'taxonomies'    => true,
				'bbpress'       => function_exists('bbp_get_version'),	// bbPress
				'bp_member'     => defined('BP_VERSION'),				// BuddyPress
				'polylang'      => defined('POLYLANG_VERSION'),			// Polylang
				'qtranslate'    => defined('QT_SUPPORTED_WP_VERSION'),	// qTranslate
				'transposh'     => defined('TRANSPOSH_PLUGIN_VER'),		// Transposh Translation Filter
				'wpml'          => class_exists('SitePress')			// WPML Multilingual Blog/CMS
			);
			foreach($modules as $name => $bool) {
				if($bool) {
					self::modules()->add(self::CLASS_PREFIX."Module_".$name,$name);
				}
			}
		}

		/**
		 * Load textdomain
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @return  void
		 */
		public static function load_textdomain() {
			load_plugin_textdomain(self::DOMAIN, false, dirname(plugin_basename(__FILE__)).'/lang/');
		}
		
		/**
		 * Register group post type
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
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
					'name'               => __('Condition Groups', self::DOMAIN),
					'singular_name'      => __('Condition Group', self::DOMAIN),
					'add_new'            => _x('Add New', 'group', self::DOMAIN),
					'add_new_item'       => __('Add New Group', self::DOMAIN),
					'edit_item'          => _x('Edit', 'group', self::DOMAIN),
					'new_item'           => '',
					'all_items'          => '',
					'view_item'          => '',
					'search_items'       => '',
					'not_found'          => '',
					'not_found_in_trash' => ''
				),
				'capabilities' => $capabilities,
				'show_ui'      => false,
				'show_in_menu' => false,
				'query_var'    => false,
				'rewrite'      => false,
				'supports'     => array('author'), //prevents fallback
			));

			register_post_status( 'negated', array(
				'label'                     => _x( 'Negated', 'post' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => false,
			));
		}

		/**
		 * Delete groups from database when their parent is deleted
		 * 
		 * @author Joachim Jensen <jv@intox.dk>
		 * @since  1.0
		 * @param  int    $post_id
		 * @return void
		 */
		public static function sync_group_deletion($post_id) {

			$post_type = get_post_type($post_id);

			// Authorize and only continue if post type is added to engine
			if (!current_user_can(self::CAPABILITY) || !self::post_types()->has($post_type))
				return;

			global $wpdb;
			$groups = (array)$wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_parent = '%d'", $post_id));
			foreach($groups as $group_id) {
				//Takes care of metadata and terms too
				wp_delete_post($group_id,true);
			}
		}

		/**
		 * Trash groups when their parent is trashed
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @param   int    $post_id
		 * @return  void
		 */
		public static function sync_group_trashed($post_id) {
			$post_type = get_post_type($post_id);

			// Only continue if post type is added to engine
			if (!self::post_types()->has($post_type))
				return;

			global $wpdb;
			$groups = (array)$wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_parent = '%d'", $post_id));
			foreach($groups as $group_id) {
				wp_trash_post($group_id);
			}
		}

		/**
		 * Untrash groups when their parent is untrashed
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @param   int    $post_id
		 * @return  void
		 */
		public static function sync_group_untrashed($post_id) {
			$post_type = get_post_type($post_id);

			// Only continue if post type is added to engine
			if (!self::post_types()->has($post_type))
				return;

			global $wpdb;
			$groups = (array)$wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_parent = '%d'", $post_id));
			foreach($groups as $group_id) {
				wp_untrash_post($group_id);
			}
		}

		/**
		 * Get filtered posts from a post type
		 * @global type    $wpdb
		 * @global WP_Post $post
		 * @return array|boolean 
		 */
		public static function get_posts($post_type) {
			global $wpdb, $post;
			
			if(!$post || is_admin() || post_password_required())
				return false;
			
			// Return cache if present
			if(!empty(self::$post_cache)) {
				if(isset(self::$post_cache[0]) && self::$post_cache[0] == false)
					return false;
				else
					return self::$post_cache;
			}

			$context_data['WHERE'] = $context_data['JOIN'] = $context_data['EXCLUDE'] = array();
			$context_data = apply_filters('cas-context-data',$context_data);

			// Check if there are any rules for this type of content
			if(empty($context_data['WHERE']))
				return false;

			$context_data['WHERE'][] = "posts.post_type = '".self::TYPE_CONDITION_GROUP."'";
			
			$post_status = array('publish',self::STATUS_NEGATED);
			$context_data['WHERE'][] = "posts.post_status IN ('".implode("','", $post_status)."')";
				
			//Syntax changed in MySQL 5.5 and MariaDB 10.0 (reports as version 5.5)
			$wpdb->query('SET'.(version_compare($wpdb->db_version(), '5.5', '>=') ? '' : ' OPTION').' SQL_BIG_SELECTS = 1');

			$groups_in_context = $wpdb->get_results(
				"SELECT posts.ID, posts.post_parent ".
				"FROM $wpdb->posts posts ".
				implode(' ',$context_data['JOIN'])."
				WHERE
				".implode(' AND ',$context_data['WHERE'])."
			",OBJECT_K);

			$groups_negated = $wpdb->get_results($wpdb->prepare(
				"SELECT p.ID, p.post_parent ".
				"FROM $wpdb->posts p ".
				"WHERE p.post_type = '%s' ".
				"AND p.post_status = '%s' ",
				self::TYPE_CONDITION_GROUP,
				self::STATUS_NEGATED
			),OBJECT_K);

			$valid = array();

			//Force update of meta cache to prevent lazy loading
			update_meta_cache('post',array_keys($groups_in_context+$groups_negated));

			//Exclude sidebars that have unrelated content in same group
			foreach($groups_in_context as $key => $sidebar) {
				$valid[$sidebar->ID] = $sidebar->post_parent;
				//TODO: move to modules
				foreach($context_data['EXCLUDE'] as $exclude) {
					//quick fix to check for taxonomies terms
					if($exclude == 'taxonomies') {
						if($wpdb->get_var("SELECT COUNT(*) FROM $wpdb->term_relationships WHERE object_id = '{$sidebar->ID}'") > 0) {
							unset($valid[$sidebar->ID]);
							break;						
						}
					}
					if(get_post_custom_values(self::PREFIX . $exclude, $sidebar->ID) !== null) {
						unset($valid[$sidebar->ID]);
						break;
					}
				}
			}

			$handled_already = array_flip($valid);
			foreach($groups_negated as $sidebar) {
				if(isset($valid[$sidebar->ID])) {
					unset($valid[$sidebar->ID]);
				} else {
					$valid[$sidebar->ID] = $sidebar->post_parent;
				}

				if($handled_already[$sidebar->post_parent]) {
					unset($valid[$sidebar->ID]);
				}
				$handled_already[$sidebar->post_parent] = 1;
				
			}

			if(!empty($valid)) {

				$context_data = array();
				$context_data['JOIN'][] = "INNER JOIN $wpdb->postmeta handle ON handle.post_id = posts.ID AND handle.meta_key = '".self::PREFIX."handle'";
				$context_data['JOIN'][] = "INNER JOIN $wpdb->postmeta exposure ON exposure.post_id = posts.ID AND exposure.meta_key = '".self::PREFIX."exposure'";
				$context_data['WHERE'][] = "posts.post_type = '".$post_type."'";
				$context_data['WHERE'][] = "exposure.meta_value ".(is_archive() || is_home() ? '>' : '<')."= '1'";
				$context_data['WHERE'][] = "posts.post_status ".(current_user_can('read_private_posts') ? "IN('publish','private')" : "= 'publish'")."";
				$context_data['WHERE'][] = "posts.ID IN(".implode(',',$valid).")";

				self::$post_cache = $wpdb->get_results("
					SELECT
						posts.ID,
						handle.meta_value handle
					FROM $wpdb->posts posts
					".implode(' ',$context_data['JOIN'])."
					WHERE
					".implode(' AND ',$context_data['WHERE'])."
					ORDER BY posts.menu_order ASC, handle.meta_value DESC, posts.post_date DESC
				",OBJECT_K);
				
			}
			
			// Return proper cache. If query was empty, tell the cache.
			return (empty(self::$post_cache) ? self::$post_cache[0] = false : self::$post_cache);
			
		}
		
		/**
		 * Add meta box to manage condition groups
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @param   string    $post_type
		 * @param   WP_Post   $post
		 */
		public static function add_group_meta_box($post_type,$post) {
			if(self::post_types()->has($post_type)) {
				add_meta_box(
					'cas-rules',
					__('Content', self::DOMAIN),
					array(__CLASS__, 'meta_box_rules'),
					$post_type,
					'normal',
					'high'
				);
			}
		}

		/**
		 * Render meta box content to manage condition groups
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @param   WP_Post    $post
		 * @return  void
		 */
		public static function meta_box_rules($post) {

			// Use nonce for verification. Unique per post
			wp_nonce_field(self::PREFIX.get_the_ID(), '_ca-sidebar-nonce');
			echo '<input type="hidden" id="current_sidebar" value="'.get_the_ID().'" />';

			$groups = self::_get_condition_groups(null,false);

			echo '<div id="cas-container">'."\n";
			echo '<div id="cas-accordion" class="accordion-container postbox'.(empty($groups) ? ' accordion-disabled' : '').'">'."\n";
			echo '<ul class="outer-border">';
			do_action('cas-module-admin-box');
			echo '</ul>';
			echo '</div>'."\n";
			echo '<div id="cas-groups" class="postbox'.(empty($groups) ? '' : ' cas-has-groups').'">'."\n";
			echo '<div class="cas-groups-header"><h3>'.__('Condition Groups',self::DOMAIN).'</h3><input type="button" class="button button-primary js-cas-group-new" value="'.__('Add New Group',self::DOMAIN).'" /></div>';
			echo '<div class="cas-groups-body"><p>'.__('Click to edit a group or create a new one. Select content on the left to add it. In each group, you can combine different types of associated content.',self::DOMAIN).'</p>';
			echo '<strong>'.__('Display sidebar with',self::DOMAIN).':</strong>';

			$i = 0;

			echo '<ul>';
			echo '<li class="cas-no-groups">'.__('No content. Please add at least one condition group to make the sidebar content aware.',self::DOMAIN).'</li>';
			foreach($groups as $group) {

				echo '<li class="cas-group-single'.($i == 0 ? ' cas-group-active' : '').'"><div class="cas-group-body">
				<span class="cas-group-control cas-group-control-active">
				<input type="button" class="button js-cas-group-save" value="'.__('Save',self::DOMAIN).'" /> | <a class="js-cas-group-cancel" href="#">'.__('Cancel',self::DOMAIN).'</a>
				</span>
				<span class="cas-group-control">
				<a class="js-cas-group-edit" href="#">'._x('Edit','group',self::DOMAIN).'</a> | <a class="submitdelete js-cas-group-remove" href="#">'.__('Remove',self::DOMAIN).'</a>
				</span>
				<div class="cas-switch">
				<input type="checkbox" id="test" name="'.self::PREFIX.'status" value="1">
				<label for="test" data-on="'.__('All but this content',self::DOMAIN).':" data-off="'.__('This content',self::DOMAIN).':"></label>
				</div>
				<div class="cas-content">';
				do_action('cas-module-print-data',$group->ID);
				echo '</div>
				<input type="hidden" class="cas_group_id" name="cas_group_id" value="'.$group->ID.'" />';

				echo '</div>';

				echo '<div class="cas-group-sep">'.__('Or',self::DOMAIN).'</div>';

				echo '</li>';	
				$i++;
			}
			echo '</ul>';
			
			echo '</div>';
			echo '<div class="cas-groups-footer">';
			echo '<input type="button" class="button button-primary js-cas-group-new" value="'.__('Add New Group',self::DOMAIN).'" />';
			echo '</div>';
			echo '</div>'."\n";
			echo '</div>'."\n";
			
		}

		/**
		 * Insert new condition group for a post type
		 * 
		 * Uses current post per default
		 * @author Joachim Jensen <jv@intox.dk>
		 * @since  1.0
		 * @param  WP_Post|int    $post
		 * @return int
		 */
		private static function _add_condition_group($post_id = null) {
			$post = get_post($post_id);

			//Make sure to go from auto-draft to draft
			if($post->post_status == 'auto-draft') {
				wp_update_post( array(
					'ID'          => $post->ID,
					'post_status' => 'draft'
				));
			}

			return wp_insert_post(array(
				'post_status' => 'publish', 
				'post_type'   => self::TYPE_CONDITION_GROUP,
				'post_author' => $post->post_author,
				'post_parent' => $post->ID,
			));
		}

		/**
		 * Get condition groups for a post type
		 * Uses current post per default
		 * Creates the first group if necessary
		 * 
		 * @author Joachim Jensen <jv@intox.dk>
		 * @since  1.0
		 * @param  WP_Post|int    $post_id
		 * @param  boolean        $create_first
		 * @return array
		 */
		private static function _get_condition_groups($post_id = null, $create_first = false) {
			$post = get_post($post_id);

			$groups = get_posts(array(
				'posts_per_page'   => -1,
				'post_type'        => self::TYPE_CONDITION_GROUP,
				'post_parent'      => $post->ID,
				'post_status'      => 'publish,'.self::STATUS_NEGATED,
				'order'            => 'ASC'
			));
			if($groups == null && $create_first) {
				$group = self::_add_condition_group($post);
				$groups[] = get_post($group);
			}

			return $groups;

		}

		/**
		 * AJAX callback to update a condition group
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @return  void
		 */
		public static function ajax_update_group() {

			$response = array();

			try {
				if(!isset($_POST['current_id']) || 
					!check_ajax_referer(self::PREFIX.$_POST['current_id'],'token',false)) {
					$response = __('Unauthorized request',self::DOMAIN);
					throw new Exception("Forbidden",403);
				}

				//Make sure some rules are sent
				if(!isset($_POST['cas_condition'])) {
					$response = __('Condition group cannot be empty',self::DOMAIN);
					throw new Exception("Internal Server Error",500);
				}

				//If ID was not sent at this point, it is a new group
				if(!isset($_POST['cas_group_id'])) {
					$post_id = self::_add_condition_group(intval($_POST['current_id']));
					$response['new_post_id'] = $post_id;
				} else {
					$post_id = intval($_POST['cas_group_id']);
				}

				wp_update_post(array(
					'ID' => $post_id,
					'post_status' => isset($_POST[self::PREFIX.'status']) ? self::STATUS_NEGATED : 'publish'
				));

				do_action('cas-module-save-data',$post_id);

				$response['message'] = __('Condition group saved',self::DOMAIN);

				echo json_encode($response);
				
			} catch(Exception $e) {
				header("HTTP/1.1 ".$e->getCode()." ".$e->getMessage());
				echo $response;
			}
			die();
		}

		/**
		 * AJAX call to remove condition group from a post type
		 * 
		 * @author Joachim Jensen <jv@intox.dk>
		 * @since  1.0
		 * @return void
		 */
		public static function ajax_remove_group() {

			$response = "";

			try {
				if(!isset($_POST['current_id'],$_POST['cas_group_id'])) {
					$response = __('Unauthorized request',self::DOMAIN);
					throw new Exception("Forbidden",403);
				}	

				if(!check_ajax_referer(self::PREFIX.$_POST['current_id'],'token',false)) {
					$response = __('Unauthorized request',self::DOMAIN);
					throw new Exception("Forbidden",403);
				}

				if(wp_delete_post(intval($_POST['cas_group_id']), true) === false) {
					$response = __('Condition group could not be removed',self::DOMAIN);
					throw new Exception("Internal Server Error",500);
				}

				echo json_encode(array(
					'message' => __('Condition group removed',self::DOMAIN)
				));
				
			} catch(Exception $e) {
				header("HTTP/1.1 ".$e->getCode()." ".$e->getMessage());
				echo $response;
			}
			die();
		}

		/**
		 * Register and enqueue scripts and styles
		 * for post edit screen
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @param   string    $hook
		 * @return  void
		 */
		public static function enqueue_scripts_styles($hook) {

			$current_screen = get_current_screen();

			if(self::post_types()->has($current_screen->post_type) && $current_screen->base == 'post') {
				
				if(!wp_script_is('accordion','registered')) {
					wp_register_script('accordion', plugins_url('/assets/js/accordion.min.js', __FILE__), array('jquery'), self::VERSION, true);
				}

				wp_register_script(self::PREFIX.'condition-groups', plugins_url('/assets/js/condition_groups.min.js', __FILE__), array('jquery','accordion'), self::VERSION, true);
				
				wp_register_style('cas_admin_style', plugins_url('/assets/css/condition_groups.css', __FILE__), array(), self::VERSION);

				wp_enqueue_script(self::PREFIX.'condition-groups');
				wp_localize_script(self::PREFIX.'condition-groups', 'WPCA', array(
					'save'          => __('Save',self::DOMAIN),
					'cancel'        => __('Cancel',self::DOMAIN),
					'or'            => __('Or',self::DOMAIN),
					'edit'          => _x('Edit','group',self::DOMAIN),
					'remove'        => __('Remove',self::DOMAIN),
					'confirmRemove' => __('Remove this group and its contents permanently?',self::DOMAIN),
					'noResults'     => __('No results found.',self::DOMAIN),
					'confirmCancel' => __('The current group has unsaved changes. Do you want to continue and discard these changes?', self::DOMAIN)
				));
				wp_enqueue_style('cas_admin_style');
			}

		}

		/**
		 * Autoload class files
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @param   string    $class
		 * @return  void
		 */
		private static function _autoload_class_files($class) {
			$path = plugin_dir_path( __FILE__ );

			if(strpos($class, self::CLASS_PREFIX) !== false) {
				$class = str_replace(self::CLASS_PREFIX, "", $class);
				$class = self::str_replace_first("_", "/", $class);
				$class = strtolower($class);
				$file = $path . $class . ".php";
				if(file_exists($file)) {
					require_once($file);
				}
			}
		}

		/**
		 * Helper function to replace first
		 * occurence of substring
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
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

	WPCACore::init();

}

//eol
