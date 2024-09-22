<?php

/**
 * The ActionMonitor class was originally part of the WP Gatsby plugin but has been modified and migrated into the Netlify Connect plugin.
 * Original Authors: Jason Bahl, Tyler Barnes
 */

namespace NetlifyConnect\ActionMonitor;

use WP_Post;
use NetlifyConnect\Admin\Settings;

/**
 * This class registers and controls a post type which can be used to
 * monitor WP events like post save or delete in order to invalidate
 * a Netlify Connect data layer's cached nodes
 */
class ActionMonitor {

	/**
	 * Whether a build hook should be dispatched. Default false.
	 *
	 * @var bool
	 */
	protected $should_dispatch = false;

	/**
	 * An array of posts ID's for posts that have been updated
	 * in this ActionMonitor instantiation
	 *
	 * @var array
	 */
	protected $updated_post_ids = [];

	/**
	 * Whether Connector Debug Mode is active
	 *
	 * @var bool 
	 */
	protected $connector_debug_mode = false;

	/**
	 * @var mixed|null|WP_Post The post object before update
	 */
	public $post_object_before_update = null;

	/**
	 * Holds the classes for each action monitor
	 *
	 * @var array
	 */
	protected $action_monitors;

	/**
	 * Set up the Action monitor when the class is initialized
	 */
	public function __construct() {
		// shows the menu in sidebar
		if (NETLIFY_CONNECT_DEBUG) {
			$this->connector_debug_mode = true;
		}

		// Initialize action monitors
		add_action('wp_loaded', [$this, 'init_action_monitors'], 11);

		// Register post type and taxonomies to track CRUD events in WordPress
		add_action('init', [$this, 'init_post_type_and_taxonomies']);
		add_filter('manage_action_monitor_posts_columns', [$this, 'add_modified_column'], 10);
		add_action(
			'manage_action_monitor_posts_custom_column',
			[
				$this,
				'render_modified_column',
			],
			10,
			2
		);

		// Trigger webhook dispatch
		add_action('shutdown', [$this, 'trigger_dispatch']);

		// add action monitor fields to REST API
		add_action('rest_api_init', [$this, 'init_action_monitor_rest_fields']);

		add_filter('acf/get_post_types', [$this, 'connect_action_monitor_acf_get_post_types'], 5, 2);
	}

	/**
	 * Exclude the NETLIFY_ACTION_MONITOR_POST_TYPE from all acf relationship & post object fields
	 */
	public function connect_action_monitor_acf_get_post_types($post_types, $field) {
		$post_types = array_diff($post_types, [NETLIFY_ACTION_MONITOR_POST_TYPE]);
		return $post_types;
	}

	/**
	 * Add fields metadata to the REST API response for action monitor posts
	 */
	public function init_action_monitor_rest_fields() {
		register_rest_field(NETLIFY_ACTION_MONITOR_POST_TYPE, 'action_type', array(
			'get_callback' => function ($data) {
				$action_type = get_post_meta($data['id'], 'action_type', true);
				return $action_type;
			},
		));

		register_rest_field(NETLIFY_ACTION_MONITOR_POST_TYPE, 'referenced_node', array(
			'get_callback' => function ($data) {
				$node_type = get_post_meta($data['id'], 'node_type', true);
				$post_type_object = get_post_type_object($node_type);

				$node_rest_base = get_post_meta($data['id'], 'referenced_node_rest_base', true) ?? '';
				if (isset($post_type_object)) {
					$node_rest_base = $post_type_object->rest_base;
				}

				if ($node_type === 'menu') {
					$node_rest_base = 'menus'; // menus are not considered post types so we just set the rest base here manually
				}
				if ($node_type === "post_tag") {
					$node_rest_base = 'tags';
				}
				if ($node_type === "user") {
					$node_rest_base = 'users';
				}

				return array(
					"id" => get_post_meta($data['id'], 'node_id', true),
					"type" => $node_type,
					"status" => get_post_meta($data['id'], 'referenced_node_status', true),
					"rest_base" => $node_rest_base,
				);
			},
		));

		if ($this->connector_debug_mode) {
			// Adds all post metadata to the REST API response for action monitor posts when in debug mode
			register_rest_field(NETLIFY_ACTION_MONITOR_POST_TYPE, 'metadata', array(
				'get_callback' => function ($data) {
					$jsonMeta = get_post_meta($data['id'], '', '');

					$processedMeta = [];
					foreach ($jsonMeta as $key => $value) {
						if (is_array($value) && !empty($value)) {
							$processedMeta[$key] = $value[0];
						} else {
							$processedMeta[$key] = $value;
						}
					}

					return $processedMeta;
				},
			));
		}
	}

	/**
	 * For Action Monitor, all of these roles need to be able to view and edit private action monitor posts so that Preview works for all roles.
	 */
	public function action_monitor_add_role_caps() {
		$roles = apply_filters(
			'netlify_private_action_monitor_roles',
			[
				'editor',
				'administrator',
				'contributor',
				'author'
			]
		);

		foreach ($roles as $the_role) {
			$role = get_role($the_role);

			if (!$role->has_cap('read_private_action_monitor_posts')) {
				$role->add_cap('read_private_action_monitor_posts');
			}

			if (!$role->has_cap('edit_others_action_monitor_posts')) {
				$role->add_cap('edit_others_action_monitor_posts');
			}
		}
	}

	/**s
	 * Get the post types that are tracked by NetlifyConnect.
	 *
	 * @return array|mixed|void
	 */
	public function get_tracked_post_types() {
		$public_post_types = get_post_types(
			[
				'show_in_rest' => true,
				'public' => true,
			]
		);

		$publicly_queryable_post_types = get_post_types(
			[
				'show_in_rest' => true,
				'public' => false,
				'publicly_queryable' => true,
			]
		);

		$excludes = [
			NETLIFY_ACTION_MONITOR_POST_TYPE => NETLIFY_ACTION_MONITOR_POST_TYPE,
		];

		$tracked_post_types = array_diff(
			array_merge($public_post_types, $publicly_queryable_post_types),
			$excludes
		);

		$tracked_post_types = apply_filters(
			'netlify_action_monitor_tracked_post_types',
			$tracked_post_types
		);

		return !empty($tracked_post_types) && is_array($tracked_post_types) ? $tracked_post_types : [];
	}

	/**
	 * Get the taxonomies that are tracked by NetlifyConnect
	 *
	 * @return array|mixed|void
	 */
	public function get_tracked_taxonomies() {
		$tracked_taxonomies = apply_filters(
			'netlify_action_monitor_tracked_taxonomies',
			get_taxonomies(
				[
					'show_in_rest' => true,
					'public' => true,
				]
			)
		);

		return !empty($tracked_taxonomies) && is_array($tracked_taxonomies) ? $tracked_taxonomies : [];
	}

	/**
	 * Register Action monitor post type and associated taxonomies.
	 *
	 * The post type is used to store records of CRUD actions that have occurred in WordPress so
	 * that Netlify Connect can keep in Sync with changes in WordPress.
	 *
	 * The taxonomies are registered to store data related to the actions, but make it more
	 * efficient to filter actions by the values as Tax Queries are much more efficient than Meta
	 * Queries.
	 */
	public function init_post_type_and_taxonomies() {

		/**
		 * Post Type: Action Monitor.
		 */
		$post_type_labels = [
			'name'          => __('Action Monitor', 'Netlify Connect'),
			'singular_name' => __('Action Monitor', 'Netlify Connect'),
		];

		// Registers the post_type that logs actions
		register_post_type(
			NETLIFY_ACTION_MONITOR_POST_TYPE,
			[
				'label'                 => __('Action Monitor', 'Netlify Connect'),
				'labels'                => $post_type_labels,
				'description'           => 'Used to keep a log of actions in WordPress for cache invalidation in the WordPress Connector.',
				'public'                => false,
				'publicly_queryable'    => true,
				'show_ui'               => $this->connector_debug_mode,
				'menu_icon'				=> "dashicons-welcome-view-site",
				'delete_with_user'      => false,
				'show_in_rest'          => true,
				'rest_base'             => '',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'has_archive'           => false,
				'show_in_menu'          => $this->connector_debug_mode,
				'show_in_nav_menus'     => false,
				'exclude_from_search'   => true,
				'capabilities'          => [
					// these custom capabilities allow any role to use Preview
					'read_private_posts' => 'read_private_action_monitor_posts',
					'edit_others_posts'  => 'edit_others_action_monitor_posts',
					// these are regular role capabilities for a CPT
					'create_post'        => 'create_post',
					'edit_post'          => 'edit_post',
					'read_post'          => 'read_post',
					'delete_post'        => 'delete_post',
					'edit_posts'         => 'edit_posts',
					'publish_posts'      => 'publish_posts',
					'create_posts'       => 'create_posts'
				],
				'map_meta_cap'          => false,
				'hierarchical'          => false,
				'rewrite'               => [
					'slug'       => NETLIFY_ACTION_MONITOR_POST_TYPE,
					'with_front' => true,
				],
				'query_var'             => true,
				'supports'              => ['title'],
			]
		);

		// Registers the taxonomy that connects the node type to the action_monitor post
		register_taxonomy(
			'netlify_action_ref_node_type',
			NETLIFY_ACTION_MONITOR_POST_TYPE,
			[
				'label'               => __('Referenced Node Type', 'Netlify Connect'),
				'public'              => false,
				'show_ui'             => $this->connector_debug_mode,
				'hierarchical'        => false,
				'show_in_nav_menus'   => false,
				'show_tagcloud'       => false,
				'show_admin_column'   => true,
			]
		);

		// Registers the taxonomy that connects the node databaseId to the action_monitor post
		register_taxonomy(
			'netlify_action_ref_node_dbid',
			NETLIFY_ACTION_MONITOR_POST_TYPE,
			[
				'label'               => __('Referenced Node Database ID', 'Netlify Connect'),
				'public'              => false,
				'show_ui'             => $this->connector_debug_mode,
				'hierarchical'        => false,
				'show_in_nav_menus'   => false,
				'show_tagcloud'       => false,
				'show_admin_column'   => true,
			]
		);

		// Registers the taxonomy that connects the node global ID to the action_monitor post
		register_taxonomy(
			'netlify_action_ref_node_id',
			NETLIFY_ACTION_MONITOR_POST_TYPE,
			[
				'label'               => __('Referenced Node Global ID', 'Netlify Connect'),
				'public'              => false,
				'show_ui'             => $this->connector_debug_mode,
				'hierarchical'        => false,
				'show_in_nav_menus'   => false,
				'show_tagcloud'       => false,
				'show_admin_column'   => true,
			]
		);

		// Registers the taxonomy that connects the action type (CREATE, UPDATE, DELETE) to the action_monitor post
		register_taxonomy(
			'netlify_action_type',
			NETLIFY_ACTION_MONITOR_POST_TYPE,
			[
				'label'               => __('Action Type', 'Netlify Connect'),
				'public'              => false,
				'show_ui'             => $this->connector_debug_mode,
				'hierarchical'        => false,
				'show_in_nav_menus'   => false,
				'show_tagcloud'       => false,
				'show_admin_column'   => true,
			]
		);

		register_taxonomy('netlify_action_stream_type', NETLIFY_ACTION_MONITOR_POST_TYPE, [
			'label'               => __('Stream Type', 'Netlify Connect'),
			'public'              => false,
			'show_ui'             => $this->connector_debug_mode,
			'hierarchical'        => false,
			'show_in_nav_menus'   => false,
			'show_tagcloud'       => false,
			'show_admin_column'   => true,
		]);
	}

	/**
	 * Adds a column to the action monitor Post Type to show the last modified time
	 *
	 * @param array $columns The column names included in the post table
	 *
	 * @return array
	 */
	public function add_modified_column(array $columns) {
		$columns['netlify_last_modified'] = __('Last Modified', 'Netlify Connect');

		return $columns;
	}

	/**
	 * Renders the last modified time in the action_monitor post type "modified" column
	 *
	 * @param string $column_name The name of the column
	 * @param int    $post_id     The ID of the post in the table
	 */
	public function render_modified_column(string $column_name, int $post_id) {
		if ('netlify_last_modified' === $column_name) {
			$m_orig   = get_post_field('post_modified', $post_id, 'raw');
			$m_stamp  = strtotime($m_orig);
			$modified = gmdate('n/j/y @ g:i a', $m_stamp);
			echo '<p class="mod-date">';
			echo '<em>' . esc_html($modified) . '</em><br />';
			echo '</p>';
		}
	}

	/**
	 * Sets should_dispatch to true
	 */
	public function schedule_dispatch() {
		$this->should_dispatch = true;
	}

	/**
	 * Deletes all posts of the action_monitor post_type that are 7 days old, as well as any
	 * associated post meta and term relationships.
	 *
	 * @return bool|int
	 */
	public function garbage_collect_actions() {
		global $wpdb;
		return $wpdb->query($wpdb->prepare("
			DELETE posts, pm, pt
			FROM {$wpdb->prefix}posts AS posts
			LEFT JOIN {$wpdb->prefix}term_relationships AS pt ON pt.object_id = posts.ID
			LEFT JOIN {$wpdb->prefix}postmeta AS pm ON pm.post_id = posts.ID
			WHERE posts.post_type = %s
			AND posts.post_modified < %s
		", NETLIFY_ACTION_MONITOR_POST_TYPE, gmdate('Y-m-d H:i:s', strtotime('-7 days'))));
	}

	/**
	 * Given the name of an Action Monitor, this returns it
	 *
	 * @param string $name The name of the Action Monitor to get
	 *
	 * @return mixed|null
	 */
	public function get_action_monitor(string $name) {
		return $this->action_monitors[$name] ?? null;
	}

	/**
	 * Use WP Action hooks to create action monitor posts
	 */
	function init_action_monitors() {

		$class_names = [
			'AcfMonitor',
			'MediaMonitor',
			'NavMenuMonitor',
			'PostMonitor',
			'PostTypeMonitor',
			'SettingsMonitor',
			'TaxonomyMonitor',
			'TermMonitor',
			'UserMonitor',
		];

		$action_monitors = [];

		foreach ($class_names as $class_name) {
			$class = 'NetlifyConnect\ActionMonitor\Monitors\\' . $class_name;
			if (class_exists($class)) {
				$monitor = new $class($this);
				$action_monitors[$class_name] = $monitor;
			}
		}

		/**
		 * Filter the action monitors. This can allow for other monitors
		 * to be registered, or can allow for monitors to be overridden.
		 *
		 * Overriding monitors is not advised, but there are cases where it might
		 * be necessary. Override with caution.
		 *
		 * @param array $action_monitors
		 * @param \NetlifyConnect\ActionMonitor\ActionMonitor $monitor The class instance, used to initialize the monitor.
		 */
		$this->action_monitors = apply_filters('netlify_action_monitors', $action_monitors, $this);

		do_action('netlify_init_action_monitors', $this->action_monitors);
	}

	/**
	 * Triggers the dispatch to the remote endpoint(s)
	 */
	public function trigger_dispatch() {
		$build_webhook_field   = Settings::prefix_get_option('builds_api_webhook', 'netlify_connect_settings', false);
		$should_call_build_webhooks =
			$build_webhook_field &&
			$this->should_dispatch;

		if ($should_call_build_webhooks) {
			$webhooks = explode(',', $build_webhook_field);

			$truthy_webhooks = array_filter($webhooks);
			$unique_webhooks = array_unique($truthy_webhooks);

			foreach ($unique_webhooks as $webhook) {
				$args = apply_filters('netlify_trigger_dispatch_args', [], $webhook);

				$res = null;
				// Used when developing on localhost without HTTPS
				if (NETLIFY_CONNECT_WEBHOOK_UNSAFE_REQUEST) {
					$res = wp_remote_post($webhook, $args);
				} else {
					$res = wp_safe_remote_post($webhook, $args);
				}
				if (is_wp_error($res)) {
					error_log('[Netlify Connect] Error requesting webhook "' . $webhook . '": ' . $res->get_error_message());
				}
			}
		}
	}
}
