const { execFileSync } = require('child_process');

module.exports = async function globalTeardown() {
  const container = process.env.TASK11_WEB_CONTAINER;
  const runId = process.env.TASK11_RUN_ID;
  if (!container || !runId) throw new Error('task11_cleanup_environment_missing');
  const webRoot = process.env.TASK11_WEB_ROOT || '/var/www';
  execFileSync('docker', [
    'exec', '-e', `TASK11_RUN_ID=${runId}`, container,
    'php', `${webRoot}/tools/task11_browser/tests/cleanup_run.php`,
  ], { stdio: 'inherit' });
};
