const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const iconRoot = path.join(repoRoot, 'assets', 'doclinc_ui', 'icon-pack');
const helperPath = path.join(repoRoot, 'application', 'helpers', 'clinical_workflow_assets_helper.php');

const expected = {
  home: 'home.svg',
  consultation: 'konsultasi.svg',
  examination: 'pemeriksaan.svg',
  prescription: 'resep.svg',
  nakes: 'nakes.svg',
  operations: 'operasional.svg',
  map_nakes: 'mapnakes.svg',
  attachment: 'lampiran.svg',
  camera: 'kamera.svg',
  video_call: 'videocall.svg',
  voice_call: 'voicecall.svg',
  mute: 'mute.svg',
  speaker: 'loudspeaker.svg',
  chat: 'chat.svg',
  send: 'send.svg',
  more: 'more.svg',
  'livekit.minimize': 'minimize.svg',
  'livekit.participants': 'participant.svg',
  'livekit.close': 'close.svg',
  'livekit.end_call': 'endcall.svg',
};

test('canonical icon pack resolves every approved semantic key', () => {
  const helper = fs.readFileSync(helperPath, 'utf8');
  for (const [semantic, filename] of Object.entries(expected)) {
    expect(helper, semantic).toContain(`'${semantic}' => '${filename}'`);
    expect(fs.existsSync(path.join(iconRoot, filename)), filename).toBeTruthy();
  }
});

test('reserved LiveKit icons are not mapped to non-LiveKit semantics', () => {
  const helper = fs.readFileSync(helperPath, 'utf8');
  expect(helper).not.toMatch(/['"](?:participant|close)\.svg['"][^\n]*['"](?:home|profile|user|modal|end_call)['"]/i);
});
