<?php
defined('BASEPATH') or exit('No direct script access allowed');

require_once __DIR__ . '/Realtime_outbox_writer.php';
require_once __DIR__ . '/Notification_delivery_service.php';

class Request_realtime_delivery
{
	private const TRANSITIONS = array(
		'created' => array('request.created', 'requests'),
		'accepted' => array('request.accepted', 'requests'),
		'cancelled' => array('request.cancelled', 'requests'),
		'completed' => array('request.completed', 'requests'),
		'pic_assigned' => array('request.pic_assigned', 'assignment'),
		'pic_reassigned' => array('request.pic_reassigned', 'assignment'),
		'pic_cleared' => array('request.pic_cleared', 'assignment'),
	);

	private $db;
	private $request_feature;
	private $notification_feature;
	private $writer;

	public function __construct($db, array $request_feature, array $notification_feature, ?Realtime_outbox_writer $writer = null)
	{
		$this->db = $db;
		$this->request_feature = $request_feature;
		$this->notification_feature = $notification_feature;
		$this->writer = $writer ?: new Realtime_outbox_writer();
	}

	public function deliver($transition, $request_id, $identity_reference, array $audiences, array $known_puskesmas_codes, array $notifications = array())
	{
		if (empty($this->request_feature['enabled'])) {
			return false;
		}
		$request_id = (int) $request_id;
		$identity_reference = (int) $identity_reference;
		if ($request_id < 1 || $identity_reference < 1 || !isset(self::TRANSITIONS[$transition])) {
			return false;
		}
		$notification_delivery = new Notification_delivery_service($this->db, $this->notification_feature, $this->writer);
		foreach ($notifications as $notification) {
			if ($notification_delivery->createWithinTransaction($notification) === false) {
				return false;
			}
		}
		$audiences = array_values(array_unique(array_filter($audiences, 'is_string')));
		if (empty($audiences)) {
			return false;
		}
		$contract = self::TRANSITIONS[$transition];
		foreach ($audiences as $audience) {
			$result = $this->writer->enqueue($this->db, array(
				'event_id' => 'request.' . $transition . ':' . $identity_reference,
				'event_type' => $contract[0],
				'aggregate_type' => 'request',
				'aggregate_id' => (string) $request_id,
				'version' => 1,
				'invalidation' => $contract[1],
				'audience' => $audience,
			), $known_puskesmas_codes, $this->request_feature);
			if (empty($result['success'])) {
				return false;
			}
		}
		return true;
	}
}
