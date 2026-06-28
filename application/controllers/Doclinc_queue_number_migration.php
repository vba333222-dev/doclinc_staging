<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Doclinc_queue_number_migration extends CI_Controller
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

		$this->add_column('queue_date', "ALTER TABLE `requests` ADD COLUMN `queue_date` DATE NULL");
		$this->add_column('queue_number', "ALTER TABLE `requests` ADD COLUMN `queue_number` INT NULL");
		$this->add_column('queue_code', "ALTER TABLE `requests` ADD COLUMN `queue_code` VARCHAR(50) NULL");
		$this->backfill_queue_codes();
		$this->add_unique_queue_code_index();

		echo "[done] queue number migration completed\n";
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

	private function backfill_queue_codes()
	{
		foreach (array('queue_date', 'queue_number', 'queue_code') as $field) {
			if (!$this->db->field_exists($field, 'requests')) {
				echo "[skip] queue backfill waiting for `{$field}`\n";
				return;
			}
		}

		$date_expr = $this->db->field_exists('created_at', 'requests')
			? 'DATE(`created_at`)'
			: ($this->db->field_exists('date', 'requests') ? 'DATE(`date`)' : 'CURDATE()');
		$bucket_expr = $this->db->field_exists('assigned_puskesmas_code', 'requests')
			? "COALESCE(NULLIF(TRIM(`assigned_puskesmas_code`), ''), 'LEGACY')"
			: "'LEGACY'";
		$created_order = $this->db->field_exists('created_at', 'requests') ? '`created_at` ASC,' : '';

		$rows = $this->db
			->select("request_id, {$bucket_expr} AS queue_bucket, COALESCE({$date_expr}, CURDATE()) AS resolved_queue_date", false)
			->where("(queue_code IS NULL OR TRIM(queue_code) = '')", null, false)
			->order_by('resolved_queue_date ASC, queue_bucket ASC, ' . $created_order . ' request_id ASC', '', false)
			->get('requests')
			->result();

		$counters = array();
		foreach ($rows as $row) {
			$bucket = $row->queue_bucket !== '' ? $row->queue_bucket : 'LEGACY';
			$queue_date = date('Y-m-d', strtotime($row->resolved_queue_date));
			$key = $bucket . '|' . $queue_date;

			if (!isset($counters[$key])) {
				$counters[$key] = $this->current_max_queue_number($bucket, $queue_date);
			}

			$counters[$key]++;
			$queue_number = $counters[$key];
			$queue_code = $bucket . '-' . date('Ymd', strtotime($queue_date)) . '-' . str_pad((string) $queue_number, 3, '0', STR_PAD_LEFT);

			$this->db
				->where('request_id', $row->request_id)
				->where("(queue_code IS NULL OR TRIM(queue_code) = '')", null, false)
				->update('requests', array(
					'queue_date' => $queue_date,
					'queue_number' => $queue_number,
					'queue_code' => $queue_code,
				));
		}

		echo "[ok] backfilled " . count($rows) . " queue codes\n";
	}

	private function current_max_queue_number($bucket, $queue_date)
	{
		$this->db->select('MAX(queue_number) AS max_queue_number', false);
		$this->db->where('queue_date', $queue_date);
		if ($this->db->field_exists('assigned_puskesmas_code', 'requests')) {
			if ($bucket === 'LEGACY') {
				$this->db->group_start();
				$this->db->where('assigned_puskesmas_code IS NULL', null, false);
				$this->db->or_where("TRIM(assigned_puskesmas_code) = ''", null, false);
				$this->db->group_end();
			} else {
				$this->db->where('TRIM(assigned_puskesmas_code) = ' . $this->db->escape($bucket), null, false);
			}
		}

		$row = $this->db->get('requests')->row();
		return (int) ($row ? $row->max_queue_number : 0);
	}

	private function add_unique_queue_code_index()
	{
		if ($this->index_exists('requests', 'uniq_requests_queue_code')) {
			echo "[skip] index exists `uniq_requests_queue_code`\n";
			return;
		}

		$duplicate = $this->db
			->select('queue_code')
			->where('queue_code IS NOT NULL', null, false)
			->where("TRIM(queue_code) <> ''", null, false)
			->group_by('queue_code')
			->having('COUNT(*) > 1', null, false)
			->limit(1)
			->get('requests')
			->row();
		if ($duplicate) {
			echo "[skip] duplicate queue_code exists; unique index not added\n";
			return;
		}

		$this->db->query('ALTER TABLE `requests` ADD UNIQUE KEY `uniq_requests_queue_code` (`queue_code`)');
		echo "[ok] added unique index `uniq_requests_queue_code`\n";
	}

	private function index_exists($table, $index)
	{
		$row = $this->db
			->select('1', false)
			->from('information_schema.STATISTICS')
			->where('TABLE_SCHEMA = DATABASE()', null, false)
			->where('TABLE_NAME', $table)
			->where('INDEX_NAME', $index)
			->limit(1)
			->get()
			->row();

		return (bool) $row;
	}
}
