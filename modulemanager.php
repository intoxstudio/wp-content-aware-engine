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

if(!class_exists("WPCAModuleManager")) {
	/**
	 * Manage module objects
	 */
	final class WPCAModuleManager extends WPCAObjectManager {

		/**
		 * Constructor
		 */
		public function __construct() {
			parent::__construct();
			add_action('init',array($this,'deploy'),98);
		}

		/**
		 * Add module to manager
		 *
		 * @since 1.0
		 * @param object  $class
		 * @param string  $name
		 */
		public function add($class,$name) {
			if(($class && class_exists($class))) {
				parent::add($class,$name);
			}
		}

		/**
		 * Deploy modules
		 * 
		 * @since   1.0
		 * @return  void
		 */
		public function deploy() {
			foreach($this->get_all() as $name => $class) {
				$obj = new $class;
				if($obj instanceof WPCAModule_Base) {
					$this->set($obj,$obj->get_id()); 
				} else {
					$this->remove($name);
					unset($obj);
				}
			}
		}
	}
}

//eol