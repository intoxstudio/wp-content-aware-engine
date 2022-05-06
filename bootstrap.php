<?php
/**
 * @package wp-content-aware-engine
 * @author Joachim Jensen <joachim@dev.institute>
 * @license GPLv3
 * @copyright 2022 by Joachim Jensen
 */

defined('ABSPATH') || exit;

/**
 * Version of this WPCA
 * @var string
 */
$this_wpca_version = '10.0';

/**
 * Class to make sure the latest
 * version of WPCA gets loaded
 *
 * @since 3.0
 */
if (!class_exists('WPCALoader')) {
    class WPCALoader
    {
        /** @var string */
        private static $last_loaded_plugin;

        /** @var array */
        private static $versions_by_path = [];

        public function __construct()
        {
        }

        /**
         * @return string
         */
        private static function get_last_loaded_plugin()
        {
            if (self::$last_loaded_plugin === null) {
                $plugins = wp_get_active_and_valid_plugins();
                self::$last_loaded_plugin = array_pop($plugins);
            }
            return self::$last_loaded_plugin;
        }

        /**
         * Add path to loader
         *
         * @since 3.0
         * @param string  $path
         * @param string  $version
         */
        public static function add($path, $version)
        {
            self::$versions_by_path[$path] = $version;
        }

        /**
         * Load file for newest version
         * and setup engine as early as possible,
         * after all plugins are loaded
         *
         * @since  3.0
         * @return void
         */
        public static function load($path)
        {
            //legacy version present, cannot continue
            if (class_exists('WPCACore')) {
                return;
            }

            //not ready
            if ($path !== self::get_last_loaded_plugin()) {
                return;
            }

            uasort(self::$versions_by_path, 'version_compare');
            foreach (array_reverse(self::$versions_by_path, true) as $path => $version) {
                $file = $path . 'core.php';
                if (file_exists($file)) {
                    include $file;
                    define('WPCA_VERSION', $version);
                    WPCACore::init();
                    do_action('wpca/loaded');
                    break;
                }
            }
        }

        /**
         * Get all paths added to loader
         * Sorted if called after plugins_loaded
         *
         * @since  3.0
         * @return array
         */
        public static function debug()
        {
            return self::$versions_by_path;
        }
    }
    add_action('plugin_loaded', ['WPCALoader','load'], PHP_INT_MAX);
}
WPCALoader::add(plugin_dir_path(__FILE__), $this_wpca_version);
