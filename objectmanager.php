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

if(!class_exists('WPCAObjectManager')) {
	/**
	 * Manage a list of objects nicely
	 */
	class WPCAObjectManager implements IteratorAggregate {

		/**
		 * List of objects
		 * 
		 * @var array
		 */
		private $objects = array();

		/**
		 * Constructor
		 *
		 * @since 1.0
		 */
		public function __construct() {
		}

		/**
		 * Add object to the manager if key is
		 * not already added
		 *
		 * @since   1.0
		 * @param   mixed    $object
		 * @param   string   $name
		 * @return  WPCAObjectManager
		 */
		public function add($object,$name) {
			if(!$this->has($name)) {
				$this->set($object,$name);
			}
			return $this;
		}

		/**
		 * Remove object with key from manager
		 *
		 * @since   1.0
		 * @param   string    $name
		 * @return  void
		 */
		public function remove($name) {
			unset($this->objects[$name]);
		}

		/**
		 * Check if manager has key
		 * 
		 * @since 1.0
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
		 * @since 1.0
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
		 * @since 1.0
		 * @param   mixed    $object
		 * @param   string   $name
		 */
		public function set($object,$name) {
			$this->objects[$name] = $object;
		}

		/**
		 * Get all objects in manager
		 *
		 * @since 1.0
		 * @return  array
		 */
		public function get_all() {
			return $this->objects;
		}

		/**
		 * Set all objects in manager
		 *
		 * @since 1.0
		 * @param   array    $objects
		 */
		public function set_all($objects) {
			$this->objects = (array)$objects;
		}

		/**
		 * Count objects
		 *
		 * @since 1.0
		 * @return  int
		 */
		public function count() {
			return count($this->objects);
		}

		/**
		 * Make objects traversable
		 *
		 * @since  4.2
		 * @return ArrayIterator
		 */
		public function getIterator() {
			return new ArrayIterator($this->objects);
		}
	}
}

//eol