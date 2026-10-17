const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
function read(relative) { return fs.readFileSync(path.join(repoRoot, relative), 'utf8'); }

test('primary navigation uses semantic icon registry', () => {
  const nav = read('application/modules/home_nakes/views/partials/nakes_bottom_nav_v.php');
  expect(nav).toContain("doclinc_ui_asset('home')");
  expect(nav).toContain("doclinc_ui_asset('nakes')");
  expect(nav).toContain("doclinc_ui_asset('operations')");
});

test('chat actions use canonical semantic assets and accessible names', () => {
  // The legacy chat.php view was consolidated into thread_v.php; assert the
  // canonical semantics against the view that actually renders the composer.
  const chat = read('application/modules/chat/views/thread_v.php');
  for (const semantic of ['video_call', 'voice_call', 'attachment', 'camera', 'send']) {
    expect(chat, semantic).toContain(`doclinc_ui_asset('${semantic}')`);
  }
  expect(chat).toMatch(/aria-label="[^"]+"/);
});

test('clinical chat composer uses canonical camera, attachment, and send assets', () => {
  const thread = read('application/modules/chat/views/thread_v.php');
  for (const semantic of ['camera', 'attachment', 'send']) {
    expect(thread, semantic).toContain(`doclinc_ui_asset('${semantic}')`);
  }
});

test('chat call entry and active-call affordance use canonical call semantics', () => {
  const thread = read('application/modules/chat/views/thread_v.php');
  expect(thread).toContain("doclinc_ui_asset('voice_call')");
  expect(thread).toContain("doclinc_ui_asset('video_call')");
  expect(thread).not.toMatch(/<svg[^>]+class=\"doclinc-call-start/);
});

test('LiveKit shell stays scoped and keeps destructive end-call distinct from close', () => {
  const view = read('application/modules/chat/views/thread_v.php');
  const css = read('assets/css/doclinc-livekit.css');
  expect(view).toContain('doclinc-livekit.css');
  expect(view).toContain("doclinc_ui_asset('livekit.close')");
  expect(view).toContain("doclinc_ui_asset('voice_call')");
  expect(css).toContain('.doclinc-call-panel');
  expect(css).toContain('@media');
});

test('reserved icon filenames are confined to the LiveKit shell', () => {
  const roots = [
    path.join(repoRoot, 'application'),
    path.join(repoRoot, 'assets', 'css'),
    path.join(repoRoot, 'assets', 'js'),
  ];
  const files = [];
  function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const absolute = path.join(dir, entry.name);
      if (entry.isDirectory()) walk(absolute);
      else if (/\.(php|js|css|html)$/i.test(entry.name)) files.push(absolute);
    }
  }
  roots.forEach(walk);
  const refs = files.flatMap((file) => {
    const text = fs.readFileSync(file, 'utf8');
    return [...text.matchAll(/(?:participant|close)\.svg/gi)].map(() => path.relative(repoRoot, file).replace(/\\/g, '/'));
  });
  const nonLiveKit = refs.filter((file) => !file.endsWith('application/helpers/clinical_workflow_assets_helper.php') && !file.endsWith('application/modules/chat/views/thread_v.php'));
  expect(nonLiveKit).toEqual([]);
});

test('runtime never references the authoring-only asset directory', () => {
  const roots = [path.join(repoRoot, 'application'), path.join(repoRoot, 'assets'), path.join(repoRoot, 'tools')];
  const forbidden = /C:\\\\Project\\\\doclinc-assets|doclinc-assets[\\/]/i;
  const hits = [];
  function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
      const absolute = path.join(dir, entry.name);
      // This asserts that *runtime* never cites the authoring-only directory.
      // Test specs legitimately cite it as a visual baseline fixture, and
      // node_modules/test-results are build artifacts, so none are scanned.
      if (entry.isDirectory() && !['node_modules', 'test-results', 'tests'].includes(entry.name)) walk(absolute);
      else if (/\.(php|js|css|json|md)$/i.test(entry.name) && forbidden.test(fs.readFileSync(absolute, 'utf8'))) hits.push(path.relative(repoRoot, absolute));
    }
  }
  roots.forEach(walk);
  expect(hits).toEqual([]);
});
