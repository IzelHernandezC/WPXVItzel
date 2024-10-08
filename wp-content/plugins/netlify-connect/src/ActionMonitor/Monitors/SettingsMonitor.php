<?php

namespace NetlifyConnect\ActionMonitor\Monitors;

class SettingsMonitor extends Monitor {

	/**
	 * @return mixed|void
	 */
	public function init() {

		add_action('updated_option', [$this, 'callback_update_option'], 10, 3);
		add_action('update_option_page_on_front', [$this, 'callback_update_page_on_front'], 10, 3);
		add_action('update_option_page_for_posts', [$this, 'callback_update_page_for_posts'], 10, 3);
		add_action('update_option_permalink_structure', [$this, 'callback_update_permalink_structure'], 10, 3);
	}

	/**
	 * Determines whether the option should be tracked by the Netlify Connect Action monitor
	 *
	 * @param string $option_name Name of the option to update.
	 * @param mixed  $old_value   The old option value.
	 * @param mixed  $value       The new option value.
	 *
	 * @return bool
	 */
	protected function should_track_option(string $option_name, $old_value, $value) {

		/**
		 * This filter allows plugins to opt-in or out of tracking for options.
		 *
		 * @param bool $should_track Whether the meta key should be tracked.
		 * @param string $option_name Name of the option to update.
		 * @param mixed  $old_value   The old option value.
		 * @param mixed  $value       The new option value.
		 *
		 * @param bool $tracked whether the meta key is tracked by Netlify Connect Action Monitor
		 */
		$should_track = apply_filters('netlify_action_monitor_should_track_option', null, $option_name, $old_value, $value);

		// If the filter has been applied return it
		if (null !== $should_track) {
			return (bool) $should_track;
		}

		// Options that are allowed to be tracked by default
		$tracked_option_names = apply_filters(
			'netlify_action_monitor_tracked_option_names',
			[
				'siteurl',
				'home',
				'blogname',
				'blogdescription',
				'start_of_week',
				'default_category',
				'default_comment_status',
				'posts_per_page',
				'date_format',
				'time_format',
				'blog_charset',
				'active_plugins',
				'category_base',
				'gmt_offset',
				'template',
				'stylesheet',
				'comment_registration',
				'default_role',
				'show_on_front',
				'tag_base',
				'show_avatars',
				'avatar_rating',
				'upload_url_path',
				'comments_per_page',
				'default_comments_page',
				'comment_order',
				'sticky_posts',
				'timezone_string',
				'default_post_format',
				'site_icon',
				'current_theme',
			]
		);

		if (in_array($option_name, $tracked_option_names, true)) {
			return true;
		}

		// If the meta key starts with an underscore, don't track it
		if ('_' === substr($option_name, 0, 1)) {
			return false;
		}

		return false;
	}

	/**
	 * Log actions when options are updated
	 *
	 * @param string $option_name Name of the option to update.
	 * @param mixed  $old_value   The old option value.
	 * @param mixed  $value       The new option value.
	 */
	public function callback_update_option(string $option_name, $old_value, $value) {

		if (!$this->should_track_option($option_name, $old_value, $value)) {
			return;
		}

		$action = [
			'title' => __('Update Setting: ', 'NetlifyConnect') . ' ' . $option_name,
			'action_type'         => "UPDATE",
			'node_id'             => 0,
			'status'              => "publish",
			'node_type'           => "settings",
		];

		/**
		 * Log an action
		 */
		$this->log_action($action);
	}

	/**
	 * Log action when permalink_structure is changed
	 *
	 * @param mixed  $old_value   The old option value.
	 * @param mixed  $new_value   The new option value.
	 * @param string $option_name Name of the option to update.
	 */
	public function callback_update_permalink_structure($old_value, $new_value, string $option_name) {

		if ($old_value === $new_value) {
			return;
		}

		$this->trigger_refetch_all(
			[
				'title' => __('Permalink structure updated', 'NetlifyConnect'),
			]
		);
	}

	/**
	 * Log action when page_on_front is changed
	 *
	 * @param mixed  $old_value   The old option value.
	 * @param mixed  $new_value   The new option value.
	 * @param string $option_name Name of the option to update.
	 */
	public function callback_update_page_on_front($old_value, $new_value, string $option_name) {

		if ((int) $old_value === (int) $new_value) {
			return;
		}

		$old_page = get_post(absint($old_value));
		$new_page = get_post(absint($new_value));

		if ($old_page instanceof \WP_Post) {

			$this->log_action(
				[
					'action_type'         => 'UPDATE',
					'title'               => $old_page->post_title,
					'node_id'             => $old_page->ID,
					'status'              => $old_page->post_status,
				]
			);
		}

		if ($new_page instanceof \WP_Post) {

			$this->log_action(
				[
					'action_type'         => 'UPDATE',
					'title'               => $new_page->post_title,
					'node_id'             => $new_page->ID,
					'status'              => $new_page->post_status,
				]
			);
		}
	}

	/**
	 * Log action when page_for_posts is changed
	 *
	 * @param mixed  $old_value   The old option value.
	 * @param mixed  $new_value   The new option value.
	 * @param string $option_name Name of the option to update.
	 */
	public function callback_update_page_for_posts($old_value, $new_value, string $option_name) {

		if ((int) $old_value === (int) $new_value) {
			return;
		}

		$old_page = get_post(absint($old_value));
		$new_page = get_post(absint($new_value));

		if ($old_page instanceof \WP_Post) {

			$this->log_action(
				[
					'action_type'         => 'UPDATE',
					'title'               => $old_page->post_title,
					'node_id'             => $old_page->ID,
					'status'              => $old_page->post_status,
				]
			);
		} else {
			$this->log_action(
				[
					'action_type'         => 'UPDATE',
					'title'               => 'Change page on front away from posts',
					'node_id'             => 'post',
					'status'              => 'publish',
				]
			);
		}

		if ($new_page instanceof \WP_Post) {

			$this->log_action(
				[
					'action_type'         => 'UPDATE',
					'title'               => $new_page->post_title,
					'node_id'             => $new_page->ID,
					'status'              => $new_page->post_status,
				]
			);
		} else {

			$this->log_action(
				[
					'action_type'         => 'UPDATE',
					'title'               => 'Set page on front to posts',
					'node_id'             => 'post',
					'status'              => 'publish',
				]
			);
		}
	}
}
