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

if(!class_exists("WPCAObjectManager")) {
	/**
	 * Manage a list of objects nicely
	 */
	class WPCAObjectManager {

		/**
		 * List of objects
		 * @var array
		 */
		private $objects = array();

		/**
		 * Constructor
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 */
		public function __construct() {
		}

		/**
		 * Add object to the manager if key is
		 * not already added
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @param   mixed    $object
		 * @param   string   $name
		 */
		public function add($object,$name) {
			if(!$this->has($name)) {
				$this->set($object,$name);
			}
		}

		/**
		 * Remove object with key from manager
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @param   string    $name
		 * @return  void
		 */
		public function remove($name) {
			unset($this->objects[$name]);
		}

		/**
		 * Check if manager has key
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @param   string    $name
		 * @return  boolean
		 */
		public function has($name) {
			return isset($this->objects[$name]);
		}

		/**
		 * Get object with key
		 * Returns null if not found
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @param   string     $name
		 * @return  mixed|null
		 */
		public function get($name) {
			return $this->has($name) ? $this->objects[$name] : null;
		}

		/**
		 * Add object to manager regardless if
		 * key exists already
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @param   mixed    $object
		 * @param   string   $name
		 */
		public function set($object,$name) {
			$this->objects[$name] = $object;
		}

		/**
		 * Get all objects in manager
		 * 
		 * @author  Joachim Jensen <jv@intox.dk>
		 * @version 1.0
		 * @return  array
		 */
		public function get_all() {
			return $this->objects;
		}
	}
}

//eol