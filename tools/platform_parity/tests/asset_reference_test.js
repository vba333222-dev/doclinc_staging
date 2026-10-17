'use strict';

/**
 * Locks the rule that every referenced local asset exists.
 *
 * The parity audit found the admin header pointing at
 * assets/images/admin-avatar.svg, which did not exist, so every admin page
 * rendered a broken image and relied on the inline onerror fallback. This suite
 * is the regression test for that class of missing asset.
 *
 * Only statically resolvable references are checked: base_url('...') literals
 * and src/href attributes whose value contains no PHP interpolation.
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

function collect(dir, extensions, skip) {
  const out = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    const relative = path.relative(root, full).split(path.sep).join('/');
    if (skip.some(prefix => relative.startsWith(prefix))) continue;
    if (entry.isDirectory()) out.push(...collect(full, extensions, skip));
    else if (extensions.some(extension => entry.name.endsWith(extension))) out.push(full);
  }
  return out;
}

const skip = ['node_modules', '.git', 'assets/vendor', 'admin_menu/system', 'system',
  'application/libraries/dompdf', 'admin_menu/application/libraries/dompdf'];
const roots = ['application', 'admin_menu/application', 'assets/js'].map(dir => path.join(root, dir));
const files = [];
for (const dir of roots) {
  if (fs.existsSync(dir)) files.push(...collect(dir, ['.php', '.js'], skip));
}
expect(files.length > 100, 'asset_reference_scan_sources_discovered');

const references = [];
const baseUrlPattern = /base_url\(\s*(['"])([^'"$<>]+)\1\s*\)/g;
const attributePattern = /(?:src|href)\s*=\s*"([^"<>$]+)"/g;
for (const file of files) {
  const relative = path.relative(root, file).split(path.sep).join('/');
  const source = fs.readFileSync(file, 'utf8');
  for (const match of source.matchAll(baseUrlPattern)) {
    references.push({ file: relative, reference: match[2].trim() });
  }
  for (const match of source.matchAll(attributePattern)) {
    references.push({ file: relative, reference: match[1].trim() });
  }
}

/** Keeps only references that point at a local file inside the repository. */
function localAsset(reference) {
  let value = reference.split('?')[0].split('#')[0].trim();
  if (value === '') return null;
  if (/^(?:[a-z][a-z0-9+.-]*:|\/\/|#)/i.test(value)) return null; // scheme, protocol-relative, anchor
  value = value.replace(/^\.\//, '');
  while (value.startsWith('../')) value = value.slice(3);
  if (value.startsWith('/')) value = value.slice(1);
  if (value.startsWith('admin_menu/')) value = value.slice('admin_menu/'.length);
  if (!/^assets\//.test(value)) return null;
  if (!/\.(?:svg|png|jpe?g|gif|webp|ico|css|js)$/i.test(value)) return null;
  return value;
}

const missing = [];
let checked = 0;
for (const item of references) {
  const asset = localAsset(item.reference);
  if (asset === null) continue;
  checked += 1;
  if (!fs.existsSync(path.join(root, asset))) missing.push(`${item.file} -> ${asset}`);
}
expect(checked >= 20, 'local_asset_references_were_actually_checked');
expect(missing.length === 0, 'every_referenced_local_asset_exists');
if (missing.length) {
  for (const entry of missing.slice(0, 20)) process.stdout.write('  missing: ' + entry + '\n');
}

// The specific asset this plan added must exist and must match the silhouette the
// admin header falls back to.
const avatar = path.join(root, 'assets/images/admin-avatar.svg');
expect(fs.existsSync(avatar), 'admin_avatar_asset_present');
if (fs.existsSync(avatar)) {
  const svg = fs.readFileSync(avatar, 'utf8');
  const header = fs.readFileSync(path.join(root, 'admin_menu/application/views/commons/header.php'), 'utf8');
  expect(header.includes("base_url(\"assets/images/admin-avatar.svg\")"), 'header_references_the_avatar');
  expect(svg.includes('<circle') && svg.includes('<ellipse') && svg.includes('#e5e9f2'),
    'avatar_matches_the_inline_fallback_silhouette');
}

process.stdout.write(`PLATFORM_PARITY_ASSET_REFERENCE_PASSED=${passed}\nPLATFORM_PARITY_ASSET_REFERENCE_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
