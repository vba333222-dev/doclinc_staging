<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Visit_workflow_presenter
{
    public function present(array $state, $actorType, array $context = array())
    {
        $actor = in_array($actorType, array('warga', 'performer', 'responsible_doctor', 'command_center'), true)
            ? $actorType
            : 'warga';
        $machine = (string) ($state['state'] ?? '');
        $visibleMachine = ($actor === 'warga' && $machine === 'CORRECTION_REQUIRED')
            ? 'WAITING_DOCTOR_REVIEW'
            : $machine;
        return array(
            'request_id' => (int) ($state['request_id'] ?? 0),
            'state' => $visibleMachine,
            'label' => $this->labelFor($machine, $actor, (string) ($context['consultation_mode'] ?? '')),
            'tone' => $this->toneFor($machine),
            'owner' => $this->ownerFor($machine),
            'next_action' => $this->nextActionFor($machine, $actor, (string) ($context['consultation_mode'] ?? '')),
            'next_action_url' => $this->nextActionUrlFor($machine, $actor, (string) ($context['consultation_mode'] ?? '')),
            'step_key' => $this->stepKeyFor($machine),
            'correction_reason' => ($actor !== 'warga' && isset($context['correction_reason']))
                ? $context['correction_reason']
                : null,
        );
    }

    public function labelFor($state, $actorType, $consultationMode = '')
    {
        if ($state === 'WAITING_DOCTOR_DISPOSITION' && strtolower(trim((string) $consultationMode)) === 'non_visit') {
            $skipped = array(
                'warga' => 'Menunggu dokter',
                'performer' => 'Menunggu dokter',
                'responsible_doctor' => 'Lanjutkan konsultasi',
                'command_center' => 'Dokter menangani',
            );
            return isset($skipped[$actorType]) ? $skipped[$actorType] : 'Menunggu dokter';
        }
        $labels = array(
            'WAITING_DOCTOR_DISPOSITION' => array('warga' => 'Menunggu dokter', 'performer' => 'Menunggu disposisi dokter', 'responsible_doctor' => 'Perlu menetapkan disposisi', 'command_center' => 'Menunggu disposisi dokter'),
            'WAITING_PERFORMER_ASSIGNMENT' => array('warga' => 'Menunggu petugas', 'performer' => 'Menunggu penugasan', 'responsible_doctor' => 'Pilih Nakes pelaksana', 'command_center' => 'Belum ada Nakes'),
            'VISIT_NOT_STARTED' => array('warga' => 'Kunjungan dijadwalkan', 'performer' => 'Mulai perjalanan', 'responsible_doctor' => 'Menunggu kunjungan dimulai', 'command_center' => 'Kunjungan belum dimulai'),
            'VISIT_EN_ROUTE' => array('warga' => 'Petugas dalam perjalanan', 'performer' => 'Konfirmasi tiba di lokasi', 'responsible_doctor' => 'Nakes dalam perjalanan', 'command_center' => 'Nakes dalam perjalanan'),
            'VISIT_ARRIVED' => array('warga' => 'Petugas tiba', 'performer' => 'Mulai pemeriksaan', 'responsible_doctor' => 'Nakes tiba di lokasi', 'command_center' => 'Nakes tiba'),
            'VISIT_IN_SERVICE' => array('warga' => 'Pemeriksaan berlangsung', 'performer' => 'Catat hasil pemeriksaan', 'responsible_doctor' => 'Pemeriksaan berlangsung', 'command_center' => 'Pemeriksaan berlangsung'),
            'WAITING_VISIT_RESULT' => array('warga' => 'Menunggu hasil', 'performer' => 'Kirim hasil kunjungan', 'responsible_doctor' => 'Menunggu hasil Nakes', 'command_center' => 'Menunggu hasil'),
            'WAITING_DOCTOR_REVIEW' => array('warga' => 'Hasil sedang ditinjau', 'performer' => 'Hasil sudah dikirim', 'responsible_doctor' => 'Tinjau hasil kunjungan', 'command_center' => 'Menunggu review dokter'),
            'CORRECTION_REQUIRED' => array('warga' => 'Hasil sedang ditinjau', 'performer' => 'Perbaiki hasil kunjungan', 'responsible_doctor' => 'Menunggu perbaikan Nakes', 'command_center' => 'Menunggu tindak lanjut'),
            'WAITING_CLINICAL_FINALIZATION' => array('warga' => 'Menunggu penyelesaian', 'performer' => 'Menunggu dokter', 'responsible_doctor' => 'Finalisasi rekam medis', 'command_center' => 'Rekam medis belum final'),
            'READY_FOR_CLOSURE' => array('warga' => 'Menunggu penutupan', 'performer' => 'Menunggu penutupan', 'responsible_doctor' => 'Tutup konsultasi', 'command_center' => 'Siap ditutup'),
            'COMPLETED' => array('warga' => 'Selesai', 'performer' => 'Selesai', 'responsible_doctor' => 'Selesai', 'command_center' => 'Selesai'),
        );
        $actor = isset($labels[$state][$actorType]) ? $actorType : 'command_center';
        return isset($labels[$state][$actor]) ? $labels[$state][$actor] : (string) $state;
    }

    public function toneFor($state)
    {
        if (in_array($state, array('COMPLETED', 'READY_FOR_CLOSURE'), true)) {
            return 'success';
        }
        if (in_array($state, array('WAITING_DOCTOR_DISPOSITION', 'WAITING_PERFORMER_ASSIGNMENT', 'WAITING_DOCTOR_REVIEW', 'CORRECTION_REQUIRED', 'WAITING_CLINICAL_FINALIZATION'), true)) {
            return 'warning';
        }
        if (in_array($state, array('VISIT_NOT_STARTED', 'VISIT_EN_ROUTE', 'VISIT_ARRIVED', 'VISIT_IN_SERVICE', 'WAITING_VISIT_RESULT'), true)) {
            return 'info';
        }
        return 'neutral';
    }

    public function ownerFor($state)
    {
        if (in_array($state, array('WAITING_DOCTOR_DISPOSITION', 'WAITING_PERFORMER_ASSIGNMENT', 'WAITING_DOCTOR_REVIEW', 'WAITING_CLINICAL_FINALIZATION', 'READY_FOR_CLOSURE'), true)) {
            return 'responsible_doctor';
        }
        if (in_array($state, array('VISIT_NOT_STARTED', 'VISIT_EN_ROUTE', 'VISIT_ARRIVED', 'VISIT_IN_SERVICE', 'WAITING_VISIT_RESULT', 'CORRECTION_REQUIRED'), true)) {
            return 'performer';
        }
        return null;
    }

    public function nextActionFor($state, $actorType, $consultationMode = '')
    {
        $skippedDisposition = $state === 'WAITING_DOCTOR_DISPOSITION'
            && strtolower(trim((string) $consultationMode)) === 'non_visit';
        if ($skippedDisposition && $actorType === 'responsible_doctor') {
            return 'Selesaikan konsultasi';
        }
        $actions = array(
            'WAITING_DOCTOR_DISPOSITION' => 'Tetapkan disposisi (opsional)',
            'WAITING_PERFORMER_ASSIGNMENT' => 'Pilih Nakes pelaksana',
            'VISIT_NOT_STARTED' => 'Mulai perjalanan dari halaman tugas',
            'VISIT_EN_ROUTE' => 'Konfirmasi tiba di lokasi',
            'VISIT_ARRIVED' => 'Mulai pemeriksaan',
            'VISIT_IN_SERVICE' => 'Catat hasil pemeriksaan',
            'WAITING_VISIT_RESULT' => 'Kirim hasil kunjungan',
            'WAITING_DOCTOR_REVIEW' => 'Tinjau hasil kunjungan',
            'CORRECTION_REQUIRED' => 'Perbaiki hasil kunjungan',
            'WAITING_CLINICAL_FINALIZATION' => 'Finalisasi rekam medis',
            'READY_FOR_CLOSURE' => 'Tutup konsultasi',
            'COMPLETED' => 'Lihat rekam medis',
        );
        if ($actorType === 'warga') {
            return $state === 'COMPLETED' ? 'Lihat hasil konsultasi' : 'Pantau status';
        }
        if ($actorType === 'command_center') {
            return $state === 'COMPLETED' ? 'Lihat rekam medis' : 'Pantau layanan';
        }
        return isset($actions[$state]) ? $actions[$state] : '';
    }

    public function nextActionUrlFor($state, $actorType = null, $consultationMode = '')
    {
        if ($actorType === 'warga') {
            return '#status-konsultasi';
        }
        if ($state === 'WAITING_DOCTOR_DISPOSITION'
            && strtolower(trim((string) $consultationMode)) === 'non_visit') {
            return $actorType === 'responsible_doctor' ? '#form_konsul_nakes' : '#status-konsultasi';
        }
        $anchors = array(
            'WAITING_DOCTOR_DISPOSITION' => '#disposition',
            'WAITING_PERFORMER_ASSIGNMENT' => '#penugasan',
            'VISIT_NOT_STARTED' => '#kunjungan',
            'VISIT_EN_ROUTE' => '#kunjungan',
            'VISIT_ARRIVED' => '#kunjungan',
            'VISIT_IN_SERVICE' => '#kunjungan',
            'WAITING_VISIT_RESULT' => '#hasil-kunjungan',
            'WAITING_DOCTOR_REVIEW' => '#review-hasil',
            'CORRECTION_REQUIRED' => '#hasil-kunjungan',
            'WAITING_CLINICAL_FINALIZATION' => '#rekam-medis',
            'READY_FOR_CLOSURE' => '#penutupan',
            'COMPLETED' => '#rekam-medis',
        );
        return isset($anchors[$state]) ? $anchors[$state] : '#alur-klinis';
    }

    public function stepKeyFor($state)
    {
        $keys = array(
            'WAITING_DOCTOR_DISPOSITION' => 'disposition',
            'WAITING_PERFORMER_ASSIGNMENT' => 'performer',
            'VISIT_NOT_STARTED' => 'visit',
            'VISIT_EN_ROUTE' => 'visit',
            'VISIT_ARRIVED' => 'visit',
            'VISIT_IN_SERVICE' => 'visit',
            'WAITING_VISIT_RESULT' => 'result',
            'WAITING_DOCTOR_REVIEW' => 'review',
            'CORRECTION_REQUIRED' => 'result',
            'WAITING_CLINICAL_FINALIZATION' => 'record',
            'READY_FOR_CLOSURE' => 'closure',
            'COMPLETED' => 'closure',
        );
        return isset($keys[$state]) ? $keys[$state] : '';
    }
}
