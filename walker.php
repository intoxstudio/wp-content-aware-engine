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

/**
 *
 * Walker for module content
 *
 */
class WPCAWalker extends Walker {

	private $label_arg;
	private $value_arg;
	private $name;

	/**
	 * Constructor
	 *
	 * @version 1.0
	 * @param   array|string    $key
	 * @param   string          $parent_col
	 * @param   string          $id_col
	 * @param   string          $label_arg
	 * @param   string          $value_arg
	 */
	private function __construct($key, $parent_col, $id_col, $label_arg, $value_arg = null) {
		
		$this->name = "cas_condition[".(is_array($key) ? implode("][", $key) : $key)."][]";
		$this->db_fields = array('parent' => $parent_col, 'id' => $id_col);
		$this->label_arg = $label_arg;
		$this->value_arg = $value_arg ? $value_arg : $id_col;
		
	}

	public static function make($key, $parent_col, $id_col, $label_arg, $value_arg = null) {
		return new self($key, $parent_col, $id_col, $label_arg, $value_arg);
	}
	
	/**
	 * Start outputting level
	 * @param string $output
	 * @param int    $depth
	 * @param array  $args 
	 * @return void 
	 */
	public function start_lvl(&$output, $depth = 0, $args = array()) {
		$indent = str_repeat("\t", $depth);
		$output .= "</li>$indent<li><ul class='children'>\n";
	}
	
	/**
	 * End outputting level
	 * @param string $output
	 * @param int    $depth
	 * @param array  $args 
	 * @return void 
	 */
	public function end_lvl(&$output, $depth = 0, $args = array()) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul></li>\n";
	}
	
	/**
	 * Start outputting element
	 * @param  string $output 
	 * @param  object $object   
	 * @param  int    $depth  
	 * @param  array  $args 
	 * @param  int 	  $current_object_id
	 * @return void
	 */
	public function start_el(&$output, $object, $depth = 0, $args = array(), $current_object_id = 0 ) {
		extract($args);

		$value = $object->{$this->value_arg};
		$title = $object->{$this->label_arg};

		$output .= "\n".'<li><label><input value="'.$value.'" type="checkbox" title="'.esc_attr( $title ).'" name="'.$this->name.'"'.$this->_checked($value,$selected_terms).'/> '.esc_html( $title ).'</label>';

	}

	/**
	 * End outputting element
	 * @param  string $output 
	 * @param  object $object   
	 * @param  int    $depth  
	 * @param  array  $args   
	 * @return void         
	 */
	public function end_el(&$output, $object, $depth = 0, $args = array() ) {
		$output .= "</li>\n";
	}

	/**
	 * Output if input is checked or not
	 *
	 * @version 2.4
	 * @param   string         $current
	 * @param   array|boolean  $selected
	 * @return  string
	 */
	private function _checked($current,$selected) {
		if(is_array($selected)) {
			return checked(in_array($current,$selected),true,false);
		}
		return checked($selected,true,false);
	}
}

//eol