<?php
if (! defined('ABSPATH')) exit; // Exit if accessed directly

add_action('rest_api_init', function () {
    register_rest_route('netlify-connect/v1', '/environment', array(
        'methods' => 'GET',
        'callback' => 'netlify_get_wp_environment',
        'permission_callback' => "netlify_endpoints_permission_callback",
    ));
});

function netlify_get_wp_environment() {
    if (!function_exists('get_plugins')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    $all_plugins = get_plugins();
    $plugins_info = array();
    $tracked_plugins = array('advanced-custom-fields/acf.php', 'advanced-custom-fields-pro/acf.php');

    foreach ($all_plugins as $plugin_path => $plugin_data) {
        if (!in_array($plugin_path, $tracked_plugins)) {
            continue;
        }
        $plugins_info[] = array(
            'name' => $plugin_data['Name'],
            'version' => $plugin_data['Version'],
            'plugin_path' => $plugin_path,
            'is_active' => is_plugin_active($plugin_path),
        );
    }


    $result = array(
        'supported_plugins' => $plugins_info,
        'wp_version' => get_bloginfo('version'),
        'php_version' => phpversion(),
        'netlify_connect_wp_plugin_version' => NETLIFYCONNECT_VERSION,
    );

    return new WP_REST_Response($result, 200);
}
