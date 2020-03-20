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
 * Transposh Module
 * Requires version 0.9.5+
 *
 * Detects if current content is:
 * a) in specific language
 *
 */
class WPCAModule_transposh extends WPCAModule_Base
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
        parent::__construct('language', __('Languages', WPCA_DOMAIN));

        $this->query_name = 'cl';
    }


    /**
     * @return bool
     */
    public function can_enable()
    {
        return defined('TRANSPOSH_PLUGIN_VER')
            && function_exists('transposh_get_current_language')
            && defined('TRANSPOSH_OPTIONS')
            && method_exists('transposh_consts', 'get_language_orig_name');
    }

    /**
     * @since  1.0
     * @return boolean
     */
    public function in_context()
    {
        return true;
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
        $data[] = transposh_get_current_language();
        return $data;
    }

    /**
     * Get content for sidebar editor
     *
     * @global object $my_transposh_plugin
     * @since  1.0
     * @param  array $args
     * @return array
     */
    protected function _get_content($args = array())
    {
        global $my_transposh_plugin;
        $langs = array();

        /**
         * isset($my_transposh_plugin->options->viewable_languages)
         * returns false because transposh dev has not implemented __isset
         * using get_option instead for robustness
         */

        $options = get_option(TRANSPOSH_OPTIONS);

        if (isset($options['viewable_languages'])) {
            foreach (explode(',', $options['viewable_languages']) as $lng) {
                $langs[$lng] = transposh_consts::get_language_orig_name($lng);
            }
        }

        if (isset($args['include'])) {
            $langs = array_intersect_key($langs, array_flip($args['include']));
        }
        return $langs;
    }
}
