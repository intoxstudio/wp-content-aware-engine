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
 *
 * Walker to render input checkboxes
 * for module content
 *
 */
class WPCAWalker extends Walker {

	/**
	 * Label property for objects.
	 * Used for reflection
	 * 
	 * @var string
	 */
	private $label_arg;

	/**
	 * Value property for objects.
	 * Used for reflection
	 * 
	 * @var string
	 */
	private $value_arg;

	/**
	 * Name for input field
	 * 
	 * @var string
	 */
	private $name;

	/**
	 * Constructor
	 *
	 * @since 1.0
	 * @param string  $key
	 * @param string  $parent_col
	 * @param string  $id_col
	 * @param string  $label_arg
	 * @param string  $value_arg
	 */
	private function __construct($key, $parent_col, $id_col, $label_arg, $value_arg = null) {
		
		$this->name = "cas_condition[".(is_array($key) ? implode("][", $key) : $key)."][]";
		$this->db_fields = array('parent' => $parent_col, 'id' => $id_col);
		$this->label_arg = $label_arg;
		$this->value_arg = $value_arg ? $value_arg : $id_col;
		
	}

	/**
	 * Factory
	 *
	 * @since  1.0
	 * @param  string  $key
	 * @param  string  $parent_col
	 * @param  string  $id_col
	 * @param  string  $label_arg
	 * @param  string  $value_arg
	 * @return WPCAWalker
	 */
	public static function make($key, $parent_col, $id_col, $label_arg, $value_arg = null) {
		return new self($key, $parent_col, $id_col, $label_arg, $value_arg);
	}
	
	/**
	 * Start outputting level
	 *
	 * @since  1.0
	 * @param  string $output
	 * @param  int    $depth
	 * @param  array  $args 
	 * @return void 
	 */
	public function start_lvl(&$output, $depth = 0, $args = array()) {
		$indent = str_repeat("\t", $depth);
		$output .= "</li>$indent<li><ul class='children'>\n";
	}
	
	/**
	 * End outputting level
	 *
	 * @since  1.0
	 * @param  string $output
	 * @param  int    $depth
	 * @param  array  $args 
	 * @return void 
	 */
	public function end_lvl(&$output, $depth = 0, $args = array()) {
		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul></li>\n";
	}
	
	/**
	 * Start outputting element
	 *
	 * @since  1.0
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
		$title = esc_html($object->{$this->label_arg});

		//todo: move to other object
		if($object instanceof WP_Post) {
			$title .= $this->_post_states($object);
		}

		$output .= "\n".'<li><label><input value="'.$value.'" type="checkbox" title="'.esc_attr( $title ).'" name="'.$this->name.'"'.$this->_checked($value,$selected_terms).'/> '. $title .'</label>';

	}

	/**
	 * End outputting element
	 *
	 * @since  1.0
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
	 * @since   1.0
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

	/**
	 * Get post states
	 *
	 * @since  1.0
	 * @see    _post_states()
	 * @param  WP_Post  $post
	 * @return string
	 */
	private function _post_states($post) {
		$post_states = array();

		if ( !empty($post->post_password) )
			$post_states['protected'] = __('Password protected');
		if ( 'private' == $post->post_status)
			$post_states['private'] = __('Private');
		if ( 'draft' == $post->post_status)
			$post_states['draft'] = __('Draft');
		if ( 'pending' == $post->post_status)
			/* translators: post state */
			$post_states['pending'] = _x('Pending', 'post state');
		if ( is_sticky($post->ID) )
			$post_states['sticky'] = __('Sticky');
		if ( 'future' === $post->post_status ) {
			$post_states['scheduled'] = __( 'Scheduled' );
		}
 
		/**
		 * Filter the default post display states used in the posts list table.
		 *
		 * @since 2.8.0
		 *
		 * @param array $post_states An array of post display states.
		 * @param int   $post        The post ID.
		 */
		$post_states = apply_filters( 'display_post_states', $post_states, $post );

		$retval = "";
		if ( ! empty($post_states) ) {
			$state_count = count($post_states);
			$i = 0;
			$retval .= ' - ';
			foreach ( $post_states as $state ) {
				++$i;
				( $i == $state_count ) ? $sep = '' : $sep = ', ';
				$retval .= "<span class='post-state'>$state$sep</span>";
			}
		}
		return $retval;
	 
	}
}

//eol