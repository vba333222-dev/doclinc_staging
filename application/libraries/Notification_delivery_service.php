<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Realtime_outbox_writer.php';

class Notification_delivery_service
{
	private $db;
	private $feature_state;
	private $outbox_writer;

	public function __construct($db, array $feature_state, ?Realtime_outbox_writer $outbox_writer = null)
	{
		$this->db = $db;
		$this->feature_state = $feature_state;
		$this->outbox_writer = $outbox_writer ?: new Realtime_outbox_writer();
	}

	public function create(array $notification)
	{
		if (empty($this->feature_state['enabled'])) {
			return $this->insertNotification($notification);
		}
		if (!$this->supportsTransactions() || $this->db->trans_begin() !== true) {
			return false;
		}

		$notification_id = $this->createWithinTransaction($notification);
		if ($notification_id === false || $this->db->trans_status() === false) {
			$this->db->trans_rollback();
			return false;
		}
		if ($this->db->trans_commit() !== true) {
			$this->db->trans_rollback();
			return false;
		}
		return $notification_id;
	}

	public function createWithinTransaction(array $notification)
	{
		$recipient_user_id = isset($notification['recipient_user_id']) ? (int) $notification['recipient_user_id'] : 0;
		if ($recipient_user_id < 1) {
			return false;
		}

		$notification_id = $this->insertNotification($notification);
		if ($notification_id === false) {
			return false;
		}

		$result = $this->outbox_writer->enqueue($this->db, array(
			'event_id' => 'notification:' . $notification_id,
			'event_type' => 'notification.created',
			'aggregate_type' => 'notification',
			'aggregate_id' => (string) $notification_id,
			'version' => 1,
			'invalidation' => 'notifications',
			'audience' => 'user:' . $recipient_user_id,
		), array(), $this->feature_state);

		return !empty($result['success']) ? $notification_id : false;
	}

	private function insertNotification(array $notification)
	{
		if (!is_object($this->db) || !method_exists($this->db, 'insert') || !method_exists($this->db, 'insert_id')) {
			return false;
		}
		$previous_debug = property_exists($this->db, 'db_debug') ? $this->db->db_debug : null;
		if (property_exists($this->db, 'db_debug')) {
			$this->db->db_debug = false;
		}
		try {
			$inserted = $this->db->insert('notifications', $notification);
			$notification_id = $inserted ? (int) $this->db->insert_id() : 0;
		} catch (Throwable $exception) {
			$notification_id = 0;
		} finally {
			if (property_exists($this->db, 'db_debug')) {
				$this->db->db_debug = $previous_debug;
			}
		}
		return $notification_id > 0 ? $notification_id : false;
	}

	private function supportsTransactions()
	{
		return is_object($this->db)
			&& method_exists($this->db, 'trans_begin')
			&& method_exists($this->db, 'trans_status')
			&& method_exists($this->db, 'trans_commit')
			&& method_exists($this->db, 'trans_rollback');
	}
}
