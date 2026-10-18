'use strict';

/* Papan status tim Puskesmas: siapa sedang menangani pasien, dan siapa tidak.
 *
 * Yang dijaga suite ini bukan tampilan, tetapi kontrak yang membuat papan itu
 * jujur: pelaku dibaca dari sumber yang sama dengan halaman Operasional,
 * badge hanya muncul untuk kunjungan yang benar-benar berjalan, dan roster
 * tetap memenuhi kontrak presence (id + atribut data-presence-*) sehingga
 * skrip presence tidak perlu diubah. */

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');
let passed = 0;
let failed = 0;
function expect(condition, label) {
  if (condition) {
    passed += 1;
    process.stdout.write(`PASS ${label}\n`);
  } else {
    failed += 1;
    process.stderr.write(`FAIL ${label}\n`);
  }
}

function methodBody(source, name) {
  const match = new RegExp(`(?:public|private) function\\s+${name}\\s*\\(`).exec(source);
  if (!match) return '';
  const open = source.indexOf('{', match.index);
  let depth = 0;
  for (let index = open; index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] === '}' && --depth === 0) return source.slice(open + 1, index);
  }
  return '';
}

function cssBlock(source, selector) {
  const start = source.indexOf(selector + ' {');
  if (start === -1) return '';
  let depth = 0;
  for (let index = source.indexOf('{', start); index < source.length; index += 1) {
    if (source[index] === '{') depth += 1;
    if (source[index] === '}' && --depth === 0) return source.slice(start, index + 1);
  }
  return '';
}

const nakes = read('application/modules/home_nakes/controllers/Home_nakes.php');
const nakesDashboard = read('application/modules/home_nakes/views/partials/nakes_dashboard_v.php');
const puskesmasCss = read('assets/css/doclinc-puskesmas.css');
const presence = read('assets/js/doclinc-nakes-presence.js');

const indexBody = methodBody(nakes, 'index');
const dutyBody = methodBody(nakes, 'puskesmas_team_duty');
const doctorBody = methodBody(nakes, 'puskesmas_staff_profession_is_doctor');

/* ---- Sumber kebenaran ---------------------------------------------------- */

expect(dutyBody.includes('doclinc_request_handling_nakes_id')
  && dutyBody.includes('doclinc_normalize_visit_status')
  && dutyBody.includes('visit_performer_user_id')
  && dutyBody.includes('responsible_doctor_user_id'),
  'team_duty_reads_the_same_handler_as_operations');

expect(dutyBody.includes("'en_route', 'arrived', 'in_service'")
  && dutyBody.includes("'disposisi'")
  && dutyBody.includes("'visit'")
  && dutyBody.includes("'menangani'"),
  'team_duty_only_marks_running_visits_and_names_each_state');

expect(dutyBody.includes("'menangani' => 1")
  && dutyBody.includes("'disposisi' => 2")
  && dutyBody.includes("'visit' => 3"),
  'team_duty_prefers_the_visit_badge_over_menangani');

expect(dutyBody.includes("$counts['idle'] = max(0, count($profession_by_user_id) - $counts['on_duty'])"),
  'team_duty_counts_cover_staff_without_duty');

expect(doctorBody.includes("preg_match('/(^|\\s)dokter($|\\s)/iu'"),
  'team_duty_recognises_doctor_professions_including_gigi');

expect(indexBody.includes("$d['nakes_team_duty'] = $team_duty['duty']")
  && indexBody.includes("$d['nakes_team_duty_counts'] = $team_duty['counts']")
  && nakes.includes("$d['nakes_team_duty'] = array();"),
  'team_duty_is_defaulted_before_the_command_center_branch');

/* ---- Kontrak roster + presence ------------------------------------------- */

expect(nakesDashboard.includes("'section_title' => 'Staf Puskesmas'")
  && (nakesDashboard.match(/'section_title' => 'Staf Puskesmas'/g) || []).length === 1,
  'team_panel_keeps_exactly_one_staff_section_title');

expect(nakesDashboard.includes('id="doclincNakesPresence"')
  && nakesDashboard.includes('data-presence-roster="true"')
  && nakesDashboard.includes('data-presence-user-id')
  && nakesDashboard.includes('data-presence-summary')
  && nakesDashboard.includes('data-presence-dot')
  && nakesDashboard.includes('data-presence-label')
  && nakesDashboard.includes('data-presence-last-seen'),
  'team_panel_keeps_the_presence_contract_intact');

expect(presence.includes('renderRoster')
  && presence.includes("querySelector('[data-presence-summary]')")
  && presence.includes("querySelectorAll('[data-presence-user-id]')")
  && presence.includes('getElementById(config.containerId)'),
  'presence_script_still_owns_the_roster_without_new_code');

/* ---- Markup papan -------------------------------------------------------- */

expect(nakesDashboard.includes("'title' => 'Sedang bertugas'")
  && nakesDashboard.includes("'title' => 'Tidak bertugas'")
  && nakesDashboard.includes('data-team-cell=')
  && nakesDashboard.includes('aria-labelledby="dlTeam'),
  'team_panel_splits_duty_and_idle_into_named_cells');

expect(nakesDashboard.includes('data-duty-state=')
  && nakesDashboard.includes("'visit' => 'Visit'")
  && nakesDashboard.includes("'disposisi' => 'Disposisi'")
  && nakesDashboard.includes("'menangani' => 'Menangani'"),
  'team_rows_expose_duty_state_and_readable_badges');

expect(nakesDashboard.includes("'empty' => 'Belum ada staf yang sedang menangani pasien.'")
  && nakesDashboard.includes("'empty' => 'Semua staf sedang bertugas.'"),
  'both_cells_explain_their_empty_state');

expect(nakesDashboard.includes('Tanpa akun')
  && nakesDashboard.includes('<?php if ($staff_user_id > 0) : ?>'),
  'staff_without_an_account_are_not_labelled_offline');

expect(!nakesDashboard.includes('$staff->no_hp'),
  'team_rows_no_longer_repeat_the_phone_number');

/* ---- Satu baris penunjuk, bukan papan kesiapan --------------------------- */

expect(nakesDashboard.includes('data-puskesmas-readiness')
  && nakesDashboard.includes('SIP belum dilengkapi')
  && nakesDashboard.includes('data-puskesmas-exception-board')
  && nakesDashboard.includes('Belum ada penanggung jawab layanan'),
  'team_panel_keeps_a_single_line_readiness_and_pic_pointer');

expect(!nakesDashboard.includes('nk-readiness-card')
  && !nakesDashboard.includes('data-puskesmas-readiness-board'),
  'readiness_board_itself_stays_out_of_the_dashboard');

/* ---- Tata letak ---------------------------------------------------------- */

const teamRowRule = cssBlock(puskesmasCss, '.dl-puskesmas-shell.dl-nakes-dashboard .dl-team-row');
const teamGridRule = cssBlock(puskesmasCss, '.dl-puskesmas-shell.dl-nakes-dashboard .dl-team-bento__grid');
const idleScrollRule = cssBlock(puskesmasCss, '.dl-puskesmas-shell.dl-nakes-dashboard .dl-team-cell[data-team-cell="idle"] .dl-team-list');
const teamListRule = cssBlock(puskesmasCss, '.dl-puskesmas-shell.dl-nakes-dashboard .dl-team-list');
const visitBadgeRule = cssBlock(puskesmasCss, '.dl-puskesmas-shell.dl-nakes-dashboard .dl-team-badge.is-visit');
const badgeBaseRule = cssBlock(puskesmasCss, '.dl-puskesmas-shell.dl-nakes-dashboard .dl-team-badge');
const queueRule = cssBlock(puskesmasCss, '.dl-puskesmas-shell.dl-nakes-dashboard .dl-team-row__queue');

expect(teamRowRule.includes('min-height: 52px;')
  && teamRowRule.includes('border-top: 1px solid')
  && teamRowRule.includes('background: transparent;'),
  'team_row_is_a_divider_not_a_card_in_a_card');

expect(teamGridRule.includes('grid-template-columns: minmax(0, 1fr);')
  && teamListRule.includes('grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));')
  && puskesmasCss.includes('container-type: inline-size;') === false
  && idleScrollRule.includes('max-height: 26rem;')
  && idleScrollRule.includes('overscroll-behavior: contain;'),
  'one_cell_column_with_self_densifying_lists');

expect(visitBadgeRule.includes('background: #E7F6EE;')
  && visitBadgeRule.includes('color: #0F5132;')
  && badgeBaseRule.includes('border: 1px solid transparent;'),
  'visit_badge_carries_colour_and_text_not_colour_alone');

/* "Menangani" adalah keadaan mayoritas: badge-nya disembunyikan secara visual
   supaya yang menonjol hanya kunjungan berjalan dan disposisi, tetapi teksnya
   tetap di DOM agar pembaca layar tidak kehilangan status. */
expect(nakesDashboard.includes("$duty_state === 'menangani' ? ' is-sr-only' : ''")
  && puskesmasCss.includes('.dl-team-badge.is-sr-only')
  && !puskesmasCss.includes('.dl-team-badge.is-menangani'),
  'menangani_state_is_kept_for_screen_readers_but_not_shown_as_a_badge');

expect(queueRule.includes('margin-left: auto;')
  && !badgeBaseRule.includes('margin-left: auto;'),
  'queue_pins_the_right_edge_so_rows_line_up');

/* Teks khusus pembaca layar diposisikan absolut, jadi baris harus menjadi
   containing block-nya — kalau tidak, kotak 1px itu bisa mendarat di luar
   baris dan memicu gulir horizontal. */
expect(/nk-staff-last-seen,[\s\S]{0,140}?dl-team-badge\.is-sr-only[\s\S]{0,400}?clip-path: inset\(50%\)/.test(puskesmasCss)
  && teamRowRule.includes('position: relative;'),
  'screen_reader_only_badge_reuses_the_hidden_text_technique');

/* Papan ini tidak menambah animasi apa pun: tidak ada transisi pada baris,
   badge, atau sel, sehingga tidak ada gerak yang perlu diredam. */
expect(!/transition:\s*all/.test(puskesmasCss)
  && !teamRowRule.includes('transition:')
  && !badgeBaseRule.includes('transition:'),
  'team_panel_adds_no_motion_of_its_own');

/* ---- Urutan baris -------------------------------------------------------- */

expect(nakesDashboard.includes("$dashboard_duty_rank = array('visit' => 3, 'disposisi' => 2, 'menangani' => 1);")
  && nakesDashboard.includes('usort($dashboard_duty_rows')
  && nakesDashboard.includes('$rank_b <=> $rank_a')
  && nakesDashboard.includes('strnatcasecmp($queue_a, $queue_b)'),
  'duty_rows_lead_with_running_visits_then_queue_number');

expect((nakesDashboard.match(/usort\(\$dashboard_idle_rows/g) || []).length === 1
  && /usort\(\$dashboard_idle_rows, static function \(\$a, \$b\) \{\s*return strnatcasecmp\(/.test(nakesDashboard)
  && !/usort\(\$dashboard_idle_rows[\s\S]{0,240}?dashboard_duty_rank/.test(nakesDashboard),
  'idle_rows_stay_alphabetical');

/* Peringkat tampilan di view harus sama dengan peringkat yang dipakai controller
   saat menggabungkan beberapa kasus untuk satu orang; kalau salah satunya
   berubah, urutan baris dan badge bisa bercerita berbeda. */
const rankPairs = source => (source.match(/'(\w+)'\s*=>\s*(\d+)/g) || [])
  .map(entry => entry.replace(/\s+/g, ''))
  .sort()
  .join(',');
const viewRankMap = /\$dashboard_duty_rank = array\(([^)]*)\);/.exec(nakesDashboard);
const controllerRankMap = /\$rank = array\(([^)]*)\);/.exec(dutyBody);
expect(viewRankMap && controllerRankMap && rankPairs(viewRankMap[1]) === rankPairs(controllerRankMap[1]),
  'duty_rank_map_mirrors_the_controller_precedence');

process.stdout.write(`PUSKESMAS_TEAM_STATUS_SOURCE_PASSED=${passed}\n`);
process.stdout.write(`PUSKESMAS_TEAM_STATUS_SOURCE_FAILED=${failed}\n`);
process.exit(failed > 0 ? 1 : 0);
