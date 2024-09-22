<?php

/**
 * Plugin Name: Netlify Connect
 * Description: Connect your WordPress site to Netlify Connect
 * Version: 3.0.3
 * Author: Netlify
 * Author URI: https://www.netlify.com/
 * Text Domain: netlify-connect
 * Domain Path: /languages/
 * Requires at least: 5.9.9
 * Requires PHP: 7.4
 * License: GPL-3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * If the codeception remote coverage file exists, require it.
 *
 * This file should only exist locally or when CI bootstraps the environment for testing
 */
if (file_exists(__DIR__ . '/c3.php')) {
    include_once __DIR__ . '/c3.php';
}

if (!defined('NETLIFY_CONNECT_DEBUG')) {
    define('NETLIFY_CONNECT_DEBUG', false);
}
if (!defined('NETLIFY_CONNECT_WEBHOOK_UNSAFE_REQUEST')) {
    define('NETLIFY_CONNECT_WEBHOOK_UNSAFE_REQUEST', false);
}
if (!defined('NETLIFY_ACTION_MONITOR_POST_TYPE')) {
    define('NETLIFY_ACTION_MONITOR_POST_TYPE', 'nf_action_monitor');
}
require __DIR__ . '/endpoints/acf-to-content-engine.php';
require __DIR__ . '/endpoints/environment.php';
require __DIR__ . "/lib/wp-settings-api.php";

/**
 * The one true NetlifyConnect class
 */
final class NetlifyConnect {

    /**
     * Instance of the main NetlifyConnect class
     *
     * @var NetlifyConnect $instance
     */
    private static $instance;

    /**
     * Returns instance of the main NetlifyConnect class
     *
     * @return NetlifyConnect
     * @throws Exception
     */
    public static function instance() {
        if (!isset(self::$instance) && !(self::$instance instanceof NetlifyConnect)) {
            self::$instance = new NetlifyConnect();
            self::$instance->setup_constants();
            self::$instance->includes();
            self::$instance->init();
        }

        return self::$instance;
    }

    /**
     * Throw error on object clone.
     * The whole idea of the singleton design pattern is that there is a single object
     * therefore, we don't want the object to be cloned.
     *
     * @since  0.0.1
     * @access public
     * @return void
     */
    public function __clone() {

        // Cloning instances of the class is forbidden.
        _doing_it_wrong(__FUNCTION__, esc_html__('The NetlifyConnect class should not be cloned.', 'netlify-connect'), '0.0.1');
    }

    /**
     * Disable unserializing of the class.
     *
     * @since  0.0.1
     * @access protected
     * @return void
     */
    public function __wakeup() {

        // De-serializing instances of the class is forbidden.
        _doing_it_wrong(__FUNCTION__, esc_html__('De-serializing instances of the NetlifyConnect class is not allowed', 'netlify-connect'), '0.0.1');
    }

    /**
     * Setup plugin constants.
     *
     * @access private
     * @since  0.0.1
     * @return void
     */
    private function setup_constants() {
        // Plugin version.
        if (!defined('NETLIFYCONNECT_VERSION')) {
            define('NETLIFYCONNECT_VERSION', '3.0.3');
        }

        // Plugin Folder Path.
        if (!defined('NETLIFYCONNECT_PLUGIN_DIR')) {
            define('NETLIFYCONNECT_PLUGIN_DIR', plugin_dir_path(__FILE__));
        }

        // Plugin Folder URL.
        if (!defined('NETLIFYCONNECT_PLUGIN_URL')) {
            define('NETLIFYCONNECT_PLUGIN_URL', plugin_dir_url(__FILE__));
        }

        // Plugin Root File.
        if (!defined('NETLIFYCONNECT_PLUGIN_FILE')) {
            define('NETLIFYCONNECT_PLUGIN_FILE', __FILE__);
        }

        // Whether to autoload the files or not.
        if (!defined('NETLIFYCONNECT_AUTOLOAD')) {
            define('NETLIFYCONNECT_AUTOLOAD', true);
        }

        // Whether to run the plugin in debug mode. Default is false.
        if (!defined('NETLIFYCONNECT_DEBUG')) {
            define('NETLIFYCONNECT_DEBUG', false);
        }
    }

    /**
     * Include required files.
     * Uses composer's autoload
     *
     * @access private
     * @since  0.0.1
     * @return void
     */
    private function includes() {

        /**
         * NETLIFYCONNECT_AUTOLOAD can be set to "false" to prevent the autoloader from running.
         * In most cases, this is not something that should be disabled, but some environments
         * may bootstrap their dependencies in a global autoloader that will autoload files
         * before we get to this point, and requiring the autoloader again can trigger fatal errors.
         *
         * The codeception tests are an example of an environment where adding the autoloader again causes issues
         * so this is set to false for tests.
         */
        if (defined('NETLIFYCONNECT_AUTOLOAD') && true === NETLIFYCONNECT_AUTOLOAD) {
            // Autoload Required Classes.
            include_once NETLIFYCONNECT_PLUGIN_DIR . 'vendor/autoload.php';
        }

        // Required non-autoloaded classes.
        include_once NETLIFYCONNECT_PLUGIN_DIR . 'access-functions.php';
    }

    /**
     * Initialize plugin functionality
     */
    public static function init() {
        /**
         * Initialize Admin Settings
         */
        new \NetlifyConnect\Admin\Settings();

        /**
         * Initialize Action Monitor
         */
        new \NetlifyConnect\ActionMonitor\ActionMonitor();
    }
}

if (!function_exists('netlify_init')) {
    function netlify_init() {
        return NetlifyConnect::instance();
    }
}

add_action(
    'plugins_loaded',
    function () {
        netlify_init();
    }
);
