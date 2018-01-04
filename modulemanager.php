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