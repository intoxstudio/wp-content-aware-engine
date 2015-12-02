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

/**
 * View Class
 */
class WPCAView {

	/**
	 * Path to view template
	 * 
	 * @var string
	 */
	private $_path;

	/**
	 * Parameters for view template
	 * 
	 * @var array
	 */
	private $_params;

	/**
	 * Template content
	 * 
	 * @var string
	 */
	private $_content;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 * @param   string    $path
	 * @param   array     $params
	 */
	private function __construct($path,$params = array()) {
		$this->_path = str_replace(".", "/", $path);
		$this->_params = $params;
	}

	/**
	 * Create instance of view
	 *
	 * @since 1.0
	 * @param   string    $name
	 * @param   array     $params
	 * @return  WPCAView
	 */
	public static function make($name,$params = array()) {
		return new self($name,$params);
	}

	/**
	 * Add possibility to set params dynamically
	 *
	 * @since 1.0
	 * @param   string    $method
	 * @param   array     $args
	 * @return  mixed
	 */
	public function __call($method,$args) {
		if(substr($method,0,3) == "set" && count($args) == 1) {
			$this->_params[strtolower(substr($method,2))] = $args[0];
			return $this;
		}
		return call_user_func_array($method, $args);
	}

	/**
	 * Get path to file
	 *
	 * @since 1.0
	 * @return  string
	 */
	private function resolve_path() {
		return plugin_dir_path( __FILE__ )."view/".$this->_path.".php";
	}

	/**
	 * Get content
	 *
	 * @since 1.0
	 * @return  string
	 */
	private function get_content() {
		if(!$this->_content) {
			ob_start();
			if($this->_params) {
				extract($this->_params);
			}
			require($this->resolve_path());
			$this->_content = ob_get_clean();
		}
		return $this->_content;
	}

	/**
	 * Render content
	 *
	 * @since 1.0
	 * @return  void
	 */
	public function render() {
		echo $this->get_content();
	}
}