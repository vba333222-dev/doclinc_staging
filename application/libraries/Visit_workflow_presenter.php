<?php
defined('BASEPATH') or exit('No direct script access allowed');
class Visit_workflow_presenter
{
    public function present(array $state, $actorType)
    {
        $actor = in_array($actorType, array('warga','performer','responsible_doctor','command_center'), true) ? $actorType : 'warga';
        $machine = (string)($state['state'] ?? '');
        $labels = array(
            'WAITING_DOCTOR_REVIEW'=>array('warga'=>'Hasil kunjungan sedang ditinjau','performer'=>'Hasil telah dikirim','responsible_doctor'=>'Menunggu review Anda','command_center'=>'Menunggu review dokter'),
            'CORRECTION_REQUIRED'=>array('warga'=>'Hasil kunjungan sedang ditinjau','performer'=>'Perlu perbaikan hasil kunjungan','responsible_doctor'=>'Menunggu perbaikan hasil kunjungan','command_center'=>'Menunggu tindak lanjut klinis'),
        );
        $visibleMachine = ($actor === 'warga' && $machine === 'CORRECTION_REQUIRED') ? 'WAITING_DOCTOR_REVIEW' : $machine;
        return array('request_id'=>(int)($state['request_id']??0),'state'=>$visibleMachine,'label'=>$labels[$machine][$actor] ?? $machine,'correction_reason'=>null);
    }
}
