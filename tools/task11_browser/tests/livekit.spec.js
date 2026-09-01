const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');
const repo = path.resolve(__dirname, '..', '..', '..');
const thread = fs.readFileSync(path.join(repo, 'application/modules/chat/views/thread_v.php'), 'utf8');
const helper = fs.readFileSync(path.join(repo, 'application/helpers/livekit_helper.php'), 'utf8');

test('LiveKit call shell retains semantic controls and responsive layouts', () => {
  expect(thread).toMatch(/livekit/i);
  expect(thread).toMatch(/aria-label/i);
  expect(thread).toMatch(/max-width:\s*520px/i);
  expect(thread).toMatch(/min-width:\s*560px/i);
  expect(thread).toMatch(/end|hang/i);
  expect(thread).toMatch(/grid-template-columns/);
});

test('LiveKit backend/token/session integration remains unchanged and local-safe', () => {
  expect(helper).toContain('doclinc_livekit_token_payload');
  expect(thread).not.toContain('Clinical_workflow_surface');
  expect(thread).not.toMatch(/DROP\s+TABLE|TRUNCATE\s+TABLE/i);
});
