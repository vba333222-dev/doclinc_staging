<?php defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('clinical_workflow_asset')) {
    function clinical_workflow_asset($semantic)
    {
        $registry = array(
            'livekit.participants' => 'participant.svg',
            'livekit.end_call' => 'voicecall.svg',
            'livekit.hangup' => 'voicecall.svg',
            'livekit.video' => 'videocall.svg',
            'ui.close' => 'close.svg',
        );
        if (!isset($registry[$semantic])) return '';
        return base_url('assets/doclinc_ui/clinical_workflow/' . $registry[$semantic]);
    }
}
