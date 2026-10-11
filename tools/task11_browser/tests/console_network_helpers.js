function installBrowserHygiene(page, expectedFailure = () => false) {
  const errors = [];
  const failures = [];
  page.on('console', msg => { if (msg.type() === 'error') errors.push(msg.text()); });
  page.on('pageerror', error => errors.push(error.message));
  page.on('requestfailed', request => { if (!expectedFailure(request.url())) failures.push(request.url()); });
  page.on('response', response => {
    if (response.status() >= 500 && !expectedFailure(response.url())) failures.push(`${response.status()} ${response.url()}`);
  });
  return { errors, failures };
}

module.exports = { installBrowserHygiene };
