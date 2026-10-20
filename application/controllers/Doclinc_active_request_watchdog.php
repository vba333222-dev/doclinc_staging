<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Menjalankan Active_request_watchdog dari CLI.
 *
 * Tidak ada cron di aplikasi ini; operator dapat memanggil perintah ini secara
 * berkala atau saat memeriksa papan operasional.
 *
 * Pemakaian:
 *   php index.php doclinc_active_request_watchdog
 *   DOCLINC_WATCHDOG_FACILITY=10280101 php index.php doclinc_active_request_watchdog
 */
class Doclinc_active_request_watchdog extends CI_Controller
{
	public function index()
	{
		if (!$this->input->is_cli_request()) {
			show_404();
			return;
		}
		$this->load->helper(array('request_authz', 'request_event', 'notification'));
		require_once APPPATH . 'libraries/Active_request_watchdog.php';

		$facility = strtoupper(trim((string) getenv('DOCLINC_WATCHDOG_FACILITY')));
		$careTeam = getenv('DOCLINC_WATCHDOG_CARE_TEAM') === '1'
			? true
			: ($this->config->item('care_team_workflow_enabled') === true);
		$watchdog = new Active_request_watchdog($this->db, $careTeam);
		$result = $watchdog->sweep($facility);
		$result['facility'] = $facility;
		$result['care_team_enabled'] = $careTeam;
		$result['reminder_minutes'] = $watchdog->reminderMinutes();
		$result['overdue_minutes'] = $watchdog->overdueMinutes();

		$this->output->set_content_type('application/json')->set_output(json_encode($result, JSON_UNESCAPED_SLASHES));
	}
}
