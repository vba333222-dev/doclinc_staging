#!/usr/bin/env python3
"""Simulasi `commons/list_card.php` tanpa PHP.

Mesin ini tidak punya `php`, sedangkan uji yang mengikat
(`tools/admin_panel/tests/copy_hygiene_unit.php`) berjalan di kontainer. Skrip
ini memeriksa hal-hal yang bisa dipastikan dari sumber:

  1. kontrak deskriptor `$list_card` di setiap view pemanggil (kunci dikenal,
     `title` wajib saat `part=open`, aksi punya label + target, varian dan ikon
     lolos daftar putih, kelas hanya berisi huruf/angka/strip);
  2. pasangan `open`/`close` — satu masing-masing per view, `close` sesudah
     `open`;
  3. keseimbangan `<div>` hasil render: bagian `open` menambah dua div, bagian
     `close` menutup dua div, sehingga view yang tadinya seimbang tetap
     seimbang. Angka ini diuji terhadap `git show HEAD:` supaya migrasi tidak
     diam-diam memindahkan bingkai yang sudah timpang.

Jalankan dari mana saja: `python3 tools/admin_panel/local/checks/list_card_sim.py`.
"""
import os
import re
import subprocess
import sys
from html.parser import HTMLParser

ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', '..', '..')) + '/'
PARTIAL = 'admin_menu/application/views/commons/list_card.php'
VIEWS = [
    'admin_menu/application/modules/konsultasi_kesehatan/views/konsultasi_kesehatan_v.php',
    'admin_menu/application/modules/kelola_staff_puskesmas/views/kelola_staff_puskesmas_v.php',
    'admin_menu/application/modules/kelola_dokter_nakes/views/kelola_dokter_nakes_v.php',
]
VARIANTS = {'primary', 'success', 'danger', 'outline-primary', 'outline-secondary'}
FA = re.compile(r'^fa[srlb]? fa-[a-z0-9-]+(?: fa-[a-z0-9-]+)*$')
CLASSES = re.compile(r'^[a-z0-9 _-]*$')

problems = []


def fail(where, message):
    problems.append('%s: %s' % (where, message))


def read(path):
    full = ROOT + path
    if not os.path.isfile(full):
        fail(path, 'berkas tidak ada')
        return None
    return open(full, encoding='utf-8').read()


def div_net(text):
    return len(re.findall(r'<div\b', text)) - len(re.findall(r'</div>', text))


VOID_TAGS = {'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
             'link', 'meta', 'source', 'track', 'wbr'}


class TagBalance(HTMLParser):
    """Pelacak sarang tag untuk potongan markup yang PHP-nya sudah dilepas."""

    def __init__(self):
        HTMLParser.__init__(self, convert_charrefs=True)
        self.stack = []
        self.stray = []
        self.mismatch = []

    def handle_starttag(self, tag, attrs):
        if tag not in VOID_TAGS:
            self.stack.append(tag)

    def handle_endtag(self, tag):
        if tag in VOID_TAGS:
            return
        if self.stack and self.stack[-1] == tag:
            self.stack.pop()
            return
        if tag in self.stack:
            self.mismatch.append('%s menutup %s' % (tag, self.stack[-1]))
            while self.stack and self.stack.pop() != tag:
                pass
            return
        self.stray.append(tag)


def markup_only(text):
    return re.sub(r'<\?(?:php\b|=)?[\s\S]*?\?>', '', text)


def php_array_body(source, start):
    """Ambil isi `array(` yang dibuka pada atau sesudah `start` (kurung seimbang)."""
    open_at = source.find('array(', start)
    if open_at == -1:
        return None
    depth = 0
    for index in range(open_at + len('array'), len(source)):
        char = source[index]
        if char == '(':
            depth += 1
        elif char == ')':
            depth -= 1
            if depth == 0:
                return source[open_at:index + 1]
    return None


def descriptor_value(body, key):
    match = re.search(r"'%s'\s*=>\s*'((?:[^'\\]|\\.)*)'" % key, body)
    return match.group(1) if match else None



def check_open_descriptor(view, body):
    title = descriptor_value(body, 'title')
    if not title:
        fail(view, "deskriptor open tanpa 'title'")
    for key in ('card_class', 'body_class'):
        value = descriptor_value(body, key)
        if value is not None and not CLASSES.match(value):
            fail(view, "'%s' berisi karakter asing: %r" % (key, value))
    if "'action'" not in body:
        return
    action = php_array_body(body, body.find("'action'"))
    if action is None:
        fail(view, 'aksi bukan array')
        return
    if not descriptor_value(action, 'label'):
        fail(view, 'aksi tanpa label akan dirender sebagai tombol kosong')
    target_key = 'target' if descriptor_value(action, 'kind') == 'modal' else 'href'
    if not descriptor_value(action, target_key):
        fail(view, "aksi tanpa '%s'" % target_key)
    variant = descriptor_value(action, 'variant')
    if variant is not None and variant not in VARIANTS:
        fail(view, 'varian di luar daftar putih: %r' % variant)
    icon = descriptor_value(action, 'icon')
    if icon is not None and not FA.match(icon):
        fail(view, 'ikon bukan nama kelas Font Awesome: %r' % icon)


partial = read(PARTIAL)
if partial is not None:
    close_at = partial.find("=== 'close'")
    return_at = partial.find('return;', close_at if close_at != -1 else 0)
    if close_at == -1 or return_at == -1:
        fail(PARTIAL, "bagian 'close' atau 'return;' tidak ditemukan")
    else:
        if div_net(partial[close_at:return_at]) != -2:
            fail(PARTIAL, 'bagian close tidak menutup tepat dua div (net %d)'
                 % div_net(partial[close_at:return_at]))
        if div_net(partial[return_at:]) != 2:
            fail(PARTIAL, 'bagian open tidak membuka tepat dua div (net %d)'
                 % div_net(partial[return_at:]))
    if '<h6' in partial:
        fail(PARTIAL, 'judul kartu memakai <h6>')
    else:
        open_balance = TagBalance()
        open_balance.feed(markup_only(partial[return_at:]))
        open_balance.close()
        close_balance = TagBalance()
        close_balance.feed(markup_only(partial[close_at:return_at]))
        close_balance.close()
        if open_balance.mismatch or open_balance.stray or open_balance.stack != ['div', 'div']:
            fail(PARTIAL, 'bagian open tidak meninggalkan tepat dua div terbuka (%r, %r, %r)'
                 % (open_balance.stack, open_balance.stray, open_balance.mismatch))
        if close_balance.mismatch or close_balance.stack or close_balance.stray != ['div', 'div']:
            fail(PARTIAL, 'bagian close tidak menutup tepat dua div (%r, %r, %r)'
                 % (close_balance.stack, close_balance.stray, close_balance.mismatch))

CALL = re.compile(r"<\?php\s*\$list_card\s*=\s*array\([\s\S]*?"
                  r"load->view\('commons/list_card'[^;]*;\s*\?>")

for view in VIEWS:
    source = read(view)
    if source is None:
        continue
    calls = source.count("load->view('commons/list_card'")
    if calls != 2:
        fail(view, 'pemanggilan partial %d, seharusnya 2 (open + close)' % calls)
    parts = []
    for match in re.finditer(r'\$list_card\s*=\s*array\(', source):
        body = php_array_body(source, match.start())
        part = descriptor_value(body, 'part') if body else None
        if part not in ('open', 'close'):
            fail(view, "'part' bukan open/close: %r" % part)
            continue
        parts.append(part)
        if part == 'open':
            check_open_descriptor(view, body)
    if parts != ['open', 'close']:
        fail(view, 'urutan bagian %r, seharusnya [open, close]' % (parts,))

    simulated = CALL.sub(lambda m: '<div><div>' if "'open'" in m.group(0)
                         else '</div></div>', source)
    head = subprocess.run(['git', 'show', 'HEAD:' + view], cwd=ROOT,
                          capture_output=True, text=True).stdout
    if simulated == source:
        fail(view, 'simulasi tidak mengganti satu pun pemanggilan partial')
    elif div_net(simulated) != div_net(head):
        fail(view, 'keseimbangan div hasil render %+d, sedangkan HEAD %+d'
             % (div_net(simulated), div_net(head)))

print('== kartu daftar (simulasi) ==')
if problems:
    for problem in problems:
        print('  FAIL - ' + problem)
    print('  gagal: %d' % len(problems))
    sys.exit(1)
print('  ok   - %d view: deskriptor sesuai kontrak, open/close berpasangan, div seimbang'
      % len(VIEWS))
print('  lulus: 1, gagal: 0')
