<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Ringkasan state klinis untuk dashboard, riwayat, dan consultation workspace.
 *
 * Kelas ini sengaja tidak menyentuh database. Controller/model mengirim array
 * ringkasan (request, disposition, performer, result, review, record, closure)
 * dan kelas ini menghitung state, langkah Clinical Thread, serta jumlah tugas.
 * Logika state harus tetap sejalan dengan Visit_workflow_state_resolver;
 * parity test mengunci urutan precedence-nya.
 */
class Clinical_workflow_summary
{
    private $careTeamEnabled;

    public function __construct($careTeamEnabled = null)
    {
        if ($careTeamEnabled === null) {
            $CI = function_exists('get_instance') ? get_instance() : null;
            $careTeamEnabled = $CI && isset($CI->config)
                && $CI->config->item('care_team_workflow_enabled') === true;
        }
        $this->careTeamEnabled = $careTeamEnabled === true;
    }

    public function stateFrom(array $summary)
    {
        $request = $this->toArray(isset($summary['request']) ? $summary['request'] : null);
        $disposition = $this->toArray(isset($summary['disposition']) ? $summary['disposition'] : null);
        $performer = $this->toArray(isset($summary['performer']) ? $summary['performer'] : null);
        $result = $this->toArray(isset($summary['result']) ? $summary['result'] : null);
        $review = $this->toArray(isset($summary['review']) ? $summary['review'] : null);
        $record = $this->toArray(isset($summary['record']) ? $summary['record'] : null);

        if (strtolower(trim((string) ($request['request_status'] ?? ''))) === 'completed') {
            $enrolled = !empty($disposition);
            return ($this->careTeamEnabled || $enrolled) ? 'COMPLETED' : null;
        }

        $enrolled = !empty($disposition);
        if (!$enrolled) {
            return $this->careTeamEnabled ? 'WAITING_DOCTOR_DISPOSITION' : null;
        }

        $decision = strtolower(trim((string) ($disposition['decision'] ?? '')));
        $finalized = $record && array_key_exists('clinical_finalized_at', $record)
            ? $record['clinical_finalized_at'] !== null
            : !empty($record['clinical_finalized_at']);

        if ($decision === 'non_visit') {
            return $finalized ? 'READY_FOR_CLOSURE' : 'WAITING_CLINICAL_FINALIZATION';
        }

        if (!empty($result)) {
            $reviewDecision = strtolower(trim((string) ($review['decision'] ?? '')));
            if ($reviewDecision === 'correction_required') {
                return 'CORRECTION_REQUIRED';
            }
            if ($reviewDecision === 'approved') {
                return $finalized ? 'READY_FOR_CLOSURE' : 'WAITING_CLINICAL_FINALIZATION';
            }
            return 'WAITING_DOCTOR_REVIEW';
        }

        $visitStatus = strtolower(trim((string) ($request['visit_status'] ?? '')));
        if ($visitStatus === 'completed') {
            return 'WAITING_VISIT_RESULT';
        }

        if (empty($performer) || strtolower(trim((string) ($performer['status'] ?? ''))) !== 'aktif') {
            return 'WAITING_PERFORMER_ASSIGNMENT';
        }

        $visitStates = array(
            'not_started' => 'VISIT_NOT_STARTED',
            'en_route' => 'VISIT_EN_ROUTE',
            'arrived' => 'VISIT_ARRIVED',
            'in_service' => 'VISIT_IN_SERVICE',
        );
        return isset($visitStates[$visitStatus]) ? $visitStates[$visitStatus] : 'WAITING_PERFORMER_ASSIGNMENT';
    }

    public function steps(array $summary, $state)
    {
        $summary = $this->normalizeSummary($summary);
        $mode = strtolower(trim((string) ($summary['request']['consultation_mode'] ?? '')));
        $nonVisit = $mode === 'non_visit';
        $definitions = array(
            array('key' => 'disposition', 'label' => 'Disposisi'),
            array('key' => 'performer', 'label' => 'Nakes'),
            array('key' => 'visit', 'label' => 'Kunjungan'),
            array('key' => 'result', 'label' => 'Hasil'),
            array('key' => 'review', 'label' => 'Review'),
            array('key' => 'record', 'label' => 'Rekam medis'),
            array('key' => 'closure', 'label' => 'Penutupan'),
        );

        $currentIndex = $state === null ? -1 : $this->currentIndex($state, $nonVisit);
        $steps = array();
        foreach ($definitions as $index => $definition) {
            $key = $definition['key'];
            $stepState = 'pending';
            if ($state === 'COMPLETED') {
                $stepState = 'done';
            } elseif ($nonVisit && $key === 'disposition' && empty($summary['disposition'])) {
                $stepState = 'skipped';
            } elseif ($nonVisit && in_array($key, array('performer', 'visit', 'result', 'review'), true)) {
                $stepState = 'skipped';
            } elseif ($state === 'CORRECTION_REQUIRED' && $key === 'review') {
                $stepState = 'blocked';
            } elseif ($index < $currentIndex) {
                $stepState = 'done';
            } elseif ($index === $currentIndex) {
                $stepState = 'current';
            }
            $steps[] = array(
                'key' => $key,
                'label' => $definition['label'],
                'state' => $stepState,
                'meta' => $this->stepMeta($key, $summary, $state),
            );
        }
        return $steps;
    }

    public function forSurface(array $surface)
    {
        $summary = $this->normalizeSummary(array(
            'request' => isset($surface['request']) ? $surface['request'] : array(),
            'disposition' => isset($surface['disposition_summary']) ? $surface['disposition_summary'] : null,
            'performer' => isset($surface['performer']) ? $surface['performer'] : null,
            'result' => isset($surface['result_summary']) ? $surface['result_summary'] : null,
            'review' => isset($surface['review_summary']) ? $surface['review_summary'] : null,
            'record' => isset($surface['finalization_summary']) ? $surface['finalization_summary'] : null,
            'closure' => isset($surface['closure_summary']) ? $surface['closure_summary'] : null,
        ));
        $state = $this->stateFrom($summary);
        $nonVisit = strtolower(trim((string) ($summary['request']['consultation_mode'] ?? ''))) === 'non_visit';
        $index = $this->currentIndex($state, $nonVisit);
        return array(
            'state' => $state,
            'steps' => $this->steps($summary, $state),
            'step_index' => $state === 'COMPLETED' ? 7 : ($state === null ? 0 : $index + 1),
            'summary' => $summary,
        );
    }

    public function map(array $summaries)
    {
        $result = array();
        foreach ($summaries as $requestId => $summary) {
            if (!is_array($summary)) {
                continue;
            }
            $normalized = $this->normalizeSummary($summary);
            if (empty($normalized['request']['request_id'])) {
                $normalized['request']['request_id'] = (int) $requestId;
            }
            $state = $this->stateFrom($normalized);
            if ($state === null) {
                continue;
            }
            $nonVisit = strtolower(trim((string) ($normalized['request']['consultation_mode'] ?? ''))) === 'non_visit';
            $index = $this->currentIndex($state, $nonVisit);
            $result[(int) $requestId] = array(
                'state' => $state,
                'steps' => $this->steps($normalized, $state),
                'step_index' => $state === 'COMPLETED' ? 7 : $index + 1,
                'summary' => $normalized,
            );
        }
        return $result;
    }

    public function actionCounts(array $map, $actorType = null)
    {
        $counts = array('needs_action' => 0, 'waiting_review' => 0, 'ready_to_close' => 0, 'completed' => 0, 'completed_today' => 0);
        $actionStates = array(
            'WAITING_DOCTOR_DISPOSITION', 'WAITING_PERFORMER_ASSIGNMENT', 'WAITING_DOCTOR_REVIEW',
            'CORRECTION_REQUIRED', 'WAITING_CLINICAL_FINALIZATION', 'READY_FOR_CLOSURE',
            'VISIT_NOT_STARTED', 'VISIT_EN_ROUTE', 'VISIT_ARRIVED', 'VISIT_IN_SERVICE', 'WAITING_VISIT_RESULT',
        );
        foreach ($map as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $state = (string) ($entry['state'] ?? '');
            if ($state === 'WAITING_DOCTOR_REVIEW') {
                $counts['waiting_review']++;
            }
            if ($state === 'READY_FOR_CLOSURE') {
                $counts['ready_to_close']++;
            }
            if ($state === 'COMPLETED') {
                $counts['completed']++;
                $closedAt = (string) ($entry['summary']['closure']['closed_at'] ?? '');
                if ($closedAt !== '' && substr($closedAt, 0, 10) === date('Y-m-d')) {
                    $counts['completed_today']++;
                }
            }
            $owner = $this->actorForState($state);
            if (in_array($state, $actionStates, true)
                && ($actorType === null || $owner === $actorType)) {
                $counts['needs_action']++;
            }
        }
        return $counts;
    }

    public function actorForState($state)
    {
        $owners = array(
            'WAITING_DOCTOR_DISPOSITION' => 'responsible_doctor',
            'WAITING_PERFORMER_ASSIGNMENT' => 'responsible_doctor',
            'WAITING_DOCTOR_REVIEW' => 'responsible_doctor',
            'WAITING_CLINICAL_FINALIZATION' => 'responsible_doctor',
            'READY_FOR_CLOSURE' => 'responsible_doctor',
            'VISIT_NOT_STARTED' => 'performer',
            'VISIT_EN_ROUTE' => 'performer',
            'VISIT_ARRIVED' => 'performer',
            'VISIT_IN_SERVICE' => 'performer',
            'WAITING_VISIT_RESULT' => 'performer',
            'CORRECTION_REQUIRED' => 'performer',
            'COMPLETED' => null,
        );
        return isset($owners[$state]) ? $owners[$state] : null;
    }

    private function currentIndex($state, $nonVisit)
    {
        if ($state === 'COMPLETED') {
            return 6;
        }
        if ($state === 'CORRECTION_REQUIRED') {
            return 3;
        }
        $map = array(
            'WAITING_DOCTOR_DISPOSITION' => 0,
            'WAITING_PERFORMER_ASSIGNMENT' => 1,
            'VISIT_NOT_STARTED' => 2,
            'VISIT_EN_ROUTE' => 2,
            'VISIT_ARRIVED' => 2,
            'VISIT_IN_SERVICE' => 2,
            'WAITING_VISIT_RESULT' => 3,
            'WAITING_DOCTOR_REVIEW' => 4,
            'WAITING_CLINICAL_FINALIZATION' => 5,
            'READY_FOR_CLOSURE' => 6,
        );
        return isset($map[$state]) ? $map[$state] : ($nonVisit ? 5 : 0);
    }

    private function stepMeta($key, array $summary, $state)
    {
        if ($key === 'disposition') {
            $decision = strtolower(trim((string) ($summary['disposition']['decision'] ?? '')));
            if ($decision === '') {
                $mode = strtolower(trim((string) ($summary['request']['consultation_mode'] ?? '')));
                if ($mode === 'non_visit') {
                    return 'Dilewati';
                }
            }
            return $decision === 'visit' ? 'Kunjungan' : ($decision === 'non_visit' ? 'Tanpa kunjungan' : 'Opsional');
        }
        if ($key === 'performer') {
            $name = trim((string) ($summary['performer']['display_name'] ?? ($summary['performer']['performer_name'] ?? '')));
            return $name !== '' ? $name : 'Belum ditunjuk';
        }
        if ($key === 'visit') {
            $labels = array(
                'not_started' => 'Belum dimulai',
                'en_route' => 'Dalam perjalanan',
                'arrived' => 'Tiba di lokasi',
                'in_service' => 'Pemeriksaan',
                'completed' => 'Selesai',
            );
            $status = strtolower(trim((string) ($summary['request']['visit_status'] ?? '')));
            return isset($labels[$status]) ? $labels[$status] : '';
        }
        if ($key === 'result') {
            $version = (int) ($summary['result']['version_no'] ?? 0);
            return $version > 0 ? 'Versi ' . $version : '';
        }
        if ($key === 'review') {
            $decision = strtolower(trim((string) ($summary['review']['decision'] ?? '')));
            if ($decision === 'approved') {
                return 'Disetujui';
            }
            if ($decision === 'correction_required') {
                return 'Perlu perbaikan';
            }
            return $state === 'WAITING_DOCTOR_REVIEW' ? 'Menunggu dokter' : '';
        }
        if ($key === 'record') {
            return !empty($summary['record']['clinical_finalized_at'])
                ? $this->shortTime($summary['record']['clinical_finalized_at'])
                : 'Belum final';
        }
        if ($key === 'closure') {
            return !empty($summary['closure']['closed_at'])
                ? $this->shortTime($summary['closure']['closed_at'])
                : '';
        }
        return '';
    }

    private function shortTime($value)
    {
        $time = strtotime((string) $value);
        return $time === false ? '' : date('d M H:i', $time);
    }

    private function normalizeSummary(array $summary)
    {
        foreach (array('request', 'disposition', 'performer', 'result', 'review', 'record', 'closure') as $key) {
            $summary[$key] = $this->toArray(isset($summary[$key]) ? $summary[$key] : null);
        }
        return $summary;
    }

    private function toArray($value)
    {
        if (is_object($value)) {
            return get_object_vars($value);
        }
        return is_array($value) ? $value : array();
    }
}


