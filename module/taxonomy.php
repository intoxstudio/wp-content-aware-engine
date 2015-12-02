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
	 * Constructor
	 */
	public function __construct() {
		parent::__construct('taxonomy',__('Taxonomies',WPCACore::DOMAIN));
		$this->type_display = true;
	}

	public function initiate() {
		parent::initiate();
		add_action('created_term',
			array($this,'term_ancestry_check'),10,3);

		foreach ($this->_get_taxonomies() as $taxonomy) {
			add_action('wp_ajax_wpca/module/'.$this->id.'-'.$taxonomy->name,
				array($this,'ajax_print_content'));
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
			// Check if content has any taxonomies supported
			$taxonomies = get_object_taxonomies(get_post_type(),'object');
			//Only want taxonomies selectable in admin
			$taxonomy_names = array();
			foreach($taxonomies as $taxonomy) {
				if(isset($tax[$taxonomy->name]))
					$taxonomy_names[] = $taxonomy->name;
			}
			if(!empty($taxonomy_names)) {
				// Check if content has any actual taxonomy terms
				$this->post_terms = wp_get_object_terms(get_the_ID(),$taxonomy_names);
				return !empty($this->post_terms);
			}
			return false;
		}
		return is_tax() || is_category() || is_tag();
	}
	
	/**
	 * Query join
	 *
	 * @since  1.0
	 * @return string 
	 */
	public function db_join() {
		global $wpdb;
		
		$joins  = "LEFT JOIN $wpdb->term_relationships term ON term.object_id = posts.ID ";
		$joins .= "LEFT JOIN $wpdb->term_taxonomy taxonomy ON taxonomy.term_taxonomy_id = term.term_taxonomy_id ";
		$joins .= "LEFT JOIN $wpdb->terms terms ON terms.term_id = taxonomy.term_id ";
		$joins .= "LEFT JOIN $wpdb->postmeta post_taxonomy ON post_taxonomy.post_id = posts.ID AND post_taxonomy.meta_key = '".WPCACore::PREFIX."taxonomy'";
		
		return $joins;
	
	}

	/**
	 * Get data from context
	 *
	 * @since  1.0
	 * @return array
	 */
	public function get_context_data() {

		if(is_singular()) {
			$terms = array();

			//Grab posts taxonomies and terms and sort them
			foreach($this->post_terms as $term) {
				$terms[$term->taxonomy][] = $term->term_id;
			}
			
			// Make rules for taxonomies and terms
			foreach($terms as $taxonomy => $term_arr) {  
				$termrules[] = "(taxonomy.taxonomy = '".$taxonomy."' AND terms.term_id IN('".implode("','",$term_arr)."'))";
				$taxrules[] = $taxonomy;
				$taxrules[] = WPCACore::PREFIX."sub_".$taxonomy;
			}

			return "(terms.slug IS NULL OR ".implode(" OR ",$termrules).") AND (post_taxonomy.meta_value IS NULL OR post_taxonomy.meta_value IN('".implode("','",$taxrules)."'))";
		
			
		}
		$term = get_queried_object();

		return "(terms.slug IS NULL OR (taxonomy.taxonomy = '".$term->taxonomy."' AND terms.slug = '".$term->slug."')) AND (post_taxonomy.meta_value IS NULL OR post_taxonomy.meta_value IN ('".$term->taxonomy."','".WPCACore::PREFIX."sub_".$term->taxonomy."'))";
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
			'include'  => '',
			'taxonomy' => 'category',
			'number'   => 20,
			'orderby'  => 'name',
			'order'    => 'ASC',
			'offset'   => 0,
			'search'   => ''
		));
		extract($args);
		$total_items = wp_count_terms($taxonomy,array('hide_empty'=>false));
		$terms = array();
		if($total_items) {
			$terms = get_terms($taxonomy, array(
				'number'     => $number,
				'hide_empty' => false,
				'include'    => $include,
				'offset'     => ($offset*$number),
				'orderby'    => $orderby,
				'order'      => $order,
				'search'     => $args['search']
			));
		}
		return $terms;
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
			//Polylang module should later take advantage of taxonomy
			if(defined('POLYLANG_VERSION')) {
				unset($this->taxonomy_objects["language"]);
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
		$terms = wp_get_object_terms( $post_id, array_keys($this->_get_taxonomies()));
		$terms_by_tax = array();
		foreach($terms as $term) {
			$terms_by_tax[$term->taxonomy][] = $term;
		}

		foreach($this->_get_taxonomies() as $taxonomy) {

			$posts = isset($terms_by_tax[$taxonomy->name]) ? $terms_by_tax[$taxonomy->name] : 0;

			if($posts || isset($ids[$taxonomy->name]) || isset($ids[WPCACore::PREFIX.'sub_' . $taxonomy->name])) {
				
				$group_data[$this->id."-".$taxonomy->name] = array(
						"label" => $taxonomy->label
				);

				if($posts) {
					$retval = array();

					//Hierarchical taxonomies use ids instead of slugs
					//see http://codex.wordpress.org/Function_Reference/wp_set_post_objects
					$value_var = ($taxonomy->hierarchical ? 'term_id' : 'slug');

					foreach ($posts as $post) {
						$retval[$post->$value_var] = $post->name;
					}
					$group_data[$this->id."-".$taxonomy->name]["data"] = $retval;
				}

				if(isset($ids[WPCACore::PREFIX.'sub_' . $taxonomy->name])) {
					$group_data[$this->id."-".$taxonomy->name]["options"] = array(
						WPCACore::PREFIX.'sub_' . $taxonomy->name => true
					);
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
			$list[$this->id."-".$taxonomy->name] = $taxonomy->label;
		}
		return $list;
	}

	/**
	 * Create module Backbone template
	 * for administration
	 *
	 * @since  2.0
	 * @return void
	 */
	public function template_condition() {
		if(WPCACore::post_types()->has(get_post_type())) {
			foreach($this->_get_taxonomies() as $taxonomy) {
				if($this->type_display) {
					$placeholder = "/".sprintf(__("%s Archives",WPCACore::DOMAIN),$taxonomy->labels->singular_name);
					$placeholder = $taxonomy->labels->all_items.$placeholder;
				}
				echo WPCAView::make("module/condition_".$this->id."_template",array(
					'id'          => $this->id,
					'placeholder' => $placeholder,
					'taxonomy'    => $taxonomy->name,
					'autoselect'  => WPCACore::PREFIX.'sub_'.$taxonomy->name
				))->render();
			}
		}
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

		preg_match('/taxonomy-(.+)$/i', $args["item_object"], $matches);
		$args['item_object'] = isset($matches[1]) ? $matches[1] : "";

		$taxonomy = get_taxonomy($args['item_object']);
		
		if(!$taxonomy) {
			return false;
		}

		$posts = $this->_get_content(array(
			'taxonomy' => $args['item_object'],
			'orderby'  => 'name',
			'order'    => 'ASC',
			'offset'    => $args['paged']-1,
			'search'   => $args['search']
		));

		$retval = array();

		//Hierarchical taxonomies use ids instead of slugs
		//see http://codex.wordpress.org/Function_Reference/wp_set_post_objects
		$value_var = ($taxonomy->hierarchical ? 'term_id' : 'slug');

		foreach ($posts as $post) {
			$retval[$post->$value_var] = $post->name;
		}
		return $retval;

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
		$tax_input = isset($_POST['cas_condition'][$this->id]) ? $_POST['cas_condition'][$this->id] : array();

		//Save terms
		//Loop through each public taxonomy
		foreach($this->_get_taxonomies() as $taxonomy) {

			$special = array(
				$taxonomy->name,
				WPCACore::PREFIX.'sub_'.$taxonomy->name
			);

			//If no terms, maybe delete old ones
			if(!isset($tax_input[$taxonomy->name])) {
				$terms = array();
				foreach ($special as $key) {
					if(isset($old[$key])) {
						delete_post_meta($post_id, $meta_key, $key);
					}
				}
			} else {
				$terms = $tax_input[$taxonomy->name];

				foreach ($special as $key) {
					$found_key = array_search($key, $terms);
					//If special key found maybe add it
					if($found_key !== false) {
						if(!isset($old[$key])) {
							add_post_meta($post_id, $meta_key, $key);
						}
						unset($terms[$found_key]);
					//Otherwise maybe delete it
					} else if(isset($old[$key])) {
						delete_post_meta($post_id, $meta_key, $key);
					}
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
	 * Register taxonomies to sidebar post type
	 *
	 * @since  1.0
	 * @return void 
	 */
	public function add_taxonomies_to_sidebar() {
		foreach($this->_get_taxonomies() as $tax) {
			foreach (WPCACore::post_types()->get_all() as $post_type) {
				register_taxonomy_for_object_type( $tax->name, $post_type->name );
			}
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
							'key'     => WPCACore::PREFIX . $this->id,
							'value'   => WPCACore::PREFIX.'sub_' . $taxonomy,
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
