const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../../..');
const controller = fs.readFileSync(path.join(root, 'admin_menu/application/modules/master_puskesmas/controllers/Master_puskesmas.php'), 'utf8');
const model = fs.readFileSync(path.join(root, 'admin_menu/application/modules/master_puskesmas/models/Master_puskesmas_m.php'), 'utf8');
const view = fs.readFileSync(path.join(root, 'admin_menu/application/modules/master_puskesmas/views/master_puskesmas_v.php'), 'utf8');

let passed = 0;
let failed = 0;
function expect(condition, label) {
    if (condition) {
        passed += 1;
        process.stdout.write(`PASS ${label}\n`);
    } else {
        failed += 1;
        process.stdout.write(`FAIL ${label}\n`);
    }
}

expect(controller.includes("userdata('level') !== 'admin'"), 'management_remains_admin_only');
expect(controller.includes("set_rules('alamat'") && controller.includes("set_rules('latitude'") && controller.includes("set_rules('longitude'"), 'facility_readiness_fields_required_server_side');
expect(controller.includes('$latitude >= -90') && controller.includes('$longitude >= -180'), 'coordinate_ranges_validated_server_side');
expect(controller.includes("$status === 'aktif' && !$this->Master_puskesmas_m->is_operationally_complete($kode)"), 'incomplete_facility_cannot_be_enabled');
expect((controller.match(/\(\$data\['status'\] \?\? ''\) === 'aktif' && !empty\(\$this->Master_puskesmas_m->readiness_issues\(\(object\) \$data\)\)/g) || []).length === 2, 'create_and_update_cannot_bypass_active_readiness');
expect(model.includes('function is_operationally_complete') && model.includes("select('kode_pkm, nama_puskesmas, alamat, latitude, longitude')"), 'activation_rechecks_persisted_facility');
expect(model.includes('function readiness_issues') && model.includes('function readiness_summary') && model.includes("'facility_address'"), 'facility_remediation_queue_uses_safe_issue_codes');
expect(controller.includes("'puskesmas_readiness_issues'") && controller.includes("'puskesmas_readiness_summary'"), 'admin_controller_projects_actionable_facility_readiness');
expect(view.includes('puskesmasReadinessFilter') && view.includes('data-puskesmas-readiness') && view.includes('Kesiapan Puskesmas aktif'), 'admin_view_filters_ready_and_incomplete_facilities');
expect(view.includes("$status === 'aktif' ? (empty($readiness_issues) ? 'ready' : 'attention') : 'inactive'"), 'inactive_facility_excluded_from_readiness_filter');
expect(view.includes('Administrator Dinas Kesehatan') && !view.includes('bulk-autofill'), 'facility_remediation_remains_explicit_admin_work');
expect((view.match(/name="alamat"[^>]*required/g) || []).length === 2, 'address_required_create_and_update');
expect((view.match(/name="latitude" step="any" min="-90" max="90"/g) || []).length === 2
    && (view.match(/name="latitude"[^\n]*required>/g) || []).length === 2, 'latitude_bounded_create_and_update');
expect((view.match(/name="longitude" step="any" min="-180" max="180"/g) || []).length === 2
    && (view.match(/name="longitude"[^\n]*required>/g) || []).length === 2, 'longitude_bounded_create_and_update');

process.stdout.write(`PUSKESMAS_ADMIN_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`PUSKESMAS_ADMIN_SOURCE_FAILED=${failed}\n`);
process.exit(failed > 0 ? 1 : 0);
