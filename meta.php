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

if(!class_exists("WPCAMeta")) {
	/**
	 * Post Meta
	 */
	class WPCAMeta {

		/**
		 * Id
		 * @var string
		 */
		private $id;

		/**
		 * Title
		 * @var string
		 */
		private $title;

		/**
		 * Description
		 * @var string
		 */
		private $description;

		/**
		 * Default value
		 * @var mixed
		 */
		private $default_value;

		/**
		 * Input type
		 * @var string
		 */
		private $input_type;

		/**
		 * Input list
		 * @var string
		 */
		private $input_list;

		/**
		 * Constructor
		 *
		 * @since 1.0
		 */
		public function __construct(
			$id,
			$title,
			$default_value = "",
			$input_type    = "text",
			$input_list    = array(),
			$description   = ""
		) {
			$this->id            = $id;
			$this->title         = $title;
			$this->default_value = $default_value;
			$this->input_type    = $input_type;
			$this->input_list    = $input_list;
			$this->description   = $description;
		}

		/**
		 * Get meta id
		 *
		 * @since  1.0
		 * @return string
		 */
		public function get_id() {
			return $this->id;
		}
		/**
		 * Get meta title
		 *
		 * @since  1.0
		 * @return string
		 */
		public function get_title() {
			return $this->title;
		}

		/**
		 * Get meta input type
		 *
		 * @since  1.0
		 * @return string
		 */
		public function get_input_type() {
			return $this->input_type;
		}

		/**
		 * Get meta input list
		 *
		 * @since  1.0
		 * @return array
		 */
		public function get_input_list() {
			return $this->input_list;
		}

		/**
		 * Set meta input list
		 *
		 * @since 1.0
		 * @param array  $input_list
		 */
		public function set_input_list($input_list) {
			$this->input_list = $input_list;
		}

		/**
		 * Get this meta data for a post
		 *
		 * @since  1.0
		 * @param  int     $post_id
		 * @param  boolean $default_fallback
		 * @param  boolean $single
		 * @return mixed
		 */
		public function get_data($post_id, $default_fallback = false, $single = true) {
			$data = get_post_meta($post_id, WPCACore::PREFIX . $this->id, $single);
			if($data == '' && $default_fallback) {
				$data = $this->default_value;
			}
			return $data;
		}

		/**
		 * Update this meta data for a post
		 *
		 * @since  1.0
		 * @param  int     $post_id
		 * @param  string  $value
		 * @return void
		 */
		public function update($post_id,$value) {
			update_post_meta($post_id, WPCACore::PREFIX . $this->id, $value);
		}

		/**
		 * Delete this meta data for a post
		 *
		 * @since  1.0
		 * @param  int     $post_id
		 * @param  string  $value
		 * @return void
		 */
		public function delete($post_id,$value) {
			delete_post_meta($post_id, WPCACore::PREFIX . $this->id, $value);
		}

		/**
		 * Get this meta data for a post
		 * represented by entry in input list
		 *
		 * @since  1.0
		 * @param  int  $post_id
		 * @return mixed
		 */
		public function get_list_data($post_id,$default_fallback = true) {
			$data = $this->get_data($post_id,$default_fallback);
			return isset($this->input_list[$data]) ? $this->input_list[$data] : null;
		}
	}
}

//eol