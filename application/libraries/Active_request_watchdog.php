<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Pengawas permintaan `Accepted` yang berhenti di tengah alur klinis.
 *
 * Permintaan yang sudah diterima puskesmas bisa macet tanpa suara: belum ada
 * dokter penanggung jawab, Nakes belum ditunjuk setelah disposisi kunjungan,
 * hasil belum dikirim, review belum dilakukan, rekam medis belum final, atau
 * konsultasi siap ditutup tetapi tidak ditutup. Disposisi dokter sendiri
 * opsional, jadi ketiadaannya bukan kondisi macet. Tidak ada penjadwal (cron),
 * jadi pengawasan bersifat "malas": dijalankan saat papan operasional dibuka
 * atau lewat CLI.
 *
 * Pengawas ini tidak memindahkan atau menutup permintaan. Ia hanya menandai
 * tahap yang macet, menulis event `request.clinical_stall` (idempoten per
 * tahap+severity), dan memberi tahu puskesmas/dokter agar ditindaklanjuti.
 */
class Active_request_watchdog
{
    const REMINDER_DEFAULT_MINUTES = 30;
    const OVERDUE_DEFAULT_MINUTES = 60;
    const SWEEP_LIMIT = 200;

    private $db;
    private $careTeamEnabled;

    public function __construct($db = null, $care_team_enabled = null)
    {
        $this->db = $db ?: get_instance()->db;
        $this->careTeamEnabled = $care_team_enabled;
        if ($this->careTeamEnabled === null) {
            $CI = get_instance();
            $this->careTeamEnabled = $CI && isset($CI->config)
                && $CI->config->item('care_team_workflow_enabled') === true;
        }
    }

    public function reminderMinutes()
    {
        return $this->configuredMinutes('clinical_stall_reminder_minutes', self::REMINDER_DEFAULT_MINUTES);
    }

    public function overdueMinutes()
    {
        return $this->configuredMinutes('clinical_stall_overdue_minutes', self::OVERDUE_DEFAULT_MINUTES);
    }

    /**
     * Daftar pengecualian untuk papan operasional. Tidak menulis event dan
     * tidak mengirim notifikasi.
     */
    public function exceptionsFor($puskesmasCode = '')
    {
        $rows = $this->stalledRows($puskesmasCode);
        $result = array();
        foreach ($rows as $row) {
            $stage = $this->stageFor($row);
            if ($stage === null) {
                continue;
            }
            $waiting = (int) $row->waiting_minutes;
            $result[] = array(
                'request_id' => (int) $row->request_id,
                'type' => $stage,
                'stage_label' => $this->stageLabel($stage),
                'severity' => $waiting >= $this->overdueMinutes() ? 'overdue' : 'reminder',
                'waiting_minutes' => $waiting,
                'visit_status' => (string) $row->visit_status,
                'consultation_mode' => (string) $row->consultation_mode,
                'patient_name' => (string) ($row->patient_name ?? ''),
                'queue_number' => isset($row->queue_number) ? (int) $row->queue_number : 0,
                'service_date' => (string) ($row->service_date ?? ''),
            );
        }
        return $result;
    }

    /**
     * Menandai dan memberi tahu tahap yang macet. Idempoten per
     * request+tahap+severity sehingga sweep berulang tidak membanjiri
     * notifikasi.
     */
    public function sweep($puskesmasCode = '')
    {
        $rows = $this->stalledRows($puskesmasCode);
        $summary = array('checked' => count($rows), 'flagged' => 0, 'notified' => 0, 'items' => array());
        foreach ($rows as $row) {
            $stage = $this->stageFor($row);
            if ($stage === null) {
                continue;
            }
            $waiting = (int) $row->waiting_minutes;
            $severity = $waiting >= $this->overdueMinutes() ? 'overdue' : 'reminder';
            $summary['flagged']++;
            $key = 'request:' . (int) $row->request_id . ':clinical-stall:' . $stage . ':' . $severity;
            if ($this->hasEvent($key)) {
                continue;
            }
            $event = array(
                'puskesmas_code' => (string) $row->assigned_puskesmas_code,
                'actor_user_id' => 0,
                'actor_staff_id' => 0,
                'domain_event_key' => $key,
                'metadata' => array(
                    'request_id' => (int) $row->request_id,
                    'stage' => $stage,
                    'severity' => $severity,
                    'waiting_minutes' => $waiting,
                    'reminder_minutes' => $this->reminderMinutes(),
                    'overdue_minutes' => $this->overdueMinutes(),
                ),
            );
            if (!function_exists('doclinc_append_request_event')
                || !doclinc_append_request_event((int) $row->request_id, 'request.clinical_stall', $event, get_instance())) {
                continue;
            }
            $this->notify($row, $stage, $severity, $waiting);
            $summary['notified']++;
            $summary['items'][] = array(
                'request_id' => (int) $row->request_id,
                'type' => $stage,
                'severity' => $severity,
                'waiting_minutes' => $waiting,
            );
        }
        return $summary;
    }

    private function stalledRows($puskesmasCode)
    {
        if (!$this->schemaReady()) {
            return array();
        }
        $queue = $this->db->field_exists('queue_number', 'requests') ? ', r.queue_number' : ', NULL AS queue_number';
        $sql = 'SELECT r.request_id, r.assigned_puskesmas_code, r.visit_status, r.consultation_mode,'
            . ' r.responsible_doctor_user_id, r.created_at, DATE(r.created_at) AS service_date,'
            . ' u.nama AS patient_name' . $queue . ','
            . ' TIMESTAMPDIFF(MINUTE, r.created_at, NOW()) AS waiting_minutes,'
            . ' (SELECT COUNT(*) FROM ' . $this->db->dbprefix('request_responsible_doctor_assignments') . " a WHERE a.request_id = r.request_id AND a.status = 'aktif') AS rd_active,"
            . ' (SELECT d.decision FROM ' . $this->db->dbprefix('visit_dispositions') . ' d WHERE d.request_id = r.request_id AND d.superseded_at IS NULL ORDER BY d.version_no DESC LIMIT 1) AS disposition_decision,'
            . ' (SELECT COUNT(*) FROM ' . $this->db->dbprefix('request_visit_performer_assignments') . " p WHERE p.request_id = r.request_id AND p.status = 'aktif') AS performer_active,"
            . ' (SELECT COUNT(*) FROM ' . $this->db->dbprefix('visit_results') . " vr WHERE vr.request_id = r.request_id AND vr.status = 'submitted') AS submitted_results,"
            . ' (SELECT COUNT(*) FROM ' . $this->db->dbprefix('clinical_reviews') . ' cr INNER JOIN ' . $this->db->dbprefix('visit_results') . ' vr2 ON vr2.visit_result_id = cr.visit_result_id WHERE vr2.request_id = r.request_id) AS review_count,'
            . ' (SELECT cr2.decision FROM ' . $this->db->dbprefix('clinical_reviews') . ' cr2 INNER JOIN ' . $this->db->dbprefix('visit_results') . ' vr3 ON vr3.visit_result_id = cr2.visit_result_id WHERE vr3.request_id = r.request_id ORDER BY vr3.version_no DESC, cr2.clinical_review_id DESC LIMIT 1) AS latest_review,'
            . ' (SELECT m.clinical_finalized_at FROM ' . $this->db->dbprefix('medicalrecords') . ' m WHERE m.request_id = r.request_id ORDER BY m.record_id DESC LIMIT 1) AS finalized_at,'
            . ' (SELECT COUNT(*) FROM ' . $this->db->dbprefix('clinical_closure_operations') . ' c WHERE c.request_id = r.request_id) AS closure_count'
            . ' FROM ' . $this->db->dbprefix('requests') . ' r'
            . ' INNER JOIN ' . $this->db->dbprefix('users') . ' u ON u.userId = r.user_id'
            . " WHERE r.request_status = 'Accepted' AND r.created_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE)";
        $params = array($this->reminderMinutes());
        if (trim((string) $puskesmasCode) !== '') {
            $sql .= ' AND r.assigned_puskesmas_code = ?';
            $params[] = trim((string) $puskesmasCode);
        }
        $sql .= ' ORDER BY r.created_at ASC LIMIT ' . self::SWEEP_LIMIT;
        $query = $this->db->query($sql, $params);
        return $query ? $query->result() : array();
    }

    private function stageFor($row)
    {
        $mode = strtolower(trim((string) ($row->consultation_mode ?? '')));
        $visit = strtolower(trim((string) ($row->visit_status ?? '')));
        $disposition = strtolower(trim((string) ($row->disposition_decision ?? '')));
        $latestReview = strtolower(trim((string) ($row->latest_review ?? '')));

        if ((int) ($row->responsible_doctor_user_id ?? 0) < 1 && (int) ($row->rd_active ?? 0) < 1) {
            return 'no_responsible_doctor';
        }
        if ($this->careTeamEnabled && $disposition === 'visit'
            && in_array($visit, array('', 'not_started'), true)
            && (int) ($row->performer_active ?? 0) < 1) {
            return 'no_performer';
        }
        if ($this->careTeamEnabled && $disposition === 'visit' && $visit === 'completed'
            && (int) ($row->submitted_results ?? 0) < 1) {
            return 'result_not_submitted';
        }
        if ($this->careTeamEnabled && (int) ($row->submitted_results ?? 0) > 0
            && (int) ($row->review_count ?? 0) < 1 && $mode === 'visit') {
            return 'review_pending';
        }
        if ($this->careTeamEnabled && $latestReview === 'correction_required') {
            return 'correction_pending';
        }
        if (empty($row->finalized_at) && ($mode === 'non_visit' || $latestReview === 'approved')) {
            return 'record_not_finalized';
        }
        if (!empty($row->finalized_at) && (int) ($row->closure_count ?? 0) < 1) {
            return 'ready_for_closure';
        }
        return null;
    }

    private function stageLabel($stage)
    {
        $labels = array(
            'no_responsible_doctor' => 'belum ada dokter penanggung jawab',
            'no_performer' => 'Nakes pelaksana belum ditunjuk',
            'result_not_submitted' => 'hasil kunjungan belum dikirim',
            'review_pending' => 'hasil menunggu review dokter',
            'correction_pending' => 'hasil menunggu koreksi Nakes',
            'record_not_finalized' => 'rekam medis belum difinalisasi',
            'ready_for_closure' => 'konsultasi siap ditutup',
        );
        return isset($labels[$stage]) ? $labels[$stage] : $stage;
    }

    private function notify($row, $stage, $severity, $waiting)
    {
        $title = $severity === 'overdue' ? 'Tahap klinis terlambat' : 'Tahap klinis perlu perhatian';
        $message = 'Permintaan ini berhenti pada tahap "' . $this->stageLabel($stage)
            . '" selama ' . $waiting . ' menit. Mohon ditindaklanjuti.';
        if (function_exists('doclinc_notify_puskesmas') && (string) $row->assigned_puskesmas_code !== '') {
            doclinc_notify_puskesmas(
                (string) $row->assigned_puskesmas_code,
                'clinical_stall_' . $severity,
                'request',
                (int) $row->request_id,
                $title,
                $message,
                0
            );
        }
        $responsible = (int) ($row->responsible_doctor_user_id ?? 0);
        if ($responsible > 0 && function_exists('doclinc_notify_user')
            && in_array($stage, array('review_pending', 'correction_pending', 'record_not_finalized', 'ready_for_closure'), true)) {
            doclinc_notify_user(
                $responsible,
                'clinical_stall_' . $severity,
                'request',
                (int) $row->request_id,
                $title,
                $message,
                0
            );
        }
    }

    private function hasEvent($domainKey)
    {
        return (bool) $this->db
            ->select('event_id')
            ->where('domain_event_key', (string) $domainKey)
            ->limit(1)
            ->get('request_events')
            ->row();
    }

    private function schemaReady()
    {
        if (!$this->db) {
            return false;
        }
        foreach (array(
            'requests', 'users', 'request_responsible_doctor_assignments', 'visit_dispositions',
            'request_visit_performer_assignments', 'visit_results', 'clinical_reviews',
            'medicalrecords', 'clinical_closure_operations', 'request_events',
        ) as $table) {
            $row = $this->db->query(
                'SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                array($table)
            )->row();
            if (!$row || (int) $row->total < 1) {
                return false;
            }
        }
        return true;
    }

    private function configuredMinutes($configKey, $default)
    {
        $CI = get_instance();
        $value = ($CI && isset($CI->config)) ? $CI->config->item((string) $configKey) : null;
        if (!is_numeric($value)) {
            return (int) $default;
        }
        $minutes = (int) $value;
        return $minutes > 0 ? $minutes : (int) $default;
    }
}
