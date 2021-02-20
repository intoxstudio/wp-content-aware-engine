<?php
/**
 * @package WP Content Aware Engine
 * @author Joachim Jensen <joachim@dev.institute>
 * @license GPLv3
 * @copyright 2020 by Joachim Jensen
 */

defined('ABSPATH') || exit;

/**
 *
 * Taxonomy Module
 *
 * Detects if current content has/is:
 * a) any term of specific taxonomy or specific term
 * b) taxonomy archive or specific term archive
 *
 */
class WPCAModule_taxonomy extends WPCAModule_Base
{
    /**
     * when condition has select terms,
     * set this value in postmeta
     * @see parent::filter_excluded_context()
     */
    const VALUE_HAS_TERMS = '-1';

    /**
     * @var string
     */
    protected $category = 'taxonomy';

    /**
     * Registered public taxonomies
     *
     * @var array
     */
    private $taxonomy_objects = [];

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
    public function __construct()
    {
        parent::__construct('taxonomy', __('Taxonomies', WPCA_DOMAIN));

        $this->query_name = 'ct';
    }

    public function initiate()
    {
        parent::initiate();
        add_action(
            'created_term',
            [$this,'term_ancestry_check'],
            10,
            3
        );

        if (is_admin()) {
            foreach ($this->_get_taxonomies() as $taxonomy) {
                add_action(
                    'wp_ajax_wpca/module/'.$this->id.'-'.$taxonomy->name,
                    [$this,'ajax_print_content']
                );
            }
        }
    }

    /**
     * Determine if content is relevant
     *
     * @since  1.0
     * @return boolean
     */
    public function in_context()
    {
        if (is_singular()) {
            $tax = $this->_get_taxonomies();
            $this->post_terms = [];
            $this->post_taxonomies = [];

            // Check if content has any taxonomies supported
            foreach (get_object_taxonomies(get_post_type()) as $taxonomy) {
                //Only want taxonomies selectable in admin
                if (isset($tax[$taxonomy])) {

                    //Check term caches, Core most likely used it
                    $terms = get_object_term_cache(get_the_ID(), $taxonomy);
                    if ($terms === false) {
                        $terms = wp_get_object_terms(get_the_ID(), $taxonomy);
                    }
                    if ($terms) {
                        $this->post_taxonomies[] = $taxonomy;
                        $this->post_terms = array_merge($this->post_terms, $terms);
                    }
                }
            }
            return !empty($this->post_terms);
        }
        return is_tax() || is_category() || is_tag();
    }

    /**
     * Query join
     *
     * @since  1.0
     * @return string
     */
    public function db_join()
    {
        global $wpdb;
        $joins = parent::db_join();
        $joins .= "LEFT JOIN $wpdb->term_relationships term ON term.object_id = p.ID ";
        return $joins;
    }

    /**
     * Get data from context
     *
     * @since  1.0
     * @return array
     */
    public function get_context_data()
    {
        $name = $this->get_query_name();

        //In more recent WP versions, term_id = term_tax_id
        //but term_tax_id has always been unique
        if (is_singular()) {
            $terms = [];
            foreach ($this->post_terms as $term) {
                $terms[] = $term->term_taxonomy_id;
            }
            $tax = $this->post_taxonomies;
        } else {
            $term = get_queried_object();
            $terms = [$term->term_taxonomy_id];
            $tax = [$term->taxonomy];
        }

        $tax[] = self::VALUE_HAS_TERMS;
        return '(term.term_taxonomy_id IS NULL OR term.term_taxonomy_id IN ('.implode(',', $terms).")) AND ($name.meta_value IS NULL OR $name.meta_value IN('".implode("','", $tax)."'))";
    }

    /**
     * Get content for sidebar editor
     *
     * @since  1.0
     * @param  array $args
     * @return array
     */
    protected function _get_content($args = [])
    {
        $total_items = wp_count_terms($args['taxonomy'], [
            'hide_empty' => $args['hide_empty']
        ]);

        $start = $args['offset'];
        $end = $start + $args['number'];
        $walk_tree = false;
        $retval = [];

        if ($total_items) {
            $taxonomy = get_taxonomy($args['taxonomy']);

            if ($taxonomy->hierarchical && !$args['search'] && !$args['include']) {
                $args['number'] = 0;
                $args['offset'] = 0;

                $walk_tree = true;
            }

            $terms = new WP_Term_Query($args);

            if ($walk_tree) {
                $sorted_terms = [];
                foreach ($terms->terms as $term) {
                    $sorted_terms[$term->parent][] = $term;
                }
                $i = 0;
                $this->_walk_tree($sorted_terms, $sorted_terms[0], $i, $start, $end, 0, $retval);
            } else {
                //Hierarchical taxonomies use ids instead of slugs
                //see http://codex.wordpress.org/Function_Reference/wp_set_post_objects
                $value_var = ($taxonomy->hierarchical ? 'term_id' : 'slug');

                foreach ($terms->terms as $term) {
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
    protected function _walk_tree($all_terms, $terms, &$i, $start, $end, $level, &$retval)
    {
        foreach ($terms as $term) {
            if ($i >= $end) {
                break;
            }

            if ($i >= $start) {
                $retval[] = [
                    'id'    => $term->term_id,
                    'text'  => htmlspecialchars_decode($term->name),
                    'level' => $level
                ];
            }

            $i++;

            if (isset($all_terms[$term->term_id])) {
                $this->_walk_tree($all_terms, $all_terms[$term->term_id], $i, $start, $end, $level + 1, $retval);
            }
        }
    }

    /**
     * Get registered public taxonomies
     *
     * @since   1.0
     * @return  array
     */
    protected function _get_taxonomies()
    {
        // List public taxonomies
        if (empty($this->taxonomy_objects)) {
            foreach (get_taxonomies(['public' => true], 'objects') as $tax) {
                $this->taxonomy_objects[$tax->name] = $tax;
            }
            if (defined('POLYLANG_VERSION')) {
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
    public function get_group_data($group_data, $post_id)
    {
        $ids = array_flip((array)get_post_custom_values(WPCACore::PREFIX . $this->id, $post_id));

        //Fetch all terms and group by tax to prevent lazy loading
        $terms = wp_get_object_terms(
            $post_id,
            array_keys($this->_get_taxonomies())
            // array(
            // 	'update_term_meta_cache' => false
            // )
        );
        $terms_by_tax = [];
        foreach ($terms as $term) {
            $terms_by_tax[$term->taxonomy][] = $term;
        }

        $title_count = $this->get_title_count();
        foreach ($this->_get_taxonomies() as $taxonomy) {
            $posts = isset($terms_by_tax[$taxonomy->name]) ? $terms_by_tax[$taxonomy->name] : 0;

            if ($posts || isset($ids[$taxonomy->name])) {
                $group_data[$this->id.'-'.$taxonomy->name] = $this->get_list_data($taxonomy, $title_count[$taxonomy->label]);
                $group_data[$this->id.'-'.$taxonomy->name]['label'] = $group_data[$this->id.'-'.$taxonomy->name]['text'];

                if ($posts) {
                    $retval = [];

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
     * Count taxonomy labels to find shared ones
     *
     * @return array
     */
    protected function get_title_count()
    {
        $title_count = [];
        foreach ($this->_get_taxonomies() as $taxonomy) {
            if (!isset($title_count[$taxonomy->label])) {
                $title_count[$taxonomy->label] = 0;
            }
            $title_count[$taxonomy->label]++;
        }
        return $title_count;
    }

    protected function get_list_data($taxonomy, $title_count)
    {
        $placeholder = '/'.sprintf(__('%s Archives', WPCA_DOMAIN), $taxonomy->labels->singular_name);
        $placeholder = $taxonomy->labels->all_items.$placeholder;
        $label = $taxonomy->label;

        if (count($taxonomy->object_type) === 1 && $title_count > 1) {
            $post_type = get_post_type_object($taxonomy->object_type[0]);
            $label .= ' (' . $post_type->label . ')';
        }

        return [
            'text'          => $label,
            'placeholder'   => $placeholder,
            'default_value' => $taxonomy->name
        ];
    }

    /**
     * @since 2.0
     * @param array $list
     *
     * @return array
     */
    public function list_module($list)
    {
        $title_count = $this->get_title_count();
        foreach ($this->_get_taxonomies() as $taxonomy) {
            $data = $this->get_list_data($taxonomy, $title_count[$taxonomy->label]);
            $data['id'] = $this->id.'-'.$taxonomy->name;
            $list[] = $data;
        }
        return $list;
    }

    /**
    * @param array $args
    *
    * @return array
    */
    protected function parse_query_args($args)
    {
        if (isset($args['item_object'])) {
            preg_match('/taxonomy-(.+)$/i', $args['item_object'], $matches);
            $args['item_object'] = isset($matches[1]) ? $matches[1] : '___';
            $taxonomy_name = $args['item_object'];
        } else {
            $taxonomy_name = 'category';
        }

        return [
            'include'                => $args['include'],
            'taxonomy'               => $taxonomy_name,
            'number'                 => $args['limit'],
            'offset'                 => ($args['paged'] - 1) * $args['limit'],
            'orderby'                => 'name',
            'order'                  => 'ASC',
            'search'                 => $args['search'],
            'hide_empty'             => false,
            'update_term_meta_cache' => false
        ];
    }

    /**
     * Save data on POST
     *
     * @since   1.0
     * @param   int    $post_id
     * @return  void
     */
    public function save_data($post_id)
    {
        $meta_key = WPCACore::PREFIX . $this->id;
        $old = array_flip(get_post_meta($post_id, $meta_key, false));
        $tax_input = $_POST['conditions'];

        $has_select_terms = false;

        //Save terms
        //Loop through each public taxonomy
        foreach ($this->_get_taxonomies() as $taxonomy) {

            //If no terms, maybe delete old ones
            if (!isset($tax_input[$this->id.'-'.$taxonomy->name])) {
                $terms = [];
                if (isset($old[$taxonomy->name])) {
                    delete_post_meta($post_id, $meta_key, $taxonomy->name);
                }
            } else {
                $terms = $tax_input[$this->id.'-'.$taxonomy->name];

                $found_key = array_search($taxonomy->name, $terms);
                //If meta key found maybe add it
                if ($found_key !== false) {
                    if (!isset($old[$taxonomy->name])) {
                        add_post_meta($post_id, $meta_key, $taxonomy->name);
                    }
                    unset($terms[$found_key]);
                //Otherwise maybe delete it
                } elseif (isset($old[$taxonomy->name])) {
                    delete_post_meta($post_id, $meta_key, $taxonomy->name);
                }

                //Hierarchical taxonomies use ids instead of slugs
                //see http://codex.wordpress.org/Function_Reference/wp_set_post_terms
                if ($taxonomy->hierarchical) {
                    $terms = array_unique(array_map('intval', $terms));
                }
            }

            if (!empty($terms)) {
                $has_select_terms = true;
            }

            wp_set_object_terms($post_id, $terms, $taxonomy->name);
        }

        if ($has_select_terms && !isset($old[self::VALUE_HAS_TERMS])) {
            add_post_meta($post_id, $meta_key, self::VALUE_HAS_TERMS);
        } elseif (!$has_select_terms && isset($old[self::VALUE_HAS_TERMS])) {
            delete_post_meta($post_id, $meta_key, self::VALUE_HAS_TERMS);
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
    public function term_ancestry_check($term_id, $tt_id, $taxonomy)
    {
        if (is_taxonomy_hierarchical($taxonomy)) {
            $term = get_term($term_id, $taxonomy);

            if ($term->parent != '0') {
                // Get sidebars with term ancestor wanting to auto-select term
                $query = new WP_Query([
                    'post_type'   => WPCACore::TYPE_CONDITION_GROUP,
                    'post_status' => [WPCACore::STATUS_OR,WPCACore::STATUS_EXCEPT,WPCACore::STATUS_PUBLISHED],
                    'meta_query'  => [
                        [
                            'key'     => WPCACore::PREFIX . 'autoselect',
                            'value'   => 1,
                            'compare' => '='
                        ]
                    ],
                    'tax_query' => [
                        [
                            'taxonomy'         => $taxonomy,
                            'field'            => 'id',
                            'terms'            => get_ancestors($term_id, $taxonomy),
                            'include_children' => false
                        ]
                    ]
                ]);
                if ($query && $query->found_posts) {
                    foreach ($query->posts as $post) {
                        wp_set_post_terms($post->ID, $term_id, $taxonomy, true);
                    }
                    do_action('wpca/modules/auto-select/'.$this->category, $query->posts, $term);
                }
            }
        }
    }
}
