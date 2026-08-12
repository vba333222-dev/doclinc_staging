<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Request_transition_orchestrator
{
	public function requestCreated($request_id, $request, $actor_user_id)
	{
		$request_id = (int) $request_id;
		if ($request_id < 1) {
			return null;
		}
		$response = array('status' => 'success', 'message' => 'Permintaan dikirim.');
		if (!$request || (int) $request->request_id !== $request_id) {
			return array('response' => $response, 'notification_result' => null);
		}
		$puskesmas_code = trim((string) ($request->assigned_puskesmas_code ?? ''));
		$puskesmas_name = trim((string) ($request->assigned_puskesmas_name ?? ''));

		$notification_result = null;
		if ($this->usesControllerNotification()) {
			$notification_result = doclinc_notify_puskesmas(
				$puskesmas_code,
				'request_created',
				'request',
				$request_id,
				'Permintaan konsultasi baru',
				'Ada permintaan konsultasi baru untuk Puskesmas ' . $puskesmas_name,
				(int) $actor_user_id
			);
		}

		return array(
			'response' => $response,
			'notification_result' => $notification_result,
		);
	}

	public function requestAccepted($request_id, $request, $actor_user_id, array $model_result)
	{
		$request_id = (int) $request_id;
		if ($request_id < 1 || ($model_result['status'] ?? '') !== 'success') {
			return null;
		}

		$notification_result = null;
		if (empty($model_result['already_accepted']) && $request && $this->usesControllerNotification()) {
			$notification_result = doclinc_notify_user(
				(int) $request->user_id,
				'request_accepted',
				'request',
				$request_id,
				'Konsultasi diterima',
				'Permintaan konsultasi Anda sudah diterima oleh petugas.',
				(int) $actor_user_id
			);
		}

		return array(
			'response' => array(
				'status' => 'success',
				'message' => $model_result['message'],
				'request_id' => $request_id,
				'request_status' => 'Accepted',
				'redirect_url' => base_url('konsultasi_nakes/konsultasi/' . $request_id),
			),
			'notification_result' => $notification_result,
		);
	}

	public function requestCancelledByOwner($request_id, $request, $actor_user_id, $model_result)
	{
		$request_id = (int) $request_id;
		if ($request_id < 1 || $model_result !== true) {
			return null;
		}

		$notification_result = null;
		if ($request && $this->usesControllerNotification()) {
			$CI = &get_instance();
			if ($CI->config->item('care_team_workflow_enabled') === true
				&& (string) ($request->request_status ?? '') === 'Pending') {
				$notification_result = doclinc_notify_puskesmas(
					(string) ($request->assigned_puskesmas_code ?? ''),
					'request_cancelled',
					'request',
					$request_id,
					'Konsultasi dibatalkan',
					'Permintaan konsultasi dibatalkan oleh pasien.',
					(int) $actor_user_id
				);
			} else {
				$recipient_id = doclinc_request_handling_nakes_id($request);
				if (!empty($recipient_id)) {
					$notification_result = doclinc_notify_user(
						(int) $recipient_id,
						'request_cancelled',
						'request',
						$request_id,
						'Konsultasi dibatalkan',
						'Permintaan konsultasi dibatalkan oleh pasien.',
						(int) $actor_user_id
					);
				}
			}
		}

		return array(
			'response' => array(
				'status' => 'success',
				'message' => 'Permintaan dibatalkan.',
				'request_id' => $request_id,
				'request_status' => 'Cancelled',
			),
			'notification_result' => $notification_result,
		);
	}

	public function requestCancelledByCommandCenter($request_id, $request, $actor_user_id, array $model_result)
	{
		$request_id = (int) $request_id;
		if ($request_id < 1 || ($model_result['status'] ?? '') !== 'success') {
			return null;
		}

		$notification_result = null;
		if ($request && $this->usesControllerNotification()) {
			$notification_result = doclinc_notify_user(
				(int) $request->user_id,
				'request_cancelled',
				'request',
				$request_id,
				'Konsultasi dibatalkan',
				'Permintaan konsultasi Anda dibatalkan oleh petugas.',
				(int) $actor_user_id
			);
		}

		return array(
			'response' => array(
				'status' => 'success',
				'message' => $model_result['message'],
				'request_id' => $request_id,
				'request_status' => 'Cancelled',
			),
			'notification_result' => $notification_result,
		);
	}

	public function requestCompleted($request_id, $request, $actor_user_id, $model_result)
	{
		$request_id = (int) $request_id;
		if ($request_id < 1 || $model_result !== true) {
			return null;
		}

		$notification_result = null;
		if ($request && $this->usesControllerNotification()) {
			$notification_result = doclinc_notify_user(
				(int) $request->user_id,
				'consultation_completed',
				'request',
				$request_id,
				'Konsultasi selesai',
				'Hasil konsultasi Anda sudah tersedia.',
				(int) $actor_user_id
			);
		}

		return array(
			'response' => array('status' => 'success', 'message' => 'Konsultasi selesai.'),
			'notification_result' => $notification_result,
		);
	}

	private function usesControllerNotification()
	{
		return !function_exists('doclinc_realtime_requests_enabled')
			|| !doclinc_realtime_requests_enabled();
	}
}
