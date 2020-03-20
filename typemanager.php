<?php
/**
 * @package WP Content Aware Engine
 * @author Joachim Jensen <joachim@dev.institute>
 * @license GPLv3
 * @copyright 2020 by Joachim Jensen
 */

defined('ABSPATH') || exit;


if (!class_exists('WPCATypeManager')) {
    /**
     * Manage module objects
     */
    final class WPCATypeManager extends WPCAObjectManager
    {

        /**
         * Constructor
         */
        public function __construct()
        {
            parent::__construct();
            add_action(
                'init',
                array($this,'set_modules'),
                999
            );
        }

        /**
         * Add module to manager
         *
         * @since 1.0
         * @param object  $class
         * @param string  $name
         */
        public function add($name, $arg = '')
        {
            parent::add(new WPCAObjectManager(), $name);
        }

        /**
         * Set initial modules
         *
         * @since   4.0
         * @return  void
         */
        public function set_modules()
        {
            do_action('wpca/types/init', $this);

            $modules = array(
                'static',
                'post_type',
                'author',
                'page_template',
                'taxonomy',
                'date',
                'bbpress',
                'bp_member',
                'pods',
                'polylang',
                'qtranslate',
                'translatepress',
                'transposh',
                'wpml'
            );

            foreach ($modules as $name) {
                $class_name = WPCACore::CLASS_PREFIX.'Module_'.$name;

                if (!class_exists($class_name)) {
                    continue;
                }

                $class = new $class_name();

                if (!($class instanceof WPCAModule_Base) || !$class->can_enable()) {
                    continue;
                }

                foreach ($this->get_all() as $post_type) {
                    $post_type->add($class, $name);
                }
            }

            do_action('wpca/modules/init', $this);

            //initiate all modules once with backwards compatibility on can_enable()
            $initiated = array();
            foreach ($this->get_all() as $post_type) {
                foreach ($post_type->get_all() as $key => $module) {
                    if (!isset($initiated[$key])) {
                        $initiated[$key] = 1;
                        $module->initiate();
                    }
                }
            }
        }
    }
}
