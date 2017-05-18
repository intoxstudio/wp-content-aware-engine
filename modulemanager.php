<?php
/**
 * @package WP Content Aware Engine
 * @copyright Joachim Jensen <jv@intox.dk>
 * @license GPLv3
 */

if (!defined('ABSPATH')) {
	exit;
}

if(!class_exists('WPCAModuleManager')) {
	/**
	 * Manage module objects
	 */
	final class WPCAModuleManager extends WPCAObjectManager {

		/**
		 * Constructor
		 */
		public function __construct() {
			parent::__construct();
		}

	}
}

//eol