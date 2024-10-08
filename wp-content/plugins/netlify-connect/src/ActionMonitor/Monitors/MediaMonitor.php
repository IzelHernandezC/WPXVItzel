<?php

namespace NetlifyConnect\ActionMonitor\Monitors;

class MediaMonitor extends  Monitor {

	/**
	 * Initialize the Media Monitor which tracks actions for Media and logs them
	 * to the Netlify Connect Action Monitor
	 *
	 * @return mixed|void
	 */
	public function init() {
		add_action('add_attachment', [$this, 'callback_add_attachment']);
		add_action('edit_attachment', [$this, 'callback_edit_attachment']);
		add_action('delete_attachment', [$this, 'callback_delete_attachment']);
		add_action('wp_save_image_editor_file', [$this, 'callback_wp_save_image_editor_file'], 10, 5);
		add_action('wp_save_image_file', [$this, 'callback_wp_save_image_file'], 10, 5);
	}

	/**
	 * Logs an action when a Media Item (attachment) is added to WordPress
	 *
	 * @param int $attachment_id The ID of the attachment
	 */
	public function callback_add_attachment(int $attachment_id) {
		// error_log('add_attachment: ' . $attachment_id);
		$attachment = get_post($attachment_id);

		$this->log_action(
			[
				'action_type'         => 'CREATE',
				'title'               => $attachment->post_title ?? "Attachment #$attachment_id",
				'status'              => 'publish',
				'node_id'             => $attachment_id,
			],
		);
	}

	/**
	 * Logs an action when Media Items are edited
	 *
	 * @param int $attachment_id
	 */
	public function callback_edit_attachment(int $attachment_id) {
		// error_log('edit_attachment: ' . $attachment_id);
		$attachment = get_post($attachment_id);

		$this->log_action(
			[
				'action_type'         => 'UPDATE',
				'title'               => $attachment->post_title ?? "Attachment #$attachment_id",
				'status'              => 'publish',
				'node_id'             => $attachment_id,
			],
		);
	}

	/**
	 * Logs an action when media items are deleted from the Media Library
	 *
	 * @param int $attachment_id The ID of the media item being deleted
	 */
	public function callback_delete_attachment(int $attachment_id) {
		// error_log('delete_attachment: ' . $attachment_id);
		$attachment = get_post($attachment_id);

		$this->log_action(
			[
				'action_type'         => 'DELETE',
				'title'               => $attachment->post_title ?? "Attachment #$attachment_id",
				'status'              => 'trash',
				'node_id'             => $attachment_id,
			],
		);
	}

	/**
	 * Logs an action when image files are saved from the image editor
	 *
	 * @param string $dummy      Unused.
	 * @param string $filename   Filename.
	 * @param string $image      Unused.
	 * @param string $mime_type  Unused.
	 * @param int    $post_id    Post ID.
	 */
	public function callback_wp_save_image_editor_file($dummy, $filename, $image, $mime_type, $post_id) {
		$this->callback_edit_attachment($post_id);
	}

	/**
	 * Logs an action when image files are saved from the image editor
	 *
	 * @param string $dummy      Unused.
	 * @param string $filename   Filename.
	 * @param string $image      Unused.
	 * @param string $mime_type  Unused.
	 * @param int    $post_id    Post ID.
	 */
	public function callback_wp_save_image_file($dummy, $filename, $image, $mime_type, $post_id) {
		return $this->callback_wp_save_image_editor_file($dummy, $filename, $image, $mime_type, $post_id);
	}
}
