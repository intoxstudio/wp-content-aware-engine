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
 *
 * Taxonomy Module
 *
 * Detects if current content has/is:
 * a) any term of specific taxonomy or specific term
 * b) taxonomy archive or specific term archive
 *
 */
class WPCAModule_taxonomy extends WPCAModule_Base {
	
	/**
	 * Registered public taxonomies
	 * 
	 * @var array
	 */
	private $taxonomy_objects = array();

	/**
	 * Terms of a given singular
	 * 
	 * @var array
	 */
	private $post_terms;

	/**
	 * Taxonomies for a given singular
	 * @var array
	 */
	private $post_taxonomies;
	
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('taxonomy',__('Taxonomies',WPCA_DOMAIN));
	}

	public function initiate() {
		parent::initiate();
		add_action('created_term',
			array($this,'term_ancestry_check'),10,3);

		if(is_admin()) {
			foreach ($this->_get_taxonomies() as $taxonomy) {
				add_action('wp_ajax_wpca/module/'.$this->id.'-'.$taxonomy->name,
					array($this,'ajax_print_content'));
			}
		}
	}
	
	/**
	 * Determine if content is relevant
	 *
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
		if(is_singular()) {
			$tax = $this->_get_taxonomies();
			$this->post_terms = array();
			$this->post_taxonomies = array();

			// Check if content has any taxonomies supported
			foreach(get_object_taxonomies(get_post_type()) as $taxonomy) {
				//Only want taxonomies selectable in admin
				if(isset($tax[$taxonomy])) {
					
					//Check term caches, Core most likely used it
					$terms = get_object_term_cache(get_the_ID(),$taxonomy);
					if ($terms === false) {
						$terms = wp_get_object_terms(get_the_ID(), $taxonomy);
					}
					if($terms) {
						$this->post_taxonomies[] = $taxonomy;
						$this->post_terms = array_merge($this->post_terms,$terms);
					}
				}
			}
			return !!$this->post_terms;
		}
		return is_tax() || is_category() || is_tag();
	}

	/**
	 * Remove posts if they have data from
	 * other contexts (meaning conditions arent met)
	 *
	 * @since  3.2
	 * @param  array  $posts
	 * @return array
	 */
	public function filter_excluded_context($posts) {
		$posts = parent::filter_excluded_context($posts);
		if($posts) {
			global $wpdb;
			$obj_w_tags = $wpdb->get_col("SELECT object_id FROM $wpdb->term_relationships WHERE object_id IN (".implode(",", array_keys($posts)).") GROUP BY object_id");
			if($obj_w_tags) {
				$posts = array_diff_key($posts, array_flip($obj_w_tags));
			}
		}
		return $posts;
	}
	
	/**
	 * Query join
	 *
	 * @since  1.0
	 * @return string 
	 */
	public function db_join() {
		global $wpdb;
		$joins  = parent::db_join();
		$joins .= "LEFT JOIN $wpdb->term_relationships term ON term.object_id = p.ID ";
		return $joins;
	
	}

	/**
	 * Get data from context
	 *
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {
		
		//In more recent WP versions, term_id = term_tax_id
		//but term_tax_id has always been unique
		if(is_singular()) {
			$terms = array();
			foreach($this->post_terms as $term) {
				$terms[] = $term->term_taxonomy_id;
			}

			return "(term.term_taxonomy_id IS NULL OR term.term_taxonomy_id IN (".implode(",",$terms).")) AND (taxonomy.meta_value IS NULL OR taxonomy.meta_value IN('".implode("','",$this->post_taxonomies)."'))";

		}
		$term = get_queried_object();

		return "(term.term_taxonomy_id IS NULL OR term.term_taxonomy_id = '".$term->term_taxonomy_id."') AND (taxonomy.meta_value IS NULL OR taxonomy.meta_value = '".$term->taxonomy."')";
	}

	/**
	 * Get content for sidebar editor
	 *
	 * @since  1.0
	 * @param  array $args
	 * @return array 
	 */
	protected function _get_content($args = array()) {
		$args = wp_parse_args($args, array(
			'include'                => '',
			'taxonomy'               => 'category',
			'number'                 => 20,
			'orderby'                => 'name',
			'order'                  => 'ASC',
			'paged'                  => 1,
			'search'                 => '',
			'hide_empty'             => false,
			'update_term_meta_cache' => false
		));

		$args['offset'] = ($args['paged']-1)*$args['number'];
		unset($args['paged']);

		$total_items = wp_count_terms($args['taxonomy'],array(
			'hide_empty' => $args['hide_empty']
		));

		$start = $args['offset'];
		$end = $start + $args['number'];
		$walk_tree = false;
		$retval = array();

		if($total_items) {
			$taxonomy = get_taxonomy($args['taxonomy']);

			if($taxonomy->hierarchical && !$args['search'] && !$args['include']) {
				
				$args['number'] = 0;
				$args['offset'] = 0;

				$walk_tree = true;

			}
			
			$terms = get_terms($args['taxonomy'],$args);

			if($walk_tree) {
				$sorted_terms = array();
				foreach ($terms as $term) {
					$sorted_terms[$term->parent][] = $term;
				}
				$i = 0;
				$this->_walk_tree($sorted_terms,$sorted_terms[0],$i,$start,$end,0,$retval);
			} else {
				//Hierarchical taxonomies use ids instead of slugs
				//see http://codex.wordpress.org/Function_Reference/wp_set_post_objects
				$value_var = ($taxonomy->hierarchical ? 'term_id' : 'slug');

				foreach ($terms as $term) {
					//term names are encoded
					$retval[$term->$value_var] = htmlspecialchars_decode($term->name);
				}
			}
		}
		return $retval;
	}

	/**
	 *  Get hierarchical content with level param
	 *
	 * @since  3.7.2
	 * @param  array  $all_terms
	 * @param  array  $terms
	 * @param  int    $i
	 * @param  int    $start
	 * @param  int    $end
	 * @param  int    $level
	 * @param  array  &$retval
	 * @return void
	 */
	protected function _walk_tree($all_terms,$terms,&$i,$start,$end,$level,&$retval) {
		foreach ($terms as $term) {
			if ( $i >= $end ) {
				break;
			}

			if ( $i >= $start ) {
				$retval[] = array(
					'id' => $term->term_id,
					'text' => htmlspecialchars_decode($term->name),
					'level' => $level
				);
			}

			$i++;

			if ( isset( $all_terms[$term->term_id] ) ) {
				$this->_walk_tree( $all_terms, $all_terms[$term->term_id], $i, $start, $end, $level + 1, $retval );
			}
		}
	}

	/**
	 * Get registered public taxonomies
	 *
	 * @since   1.0
	 * @return  array
	 */
	protected function _get_taxonomies() {
		// List public taxonomies
		if (empty($this->taxonomy_objects)) {
			foreach (get_taxonomies(array('public' => true), 'objects') as $tax) {
				$this->taxonomy_objects[$tax->name] = $tax;
			}
			if(defined('POLYLANG_VERSION')) {
				unset($this->taxonomy_objects['language']);
			}
		}
		return $this->taxonomy_objects;
	}

	/**
	 * Get data for condition group
	 *
	 * @since  2.0
	 * @param  array  $group_data
	 * @param  int    $post_id
	 * @return array
	 */
	public function get_group_data($group_data,$post_id) {
		$ids = array_flip((array)get_post_custom_values(WPCACore::PREFIX . $this->id, $post_id));

		//Fetch all terms and group by tax to prevent lazy loading
		$terms = wp_get_object_terms(
			$post_id,
			array_keys($this->_get_taxonomies())
			// array(
			// 	'update_term_meta_cache' => false
			// )
		);
		$terms_by_tax = array();
		foreach($terms as $term) {
			$terms_by_tax[$term->taxonomy][] = $term;
		}

		foreach($this->_get_taxonomies() as $taxonomy) {

			$posts = isset($terms_by_tax[$taxonomy->name]) ? $terms_by_tax[$taxonomy->name] : 0;

			if($posts || isset($ids[$taxonomy->name])) {

				$placeholder = '/'.sprintf(__('%s Archives',WPCA_DOMAIN),$taxonomy->labels->singular_name);
				$placeholder = $taxonomy->labels->all_items.$placeholder;
				
				$group_data[$this->id.'-'.$taxonomy->name] = array(
					'label'         => $taxonomy->label,
					'placeholder'   => $placeholder,
					'default_value' => $taxonomy->name
				);

				if($posts) {
					$retval = array();

					//Hierarchical taxonomies use ids instead of slugs
					//see http://codex.wordpress.org/Function_Reference/wp_set_post_objects
					$value_var = ($taxonomy->hierarchical ? 'term_id' : 'slug');

					foreach ($posts as $post) {
						$retval[$post->$value_var] = $post->name;
					}
					$group_data[$this->id.'-'.$taxonomy->name]['data'] = $retval;
				}
			}
		}
		return $group_data;
	}

	/**
	 * Set module info in list
	 *
	 * @since  2.0
	 * @param  array  $list
	 * @return array
	 */
	public function list_module($list) {
		foreach($this->_get_taxonomies() as $taxonomy) {
			$placeholder = '/'.sprintf(__('%s Archives',WPCA_DOMAIN),$taxonomy->labels->singular_name);
			$placeholder = $taxonomy->labels->all_items.$placeholder;
			$list[$this->id.'-'.$taxonomy->name] = array(
				'name'          => $taxonomy->label,
				'placeholder'   => $placeholder,
				'default_value' => $taxonomy->name
			);
		}
		return $list;
	}

	/**
	 * Get content in JSON
	 *
	 * @since   1.0
	 * @param   array    $args
	 * @return  array
	 */
	public function ajax_get_content($args) {

		$args = wp_parse_args($args, array(
			'item_object'    => 'post',
			'paged'          => 1,
			'search'         => ''
		));

		preg_match('/taxonomy-(.+)$/i', $args['item_object'], $matches);
		$args['item_object'] = isset($matches[1]) ? $matches[1] : "";

		$taxonomy = get_taxonomy($args['item_object']);
		
		if(!$taxonomy) {
			return false;
		}

		$args['taxonomy'] = $args['item_object'];
		unset($args['item_object']);

		return $this->_get_content($args);
	}

	/**
	 * Save data on POST
	 *
	 * @since   1.0
	 * @param   int    $post_id
	 * @return  void
	 */
	public function save_data($post_id) {
		//parent::save_data($post_id);
		$meta_key = WPCACore::PREFIX . $this->id;
		$old = array_flip(get_post_meta($post_id, $meta_key, false));
		$tax_input = $_POST['conditions'];

		//Save terms
		//Loop through each public taxonomy
		foreach($this->_get_taxonomies() as $taxonomy) {

			//If no terms, maybe delete old ones
			if(!isset($tax_input[$this->id.'-'.$taxonomy->name])) {
				$terms = array();
				if(isset($old[$taxonomy->name])) {
					delete_post_meta($post_id, $meta_key, $taxonomy->name);
				}
			} else {
				$terms = $tax_input[$this->id.'-'.$taxonomy->name];

				$found_key = array_search($taxonomy->name, $terms);
				//If meta key found maybe add it
				if($found_key !== false) {
					if(!isset($old[$taxonomy->name])) {
						add_post_meta($post_id, $meta_key, $taxonomy->name);
					}
					unset($terms[$found_key]);
				//Otherwise maybe delete it
				} else if(isset($old[$taxonomy->name])) {
					delete_post_meta($post_id, $meta_key, $taxonomy->name);
				}

				//Hierarchical taxonomies use ids instead of slugs
				//see http://codex.wordpress.org/Function_Reference/wp_set_post_terms
				if($taxonomy->hierarchical) {
					$terms = array_unique(array_map('intval', $terms));
				}
			}

			wp_set_object_terms( $post_id, $terms, $taxonomy->name );

		}

	}

	/**
	 * Auto-select children of selected ancestor
	 *
	 * @since  1.0
	 * @param  int    $term_id  
	 * @param  int    $tt_id    
	 * @param  string $taxonomy 
	 * @return void           
	 */
	public function term_ancestry_check($term_id, $tt_id, $taxonomy) {
		
		if(is_taxonomy_hierarchical($taxonomy)) {
			$term = get_term($term_id, $taxonomy);

			if($term->parent != '0') {	
				// Get sidebars with term ancestor wanting to auto-select term
				$query = new WP_Query(array(
					'post_type'  => WPCACore::TYPE_CONDITION_GROUP,
					'meta_query' => array(
						array(
							'key'     => WPCACore::PREFIX . 'autoselect',
							'value'   => 1,
							'compare' => '='
						)
					),
					'tax_query' => array(
						array(
							'taxonomy'         => $taxonomy,
							'field'            => 'id',
							'terms'            => get_ancestors($term_id, $taxonomy),
							'include_children' => false
						)
					)
				));
				if($query && $query->found_posts) {
					foreach($query->posts as $post) {
						wp_set_post_terms($post->ID, $term_id, $taxonomy, true);
					}
				}
			}
		}
	}

}
