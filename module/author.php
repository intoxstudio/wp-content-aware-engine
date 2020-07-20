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
 * Author Module
 *
 * Detects if current content is:
 * a) post type written by any or specific author
 * b) any or specific author archive
 *
 */
class WPCAModule_author extends WPCAModule_Base
{

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('author', __('Authors', WPCA_DOMAIN));
        $this->placeholder = __('All Authors', WPCA_DOMAIN);
        $this->default_value = $this->id;
        $this->query_name = 'ca';
    }

    /**
     * Determine if content is relevant
     *
     * @since  1.0
     * @return boolean
     */
    public function in_context()
    {
        return (is_singular() && !is_front_page()) || is_author();
    }

    /**
     * Get data from context
     *
     * @global WP_Post $post
     * @since  1.0
     * @return array
     */
    public function get_context_data()
    {
        global $post;
        return array(
            $this->id,
            (string)(is_singular() ? $post->post_author : get_query_var('author'))
        );
    }

    /**
     * @param array $args
     *
     * @return array
     */
    protected function parse_query_args($args)
    {
        $new_args = array(
            'number'      => $args['limit'],
            'offset'      => ($args['paged'] - 1) * $args['limit'],
            'search'      => $args['search'],
            'fields'      => array('ID','display_name'),
            'orderby'     => 'display_name',
            'order'       => 'ASC',
            'include'     => $args['include'],
            'count_total' => false,
        );
        if ($new_args['search']) {
            if (false !== strpos($new_args['search'], '@')) {
                $new_args['search_columns'] = array( 'user_email' );
            } elseif (is_numeric($new_args['search'])) {
                $new_args['search_columns'] = array( 'user_login', 'ID' );
            } else {
                $new_args['search_columns'] = array( 'user_nicename', 'user_login', 'display_name' );
            }
            $new_args['search'] = '*'.$new_args['search'].'*';
        }
        return $new_args;
    }

    /**
     * Get authors
     *
     * @since  1.0
     * @param  array     $args
     * @return array
     */
    protected function _get_content($args = array())
    {
        $user_query = new WP_User_Query($args);
        $author_list = array();

        if ($user_query->results) {
            foreach ($user_query->get_results()  as $user) {
                $author_list[] = array(
                    'id'   => $user->ID,
                    'text' => $user->display_name
                );
            }
        }
        return $author_list;
    }
}
