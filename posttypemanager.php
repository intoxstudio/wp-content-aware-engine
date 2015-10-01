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

if(!class_exists("WPCAPostTypeManager")) {
	/**
	 * Manage post type objects
	 */
	final class WPCAPostTypeManager extends WPCAObjectManager {

		/**
		 * Constructor
		 */
		public function __construct() {
			parent::__construct();
			add_action('init',array($this,'deploy'),98);
		}

		/**
		 * Add post type to the manager
		 * 
		 * @since   1.0
		 * @param   int|WP_Post    $post_type
		 */
		public function add($post_type,$name="") {
			if(!is_object($post_type)) {
				$post_type = get_post_type_object($post_type);
			}
			parent::add($post_type,$post_type->name);
		}

		/**
		 * Deploy post types
		 * 
		 * @since   1.0
		 * @return  void
		 */
		public function deploy() {
			foreach($this->get_all() as $post_type) {
				add_filter('manage_'.$post_type->name.'_columns',
					array(&$this,'metabox_preferences'));
			}
		}

		/**
		 * Display module in Screen Settings
		 *
		 * @since   1.0
		 * @param   array    $columns
		 * @return  array
		 */
		public function metabox_preferences($columns) {
			$columns['_title'] = __("Conditional Content",WPCACore::DOMAIN);
			return $columns;
		}
	}
}

//eol