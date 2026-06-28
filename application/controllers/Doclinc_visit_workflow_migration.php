<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Doclinc_visit_workflow_migration extends CI_Controller
{
	public function index()
	{
		if (!$this->input->is_cli_request()) {
			show_404();
			return;
		}

		$this->load->database();

		if (!$this->db->table_exists('requests')) {
			echo "[error] table `requests` not found\n";
			return;
		}

		$this->add_column('visit_status', "ALTER TABLE `requests` ADD COLUMN `visit_status` VARCHAR(30) NULL DEFAULT 'not_started'");
		$this->add_column('visit_started_at', "ALTER TABLE `requests` ADD COLUMN `visit_started_at` DATETIME NULL");
		$this->add_column('visit_arrived_at', "ALTER TABLE `requests` ADD COLUMN `visit_arrived_at` DATETIME NULL");
		$this->add_column('visit_in_service_at', "ALTER TABLE `requests` ADD COLUMN `visit_in_service_at` DATETIME NULL");
		$this->add_column('visit_completed_at', "ALTER TABLE `requests` ADD COLUMN `visit_completed_at` DATETIME NULL");

		echo "[done] visit workflow migration completed\n";
	}

	private function add_column($column, $sql)
	{
		if ($this->db->field_exists($column, 'requests')) {
			echo "[skip] column exists `requests`.`{$column}`\n";
			return;
		}

		$this->db->query($sql);
		echo "[ok] added column `requests`.`{$column}`\n";
	}
}
