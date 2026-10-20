const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const repoRoot = path.resolve(__dirname, '..', '..', '..');
const view = fs.readFileSync(path.join(repoRoot, 'application/modules/chat/views/thread_v.php'), 'utf8');
const homeController = fs.readFileSync(path.join(repoRoot, 'application/modules/home/controllers/Home.php'), 'utf8');
const callModel = fs.readFileSync(path.join(repoRoot, 'application/modules/chat/models/Call_session_m.php'), 'utf8');

test('Warga incoming-call surface exposes truthful caller identity and accept/decline actions', () => {
  expect(view).toContain('id="doclincIncomingCall"');
  expect(view).toContain('PANGGILAN MASUK');
  expect(view).toContain('id="doclincIncomingCaller"');
  expect(view).toContain('id="doclincIncomingType"');
  expect(view).toContain('id="doclincIncomingReject"');
  expect(view).toContain('Tolak Panggilan');
  expect(view).toContain('id="doclincIncomingAnswer"');
  expect(view).toContain('aria-label="Terima panggilan"');
  expect(view).toContain("callType === 'audio' ? 'Panggilan suara' : 'Panggilan video'");
  expect(view).toContain('call.caller_name || \'Nakes DokLinc\'');
});

test('Warga incoming-call UI is backed by request-bound server contracts', () => {
  expect(view).toContain("const canReceiveCall = <?= json_encode($can_receive_call); ?>;");
  expect(view).toContain('incomingCallUrl + \'?request_id=\'');
  expect(view).toContain('postForm(answerCallUrl');
  expect(view).toContain('postForm(rejectCallUrl');

  expect(homeController).toContain("$this->session->userdata('role') !== 'warga'");
  expect(homeController).toContain('get_latest_incoming_for_warga($user_id, $request_id, false)');
  expect(homeController).toContain("$request->request_status !== 'Accepted'");
  expect(homeController).toContain("$request->user_id !== (string) $user_id");

  expect(callModel).toContain("->where('call_sessions.callee_user_id', $user_id)");
  expect(callModel).toContain("->where('requests.user_id', $user_id)");
  expect(callModel).toContain("->where('requests.request_status', 'Accepted')");
  expect(callModel).toContain("->where('call_sessions.status', 'ringing')");
  expect(callModel).toContain('users.nama AS caller_name');
});

test('incoming-call copy remains Indonesian and does not expose internal identifiers', async ({ page }) => {
  await page.setContent(`
    <section id="doclincIncomingCall" aria-live="polite">
      <p>PANGGILAN MASUK</p><p id="doclincIncomingCaller">dr. Sintetik</p>
      <p id="doclincIncomingType">Panggilan video</p>
      <button id="doclincIncomingReject">Tolak Panggilan</button>
      <button id="doclincIncomingAnswer" aria-label="Terima panggilan">Terima</button>
    </section>`);
  await expect(page.getByText('PANGGILAN MASUK')).toBeVisible();
  await expect(page.locator('#doclincIncomingCaller')).toHaveText('dr. Sintetik');
  await expect(page.locator('#doclincIncomingType')).toHaveText('Panggilan video');
  await expect(page.getByRole('button', { name: 'Tolak Panggilan' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Terima panggilan' })).toBeVisible();
  await expect(page.locator('body')).not.toContainText('request_id');
  await expect(page.locator('body')).not.toContainText('room_name');
});
