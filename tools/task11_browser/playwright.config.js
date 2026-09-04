const { defineConfig, devices } = require('@playwright/test');

function validateBaseUrl(value) {
  let parsed;
  try { parsed = new URL(value); } catch (error) { throw new Error('TASK11_BASE_URL must be a valid loopback URL'); }
  if (parsed.protocol !== 'http:' || parsed.hostname !== '127.0.0.1' || !parsed.port || parsed.username || parsed.password) {
    throw new Error('TASK11_BASE_URL must use http://127.0.0.1 with an explicit port');
  }
  const port = Number(parsed.port);
  if (!Number.isInteger(port) || port < 1 || port > 65535) throw new Error('TASK11_BASE_URL port is invalid');
  return parsed.toString().replace(/\/$/, '');
}

const baseURL = validateBaseUrl(process.env.TASK11_BASE_URL || 'http://127.0.0.1:18090');

module.exports = defineConfig({
  testDir: './tests',
  timeout: 120000,
  workers: 1,
  expect: { timeout: 5000 },
  reporter: process.env.CI ? [['line'], ['html', { outputFolder: 'playwright-report', open: 'never' }]] : [['list']],
  use: {
    baseURL,
    ignoreHTTPSErrors: false,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    { name: 'chromium-desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'chromium-tablet', use: { ...devices['iPad Mini'], browserName: 'chromium' } },
    { name: 'chromium-mobile', use: { ...devices['Pixel 5'] } },
  ],
});

module.exports.validateBaseUrl = validateBaseUrl;
