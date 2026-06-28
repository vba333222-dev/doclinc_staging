<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Doclinc_pic_nakes_migration extends CI_Controller
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

		$this->add_column('assigned_nakes_user_id', "ALTER TABLE `requests` ADD COLUMN `assigned_nakes_user_id` INT NULL");
		$this->add_column('assigned_nakes_at', "ALTER TABLE `requests` ADD COLUMN `assigned_nakes_at` DATETIME NULL");
		$this->add_column('assigned_nakes_by_user_id', "ALTER TABLE `requests` ADD COLUMN `assigned_nakes_by_user_id` INT NULL");
		$this->add_column('consultation_mode', "ALTER TABLE `requests` ADD COLUMN `consultation_mode` VARCHAR(30) NULL");

		echo "[done] PIC nakes migration completed\n";
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
