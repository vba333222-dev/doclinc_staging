'use strict';

/**
 * Locks the rule that every table the managed workflow depends on is created by a
 * migration in this repository.
 *
 * The parity audit found four tables absent from the live schema
 * (admin_user_capabilities, clinical_access_audit, nakes_facility_placements,
 * nakes_facility_transfers) plus three that had no migration at all (fcm_tokens,
 * locations, tbl_riwayat). Every consumer wraps the query in table_exists(), so
 * the features vanished instead of raising an error - the visit_dispositions
 * class of silent 500.
 *
 * This suite asserts the migration set covers those tables, and that the code
 * which depends on them still guards with table_exists() rather than assuming
 * presence.
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

const migrationsDir = path.join(root, 'application/migrations');
const migrationFiles = fs.readdirSync(migrationsDir).filter(name => name.endsWith('.php'));
expect(migrationFiles.length >= 20, 'migration_set_present');

const migrationParts = [];
for (const migrationName of migrationFiles) {
  migrationParts.push(read('application/migrations/' + migrationName));
}
const migrationSource = migrationParts.join('\n');
expect(migrationSource.length > 1000, 'migration_sources_read');

/** True when some migration creates the given table. */
function createsTable(table) {
  const pattern = new RegExp('CREATE\\s+TABLE\\s+(?:IF\\s+NOT\\s+EXISTS\\s+)?`?' + table + '`?\\b', 'i');
  return pattern.test(migrationSource);
}

const requiredTables = [
  'admin_user_capabilities',
  'clinical_access_audit',
  'nakes_facility_placements',
  'nakes_facility_transfers',
  'fcm_tokens',
  'locations',
  'tbl_riwayat',
];
for (const table of requiredTables) {
  expect(createsTable(table), `migration_creates_${table}`);
}

// The foundation tables the managed workflow itself needs.
for (const table of ['visit_dispositions', 'request_visit_performer_assignments', 'visit_assignment_operations']) {
  expect(createsTable(table), `migration_creates_${table}`);
}

// The libraries that consume the newly restored tables must stay defensive: a
// missing table has to degrade, not raise. Two guard styles exist in the
// codebase - table_exists() and an information_schema lookup - so each consumer
// is checked against the style it actually uses.
const guardedConsumers = [
  ['application/libraries/Visit_assignment_service.php', 'nakes_facility_placements', 'information_schema'],
  ['application/libraries/Care_team_service.php', 'nakes_facility_placements', 'table_exists'],
  ['application/libraries/Care_team_service.php', 'visit_dispositions', 'table_exists'],
  // The workflow policy is read on the accept path. An unguarded read of a
  // table that some installations never migrated turned "Terima" into a 500
  // with a generic message, so the guard is locked here too.
  ['application/libraries/Visit_workflow_policy.php', 'visit_dispositions', 'table_exists'],
];
for (const [file, table, style] of guardedConsumers) {
  const source = read(file);
  const guarded = style === 'table_exists'
    ? source.includes(`table_exists('${table}')`)
    : source.includes('information_schema.tables') && source.includes(table);
  expect(guarded, `consumer_guards_${table}_in_${path.basename(file, '.php')}`);
}

// The three restored columns-bearing tables must still be created by the legacy
// runtime migration specifically, so a future re-split does not lose them.
const legacy = read('application/migrations/20261012000100_legacy_runtime_tables.php');
for (const table of ['fcm_tokens', 'locations', 'tbl_riwayat']) {
  expect(legacy.includes(table), `legacy_runtime_migration_covers_${table}`);
}

process.stdout.write(`PLATFORM_PARITY_SCHEMA_COVERAGE_PASSED=${passed}\nPLATFORM_PARITY_SCHEMA_COVERAGE_FAILED=${failed}\n`);
process.exit(failed === 0 ? 0 : 1);
