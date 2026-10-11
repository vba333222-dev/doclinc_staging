function installBrowserHygiene(page, expectedFailure = () => false) {
  const errors = []; const failures = [];
  page.on('console', msg => { if (msg.type() === 'error' && !expectedFailure(msg.text())) errors.push(msg.text()); });
  page.on('pageerror', error => { if (!expectedFailure(error.message)) errors.push(error.message); });
  page.on('requestfailed', request => { if (!expectedFailure(request.url())) failures.push(request.url()); });
  page.on('response', response => { if (response.status() >= 400 && !expectedFailure(response.url())) failures.push(`${response.status()} ${response.url()}`); });
  return {
    errors,
    failures,
    assertClean() {
      if (errors.length || failures.length) throw new Error(`browser_hygiene_errors=${JSON.stringify({ errors, failures })}`);
    },
  };
}
module.exports = { installBrowserHygiene };
