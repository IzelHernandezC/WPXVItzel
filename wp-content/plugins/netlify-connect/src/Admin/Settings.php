<?php

namespace NetlifyConnect\Admin;

class Settings {

	private $settings_api;

	function __construct() {

		$this->settings_api = new \WPConnector_Settings_API;

		add_action('admin_init', [$this, 'admin_init']);
		add_action('admin_menu', [$this, 'register_settings_page']);
		add_filter('plugin_action_links_netlify-connect/netlify-connect.php', [$this, 'netlify_connect_add_settings_link']);
	}

	public function netlify_connect_add_settings_link($links) {
		$settings_link = '<a href="options-general.php?page=netlify-connect">Settings</a>';
		array_push($links, $settings_link);
		return $links;
	}

	function admin_init() {
		//set the settings
		$this->settings_api->set_sections($this->get_settings_sections());
		$this->settings_api->set_fields($this->get_settings_fields());

		//initialize settings
		$this->settings_api->admin_init();
	}

	function admin_menu() {
		add_options_page(
			'Settings API',
			'Settings API',
			'delete_posts',
			'settings_api_test',
			[
				$this,
				'plugin_page',
			]
		);
	}


	function get_settings_sections() {
		$sections = [
			[
				'id'    => 'netlify_connect_settings',
				'title' => __('Netlify Connect Settings', 'netlify_connect_settings'),
			],
		];

		return $sections;
	}

	public function register_settings_page() {
		add_options_page(
			'Netlify',
			'Netlify Connect',
			'manage_options',
			'netlify-connect',
			[
				$this,
				'plugin_page',
			]
		);
	}


	function plugin_page() {
		echo '<div class="wrap">';
		echo '<div class="notice-info notice">
			<p>
				<a target="_blank" href="'
			. esc_url('https://docs.netlify.com/connect/get-started/?connect-data-source-type=wordpress#create-and-configure-a-data-layer') . '">
					Learn how to configure Netlify Connect for WordPress here.
				</a>
			</p>
		</div>';
		$this->settings_api->show_navigation();
		$this->settings_api->show_forms();
		echo '</div>';
	}

	static public function prefix_get_option($option, $section, $default = '') {
		$options = get_option($section);

		if (isset($options[$option])) {
			return $options[$option];
		}

		return $default;
	}

	public static function sanitize_url_field($input) {
		$urls = explode(',', $input);
		if (count($urls) > 1) {

			// validate all urls
			$validated_urls = array_map(
				function ($url) {
					return filter_var($url, FILTER_VALIDATE_URL);
				},
				$urls
			);

			// then put em back together
			return implode(',', $validated_urls);
		}

		return filter_var($input, FILTER_VALIDATE_URL);
	}

	public static function get_setting($key) {
		$netlify_connect_settings = get_option('netlify_connect_settings');

		return $netlify_connect_settings[$key] ?? null;
	}

	/**
	 * Returns all the settings fields
	 *
	 * @return array settings fields
	 */
	function get_settings_fields() {
		$settings_fields = [
			'netlify_connect_settings' => [
				[
					'name'              => 'builds_api_webhook',
					'label'             => __('Data layer Webhook URL', 'netlify_connect_settings'),
					'desc'              => __('Use a comma-separated list to configure multiple webhooks.<br><a href="https://docs.netlify.com/connect/monitor-sync-events/#trigger-a-sync-with-the-webhook" target="_blank">Learn how to find your Webhook URL in the Netlify Connect docs.</>', 'netlify_connect_settings'),
					'placeholder'       => __('https://', 'netlify_connect_settings'),
					'type'              => 'text',
					'sanitize_callback' => function ($input) {
						return $this->sanitize_url_field($input);
					},
				],
			],
		];

		return $settings_fields;
	}
}
