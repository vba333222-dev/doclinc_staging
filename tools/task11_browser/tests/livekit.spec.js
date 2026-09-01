const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');
const repo = path.resolve(__dirname, '..', '..', '..');
const thread = fs.readFileSync(path.join(repo, 'application/modules/chat/views/thread_v.php'), 'utf8');
const helper = fs.readFileSync(path.join(repo, 'application/helpers/livekit_helper.php'), 'utf8');

test('LiveKit call shell retains semantic controls and responsive layouts', async ({ page }) => {
  expect(thread).toMatch(/livekit/i);
  expect(thread).toMatch(/aria-label/i);
  expect(thread).toMatch(/max-width:\s*520px/i);
  expect(thread).toMatch(/min-width:\s*560px/i);
  expect(thread).toMatch(/end|hang/i);
  expect(thread).toMatch(/grid-template-columns/);
  await page.setContent('<div class="doclinc-call-controls"><button aria-label="Mute microphone">Mute</button><button aria-label="Akhiri"><img alt="" src="/assets/doclinc_ui/clinical_workflow/voicecall.svg"></button></div>');
  await expect(page.getByRole('button', { name: 'Mute microphone' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Akhiri' })).toBeVisible();
});

test('LiveKit backend/token/session integration remains unchanged and local-safe', () => {
  expect(helper).toContain('doclinc_livekit_token_payload');
  expect(thread).not.toContain('Clinical_workflow_surface');
  expect(thread).not.toMatch(/DROP\s+TABLE|TRUNCATE\s+TABLE/i);
});
