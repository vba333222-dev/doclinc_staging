#!/usr/bin/env python3
"""Periksa bahwa setiap teks di $page_head berasal dari kepala halaman lama.

Untuk tiap view yang sudah dimigrasi:
  - ambil blok kepala halaman dari `git show HEAD:<path>`
  - ambil literal string di blok `$page_head = array(...)` pada berkas baru
  - setiap literal (kecuali kunci array) harus muncul di blok lama
  - jumlah aksi harus sama dengan jumlah <a>/<button> di kepala lama
    (dikurangi yang memang sengaja dibuang / ditambah yang sengaja naik)
"""
import os, re, subprocess, sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', '..', '..'))
os.chdir(ROOT)

# Aksi yang sengaja berubah: nama berkas -> (jumlah aksi lama, jumlah aksi baru, alasan)
EXPECTED_ACTION_DELTA = {
    'kelola_warga_v.php': (1, 0, 'tombol Generate Report ber-href="#" dibuang'),
}
EXTRA_ACTIONS_OK = {
    # nama: alasan boleh ada aksi yang tidak ada di kepala lama
}

# Subjudul yang sengaja ditulis ulang pada penyelarasan copy panel.
#
# Halaman-halaman ini dulu hanya mencetak <h1> tanpa subjudul, atau memakai
# kalimat panjang dari kepala halaman lama (dua klausa, nama menu lama, huruf
# kapital di tengah kalimat). Aturan copy panel meminta satu kalimat pendek yang
# menyebut isi halaman, jadi teksnya memang berbeda dari kepala lama. Tanpa
# daftar ini gerbang akan terus berbunyi untuk perubahan yang sudah diputuskan,
# dan bunyi yang selalu ada akhirnya diabaikan. Yang dicatat hanya teks BARU
# beserta alasannya; teks lain yang tidak ada di kepala lama tetap dianggap
# temuan.
EXPECTED_SUBTITLE_REWRITE = {
    'icd10_v.php': {
        'Kode ICD-10 WHO 2019 beserta bab dan bloknya.':
            'kepala lama hanya <h1>; subjudul baru menyebut sumber kode, satu kalimat',
    },
    'diagnosis_v.php': {
        'ICD-10 per kecamatan · ':
            'kepala lama hanya <h1>; subjudul baru menyebut satuan wilayah dan periode',
    },
    'kelola_keluhan_v.php': {
        'Pilihan gejala saat warga membuat konsultasi.':
            'kepala lama hanya <h1>; subjudul baru menyebut asal pilihan gejala',
    },
    'wilayah_v.php': {
        'Dasar pemetaan wilayah pada infografis penyakit.':
            'kepala lama hanya <h1>; subjudul baru menyebut kegunaan data wilayah',
    },
    'kelola_staff_puskesmas_v.php': {
        'Staf dan akun login personal per Puskesmas.':
            'kepala lama hanya <h1>; subjudul baru memisahkan staf dari akun login',
    },
    'audit_v.php': {
        'Catatan aksi pengguna · ':
            'subjudul lama menyebut "penampil audit" dua kali; kini periode saja',
    },
    'kapabilitas_v.php': {
        'Hak tambahan per akun, di luar peran admin.':
            'subjudul lama menyebut menu lain; kini menjelaskan batas kapabilitas',
    },
    'sesi_v.php': {
        'Status sesi per akun dan konfigurasi keamanan.':
            'subjudul lama menyebut menu lain; kini menyebut dua isi halaman',
    },
    'kelola_layanan_kesehatan_v.php': {
        'Data layanan lama. Konsultasi harian ada di Monitoring konsultasi.':
            'catatan bantuan lama digabung ke subjudul dan dipendekkan',
    },
    'kelola_news_feed_v.php': {
        'Berita yang tampil di situs publik DocLink.':
            'kepala lama hanya <h1>; subjudul baru menyebut tampilan di situs publik',
    },
    'konten_landing_v.php': {
        'Teks halaman perkenalan DocLinc.':
            'kepala lama hanya <h1>; subjudul baru menyebut objek yang disunting',
    },
    'legacy_unavailable_v.php': {
        'Editor ini tidak aktif di database ini.':
            'kepala lama hanya <h1>; subjudul baru menyatakan sebab halaman kosong',
    },
    'home_v.php': {
        'Ringkasan pelayanan warga Kota Cilegon.':
            'kepala lama tanpa subjudul; satu kalimat menyebut cakupan dasbor',
        'Cara membaca angka di halaman ini':
            'label bantuan lama berhuruf kapital di tiap kata; kini kalimat biasa',
    },
}

PAGES = [
    'admin_menu/application/modules/icd10/views/icd10_v.php',
    'admin_menu/application/modules/diagnosis/views/diagnosis_v.php',
    'admin_menu/application/modules/kelola_keluhan/views/kelola_keluhan_v.php',
    'admin_menu/application/modules/kelola_tindakan/views/kelola_tindakan_v.php',
    'admin_menu/application/modules/master_puskesmas/views/master_puskesmas_v.php',
    'admin_menu/application/modules/wilayah/views/wilayah_v.php',
    'admin_menu/application/modules/kelola_staff_puskesmas/views/kelola_staff_puskesmas_v.php',
    'admin_menu/application/modules/kelola_dokter_nakes/views/kelola_dokter_nakes_v.php',
    'admin_menu/application/modules/rekam_medis/views/rekam_medis_v.php',
    'admin_menu/application/modules/konsultasi_kesehatan/views/konsultasi_kesehatan_v.php',
    'admin_menu/application/modules/laporan/views/laporan_v.php',
    'admin_menu/application/modules/audit/views/audit_v.php',
    'admin_menu/application/modules/kapabilitas/views/kapabilitas_v.php',
    'admin_menu/application/modules/sesi/views/sesi_v.php',
    'admin_menu/application/modules/kelola_warga/views/kelola_warga_v.php',
    'admin_menu/application/modules/kelola_layanan_kesehatan/views/kelola_layanan_kesehatan_v.php',
    'admin_menu/application/modules/kelola_news_feed/views/kelola_news_feed_v.php',
    'admin_menu/application/modules/konten_landing/views/konten_landing_v.php',
    'admin_menu/application/modules/landing_page/views/legacy_unavailable_v.php',
    'admin_menu/application/modules/home/views/home_v.php',
]


def old_block(path):
    src = subprocess.run(['git', 'show', 'HEAD:' + path],
                         capture_output=True, text=True).stdout
    m = re.search(r'<div class="[^"]*(?:you-are-here|dl-adm-head)[^"]*"', src)
    if not m:
        return None
    i = m.start()
    depth = 0
    end = i
    for tag in re.finditer(r'<div\b|</div>', src[i:]):
        depth += -1 if tag.group(0).startswith('</') else 1
        end = i + tag.end()
        if depth == 0:
            break
    block = src[i:end]
    # Catatan bantuan satu kalimat yang digabung ke `sub` ikut dibandingkan.
    tail = src[end:end + 1200]
    m2 = re.search(r'<div class="doclinc-helper-note[^"]*">.*?</div>', tail, re.S)
    if m2:
        block += '\n' + m2.group(0)
    return block


def plain(html):
    html = re.sub(r'<!--.*?-->', ' ', html, flags=re.S)
    html = re.sub(r'<\?.*?\?>', ' ', html, flags=re.S)
    html = re.sub(r'<[^>]+>', ' ', html)
    for ent, ch in [('&middot;', '\u00b7'), ('&amp;', '&'), ('&nbsp;', ' '),
                    ('&mdash;', '\u2014'), ('&ldquo;', '\u201c'),
                    ('&rdquo;', '\u201d'), ('&rarr;', '\u2192')]:
        html = html.replace(ent, ch)
    html = re.sub(r'\s+', ' ', html)
    return re.sub(r'\s+([.,;:])', r'\1', html)


def descriptor(path):
    src = open(path, encoding='utf-8').read()
    m = re.search(r"\$page_head\s*=\s*array\(", src)
    if not m:
        return None
    i = m.end() - 1
    depth = 0
    end = i
    for p in range(end, len(src)):
        if src[p] == '(':
            depth += 1
        elif src[p] == ')':
            depth -= 1
            if depth == 0:
                end = p + 1
                break
    return src[m.end() - 1:end]


bad = 0
for path in PAGES:
    name = os.path.basename(path)
    blk = old_block(path)
    desc = descriptor(path)
    if blk is None or desc is None:
        print('LEWAT', name, '(kepala lama / deskriptor tidak ditemukan)')
        continue
    lits = [s for s in re.findall(r"'((?:[^'\\]|\\.)*)'", desc) if len(s) >= 4]
    keys = set(re.findall(r"'([a-z_]+)'\s*=>", desc))
    body = [l for l in lits if l not in keys and l not in ('link', 'help', 'modal')]
    text = plain(blk)
    missing = [l for l in body if l not in text and l not in blk]
    approved = EXPECTED_SUBTITLE_REWRITE.get(name, {})
    rewritten = [l for l in missing if l in approved]
    missing = [l for l in missing if l not in approved]
    old_actions = len(re.findall(r'<(?:a|button)\b', re.sub(r'<!--.*?-->', ' ', blk, flags=re.S)))
    new_actions = len(re.findall(r"'kind'\s*=>", desc))
    ok = not missing
    if new_actions != old_actions:
        exp = EXPECTED_ACTION_DELTA.get(name)
        ok = ok and exp is not None and exp[1] == new_actions and exp[0] == old_actions
    note = ''
    if rewritten:
        note += ' subjudul ditulis ulang: %d (tercatat)' % len(rewritten)
    if missing:
        note += ' teks tak ada di kepala lama: %r' % missing
    if new_actions != old_actions:
        exp = EXPECTED_ACTION_DELTA.get(name)
        note += ' aksi %d->%d%s' % (old_actions, new_actions,
                                   '' if exp else ' (TIDAK DIDOKUMENTASIKAN)')
    print(('ok   ' if ok else 'CEK  ') + name + note)
    if not ok:
        bad += 1

print('\n%d berkas perlu dilihat' % bad)
sys.exit(0 if bad == 0 else 1)
