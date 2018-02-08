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

/**
 * Version of this WPCA
 * @var string
 */
$this_wpca_version = '4.3';

/**
 * Class to make sure the latest
 * version of WPCA gets loaded
 *
 * @since 3.0
 */
if(!class_exists('WPCALoader')) {
	class WPCALoader {

		/**
		 * Absolute paths and versions
		 * @var array
		 */
		private static $_paths = array();

		public function __construct() {
		}

		/**
		 * Add path to loader
		 *
		 * @since 3.0
		 * @param string  $path
		 * @param string  $version
		 */
		public static function add($path,$version) {
			self::$_paths[$path] = $version;
		}

		/**
		 * Load file for newest version
		 * and setup engine
		 *
		 * @since  3.0
		 * @return void
		 */
		public static function load() {

			//legacy version present, cannot continue
			if(class_exists('WPCACore')) {
				return;
			}

			arsort(self::$_paths);

			foreach (self::$_paths as $path => $version) {
				$file = $path.'core.php';
				if(file_exists($file)) {
					include($file);
					define('WPCA_VERSION',$version);
					WPCACore::init();
					do_action('wpca/loaded');
					break;
				}
			}
		}

		/**
		 * Get all paths added to loader
		 * Sorted if called after plugins_loaded
		 *
		 * @since  3.0
		 * @return array
		 */
		public static function debug() {
			return self::$_paths;
		}

	}
	//Hook as early as possible after plugins are loaded
	add_action('plugins_loaded',array('WPCALoader','load'),-999999);
}
WPCALoader::add(plugin_dir_path( __FILE__ ),$this_wpca_version);

//