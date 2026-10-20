'use strict';

/**
 * Locks the rule that every declared route resolves to a real controller and
 * method.
 *
 * The staging mirror exposes seven roles and every route was found to resolve,
 * but nothing in the suite asserted that. A route pointing at a renamed or
 * removed method fails only when a user happens to hit it, which is exactly the
 * kind of latent gap this plan exists to close.
 */

const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '../../..');
let passed = 0;
let failed = 0;
function expect(condition, name) {
  if (condition) { passed += 1; process.stdout.write(`PASS ${name}\n`); }
  else { failed += 1; process.stdout.write(`FAIL ${name}\n`); }
}

/** Indexes every controller file by its lower-cased class/file name. */
function controllerIndex(baseDir) {
  const index = new Map();
  const controllersDir = path.join(baseDir, 'controllers');
  if (fs.existsSync(controllersDir)) {
    for (const name of fs.readdirSync(controllersDir)) {
      if (name.endsWith('.php')) index.set(name.slice(0, -4).toLowerCase(), path.join(controllersDir, name));
    }
  }
  const modulesDir = path.join(baseDir, 'modules');
  if (fs.existsSync(modulesDir)) {
    for (const moduleName of fs.readdirSync(modulesDir)) {
      const moduleControllers = path.join(modulesDir, moduleName, 'controllers');
      if (!fs.existsSync(moduleControllers)) continue;
      for (const name of fs.readdirSync(moduleControllers)) {
        if (!name.endsWith('.php')) continue;
        const key = name.slice(0, -4).toLowerCase();
        if (!index.has(key)) index.set(key, path.join(moduleControllers, name));
      }
    }
  }
  return index;
}

/** Removes PHP comments so the commented CI3 route template is not parsed. */
function stripComments(source) {
  return source
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/(^|[^:])\/\/[^\n]*/g, '$1');
}

/**
 * Resolves a route target. CI3 accepts both controller/method and
 * directory/controller/method, so a three-part target shifts the method along.
 */
function targetParts(target) {
  const parts = target.split('/').filter(part => part !== '');
  if (parts.length < 2) return null;
  if (parts.length === 2) return { controller: parts[0], method: parts[1] };
  return { controller: parts[1], method: parts[2] };
}

function checkRoutes(label, baseDir) {
  const routesFile = path.join(baseDir, 'config/routes.php');
  if (!fs.existsSync(routesFile)) return { resolved: 0, unresolved: [], declared: 0 };
  const source = stripComments(fs.readFileSync(routesFile, 'utf8'));
  const index = controllerIndex(baseDir);
  const unresolved = [];
  let resolved = 0;
  let declared = 0;
  const pattern = /\$route\[[^\]]+\]\s*=\s*'([^']+)'\s*;/g;
  for (const match of source.matchAll(pattern)) {
    const target = match[1].trim();
    if (target === '') continue; // explicit empty 404_override
    const parsed = targetParts(target);
    if (!parsed) continue;
    declared += 1;
    const controller = parsed.controller.toLowerCase();
    const method = parsed.method.replace(/\$[0-9]+/g, '').trim();
    if (method === '') continue;
    const file = index.get(controller);
    if (!file) { unresolved.push(`${label}: ${target} -> controller ${parsed.controller} not found`); continue; }
    const controllerSource = fs.readFileSync(file, 'utf8');
    if (!new RegExp('function\\s+' + method + '\\s*\\(', 'i').test(controllerSource)) {
      unresolved.push(`${label}: ${target} -> method ${method}() not found`);
      continue;
    }
    resolved += 1;
  }
  return { resolved, unresolved, declared };
}

const main = checkRoutes('app', path.join(root, 'application'));
expect(main.declared >= 15, 'main_app_routes_declared');
expect(main.resolved >= 15, 'main_app_routes_resolved');
expect(main.unresolved.length === 0, 'every_main_app_route_resolves');
for (const entry of main.unresolved.slice(0, 15)) process.stdout.write('  unresolved: ' + entry + '\n');

const admin = checkRoutes('admin', path.join(root, 'admin_menu/application'));
expect(admin.unresolved.length === 0, 'every_admin_app_route_resolves');
for (const entry of admin.unresolved.slice(0, 15)) process.stdout.write('  unresolved: ' + entry + '\n');

// The clinical routes the managed workflow depends on are declared explicitly and
// must keep resolving to their handlers. The visit-performer and service-mode
// endpoints are default-routed, so they are asserted against the controller
// instead of routes.php.
const routes = fs.readFileSync(path.join(root, 'application/config/routes.php'), 'utf8');
const clinicalTargets = {
  'clinical/disposition': 'home_nakes/clinical_disposition',
  'clinical/review': 'home_nakes/clinical_review',
  'clinical/finalize': 'home_nakes/clinical_finalize',
  'clinical/amend': 'home_nakes/clinical_amend',
  'clinical/close': 'home_nakes/clinical_close',
  'clinical/record': 'home_nakes/clinical_record',
};
for (const [pattern, target] of Object.entries(clinicalTargets)) {
  expect(routes.includes(`$route['${pattern}'] = '${target}';`), `clinical_route_${pattern.replace(/\//g, '_')}_declared`);
}
const homeNakes = fs.readFileSync(path.join(root, 'application/modules/home_nakes/controllers/Home_nakes.php'), 'utf8');
for (const method of ['assign_visit_performer', 'choose_service_mode', 'clinical_disposition']) {
  expect(new RegExp('function\\s+' + method + '\\s*\\(', 'i').test(homeNakes), `handler_${method}_present`);
}

process.stdout.write(`PLATFORM_PARITY_ROUTE_RESOLUTION_PASSED=${passed}\nPLATFORM_PARITY_ROUTE_RESOLUTION_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
