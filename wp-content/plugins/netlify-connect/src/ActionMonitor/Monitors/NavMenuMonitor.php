<?php

namespace NetlifyConnect\ActionMonitor\Monitors;

use WP_Term;

class NavMenuMonitor extends Monitor {

	/**
	 * @return mixed|void
	 */
	public function init() {
		// Log when menus are added/removed from a theme location
		add_filter('pre_set_theme_mod_nav_menu_locations', [$this, 'callback_set_nav_menu_locations'], 10, 2);
		// Log when a nav menu is updated
		add_action('wp_update_nav_menu', [$this, 'callback_update_nav_menu'], 10, 2);
		// Log when a nav menu is deleted
		add_action('wp_delete_nav_menu', [$this, 'callback_delete_nav_menu'], 10, 3);
	}

	/**
	 * Callback for menu locations theme mod. When menu locations are set/unset.
	 *
	 * @param array $value The old value of the nav menu locations
	 * @param mixed|array|bool $old_value The new value of the nav menu locations
	 *
	 * @return mixed
	 */
	public function callback_set_nav_menu_locations(array $value, $old_value) {
		error_log("callback_set_nav_menu_locations: ");

		$old_locations = !empty($old_value) && is_array($old_value) ? $old_value : [];
		$new_locations = !empty($value) && is_array($value) ? $value : [];

		// If old locations are same as new locations, do nothing
		if ($old_locations === $new_locations) {
			return $value;
		}

		// Log the diffed menus
		$this->log_diffed_menus($old_locations, $new_locations);

		// Return the value passed to the filter, without making any changes
		return $value;
	}

	/**
	 * Determines whether a menu is considered public and should be tracked
	 * by the activity monitor
	 *
	 * @param int $menu_id ID of the menu
	 *
	 * @return bool
	 */
	public function is_menu_public(int $menu_id) {

		$locations         = get_theme_mod('nav_menu_locations');
		$assigned_menu_ids = !empty($locations) ? array_values($locations) : [];

		if (empty($assigned_menu_ids)) {
			return false;
		}

		if (in_array($menu_id, $assigned_menu_ids, true)) {
			return true;
		}

		return false;
	}

	/**
	 * Log action when a nav menu is updated
	 *
	 * @param int   $menu_id The ID of the menu being updated
	 * @param array $menu_data The data associated with the menu
	 */
	public function callback_update_nav_menu(int $menu_id, array $menu_data = []) {
		error_log("callback_update_nav_menu: " . $menu_id);

		$menu = get_term_by('id', absint($menu_id), 'nav_menu');

		$this->log_action(
			[
				'action_type'         => 'UPDATE',
				'title'               => $menu->name,
				'status'              => 'publish',
				'node_id'             => (int) $menu->term_id,
				'node_type'			  => 'menu',
				'rest_base'			  => 'menus',
			]
		);
	}

	/**
	 * Given an array of old menu locations and new menu locations, this
	 * diffs them and logs an action for the menu assigned to the added/removed location
	 *
	 * @param array $old_locations Old locations with a menu assigned
	 * @param array $new_locations New locations with a menu assigned
	 */
	public function log_diffed_menus(array $old_locations, array $new_locations) {

		// If old locations are same as new locations, do nothing
		if ($old_locations === $new_locations) {
			return;
		}

		// Trigger an action for each added location
		$added = array_diff($new_locations, $old_locations);
		if (!empty($added) && is_array($added)) {
			foreach ($added as $location => $added_menu_id) {

				if (!empty($menu = get_term_by('id', (int) $added_menu_id, 'nav_menu')) && $menu instanceof WP_Term) {
					$this->log_action(
						[
							'action_type'         => 'CREATE',
							'title'               => $menu->name,
							'status'              => 'publish',
							'node_id'             => (int) $added_menu_id,
						]
					);
				}
			}
		}

		// Trigger an action for each location deleted
		$removed = array_diff($old_locations, $new_locations);
		if (!empty($removed)) {
			foreach ($removed as $location => $removed_menu_id) {

				$this->log_action(
					[
						'action_type'         => 'DELETE',
						'title'               => $removed_menu_id,
						'status'              => 'trash',
						'node_id'             => (int) $removed_menu_id,
						'node_type'			  => 'menu',
					]
				);
			}
		}
	}

	/**
	 * Log an action when a menu is deleted
	 *
	 * @param int $term_id ID of the deleted menu.
	 */
	public function callback_delete_nav_menu($term_id) {
		error_log("callback_delete_nav_menu: " . $term_id);

		$this->log_action(
			[
				'action_type'         => 'DELETE',
				'title'               => '#' . $term_id,
				'status'              => 'trash',
				'node_id'             => (int) $term_id,
				'node_type'			  => 'menu',
			]
		);
	}
}
