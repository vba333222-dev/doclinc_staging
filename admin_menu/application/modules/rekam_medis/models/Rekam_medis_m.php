<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Rekam_medis_m extends MX_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->db = $this->load->database('default', TRUE);
	}

	private function ready()
	{
		return $this->db->table_exists('medicalrecords')
			&& $this->db->table_exists('requests')
			&& $this->db->field_exists('request_id', 'medicalrecords')
			&& $this->db->field_exists('request_id', 'requests');
	}

	private function field_expr($table, $field, $alias)
	{
		return $this->db->field_exists($field, $table) ? "$table.$field AS $alias" : "NULL AS $alias";
	}

	private function record_id_expr()
	{
		return $this->db->field_exists('record_id', 'medicalrecords')
			? 'medicalrecords.record_id'
			: 'medicalrecords.request_id';
	}

	private function diagnosis_expr()
	{
		return $this->db->field_exists('diagnosis', 'medicalrecords')
			? 'medicalrecords.diagnosis'
			: 'NULL';
	}

	private function created_expr()
	{
		if ($this->db->field_exists('created_at', 'medicalrecords')) {
			return 'medicalrecords.created_at';
		}
		if ($this->db->field_exists('updated_at', 'medicalrecords')) {
			return 'medicalrecords.updated_at';
		}
		if ($this->db->field_exists('created_at', 'requests')) {
			return 'requests.created_at';
		}
		if ($this->db->field_exists('date', 'requests')) {
			return 'requests.date';
		}

		return 'NULL';
	}

	private function puskesmas_code_expr()
	{
		$raw = $this->db->field_exists('assigned_puskesmas_code', 'requests')
			? "NULLIF(TRIM(requests.assigned_puskesmas_code), '')"
			: 'NULL';

		return "CASE WHEN UPPER(COALESCE($raw, '')) = 'DEFAULT' THEN NULL ELSE $raw END";
	}

	private function puskesmas_name_expr()
	{
		$assigned_name = $this->db->field_exists('assigned_puskesmas_name', 'requests')
			? "NULLIF(TRIM(requests.assigned_puskesmas_name), '')"
			: 'NULL';
		$assigned_name = "CASE WHEN UPPER(COALESCE($assigned_name, '')) IN ('DEFAULT', 'PUSKESMAS DEFAULT') THEN NULL ELSE $assigned_name END";
		$assigned_code = $this->puskesmas_code_expr();
		$master_name = $this->can_join_puskesmas() ? 'assigned_puskesmas.nama_puskesmas' : 'NULL';

		return "COALESCE($assigned_name, $master_name, $assigned_code, 'Perlu dicek')";
	}

	private function can_join_puskesmas()
	{
		return $this->db->table_exists('m_puskesmas')
			&& $this->db->field_exists('kode_pkm', 'm_puskesmas')
			&& $this->db->field_exists('nama_puskesmas', 'm_puskesmas');
	}

	private function can_join_patient_user()
	{
		return $this->db->table_exists('users')
			&& $this->db->field_exists('userId', 'users')
			&& $this->db->field_exists('nama', 'users')
			&& $this->db->field_exists('user_id', 'requests');
	}

	private function can_join_provider_user()
	{
		return $this->db->table_exists('users')
			&& $this->db->field_exists('userId', 'users')
			&& $this->db->field_exists('nama', 'users')
			&& $this->db->field_exists('accepted_by_user_id', 'requests');
	}

	private function patient_name_expr()
	{
		return $this->can_join_patient_user() ? 'patient_user.nama' : 'NULL';
	}

	private function provider_name_expr()
	{
		return $this->can_join_provider_user() ? 'provider_user.nama' : 'NULL';
	}

	private function join_base()
	{
		$this->db->from('medicalrecords');
		$this->db->join('requests', 'requests.request_id = medicalrecords.request_id', 'left');
		if ($this->can_join_patient_user()) {
			$this->db->join('users patient_user', 'patient_user.userId = requests.user_id', 'left');
		}
		if ($this->can_join_provider_user()) {
			$this->db->join('users provider_user', 'provider_user.userId = requests.accepted_by_user_id', 'left');
		}
		if ($this->can_join_puskesmas()) {
			$this->db->join('m_puskesmas assigned_puskesmas', 'assigned_puskesmas.kode_pkm = ' . $this->puskesmas_code_expr(), 'left', FALSE);
		}
	}

	private function apply_filters($filters)
	{
		$filters = is_array($filters) ? $filters : array();
		$period = isset($filters['period']) ? (string) $filters['period'] : '30';
		$date_expr = $this->created_expr();
		if (in_array($period, array('7', '30', '90'), true) && $date_expr !== 'NULL') {
			$this->db->where("DATE($date_expr) >= DATE_SUB(CURDATE(), INTERVAL " . (int) $period . " DAY)", NULL, FALSE);
		}

		$puskesmas = isset($filters['puskesmas']) ? trim((string) $filters['puskesmas']) : '';
		if ($puskesmas !== '') {
			$this->db->where($this->puskesmas_code_expr() . ' = ' . $this->db->escape($puskesmas), NULL, FALSE);
		}

		$keyword = isset($filters['keyword']) ? trim((string) $filters['keyword']) : '';
		if ($keyword !== '') {
			$like = '%' . $this->db->escape_like_str($keyword) . '%';
			$clauses = array(
				$this->diagnosis_expr() . ' LIKE ' . $this->db->escape($like),
				$this->puskesmas_name_expr() . ' LIKE ' . $this->db->escape($like),
				$this->puskesmas_code_expr() . ' LIKE ' . $this->db->escape($like),
				'CAST(requests.request_id AS CHAR) LIKE ' . $this->db->escape($like),
			);
			if ($this->can_join_patient_user()) {
				$clauses[] = 'patient_user.nama LIKE ' . $this->db->escape($like);
			}
			$this->db->where('(' . implode(' OR ', $clauses) . ')', NULL, FALSE);
		}
	}

	private function select_record_fields()
	{
		$date_expr = $this->created_expr();
		$updated_expr = $this->db->field_exists('updated_at', 'medicalrecords') ? 'medicalrecords.updated_at' : 'NULL';
		$mode_expr = $this->db->field_exists('consultation_mode', 'requests') ? 'requests.consultation_mode' : 'NULL';
		$visit_expr = $this->db->field_exists('visit_status', 'requests') ? 'requests.visit_status' : 'NULL';
		$status_expr = $this->db->field_exists('request_status', 'requests') ? 'requests.request_status' : 'NULL';

		$this->db->select($this->record_id_expr() . ' AS record_id', FALSE);
		$this->db->select('medicalrecords.request_id AS request_id', FALSE);
		$this->db->select("$date_expr AS created_at", FALSE);
		$this->db->select("$updated_expr AS updated_at", FALSE);
		$this->db->select($this->patient_name_expr() . ' AS patient_name', FALSE);
		$this->db->select($this->provider_name_expr() . ' AS provider_name', FALSE);
		$this->db->select($this->puskesmas_name_expr() . ' AS puskesmas_name', FALSE);
		$this->db->select($this->puskesmas_code_expr() . ' AS puskesmas_code', FALSE);
		$this->db->select($status_expr . ' AS request_status', FALSE);
		$this->db->select($mode_expr . ' AS consultation_mode', FALSE);
		$this->db->select($visit_expr . ' AS visit_status', FALSE);
		$this->db->select($this->diagnosis_expr() . ' AS diagnosis', FALSE);
		$this->db->select($this->field_expr('medicalrecords', 'treatment', 'treatment'), FALSE);
		$this->db->select($this->field_expr('medicalrecords', 'recommendations', 'recommendations'), FALSE);
		$this->db->select($this->field_expr('medicalrecords', 'notes', 'notes'), FALSE);
	}

	public function get_records($filters = array(), $limit = 200)
	{
		if (!$this->ready()) {
			return array();
		}

		$this->select_record_fields();
		$this->join_base();
		$this->apply_filters($filters);
		$this->db->order_by($this->created_expr(), 'DESC', FALSE);
		$this->db->limit((int) $limit > 0 ? (int) $limit : 200);

		return $this->db->get()->result();
	}

	public function get_record_detail($record_id)
	{
		if (!$this->ready()) {
			return null;
		}

		$this->select_record_fields();
		$this->join_base();
		$this->db->where($this->record_id_expr() . ' = ' . (int) $record_id, NULL, FALSE);

		return $this->db->get()->row();
	}

	public function get_summary($filters = array())
	{
		$summary = array(
			'total' => 0,
			'last_30_days' => 0,
			'puskesmas_count' => 0,
			'top_diagnosis' => '-',
			'top_diagnosis_count' => 0,
		);
		if (!$this->ready()) {
			return $summary;
		}

		$this->join_base();
		$this->apply_filters($filters);
		$summary['total'] = (int) $this->db->count_all_results();

		$date_expr = $this->created_expr();
		if ($date_expr !== 'NULL') {
			$this->join_base();
			$this->db->where("DATE($date_expr) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", NULL, FALSE);
			$summary['last_30_days'] = (int) $this->db->count_all_results();
		}

		$this->db->select('COUNT(DISTINCT ' . $this->puskesmas_code_expr() . ') AS total', FALSE);
		$this->join_base();
		$this->apply_filters($filters);
		$row = $this->db->get()->row();
		$summary['puskesmas_count'] = (int) ($row ? $row->total : 0);

		if ($this->db->field_exists('diagnosis', 'medicalrecords')) {
			$this->db->select('TRIM(medicalrecords.diagnosis) AS diagnosis, COUNT(*) AS total', FALSE);
			$this->join_base();
			$this->apply_filters($filters);
			$this->db->where('medicalrecords.diagnosis IS NOT NULL', NULL, FALSE);
			$this->db->where("TRIM(medicalrecords.diagnosis) <> ''", NULL, FALSE);
			$this->db->group_by('TRIM(medicalrecords.diagnosis)', FALSE);
			$this->db->order_by('total', 'DESC');
			$this->db->limit(1);
			$row = $this->db->get()->row();
			if ($row) {
				$summary['top_diagnosis'] = $row->diagnosis;
				$summary['top_diagnosis_count'] = (int) $row->total;
			}
		}

		return $summary;
	}

	public function get_puskesmas_options()
	{
		if (!$this->ready()) {
			return array();
		}

		$this->db->select($this->puskesmas_code_expr() . ' AS kode_pkm, ' . $this->puskesmas_name_expr() . ' AS nama_puskesmas', FALSE);
		$this->join_base();
		$this->db->where($this->puskesmas_code_expr() . ' IS NOT NULL', NULL, FALSE);
		$this->db->group_by($this->puskesmas_code_expr(), FALSE);
		$this->db->group_by($this->puskesmas_name_expr(), FALSE);
		$this->db->order_by('nama_puskesmas', 'ASC');

		return $this->db->get()->result();
	}
}
