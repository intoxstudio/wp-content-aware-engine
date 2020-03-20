<?php
/**
 * @package WP Content Aware Engine
 * @author Joachim Jensen <joachim@dev.institute>
 * @license GPLv3
 * @copyright 2020 by Joachim Jensen
 */

defined('ABSPATH') || exit;

if (!class_exists('WPCACore')) {
    $domain = explode('/', plugin_basename(__FILE__));
    define('WPCA_DOMAIN', $domain[0]);
    define('WPCA_PATH', plugin_dir_path(__FILE__));

    /**
     * Core for WordPress Content Aware Engine
     */
    final class WPCACore
    {

        /**
         * Using class prefix instead of namespace
         * for PHP5.2 compatibility
         */
        const CLASS_PREFIX = 'WPCA';

        /**
         * Prefix for data (keys) stored in database
         */
        const PREFIX = '_ca_';

        /**
         * Post Type for condition groups
         */
        const TYPE_CONDITION_GROUP = 'condition_group';

        /**
         * Post Statuses for condition groups
         */
        const STATUS_NEGATED = 'negated';
        const STATUS_PUBLISHED = 'publish';

        /**
         * Exposures for condition groups
         */
        const EXP_SINGULAR = 0;
        const EXP_SINGULAR_ARCHIVE = 1;
        const EXP_ARCHIVE = 2;

        /**
         * @deprecated 7.0
         */
        const CAPABILITY = 'edit_theme_options';

        /**
         * Name for generated nonces
         */
        const NONCE = '_ca_nonce';

        const OPTION_CONDITION_TYPE_CACHE = '_ca_condition_type_cache';

        /**
         * Post Types that use the engine
         * @var WPCAPostTypeManager
         */
        private static $type_manager;

        /**
         * Conditions retrieved from database
         * @var array
         */
        private static $condition_cache = array();

        /**
         * Objects retrieved from database
         * @var array
         */
        private static $post_cache = array();

        /**
         * Constructor
         */
        public static function init()
        {
            spl_autoload_register(array(__CLASS__,'_autoload_class_files'));

            if (is_admin()) {
                add_action(
                    'admin_enqueue_scripts',
                    array(__CLASS__,'add_group_script_styles'),
                    9
                );
                add_action(
                    'delete_post',
                    array(__CLASS__,'sync_group_deletion')
                );
                add_action(
                    'trashed_post',
                    array(__CLASS__,'sync_group_trashed')
                );
                add_action(
                    'untrashed_post',
                    array(__CLASS__,'sync_group_untrashed')
                );
                add_action(
                    'add_meta_boxes',
                    array(__CLASS__,'add_group_meta_box'),
                    10,
                    2
                );
                add_action(
                    'wpca/modules/save-data',
                    array(__CLASS__,'save_condition_options'),
                    10,
                    3
                );

                add_action(
                    'wp_ajax_wpca/add-rule',
                    array(__CLASS__,'ajax_update_group')
                );
            }

            add_action(
                'init',
                array(__CLASS__,'add_group_post_type'),
                99
            );

            add_action(
                'init',
                array(__CLASS__,'schedule_cache_condition_types'),
                99
            );

            add_action(
                'wpca/cache_condition_types',
                array(__CLASS__,'cache_condition_types'),
                999
            );
        }

        /**
         * Get post type manager
         *
         * @deprecated 4.0
         * @since   1.0
         * @return  WPCAPostTypeManager
         */
        public static function post_types()
        {
            return self::types();
        }

        /**
         * Get type manager
         *
         * @since   4.0
         * @return  WPCAPostTypeManager
         */
        public static function types()
        {
            if (!isset(self::$type_manager)) {
                self::$type_manager = new WPCATypeManager();
            }
            return self::$type_manager;
        }

        /**
         * @since 8.0
         *
         * @return void
         */
        public static function schedule_cache_condition_types()
        {
            if (wp_next_scheduled('wpca/cache_condition_types') !== false) {
                return;
            }

            wp_schedule_event(get_gmt_from_date('today 02:00:00', 'U'), 'daily', 'wpca/cache_condition_types');
        }

        /**
         * Cache condition types currently in use
         *
         * @since 8.0
         *
         * @return void
         */
        public static function cache_condition_types()
        {
            $all_modules = array();
            $modules_by_type = array();
            $ignored_modules = array('taxonomy' => 1);
            $cache = array();

            $types = self::types();
            foreach ($types as $type => $modules) {
                $modules_by_type[$type] = array();
                $cache[$type] = array();
                foreach ($modules as $module) {
                    if (isset($ignored_modules[$module->get_id()])) {
                        continue;
                    }

                    $modules_by_type[$type][$module->get_data_key()] = $module->get_id();
                    $all_modules[$module->get_data_key()] = $module->get_data_key();
                }
            }

            if (!$all_modules) {
                update_option(self::OPTION_CONDITION_TYPE_CACHE, array());
                return;
            }

            global $wpdb;

            $query = '
SELECT p.post_type, m.meta_key
FROM '.$wpdb->posts.' p
INNER JOIN '.$wpdb->posts.' c ON c.post_parent = p.ID
INNER JOIN '.$wpdb->postmeta.' m ON m.post_id = c.ID
WHERE p.post_type IN ('.self::sql_prepare_in(array_keys($modules_by_type)).')
AND m.meta_key IN ('.self::sql_prepare_in($all_modules).')
GROUP BY p.post_type, m.meta_key
';

            $results = (array) $wpdb->get_results($query);

            foreach ($results as $result) {
                if (isset($modules_by_type[$result->post_type][$result->meta_key])) {
                    $cache[$result->post_type][] = $modules_by_type[$result->post_type][$result->meta_key];
                }
            }

            update_option(self::OPTION_CONDITION_TYPE_CACHE, $cache);
        }

        /**
         * Register group post type
         *
         * @since   1.0
         * @return  void
         */
        public static function add_group_post_type()
        {
            //This is just a safety placeholder,
            //authorization will be done with parent object's cap
            $capability = 'edit_theme_options';
            $capabilities = array(
                'edit_post'          => $capability,
                'read_post'          => $capability,
                'delete_post'        => $capability,
                'edit_posts'         => $capability,
                'delete_posts'       => $capability,
                'edit_others_posts'  => $capability,
                'publish_posts'      => $capability,
                'read_private_posts' => $capability
            );

            register_post_type(self::TYPE_CONDITION_GROUP, array(
                'labels' => array(
                    'name'          => __('Condition Groups', WPCA_DOMAIN),
                    'singular_name' => __('Condition Group', WPCA_DOMAIN),
                ),
                'capabilities'        => $capabilities,
                'public'              => false,
                'hierarchical'        => false,
                'exclude_from_search' => true,
                'publicly_queryable'  => false,
                'show_ui'             => false,
                'show_in_menu'        => false,
                'show_in_nav_menus'   => false,
                'show_in_admin_bar'   => false,
                'has_archive'         => false,
                'rewrite'             => false,
                'query_var'           => false,
                'supports'            => array('author'),
                'can_export'          => false,
                'delete_with_user'    => false
            ));

            register_post_status(self::STATUS_NEGATED, array(
                'label'                     => _x('Negated', 'condition status', WPCA_DOMAIN),
                'public'                    => false,
                'exclude_from_search'       => true,
                'show_in_admin_all_list'    => false,
                'show_in_admin_status_list' => false,
            ));
        }

        /**
         * Get group IDs by their parent ID
         *
         * @since   1.0
         * @param   int    $parent_id
         * @return  array
         */
        private static function get_group_ids_by_parent($parent_id)
        {
            if (!self::types()->has(get_post_type($parent_id))) {
                return array();
            }

            global $wpdb;
            return (array)$wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_parent = '%d'", $parent_id));
        }

        /**
         * Delete groups from database when their parent is deleted
         *
         * @since  1.0
         * @param  int    $post_id
         * @return void
         */
        public static function sync_group_deletion($post_id)
        {
            $groups = self::get_group_ids_by_parent($post_id);
            if ($groups) {
                foreach ($groups as $group_id) {
                    //Takes care of metadata and terms too
                    wp_delete_post($group_id, true);
                }
            }
        }

        /**
         * Trash groups when their parent is trashed
         *
         * @since   1.0
         * @param   int    $post_id
         * @return  void
         */
        public static function sync_group_trashed($post_id)
        {
            $groups = self::get_group_ids_by_parent($post_id);
            if ($groups) {
                foreach ($groups as $group_id) {
                    wp_trash_post($group_id);
                }
            }
        }

        /**
         * Untrash groups when their parent is untrashed
         *
         * @since   1.0
         * @param   int    $post_id
         * @return  void
         */
        public static function sync_group_untrashed($post_id)
        {
            $groups = self::get_group_ids_by_parent($post_id);
            if ($groups) {
                foreach ($groups as $group_id) {
                    wp_untrash_post($group_id);
                }
            }
        }

        private static $wp_query_original = array();

        /**
         * Get filtered condition groups
         *
         * @since  2.0
         * @return array
         */
        public static function get_conditions($post_type)
        {
            global $wpdb, $wp_query, $post;

            if ((!$wp_query->query && !$post) || is_admin()) {
                return array();
            }

            // Return cache if present
            if (isset(self::$condition_cache[$post_type])) {
                return self::$condition_cache[$post_type];
            }

            $excluded = array();
            $where = array();
            $join = array();

            $cache = array(
                $post_type
            );

            $modules = self::types()->get($post_type)->get_all();
            $modules = self::filter_condition_type_cache($post_type, $modules);

            foreach (self::types() as $other_type => $other_modules) {
                if ($other_type == $post_type) {
                    continue;
                }
                if (self::filter_condition_type_cache($other_type, $other_modules->get_all()) === $modules) {
                    $cache[] = $other_type;
                }
            }

            self::fix_wp_query();

            foreach ($modules as $module) {
                $id = $module->get_id();
                $name = $module->get_query_name();
                if (apply_filters("wpca/module/$id/in-context", $module->in_context())) {
                    $join[$id] = apply_filters("wpca/module/$id/db-join", $module->db_join());
                    $data = $module->get_context_data();
                    if (is_array($data)) {
                        $data = "($name.meta_value IS NULL OR $name.meta_value IN ('".implode("','", $data) ."'))";
                    }
                    $where[$id] = apply_filters("wpca/module/$id/db-where", $data);
                } else {
                    $excluded[] = $module;
                }
            }

            // Check if there are any conditions for current content
            $groups_in_context = array();
            if (!empty($where)) {
                $post_status = array(
                    self::STATUS_PUBLISHED,
                    self::STATUS_NEGATED
                );

                if (defined('CAS_SQL_CHUNK_SIZE') && CAS_SQL_CHUNK_SIZE > 0) {
                    $chunk_size = CAS_SQL_CHUNK_SIZE;
                } else {
                    //Syntax changed in MySQL 5.5 and MariaDB 10.0 (reports as version 5.5)
                    $wpdb->query('SET'.(version_compare($wpdb->db_version(), '5.5', '>=') ? ' SESSION' : ' OPTION').' SQL_BIG_SELECTS = 1');
                    $chunk_size = count($join);
                }

                $joins = array_chunk($join, $chunk_size);
                $joins_max = count($joins) - 1;
                $wheres = array_chunk($where, $chunk_size);
                $group_ids = array();
                $groups_in_context = array();

                $where2 = array();
                $where2[] = "p.post_type = '".self::TYPE_CONDITION_GROUP."'";
                $where2[] = "p.post_status IN ('".implode("','", $post_status)."')";
                //exposure
                $where2[] = 'p.menu_order '.(is_archive() || is_home() ? '>=' : '<=').' 1';

                foreach ($joins as $i => $join) {
                    if ($i == $joins_max) {
                        $groups_in_context = $wpdb->get_results(
                            'SELECT p.ID, p.post_parent '.
                            "FROM $wpdb->posts p ".
                            implode(' ', $join).'
                            WHERE
                            '.implode(' AND ', $wheres[$i]).'
                            AND '.implode(' AND ', $where2).
                            (!empty($group_ids) ? ' AND p.id IN ('.implode(',', $group_ids).')' : ''),
                            OBJECT_K
                        );
                        break;
                    }

                    $group_ids = array_merge($group_ids, $wpdb->get_col(
                        'SELECT p.ID '.
                        "FROM $wpdb->posts p ".
                        implode(' ', $join).'
                        WHERE
                        '.implode(' AND ', $wheres[$i]).'
                        AND '.implode(' AND ', $where2)
                    ));
                }
            }

            $groups_negated = $wpdb->get_results($wpdb->prepare(
                'SELECT p.ID, p.post_parent '.
                "FROM $wpdb->posts p ".
                "WHERE p.post_type = '%s' ".
                "AND p.post_status = '%s' ",
                self::TYPE_CONDITION_GROUP,
                self::STATUS_NEGATED
            ), OBJECT_K);

            if (!empty($groups_in_context) || !empty($groups_negated)) {
                //Force update of meta cache to prevent lazy loading
                update_meta_cache('post', array_keys($groups_in_context + $groups_negated));
            }

            //condition group => type
            $valid = array();
            foreach ($groups_in_context as $group) {
                $valid[$group->ID] = $group->post_parent;
            }

            //Exclude types that have unrelated content in same group
            foreach ($excluded as $module) {
                $valid = $module->filter_excluded_context($valid);
            }

            //Filter negated groups
            //type => group
            $handled_already = array_flip($valid);
            foreach ($groups_negated as $group) {
                if (isset($valid[$group->ID])) {
                    unset($valid[$group->ID]);
                } else {
                    $valid[$group->ID] = $group->post_parent;
                }
                if (isset($handled_already[$group->post_parent])) {
                    unset($valid[$group->ID]);
                }
                $handled_already[$group->post_parent] = 1;
            }

            self::restore_wp_query();

            foreach ($cache as $cache_type) {
                self::$condition_cache[$cache_type] = $valid;
            }

            return self::$condition_cache[$post_type];
        }

        /**
         * Get filtered posts from a post type
         *
         * @since  1.0
         * @global type     $wpdb
         * @global WP_Query $wp_query
         * @global WP_Post  $post
         * @return array
         */
        public static function get_posts($post_type)
        {
            global $wpdb, $wp_query, $post;

            // Return cache if present
            if (isset(self::$post_cache[$post_type])) {
                return self::$post_cache[$post_type];
            }

            if (!self::types()->has($post_type) || (!$wp_query->query && !$post) || is_admin()) {
                return false;
            }

            $valid = self::get_conditions($post_type);

            self::$post_cache[$post_type] = array();

            if ($valid) {
                $results = $wpdb->get_results("
					SELECT
						p.ID,
						p.post_type,
						h.meta_value handle
					FROM $wpdb->posts p
					INNER JOIN $wpdb->postmeta h ON h.post_id = p.ID AND h.meta_key = '".self::PREFIX."handle'
					WHERE
						p.post_type = '".$post_type."' AND
						p.post_status = 'publish' AND
						p.ID IN(".implode(',', $valid).')
					ORDER BY p.menu_order ASC, h.meta_value DESC, p.post_date DESC
				', OBJECT_K);

                self::$post_cache[$post_type] = apply_filters("wpca/posts/{$post_type}", $results);
            }
            return self::$post_cache[$post_type];
        }

        /**
         * Add meta box to manage condition groups
         *
         * @since   1.0
         * @param   string    $post_type
         * @param   WP_Post   $post
         */
        public static function add_group_meta_box($post_type, $post)
        {
            self::render_group_meta_box($post, $post_type, 'normal', 'default');
        }

        public static function render_group_meta_box($post, $screen, $context = 'normal', $priority = 'default')
        {
            if (!self::types()->has($post->post_type)) {
                return;
            }

            $post_type_obj = get_post_type_object($post->post_type);

            if (!current_user_can($post_type_obj->cap->edit_post, $post->ID)) {
                return;
            }

            $template = WPCAView::make('condition_options');
            add_action('wpca/group/settings', array($template,'render'), -1, 2);

            $template = WPCAView::make('group_template', array(
                'post_type' => $post->post_type
            ));
            add_action('admin_footer', array($template,'render'));

            $template = WPCAView::make('condition_template');
            add_action('admin_footer', array($template,'render'));

            $view = WPCAView::make('meta_box', array(
                'post_type' => $post->post_type,
                'nonce'     => wp_nonce_field(self::PREFIX.$post->ID, self::NONCE, true, false),
            ));

            $title = isset($post_type_obj->labels->ca_title) ? $post_type_obj->labels->ca_title : __('Conditional Logic', WPCA_DOMAIN);

            add_meta_box(
                'cas-rules',
                $title,
                array($view,'render'),
                $screen,
                $context,
                $priority
            );
        }

        /**
         * Insert new condition group for a post type
         * Uses current post per default
         *
         * @since  1.0
         * @param  WP_Post|int    $post
         * @return int
         */
        public static function add_condition_group($post_id = null)
        {
            $post = get_post($post_id);

            //Make sure to go from auto-draft to draft
            if ($post->post_status == 'auto-draft') {
                wp_update_post(array(
                    'ID'          => $post->ID,
                    'post_title'  => '',
                    'post_status' => 'draft'
                ));
            }

            return wp_insert_post(array(
                'post_status' => self::STATUS_PUBLISHED,
                'menu_order'  => self::EXP_SINGULAR_ARCHIVE,
                'post_type'   => self::TYPE_CONDITION_GROUP,
                'post_author' => $post->post_author,
                'post_parent' => $post->ID,
            ));
        }

        /**
         * Get condition groups for a post type
         * Uses current post per default
         *
         * @since  1.0
         * @param  WP_Post|int    $post_id
         * @return array
         */
        private static function get_condition_groups($post_id = null)
        {
            $post = get_post($post_id);
            $groups = array();

            if ($post) {
                $groups = get_posts(array(
                    'posts_per_page' => -1,
                    'post_type'      => self::TYPE_CONDITION_GROUP,
                    'post_parent'    => $post->ID,
                    'post_status'    => array(self::STATUS_PUBLISHED,self::STATUS_NEGATED),
                    'order'          => 'ASC'
                ));
            }
            return $groups;
        }

        /**
         * AJAX callback to update a condition group
         *
         * @since 1.0
         * @return  void
         */
        public static function ajax_update_group()
        {
            if (!isset($_POST['current_id']) ||
                !check_ajax_referer(self::PREFIX.$_POST['current_id'], 'token', false)) {
                wp_send_json_error(__('Unauthorized request', WPCA_DOMAIN), 403);
            }

            $parent_id = (int)$_POST['current_id'];
            $parent_type = get_post_type_object($_POST['post_type']);

            if (! current_user_can($parent_type->cap->edit_post, $parent_id)) {
                wp_send_json_error(__('Unauthorized request', WPCA_DOMAIN), 403);
            }

            $response = array(
                'message' => __('Conditions updated', WPCA_DOMAIN)
            );

            //Make sure some rules are sent
            if (!isset($_POST['conditions'])) {
                //Otherwise we delete group
                if ($_POST['id'] && wp_delete_post(intval($_POST['id']), true) === false) {
                    wp_send_json_error(__('Could not delete conditions', WPCA_DOMAIN), 500);
                }

                $response['removed'] = true;
                wp_send_json($response);
            }

            //If ID was not sent at this point, it is a new group
            if (!$_POST['id']) {
                $post_id = self::add_condition_group($parent_id);
                $response['new_post_id'] = $post_id;
            } else {
                $post_id = (int)$_POST['id'];
            }

            wp_update_post(array(
                'ID'          => $post_id,
                'post_status' => $_POST['status'] == self::STATUS_NEGATED ? self::STATUS_NEGATED : self::STATUS_PUBLISHED,
                'menu_order'  => (int)$_POST['exposure']
            ));

            //Prune condition type cache, will rebuild within 24h
            update_option(self::OPTION_CONDITION_TYPE_CACHE, array());

            foreach (self::types()->get($parent_type->name)->get_all() as $module) {
                //send $_POST here
                $module->save_data($post_id);
            }

            do_action('wpca/modules/save-data', $post_id, $parent_type->name);

            wp_send_json($response);
        }

        /**
         * Save registered meta for condition group
         *
         * @since  3.2
         * @param  int  $group_id
         * @return void
         */
        public static function save_condition_options($group_id, $post_type)
        {
            $meta_keys = self::get_condition_meta_keys($post_type);
            foreach ($meta_keys as $key => $default_value) {
                $value = isset($_POST[$key]) ? $_POST[$key] : false;
                if ($value) {
                    update_post_meta($group_id, $key, $value);
                } elseif (get_post_meta($group_id, $key, true)) {
                    delete_post_meta($group_id, $key);
                }
            }
        }

        public static function add_group_script_styles($hook)
        {
            $current_screen = get_current_screen();

            wp_register_style(
                self::PREFIX.'condition-groups',
                plugins_url('/assets/css/condition_groups.css', __FILE__),
                array(),
                WPCA_VERSION
            );

            if (self::types()->has($current_screen->post_type) && $current_screen->base == 'post') {
                self::enqueue_scripts_styles($hook);
            }
        }

        /**
         * Get condition option defaults
         *
         * @since  3.2
         * @param  string  $post_type
         * @return array
         */
        public static function get_condition_meta_keys($post_type)
        {
            $group_meta = array(
                '_ca_autoselect' => 0
            );
            return apply_filters('wpca/condition/meta', $group_meta, $post_type);
        }

        /**
         * Register and enqueue scripts and styles
         * for post edit screen
         *
         * @since 1.0
         * @param   string    $hook
         * @return  void
         */
        public static function enqueue_scripts_styles($hook)
        {
            $post_type = get_post_type();

            $group_meta = self::get_condition_meta_keys($post_type);

            $groups = self::get_condition_groups();
            $data = array();
            $i = 0;
            foreach ($groups as $group) {
                $data[$i] = array(
                    'id'         => $group->ID,
                    'status'     => $group->post_status,
                    'exposure'   => $group->menu_order,
                    'conditions' => array()
                );

                foreach (self::types()->get($post_type)->get_all() as $module) {
                    $data[$i]['conditions'] = $module->get_group_data($data[$i]['conditions'], $group->ID);
                }

                foreach ($group_meta as $meta_key => $default_value) {
                    $value = get_post_meta($group->ID, $meta_key, true);
                    if ($value === false) {
                        $value = $default_value;
                    }
                    $data[$i][$meta_key] = $value;
                }
                $i++;
            }

            $conditions = array(
                'general' => array(
                    'text'     => __('General'),
                    'children' => array()
                ),
                'post_type' => array(
                    'text'     => __('Post Types'),
                    'children' => array()
                ),
                'taxonomy' => array(
                    'text'     => __('Taxonomies'),
                    'children' => array()
                ),
                'plugins' => array(
                    'text'     => __('Plugins'),
                    'children' => array()
                )
            );

            foreach (self::types()->get($post_type)->get_all() as $module) {
                $category = $module->get_category();
                if (!isset($conditions[$category])) {
                    $category = 'general';
                }

                //array_values used for backwards compatibility
                $conditions[$category]['children'] = array_values($module->list_module($conditions[$category]['children']));
            }

            foreach ($conditions as $key => $condition) {
                if (empty($condition['children'])) {
                    unset($conditions[$key]);
                }
            }

            //Make sure to use packaged version
            if (wp_script_is('select2', 'registered')) {
                wp_deregister_script('select2');
                wp_deregister_style('select2');
            }

            $plugins_url = plugins_url('', __FILE__);

            //Add to head to take priority
            //if being added under other name
            wp_register_script(
                'select2',
                $plugins_url . '/assets/js/select2.min.js',
                array('jquery'),
                '4.0.3',
                false
            );

            wp_register_script(
                'backbone.trackit',
                $plugins_url . '/assets/js/backbone.trackit.min.js',
                array('backbone'),
                '0.1.0',
                true
            );

            wp_register_script(
                'backbone.epoxy',
                $plugins_url . '/assets/js/backbone.epoxy.min.js',
                array('backbone'),
                '1.3.3',
                true
            );

            wp_register_script(
                self::PREFIX.'condition-groups',
                $plugins_url . '/assets/js/condition_groups.min.js',
                array('jquery','select2','backbone.trackit','backbone.epoxy'),
                WPCA_VERSION,
                true
            );

            wp_enqueue_script(self::PREFIX.'condition-groups');
            wp_localize_script(self::PREFIX.'condition-groups', 'WPCA', array(
                'searching'      => __('Searching', WPCA_DOMAIN),
                'noResults'      => __('No results found.', WPCA_DOMAIN),
                'loadingMore'    => __('Loading more results', WPCA_DOMAIN),
                'unsaved'        => __('Conditions have unsaved changes. Do you want to continue and discard these changes?', WPCA_DOMAIN),
                'newGroup'       => __('New condition group', WPCA_DOMAIN),
                'newCondition'   => __('Meet ALL of these conditions', WPCA_DOMAIN),
                'conditions'     => array_values($conditions),
                'groups'         => $data,
                'meta_default'   => $group_meta,
                'post_type'      => $post_type,
                'text_direction' => is_rtl() ? 'rtl' : 'ltr'
            ));
            wp_enqueue_style(self::PREFIX.'condition-groups');

            //@todo remove when ultimate member includes fix
            wp_dequeue_style('um_styles');

            //@todo remove when events calendar pro plugin includes fix
            wp_dequeue_script('tribe-select2');
        }

        /**
         * Modify wp_query for plugin compatibility
         *
         * @since  5.0
         * @return void
         */
        private static function fix_wp_query()
        {
            $query = array();

            //When themes don't declare WooCommerce support,
            //conditionals are not working properly for Shop
            if (defined('WOOCOMMERCE_VERSION') && function_exists('is_shop') && is_shop() && !is_post_type_archive('product')) {
                $query = array(
                    'is_archive'           => true,
                    'is_post_type_archive' => true,
                    'is_page'              => false,
                    'is_singular'          => false,
                    'query_vars'           => array(
                        'post_type' => 'product'
                    )
                );
            }

            self::set_wp_query($query);
        }

        /**
         * Restore original wp_query
         *
         * @since  5.0
         * @return void
         */
        private static function restore_wp_query()
        {
            self::set_wp_query(self::$wp_query_original);
            self::$wp_query_original = array();
        }

        /**
         * Set properties in wp_query and save original value
         *
         * @since 5.0
         * @param array  $query
         */
        private static function set_wp_query($query)
        {
            global $wp_query;
            foreach ($query as $key => $val) {
                $is_array = is_array($val);

                if (!isset(self::$wp_query_original[$key])) {
                    self::$wp_query_original[$key] = $is_array ? array() : $wp_query->$key;
                }

                if ($is_array) {
                    foreach ($val as $k1 => $v1) {
                        if (!isset(self::$wp_query_original[$key][$k1])) {
                            self::$wp_query_original[$key][$k1] = $wp_query->{$key}[$k1];
                        }
                        $wp_query->{$key}[$k1] = $v1;
                    }
                } else {
                    $wp_query->$key = $val;
                }
            }
        }

        /**
         * Autoload class files
         *
         * @since 1.0
         * @param   string    $class
         * @return  void
         */
        private static function _autoload_class_files($class)
        {
            if (strpos($class, self::CLASS_PREFIX) === 0) {
                $class = str_replace(self::CLASS_PREFIX, '', $class);
                $class = self::str_replace_first('_', '/', $class);
                $class = strtolower($class);
                $file = WPCA_PATH . $class . '.php';
                if (file_exists($file)) {
                    include($file);
                }
            }
        }

        /**
         * Helper function to replace first
         * occurence of substring
         *
         * @since 1.0
         * @param   string    $search
         * @param   string    $replace
         * @param   string    $subject
         * @return  string
         */
        private static function str_replace_first($search, $replace, $subject)
        {
            $pos = strpos($subject, $search);
            if ($pos !== false) {
                $subject = substr_replace($subject, $replace, $pos, strlen($search));
            }
            return $subject;
        }

        /**
         * @since 8.0
         * @param array $input
         *
         * @return string
         */
        private static function sql_prepare_in($input)
        {
            $output = array_map(function ($value) {
                return "'" . esc_sql($value) . "'";
            }, $input);
            return implode(',', $output);
        }

        /**
         * @since 8.0
         * @param string $type
         * @param array $modules
         *
         * @return array
         */
        private static function filter_condition_type_cache($type, $modules)
        {
            $included_conditions = get_option(self::OPTION_CONDITION_TYPE_CACHE, array());

            if (!$included_conditions || !isset($included_conditions[$type])) {
                return $modules;
            }

            $ignored_modules = array('taxonomy' => 1);
            $included_conditions_lookup = array_flip($included_conditions[$type]);
            $filtered_modules = array();

            foreach ($modules as $module) {
                if (isset($ignored_modules[$module->get_id()]) || isset($included_conditions_lookup[$module->get_id()])) {
                    $filtered_modules[] = $module;
                }
            }

            return $filtered_modules;
        }
    }
}
