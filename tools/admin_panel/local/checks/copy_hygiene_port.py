#!/usr/bin/env python3
"""Port 1:1 dari tools/admin_panel/tests/copy_hygiene_unit.php (tanpa PHP)."""
import glob, os, re, sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', '..', '..')) + '/'
passed = 0
failed = 0
debts = []
report_only = '--report' in sys.argv


def read(rel):
    p = ROOT + rel
    return open(p, encoding='utf-8').read() if os.path.isfile(p) else None


def chk(cond, label, detail=''):
    global passed, failed
    if cond:
        passed += 1
        print('  ok   -', label)
        return
    if report_only:
        debts.append(label)
        print('  utang-', label, '' if detail == '' else '  (' + detail + ')')
        return
    failed += 1
    print('  FAIL -', label, '' if detail == '' else '  (' + detail + ')')



pages = [
    'modules/icd10/views/icd10_v.php',
    'modules/diagnosis/views/diagnosis_v.php',
    'modules/kelola_keluhan/views/kelola_keluhan_v.php',
    'modules/kelola_tindakan/views/kelola_tindakan_v.php',
    'modules/master_puskesmas/views/master_puskesmas_v.php',
    'modules/wilayah/views/wilayah_v.php',
    'modules/kelola_staff_puskesmas/views/kelola_staff_puskesmas_v.php',
    'modules/kelola_dokter_nakes/views/kelola_dokter_nakes_v.php',
    'modules/rekam_medis/views/rekam_medis_v.php',
    'modules/konsultasi_kesehatan/views/konsultasi_kesehatan_v.php',
    'modules/laporan/views/laporan_v.php',
    'modules/audit/views/audit_v.php',
    'modules/kapabilitas/views/kapabilitas_v.php',
    'modules/sesi/views/sesi_v.php',
    'modules/kelola_warga/views/kelola_warga_v.php',
    'modules/kelola_layanan_kesehatan/views/kelola_layanan_kesehatan_v.php',
    'modules/kelola_news_feed/views/kelola_news_feed_v.php',
    'modules/konten_landing/views/konten_landing_v.php',
    'modules/landing_page/views/legacy_unavailable_v.php',
]
dashboard = 'modules/home/views/home_v.php'
page_head_path = 'admin_menu/application/views/commons/page_head.php'
empty_state_path = 'admin_menu/application/views/commons/empty_state.php'

header = read('admin_menu/application/views/commons/header.php') or ''
chk(re.search(r'assets/css/doclinc-admin-shell\.css', header) is not None,
    'header memuat doclinc-admin-shell.css', 'nama berkas CSS shell')

shell_css = read('admin_menu/assets/css/doclinc-admin-shell.css')
chk(shell_css is not None, 'berkas CSS shell tersedia', 'assets/css/doclinc-admin-shell.css')
shell_css = shell_css or ''
custom_css = read('admin_menu/assets/css/custom.css') or ''
print('== partial bersama ==')
head_src = read(page_head_path)
empty_src = read(empty_state_path)
chk(head_src is not None, 'partial kepala halaman tersedia', page_head_path)
chk(empty_src is not None, 'partial keadaan kosong tersedia', empty_state_path)
chk(head_src is None or head_src.count('<h1') == 1,
    'partial kepala halaman mencetak <h1> tepat satu kali',
    'ditemukan %d' % (0 if head_src is None else head_src.count('<h1')))
chk(head_src is None or 'dl-adm-head-title' in head_src,
    'partial memakai kelas judul yang sudah ada')
chk(empty_src is None or 'doclinc-empty-state' in empty_src,
    'partial keadaan kosong memakai kelas dasar dasbor')
chk((head_src is None or 'style=' not in head_src)
    and (empty_src is None or 'style=' not in empty_src),
    'tidak ada gaya inline di dalam partial')
chk(head_src is None or 'echo $page_head[' not in head_src,
    'partial tidak mencetak HTML mentah dari pemanggil')
chk(head_src is None or 'outline-secondary' in head_src,
    'partial menyaring varian tombol dengan daftar putih')
chk(head_src is None or 'preg_match' in head_src,
    'partial menyaring nama ikon dengan pola kelas')
chk(head_src is None or head_src.count('fa-info-circle') == 1,
    'tombol bantuan memakai satu ikon yang ada di bundel FA5', 'fa-info-circle')

print('\n== satu sumber gaya judul ==')
chk(shell_css.count('.dl-adm-head-title') == 1,
    'judul halaman didefinisikan sekali di doclinc-admin-shell.css',
    'ditemukan %d' % shell_css.count('.dl-adm-head-title'))
chk('.dl-adm-head-title' not in custom_css, 'custom.css tidak menimpa judul halaman')
chk(shell_css.count('.dl-adm-empty-title') == 1,
    'judul keadaan kosong didefinisikan sekali',
    'ditemukan %d' % shell_css.count('.dl-adm-empty-title'))

forced = []
for line in re.split(r'\r\n|\r|\n', shell_css):
    low = line.lower()
    if '!important' not in low:
        continue
    if 'dl-adm-head' in low or 'dl-adm-empty' in low:
        forced.append(line.strip())
chk(not forced, 'tidak ada !important pada selektor judul', ' | '.join(forced[:2]))

custom_pos = header.find('assets/css/custom.css')
shell_pos = header.find('assets/css/doclinc-admin-shell.css')
chk(custom_pos != -1 and shell_pos != -1 and shell_pos > custom_pos,
    'CSS shell dimuat sesudah custom.css')

print('\n== halaman modul ==')
without_partial, wrong_h1, inline_size = [], [], []
for rel in pages:
    src = read('admin_menu/application/' + rel)
    if src is None:
        chk(False, 'view tersedia: ' + rel)
        continue
    uses_partial = 'commons/page_head' in src
    if not uses_partial:
        without_partial.append(os.path.basename(rel))
    h1_count = len(re.findall(r'<h1[\s>]', src, re.I)) + (1 if uses_partial else 0)
    if h1_count != 1:
        wrong_h1.append('%s(%d)' % (os.path.basename(rel), h1_count))
    if re.search(r'<h[12][^>]*style="[^"]*font-size', src, re.I) is not None:
        inline_size.append(os.path.basename(rel))
chk(not without_partial, 'setiap halaman memakai partial kepala halaman',
    '%d belum: %s' % (len(without_partial), ', '.join(without_partial[:6])))
chk(not wrong_h1, 'setiap halaman punya tepat satu <h1>', ', '.join(wrong_h1))
chk(not inline_size, 'tidak ada ukuran huruf judul lewat atribut style', ', '.join(inline_size))

print('\n== dasbor ==')
home = read('admin_menu/application/' + dashboard)
chk(home is not None, 'view dasbor tersedia', dashboard)
chk(home is not None and 'id="dlMonHelp"' in home,
    'modal bantuan dasbor tetap di dalam home_v.php', 'penanda id="dlMonHelp"')
chk(home is not None and 'commons/page_head' in home,
    'dasbor memakai partial kepala halaman')
chk(home is not None
    and len(re.findall(r'<h1[\s>]', home, re.I))
        + (1 if 'commons/page_head' in home else 0) == 1,
    'dasbor punya tepat satu <h1>')

print('\n== keadaan kosong ==')
chk(home is not None and "load->view('commons/empty_state'" in home,
    'dasbor merender keadaan kosong lewat partial')
chk(home is not None and 'doclinc-empty-state' not in home,
    'dasbor tidak lagi menulis markup keadaan kosong sendiri',
    'ditemukan %d blok lokal' % (home or '').count('doclinc-empty-state'))
laporan_view = read('admin_menu/application/modules/laporan/views/laporan_v.php')
chk(laporan_view is not None and (laporan_view or '').count("load->view('commons/empty_state'") == 1,
    'laporan merender keadaan kosong awal lewat partial')
chk(laporan_view is not None and (laporan_view or '').count('function emptyStateHtml') == 1,
    'laporan punya satu perakit keadaan kosong untuk daftar dari JS')
chk(laporan_view is not None and (laporan_view or '').count('emptyStateHtml(') >= 3,
    'daftar dari JS memakai perakit keadaan kosong yang sama',
    'ditemukan %d pemakaian' % (laporan_view or '').count('emptyStateHtml('))

print('\n== kartu daftar ==')
list_card_path = 'admin_menu/application/views/commons/list_card.php'
list_card_src = read(list_card_path)
chk(list_card_src is not None, 'partial kartu daftar tersedia', list_card_path)
chk(list_card_src is not None and "=== 'close'" in list_card_src,
    'partial kartu daftar punya bagian penutup')
chk(list_card_src is not None and 'echo $list_card[' not in list_card_src,
    'partial kartu daftar tidak mencetak HTML mentah dari pemanggil')
chk(list_card_src is not None and (list_card_src or '').count('preg_match') >= 2,
    'partial kartu daftar menyaring kelas dan ikon dengan pola')
chk(list_card_src is not None and '<h2 class="h6' in list_card_src,
    'judul kartu memakai tingkat judul yang benar tanpa mengubah ukuran')
chk(list_card_src is not None and '<h6' not in list_card_src,
    'partial kartu daftar tidak memakai <h6> untuk judul')
chk(list_card_src is not None
    and 'dl-adm-action"' in list_card_src
    and not re.search(r'class="[^"]*dl-adm-action-sm', list_card_src),
    'aksi kepala kartu memakai kelas aksi bersama tanpa varian mati')
chk(len(re.findall(r'min-height:\s*\d+px', shell_css)) == 0,
    'CSS shell tidak mendeklarasikan tinggi aksi yang kalah oleh lapisan token',
    'ditemukan %d deklarasi' % len(re.findall(r'min-height:\s*\d+px', shell_css)))
reference_css = read('assets/css/doclinc-reference-ui.css')
chk(reference_css is not None
    and len(re.findall(r'\.doclinc-admin-shell \.btn-[a-z]+[^{]*\{[^}]*min-height:\s*var\(--dl-ref-control', reference_css)) >= 1
    and '--dl-ref-control-primary: 48px' in reference_css,
    'tinggi aksi dipegang lapisan token referensi',
    '' if reference_css is not None else 'assets/css/doclinc-reference-ui.css tidak terbaca')
chk('dl-adm-action-sm' not in custom_css,
    'custom.css tidak menimpa ukuran aksi kepala kartu')

list_card_pages = {
    'modules/konsultasi_kesehatan/views/konsultasi_kesehatan_v.php': 0,
    'modules/kelola_staff_puskesmas/views/kelola_staff_puskesmas_v.php': 1,
    'modules/kelola_dokter_nakes/views/kelola_dokter_nakes_v.php': 0,
    'modules/master_puskesmas/views/master_puskesmas_v.php': 0,
}
unpaired, frame_rewritten = [], []
for rel, allowed_local_frames in list_card_pages.items():
    src = read('admin_menu/application/' + rel)
    if src is None:
        chk(False, 'view daftar tersedia: ' + rel)
        continue
    opens = src.count("'part' => 'open'")
    closes = src.count("'part' => 'close'")
    if opens != 1 or closes != 1:
        unpaired.append('%s(open %d, close %d)' % (os.path.basename(rel), opens, closes))
    local_frames = src.count('doclinc-table-card')
    if local_frames > allowed_local_frames:
        frame_rewritten.append('%s(%d)' % (os.path.basename(rel), local_frames))
chk(not unpaired, 'setiap kartu daftar dibuka dan ditutup lewat partial', ', '.join(unpaired))
chk(not frame_rewritten, 'tidak ada view daftar yang menulis bingkai kartu sendiri',
    ', '.join(frame_rewritten))

konsultasi_view = read('admin_menu/application/modules/konsultasi_kesehatan/views/konsultasi_kesehatan_v.php') or ''
th_total = len(re.findall(r'<th[\s>]', konsultasi_view, re.I))
th_scoped = len(re.findall(r'<th scope="col">', konsultasi_view, re.I))
chk(th_total > 0 and th_total == th_scoped,
    'setiap header kolom konsultasi punya scope="col"',
    'ditemukan %d/%d' % (th_scoped, th_total))

print('\n== label tombol ==')
generic_labels = {'submit', 'ok', 'oke', 'cancel', 'save', 'delete', 'update',
                  'search', 'proses', 'terapkan', 'eksekusi', 'lanjut'}
generic_found = []
for rel in pages + [dashboard]:
    src = read('admin_menu/application/' + rel)
    if src is None:
        continue
    for inner in re.findall(r'<button[^>]*>([\s\S]*?)</button>', src, re.I):
        label = re.sub(r'<[^>]*>', '', inner)
        label = ' '.join(label.split()).strip().lower()
        if label in generic_labels:
            generic_found.append('%s: %s' % (os.path.basename(rel), label))
chk(not generic_found, 'tidak ada tombol berlabel kata generik saja',
    ', '.join(generic_found[:4]))

print('\n== pesan galat controller ==')
# Pesan galat yang berhenti pada "apa yang gagal" memaksa pengguna menebak
# keadaan datanya. Yang dikunci: setiap pesan keluarga "Gagal ..." menyebut
# keadaan data dan langkah berikutnya dalam kalimat kedua.
gagal_total = 0
gagal_tanpa_langkah = []
for path in sorted(glob.glob(ROOT + 'admin_menu/application/modules/*/controllers/*.php')):
    with open(path, encoding='utf-8') as handle:
        src = handle.read()
    for message in re.findall(r"set_flashdata\('error',\s*'(Gagal [^']*)'\)", src):
        gagal_total += 1
        if not re.match(r'^Gagal [^.]+\.\s+\S', message):
            gagal_tanpa_langkah.append('%s: %s' % (os.path.basename(path), message))
chk(gagal_total > 0, 'pesan galat "Gagal ..." di controller terbaca',
    'ditemukan %d' % gagal_total)
chk(not gagal_tanpa_langkah,
    'setiap pesan galat "Gagal ..." menyebut keadaan data dan langkah berikutnya',
    ' | '.join(gagal_tanpa_langkah[:4]))

print('\n== ringkasan ==')
if report_only:
    print('  utang: %d butir, lulus: %d' % (len(debts), passed))
    sys.exit(0)
print('  lulus: %d, gagal: %d' % (passed, failed))
sys.exit(0 if failed == 0 else 1)
