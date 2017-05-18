<?php
/**
 * @package WP Content Aware Engine
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */

if (!defined('ABSPATH')) {
	exit;
}

if(!class_exists('WPCAPostTypeManager')) {
	/**
	 * Manage module objects
	 */
	final class WPCAPostTypeManager extends WPCAObjectManager {

		/**
		 * Add module to manager
		 *
		 * @since 1.0
		 * @param object  $class
		 * @param string  $name
		 */
		public function add($name,$arg='') {
			parent::add($name,$name);
		}

	}
}

//eol