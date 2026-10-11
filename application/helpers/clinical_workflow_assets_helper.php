<?php defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('doclinc_ui_asset_registry')) {
    function doclinc_ui_asset_registry()
    {
        return array(
            'home' => 'home.svg',
            'consultation' => 'konsultasi.svg',
            'examination' => 'pemeriksaan.svg',
            'prescription' => 'resep.svg',
            'nakes' => 'nakes.svg',
            'operations' => 'operasional.svg',
            'map_nakes' => 'mapnakes.svg',
            'attachment' => 'lampiran.svg',
            'camera' => 'kamera.svg',
            'video_call' => 'videocall.svg',
            'voice_call' => 'voicecall.svg',
            'mute' => 'mute.svg',
            'speaker' => 'loudspeaker.svg',
            'chat' => 'chat.svg',
            'send' => 'send.svg',
            'more' => 'more.svg',
            'livekit.minimize' => 'minimize.svg',
            'livekit.participants' => 'participant.svg',
            'livekit.close' => 'close.svg',
            'livekit.brand_logo' => 'doklinclogo.svg',
            'livekit.end_call' => 'endcall.svg',
        );
    }

    function doclinc_ui_asset($semantic)
    {
        $registry = doclinc_ui_asset_registry();
        if (!isset($registry[$semantic])) return '';
        return base_url('assets/doclinc_ui/icon-pack/' . $registry[$semantic]);
    }

}

if (!function_exists('clinical_workflow_asset')) {
    function clinical_workflow_asset($semantic)
    {
        $legacy = array(
            'livekit.end_call' => 'livekit.end_call',
            'livekit.hangup' => 'livekit.end_call',
            'livekit.video' => 'video_call',
            'ui.close' => 'livekit.close',
        );
        if (isset($legacy[$semantic])) $semantic = $legacy[$semantic];
        return doclinc_ui_asset($semantic);
    }
}
