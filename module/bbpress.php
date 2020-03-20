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
 * bbPress Module
 * Requires bbPress 2.5+
 *
 * Detects if current content is:
 * a) any or specific bbpress user profile
 *
 */
class WPCAModule_bbpress extends WPCAModule_author
{

    /**
     * @var string
     */
    protected $category = 'plugins';

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->id = 'bb_profile';
        $this->name = __('bbPress User Profiles', WPCA_DOMAIN);
        $this->placeholder = __('All Profiles', WPCA_DOMAIN);
        $this->default_value = $this->id;

        $this->query_name = 'cbb';
    }

    /**
     * Initiate module
     *
     * @since  2.0
     * @return void
     */
    public function initiate()
    {
        parent::initiate();
        add_filter(
            'wpca/module/post_type/db-where',
            array($this,'add_forum_dependency')
        );
    }

    /**
     * @return bool
     */
    public function can_enable()
    {
        return function_exists('bbp_get_version')
            && function_exists('bbp_is_single_user')
            && function_exists('bbp_get_displayed_user_id')
            && function_exists('bbp_get_forum_id');
    }

    /**
     * @since  1.0
     * @return boolean
     */
    public function in_context()
    {
        return bbp_is_single_user();
    }

    /**
     * Get data from context
     *
     * @since  1.0
     * @return array
     */
    public function get_context_data()
    {
        $data = array($this->id);
        $data[] = bbp_get_displayed_user_id();
        return $data;
    }

    /**
     * Sidebars to be displayed with forums will also
     * be dislpayed with respective topics and replies
     *
     * @since  1.0
     * @param  string $where
     * @return string
     */
    public function add_forum_dependency($where)
    {
        if (is_singular(array('topic','reply'))) {
            $data = array(
                get_post_type(),
                get_the_ID(),
                'forum'
            );
            $data[] = bbp_get_forum_id();
            $where = "(cp.meta_value IS NULL OR cp.meta_value IN('".implode("','", $data)."'))";
        }
        return $where;
    }
}
