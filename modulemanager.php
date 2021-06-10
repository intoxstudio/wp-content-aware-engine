<?php
/**
 * @package wp-content-aware-engine
 * @author Joachim Jensen <joachim@dev.institute>
 * @license GPLv3
 * @copyright 2021 by Joachim Jensen
 */

defined('ABSPATH') || exit;

if (!class_exists('WPCAModuleManager')) {
    /**
     * Manage module objects
     */
    final class WPCAModuleManager extends WPCAObjectManager
    {

        /**
         * Constructor
         */
        public function __construct()
        {
            parent::__construct();
        }
    }
}
