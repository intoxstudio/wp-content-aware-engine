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
	}
}

//eol