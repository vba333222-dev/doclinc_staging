#!/usr/bin/env python3
"""Setiap kalimat keadaan kosong yang lama harus masih ada setelah migrasi.

Membandingkan teks (bukan markup): blok `.doclinc-empty-state` di HEAD dipecah
jadi kalimat, lalu tiap kalimat dicari di berkas sekarang dengan spasi
dinormalkan — jadi kalimat yang dipindah ke `title`/`message` tetap lolos,
kalimat yang hilang atau berubah kata tidak.
"""
import os, re, subprocess, sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', '..', '..'))
os.chdir(ROOT)

FILES = [
    'admin_menu/application/modules/home/views/home_v.php',
    'admin_menu/application/modules/laporan/views/laporan_v.php',
    'admin_menu/application/modules/konsultasi_kesehatan/views/konsultasi_kesehatan_v.php',
]

# Kalimat yang sengaja dibuang / diubah, beserta alasannya.
#
# Penyelarasan copy panel menyeragamkan kalimat keadaan kosong menjadi satu pola:
# "belum tersedia; <akibatnya>" untuk data yang belum ada, dan "belum ada ..."
# untuk hasil yang memang kosong. Kalimat lama memakai ", jadi ..." dan sebagian
# mengulang periode di dalam badan kalimat sehingga judul dan isinya berbunyi
# sama. Kalimat di bawah ini diganti, bukan dihapus tanpa jejak: versi barunya
# masih menyebut keadaan data dan langkah berikutnya.
REMOVED = {
    'Master Puskesmas belum tersedia, jadi perbandingan per fasilitas belum dapat disusun':
        'diganti "Master Puskesmas belum tersedia; perbandingan per fasilitas belum bisa disusun"',
    'Tidak ada permintaan pada periode ini untuk fasilitas mana pun.':
        'diganti "Belum ada permintaan pada periode ini." - fasilitas sudah disebut judul',
    'Sumber data permintaan belum tersedia, jadi pengecualian belum dapat diperiksa':
        'diganti "Data permintaan belum tersedia; pengecualian belum bisa diperiksa"',
    'Tidak ada permintaan yang memenuhi aturan eskalasi saat ini.':
        'diganti "Tidak ada permintaan yang memenuhi aturan eskalasi." - "saat ini" mubazir',
    'Tabel permintaan belum tersedia, jadi mutu data belum dapat diperiksa':
        'diganti "Tabel permintaan belum tersedia; mutu data belum bisa diperiksa"',
}


def norm(text):
    return re.sub(r'\s+', ' ', text).strip()


def head_source(path):
    return subprocess.run(['git', 'show', 'HEAD:' + path],
                          capture_output=True, text=True).stdout


def empty_blocks(src):
    blocks = []
    for m in re.finditer(r'<div class="doclinc-empty-state[^"]*"[^>]*>', src):
        depth, end = 0, m.start()
        for tag in re.finditer(r'<div\b|</div>', src[m.start():]):
            depth += -1 if tag.group(0).startswith('</') else 1
            end = m.start() + tag.end()
            if depth == 0:
                break
        blocks.append(src[m.start():end])
    return blocks


def sentences(block):
    text = re.sub(r'<!--.*?-->', ' ', block, flags=re.S)
    text = re.sub(r'<\?.*?\?>', ' ', text, flags=re.S)
    text = re.sub(r'<[^>]+>', ' ', text)
    text = text.replace('&middot;', '\u00b7').replace('&amp;', '&')
    text = norm(text).strip(' .')
    return [s.strip() for s in re.split(r'(?<=\.)\s+', text) if len(s.strip()) >= 4]


bad = 0
for path in FILES:
    old = head_source(path)
    now = norm(open(path, encoding='utf-8').read())
    now = re.sub(r"'\s*\.\s*'", '', now)  # gabungkan literal yang disambung
    lost = []
    for block in empty_blocks(old):
        for sentence in sentences(block):
            if sentence in REMOVED:
                continue
            if norm(sentence) not in now:
                lost.append(sentence)
    name = os.path.basename(path)
    if lost:
        bad += 1
        print('HILANG', name)
        for s in lost:
            print('   -', s)
    else:
        print('ok   ', name, '(%d kalimat)' % sum(len(sentences(b)) for b in empty_blocks(old)))

print('\n%d berkas kehilangan kalimat' % bad)
sys.exit(0 if bad == 0 else 1)
