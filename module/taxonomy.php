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
		parent::__construct('taxonomy',__('Taxonomies',WPCACore::DOMAIN),true);
		$this->type_display = true;
		$this->searchable = true;

		add_action('created_term', array(&$this,'term_ancestry_check'),10,3);

	}

	/**
	 * Display module in Screen Settings
	 *
	 * @since   1.0
	 * @param   array    $columns
	 * @return  array
	 */
	public function metabox_preferences($columns) {
		foreach ($this->_get_taxonomies() as $tax) {
			$columns['box-'.$this->id.'-'.$tax->name] = $tax->label;
		}
		return $columns;
	}
	
	/**
	 * Determine if content is relevant
	 *
	 * @since  1.0
	 * @return boolean 
	 */
	public function in_context() {
		if(is_singular()) {
			// Check if content has any taxonomies supported
			$taxonomies = get_object_taxonomies(get_post_type(),'object');
			//Only want public taxonomies
			$taxonomy_names = array();
			foreach($taxonomies as $taxonomy) {
				if($taxonomy->public)
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
		if(!$total_items) {
			$terms = array();
		} else {
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
	
		$per_page = $number;
		$this->pagination = array(
			'paged'       => $offset+1,
			'per_page'    => $per_page,
			'total_pages' => ceil($total_items/$per_page),
			'total_items' => $total_items
		);

		
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
		}
		return $this->taxonomy_objects;
	}

	/**
	 * Print condition data for a group
	 *
	 * @since   1.0
	 * @param   int    $post_id
	 * @return  void
	 */
	public function print_group_data($post_id) {
		$ids = array_flip((array)get_post_custom_values(WPCACore::PREFIX . $this->id, $post_id));

		//Fetch all terms and group by tax to prevent lazy loading
		$terms = wp_get_object_terms( $post_id, array_keys($this->_get_taxonomies()));
		$terms_by_tax = array();
		foreach($terms as $term) {
			$terms_by_tax[$term->taxonomy][] = $term;
		}

		foreach($this->_get_taxonomies() as $taxonomy) {

			$posts = isset($terms_by_tax[$taxonomy->name]) ? $terms_by_tax[$taxonomy->name] : 0;

			//$posts = wp_get_object_terms( $post_id, $taxonomy->name);
			if($posts || isset($ids[$taxonomy->name]) || isset($ids[WPCACore::PREFIX.'sub_' . $taxonomy->name])) {
				echo '<div class="cas-condition cas-condition-'.$this->id.'-'.$taxonomy->name.'">';
				echo '<div class="cas-group-sep">';
				_e('And',WPCACore::DOMAIN);
				echo '</div>';
				echo '<h4>'.$taxonomy->label.'</h4>';
				echo '<ul>';
				if(isset($ids[WPCACore::PREFIX.'sub_' . $taxonomy->name])) {
					echo '<li class=""><label><input type="checkbox" name="cas_condition['.$this->id.'][]" value="'.WPCACore::PREFIX.'sub_' . $taxonomy->name . '" checked="checked" /> ' . __('Automatically select new children of a selected ancestor', WPCACore::DOMAIN) . '</label></li>' . "\n";
				}
				if(isset($ids[$taxonomy->name])) {
					echo '<li class=""><label><input type="checkbox" name="cas_condition['.$this->id.'][]" value="'.$taxonomy->name.'" checked="checked" /> '.$taxonomy->labels->all_items.'</label></li>' . "\n";
				}
				if($posts) {
					echo $this->term_checklist($taxonomy, $posts);
				}
				echo '</ul>';
				echo '</div>';
			}
		}

	}

	/**
	 * Meta box content
	 * 
	 * @global WP_Post $post
	 * @since  1.0
	 * @return void 
	 */
	public function meta_box_content() {
		global $post;

		$screen = get_current_screen();

		$hidden_columns  = get_hidden_columns( $screen->id );

		foreach ($this->_get_taxonomies() as $taxonomy) {

			$id = 'box-'.$this->id.'-'.$taxonomy->name;
			$hidden = in_array($id, $hidden_columns) ? ' hide-if-js' : '';

			echo '<li id="'.$id.'" class="manage-column column-'.$id.' control-section accordion-section'.$hidden.'">';
			echo '<h3 class="accordion-section-title" title="'.$taxonomy->label.'" tabindex="0">'.$taxonomy->label.'</h3>'."\n";
			echo '<div class="accordion-section-content cas-rule-content" data-cas-module="'.$this->id.'" id="cas-' . $this->id . '-' . $taxonomy->name . '">';

			$terms = $this->_get_content(array('taxonomy' => $taxonomy->name));
			

			if($taxonomy->hierarchical) {
				echo '<ul><li>' . "\n";
				echo '<label><input type="checkbox" name="cas_condition['.$this->id.'][]" value="'.WPCACore::PREFIX.'sub_' . $taxonomy->name . '" /> ' . __('Automatically select new children of a selected ancestor', WPCACore::DOMAIN) . '</label>' . "\n";
				echo '</li></ul>' . "\n";
			}
			echo '<ul><li>' . "\n";
			echo '<label><input class="cas-chk-all" type="checkbox" name="cas_condition['.$this->id.'][]" value="' . $taxonomy->name . '" /> ' . $taxonomy->labels->all_items . '</label>' . "\n";
			echo '</li></ul>' . "\n";
	
			if (!$terms) {
				echo '<p>' . __('No items.') . '</p>';
			} else {

				//No need to use two queries before knowing there are items
				if(count($terms) < 20) {
					$popular_terms = $terms;
				} else {
					$popular_terms = $this->_get_content(array('taxonomy' => $taxonomy->name, 'orderby' => 'count', 'order' => 'DESC'));
				}
				

				$tabs = array();
				$tabs['popular'] = array(
					'title'   => __('Most Used'),
					'status'  => true,
					'content' => $this->term_checklist($taxonomy, $popular_terms)
				);
				$tabs['all'] = array(
					'title'   => __('View All'),
					'status'  => false,
					'content' => $this->term_checklist($taxonomy, $terms, false, true)
				);
				if($this->searchable) {
					$tabs['search'] = array(
						'title' => __('Search'),
						'status' => false,
						'content' => '',
						'content_before' => '<p><input data-cas-item_object="'.$taxonomy->name.'" class="cas-autocomplete-' . $this->id . ' cas-autocomplete quick-search" id="cas-autocomplete-' . $this->id . '-' . $taxonomy->name . '" type="search" name="cas-autocomplete" value="" placeholder="'.__('Search').'" autocomplete="off" /><span class="spinner"></span></p>'
					);
				}

				echo $this->create_tab_panels($this->id . '-' . $taxonomy->name,$tabs);
				
			}

			echo '<p class="button-controls">';

			echo '<span class="add-to-group"><input data-cas-condition="'.$this->id.'-'.$taxonomy->name.'" type="button" name="" id="cas-' . $this->id . '-' . $taxonomy->name . '-add" class="js-cas-condition-add button" value="'.__('Add to Group',WPCACore::DOMAIN).'"></span>';

			echo '</p>';

			echo '</div>'."\n";
			echo '</li>';
		}
	}

	/**
	 * Show terms from a specific taxonomy
	 *
	 * @since  1.0
	 * @param  int     $post_id      
	 * @param  object  $taxonomy     
	 * @param  array   $terms        
	 * @param  array   $selected_ids 
	 * @return void 
	 */
	private function term_checklist($taxonomy, $terms, $selected_terms = false, $pagination = false) {

		//Hierarchical taxonomies use ids instead of slugs
		//see http://codex.wordpress.org/Function_Reference/wp_set_post_objects
		$value_var = ($taxonomy->hierarchical ? 'term_id' : 'slug');
		$args = array(
			'taxonomy'       => $taxonomy,
			'selected_terms' => $selected_terms
		);
		
		$return = WPCAWalker::make(array("tax_input",$taxonomy->name),'parent','term_id','name',$value_var)
		->walk($terms, 0, $args);

		if($pagination) {
			$paginate = paginate_links(array(
				'base'      => admin_url( 'admin-ajax.php').'%_%',
				'format'    => '?paged=%#%',
				'total'     => $this->pagination['total_pages'],
				'current'   => $this->pagination['paged'],
				'mid_size'  => 2,
				'end_size'  => 1,
				'prev_next' => true,
				'prev_text' => 'prev',
				'next_text' => 'next',
				'add_args'  => array('item_object'=>$taxonomy->name),
			));
			$return = $paginate.$return.$paginate;
		}

		return $return;

	}

	/**
	 * Get content in HTML
	 *
	 * @since   1.0
	 * @param   array    $args
	 * @return  string
	 */
	public function ajax_get_content($args) {

		$args = wp_parse_args($args, array(
			'item_object'    => 'post',
			'paged'          => 1,
			'search'         => ''
		));

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

		return $this->term_checklist($taxonomy, $posts, array(), empty($args['search']));

	}

	/**
	 * Save data on POST
	 *
	 * @since   1.0
	 * @param   int    $post_id
	 * @return  void
	 */
	public function save_data($post_id) {
		parent::save_data($post_id);

		$tax_input = isset($_POST['cas_condition']['tax_input']) ? $_POST['cas_condition']['tax_input'] : array();

		//Save terms
		//Loop through each public taxonomy
		foreach($this->_get_taxonomies() as $taxonomy) {

			if (current_user_can($taxonomy->cap->assign_terms) ) {

				//If no terms, maybe delete old ones
				if(!isset($tax_input[$taxonomy->name])) {
					$terms = null;
				} else {
					$terms = $tax_input[$taxonomy->name];

					//Hierarchical taxonomies use ids instead of slugs
					//see http://codex.wordpress.org/Function_Reference/wp_set_post_terms
					if($taxonomy->hierarchical) {
						$terms = array_unique(array_map('intval', $terms));
					}						
				}

				wp_set_object_terms( $post_id, $terms, $taxonomy->name );
			}			

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
