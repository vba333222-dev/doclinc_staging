'use strict';

/**
 * Locks the feature-flag resolution contract.
 *
 * Doclinc_feature_flags::resolve() only enables a feature when the runtime
 * environment is one of staging/uat AND equals the feature's own environment.
 * Any flag that is given an enabled value but no environment - or whose runtime
 * environment variable is unset - resolves to flag_missing_or_malformed and is
 * silently disabled.
 *
 * That is how the staging mirror lost care_team_workflow, nakes_presence,
 * puskesmas_operations and role_prerequisites (unset
 * DOCLINC_REALTIME_CLIENT_RUNTIME_ENVIRONMENT), and later admin capabilities and
 * clinical audit (Admin_capability_flags reads DOCLINC_RUNTIME_ENVIRONMENT, not
 * the realtime one), and visit proof (gated by DOCLINC_VISIT_PROOF_REQUIRED
 * rather than an _ENABLED suffix). This suite locks every one of those rules.
 */

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
let passed = 0;
let failed = 0;
function expect(condition, name) {
  if (condition) { passed += 1; process.stdout.write(`PASS ${name}\n`); }
  else { failed += 1; process.stdout.write(`FAIL ${name}\n`); }
}

function phpFiles(dir) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (entry.name === 'node_modules' || entry.name === '.git') continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) out.push(...phpFiles(full));
    else if (entry.name.endsWith('.php')) out.push(full);
  }
  return out;
}

/** Returns the argument text of every Doclinc_feature_flags::resolve(...) call. */
function resolveCallArguments(source) {
  const calls = [];
  const needle = 'Doclinc_feature_flags::resolve(';
  let index = source.indexOf(needle);
  while (index >= 0) {
    let depth = 0;
    let cursor = index + needle.length - 1;
    for (; cursor < source.length; cursor += 1) {
      if (source[cursor] === '(') depth += 1;
      else if (source[cursor] === ')') {
        depth -= 1;
        if (depth === 0) break;
      }
    }
    calls.push(source.slice(index + needle.length, cursor));
    index = source.indexOf(needle, cursor);
  }
  return calls;
}

const flags = read('application/libraries/Doclinc_feature_flags.php');
const config = read('application/config/config.php');
const adminFlags = read('application/libraries/Admin_capability_flags.php');

// 1. The resolver itself must gate on the runtime environment.
expect(flags.includes("array('staging', 'uat')"), 'resolver_restricts_runtime_environments');
expect(flags.includes("'enabled' => false") && flags.includes("'reason' => 'disabled'"),
  'resolver_defaults_to_disabled');
expect(flags.includes('hash_equals($feature_environment, $runtime_environment)')
  && flags.includes("'environment_mismatch'"), 'resolver_requires_matching_environments');
expect(flags.includes("'runtime_environment_not_allowed'")
  && flags.includes("'feature_environment_not_allowed'"),
  'resolver_reports_environment_rejections');
expect(flags.includes("flag_missing_or_malformed"), 'missing_or_malformed_flags_are_reported');

// 2. Every resolve() call site must supply a feature environment, otherwise the
//    feature can never enable no matter what the deployment sets.
const callSites = [];
for (const file of phpFiles(path.join(root, 'application'))) {
  const relative = path.relative(root, file).split(path.sep).join('/');
  for (const args of resolveCallArguments(read(relative))) {
    callSites.push({ relative, args: args.replace(/\s+/g, ' ') });
  }
}
expect(callSites.length >= 5, 'feature_resolve_call_sites_discovered');
const withoutEnvironment = callSites.filter(site => !/_ENVIRONMENT|_environment/.test(site.args));
expect(withoutEnvironment.length === 0,
  'every_feature_resolve_call_supplies_an_environment');
if (withoutEnvironment.length) {
  process.stdout.write('  offending: ' + withoutEnvironment.map(s => s.relative).join(', ') + '\n');
}
const withoutRuntime = callSites.filter(site => !/runtime_environment|runtimeEnvironment/i.test(site.args));
expect(withoutRuntime.length === 0, 'every_feature_resolve_call_supplies_a_runtime_environment');
if (withoutRuntime.length) {
  process.stdout.write('  offending: ' + withoutRuntime.map(s => s.relative).join(', ') + '\n');
}

// The runtime environment must be the third argument, so a call cannot pass the
// feature environment twice and appear correct.
function topLevelArguments(text) {
  const parts = [];
  let depth = 0;
  let current = '';
  for (const character of text) {
    if (character === '(' || character === '[') depth += 1;
    if (character === ')' || character === ']') depth -= 1;
    if (character === ',' && depth === 0) { parts.push(current); current = ''; continue; }
    current += character;
  }
  parts.push(current);
  return parts.map(part => part.trim());
}
const wrongArity = callSites.filter(site => topLevelArguments(site.args).length !== 3);
expect(wrongArity.length === 0, 'every_feature_resolve_call_passes_three_arguments');
const missingRuntimeArgument = callSites.filter(site => {
  const args = topLevelArguments(site.args);
  return args.length !== 3 || !/runtime_environment|runtimeEnvironment/i.test(args[2]);
});
expect(missingRuntimeArgument.length === 0, 'runtime_environment_is_the_third_argument');


// 3. config.php must resolve every feature against one shared runtime environment
//    variable, so no family can be forgotten.
expect(config.includes("getenv('DOCLINC_REALTIME_CLIENT_RUNTIME_ENVIRONMENT')"),
  'config_reads_the_shared_runtime_environment');
const runtimeUses = (config.match(/\$realtime_client_runtime_environment_env/g) || []).length;
expect(runtimeUses >= 6, 'shared_runtime_environment_reused_by_every_family');

// 4. The admin capability flags read their own runtime environment variable; it
//    is not the realtime one, so it must be read explicitly.
expect(adminFlags.includes("getenv('DOCLINC_RUNTIME_ENVIRONMENT')"),
  'admin_capability_flags_read_their_own_runtime_environment');
expect(adminFlags.includes("DOCLINC_ADMIN_CAPABILITIES_ENABLED")
  && adminFlags.includes("DOCLINC_ADMIN_CAPABILITIES_ENVIRONMENT"),
  'admin_capabilities_flag_pair_present');
expect(adminFlags.includes("DOCLINC_CLINICAL_AUDIT_ENABLED")
  && adminFlags.includes("DOCLINC_CLINICAL_AUDIT_ENVIRONMENT"),
  'clinical_audit_flag_pair_present');

// 5. Visit proof is gated by DOCLINC_VISIT_PROOF_REQUIRED, not an _ENABLED suffix.
expect(config.includes("getenv('DOCLINC_VISIT_PROOF_REQUIRED')"),
  'visit_proof_reads_its_required_variable');
expect(config.includes("getenv('DOCLINC_VISIT_PROOF_ENVIRONMENT')"),
  'visit_proof_reads_its_environment_variable');
expect(config.includes("$visit_proof_required_env,") && config.includes("$visit_proof_environment_env,"),
  'visit_proof_passes_both_values_to_resolver');

process.stdout.write(`PLATFORM_PARITY_FEATURE_FLAG_MATRIX_PASSED=${passed}\nPLATFORM_PARITY_FEATURE_FLAG_MATRIX_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
