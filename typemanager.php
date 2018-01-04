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

if(!class_exists('WPCATypeManager')) {
	/**
	 * Manage module objects
	 */
	final class WPCATypeManager extends WPCAObjectManager {

		/**
		 * Constructor
		 */
		public function __construct() {
			parent::__construct();
			add_action('init',
				array($this,'set_modules'),999);
		}

		/**
		 * Add module to manager
		 *
		 * @since 1.0
		 * @param object  $class
		 * @param string  $name
		 */
		public function add($name,$arg='') {
			parent::add(new WPCAModuleManager(),$name);
		}

		/**
		 * Set initial modules
		 * 
		 * @since   4.0
		 * @return  void
		 */
		public function set_modules() {

			do_action('wpca/types/init',$this);

			$modules = array(
				'static'        => true,
				'post_type'     => true,
				'author'        => true,
				'page_template' => true,
				'taxonomy'      => true,
				'date'          => true,
				'bbpress'       => function_exists('bbp_get_version'),
				'bp_member'     => defined('BP_VERSION'),
				'pods'          => defined('PODS_DIR'),
				'polylang'      => defined('POLYLANG_VERSION'),
				'qtranslate'    => defined('QTX_VERSION'),
				'transposh'     => defined('TRANSPOSH_PLUGIN_VER'),
				'wpml'          => defined('ICL_SITEPRESS_VERSION')
			);

			foreach($modules as $name => $bool) {
				if($bool) {
					$class_name = WPCACore::CLASS_PREFIX.'Module_'.$name;
					$class = new $class_name();
					foreach ($this->get_all() as $post_type) {
						$post_type->add($class,$name);
					}
				}
			}

			do_action('wpca/modules/init',$this);
		}

	}
}

//eol