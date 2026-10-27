const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../../..');
const controller = fs.readFileSync(path.join(root, 'admin_menu/application/modules/master_puskesmas/controllers/Master_puskesmas.php'), 'utf8');
const model = fs.readFileSync(path.join(root, 'admin_menu/application/modules/master_puskesmas/models/Master_puskesmas_m.php'), 'utf8');
const view = fs.readFileSync(path.join(root, 'admin_menu/application/modules/master_puskesmas/views/master_puskesmas_v.php'), 'utf8');
/* Sejak `master_puskesmas` hanya mengalihkan ke daftar Puskesmas, layar yang
   menampilkan kesiapan fasilitas dan menyunting profil adalah modul
   `kelola_dokter_nakes` (daftar + halaman detail). Dua berkas itu ikut dibaca
   supaya kontraknya tetap dijaga di tempat yang benar-benar dirender. */
const hubController = fs.readFileSync(path.join(root, 'admin_menu/application/modules/kelola_dokter_nakes/controllers/Kelola_dokter_nakes.php'), 'utf8');
const hubModel = fs.readFileSync(path.join(root, 'admin_menu/application/modules/kelola_dokter_nakes/models/Kelola_dokter_nakes_m.php'), 'utf8');
const hubView = fs.readFileSync(path.join(root, 'admin_menu/application/modules/kelola_dokter_nakes/views/kelola_dokter_nakes_v.php'), 'utf8');
const hubDetail = fs.readFileSync(path.join(root, 'admin_menu/application/modules/kelola_dokter_nakes/views/puskesmas_detail_v.php'), 'utf8');

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
expect(hubController.includes("'puskesmas_readiness_summary'") && hubModel.includes('function readiness_summary'), 'hub_controller_projects_actionable_facility_readiness');
expect(hubView.includes('puskesmasReadinessFilter') && hubView.includes('data-puskesmas-readiness') && hubView.includes('Kesiapan Puskesmas aktif'), 'hub_view_filters_ready_and_incomplete_facilities');
expect(hubView.includes("$status === 'aktif' ? (empty($readiness_issues) ? 'ready' : 'attention') : 'inactive'"), 'inactive_facility_excluded_from_readiness_filter');
expect(hubModel.includes('function readiness_issues') && model.includes('function readiness_issues'), 'readiness_rules_borrowed_not_rewritten');
expect(hubDetail.includes('readiness_issues') && hubDetail.includes('name="latitude"') && hubDetail.includes('name="longitude"'), 'detail_screen_can_close_readiness_gaps');
expect(!hubView.includes('bulk-autofill') && !hubDetail.includes('bulk-autofill'), 'facility_remediation_remains_explicit_admin_work');
expect((view.match(/name="alamat"[^>]*required/g) || []).length === 2, 'address_required_create_and_update');
expect((view.match(/name="latitude" step="any" min="-90" max="90"/g) || []).length === 2
    && (view.match(/name="latitude"[^\n]*required>/g) || []).length === 2, 'latitude_bounded_create_and_update');
expect((view.match(/name="longitude" step="any" min="-180" max="180"/g) || []).length === 2
    && (view.match(/name="longitude"[^\n]*required>/g) || []).length === 2, 'longitude_bounded_create_and_update');

process.stdout.write(`PUSKESMAS_ADMIN_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`PUSKESMAS_ADMIN_SOURCE_FAILED=${failed}\n`);
process.exit(failed > 0 ? 1 : 0);
