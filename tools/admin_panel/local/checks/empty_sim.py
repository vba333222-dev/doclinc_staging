#!/usr/bin/env python3
"""Simulasi render `commons/empty_state.php` dari deskriptor di dalam view.

Partial mengabaikan kunci yang tidak dikenal tanpa bersuara, jadi salah tulis
kunci (`titel`, `mesage`, `varian`) menghasilkan keadaan kosong yang tetap
tampil tetapi kehilangan bagiannya. Skrip ini memeriksa setiap deskriptor
terhadap kontrak partial, lalu mencetak bentuk yang dihasilkan.
"""
import os, re, sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..', '..', '..'))
os.chdir(ROOT)

FILES = [
    ('admin_menu/application/modules/home/views/home_v.php', 'home'),
    ('admin_menu/application/modules/laporan/views/laporan_v.php', 'laporan'),
    ('admin_menu/application/modules/konsultasi_kesehatan/views/konsultasi_kesehatan_v.php', 'konsultasi'),
]
KEYS = {'title', 'message', 'icon', 'action'}
ACTION_KEYS = {'label', 'href', 'icon', 'variant'}
VARIANTS = {'primary', 'success', 'danger', 'outline-primary', 'outline-secondary'}
ICON = re.compile(r'^fa[srlb]? fa-[a-z0-9-]+(?: fa-[a-z0-9-]+)*$')


def esc(s):
    return (s.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')
             .replace('"', '&quot;').replace("'", '&#39;'))


def block_at(src, start):
    """Ambil array(...) mulai dari posisi '(' dengan menghitung kurung."""
    depth = 0
    for p in range(start, len(src)):
        if src[p] == '(':
            depth += 1
        elif src[p] == ')':
            depth -= 1
            if depth == 0:
                return src[start:p + 1]
    return ''


def pairs(fragment):
    """Pasangan 'kunci' => nilai pada satu tingkat array saja.

    Kurung dihitung supaya `'action' => array('label' => ...)` tidak dilaporkan
    sebagai dua kunci tambahan di tingkat atas.
    """
    out = {}
    depth, i, n = 0, 0, len(fragment)
    while i < n:
        ch = fragment[i]
        if ch == '(':
            depth += 1
            i += 1
            continue
        if ch == ')':
            depth -= 1
            i += 1
            continue
        if depth == 1 and ch == "'":
            m = re.match(r"'([a-z_]+)'\s*=>\s*", fragment[i:])
            if m:
                key = m.group(1)
                j = i + m.end()
                if fragment[j:j + 6] == 'array(':
                    inner = block_at(fragment, j + 5)
                    out[key] = ('array', inner)
                    i = j + len(inner)
                    continue
                lit = re.match(r"'((?:[^'\\]|\\.)*)'", fragment[j:])
                if lit:
                    out[key] = ('str', lit.group(1))
                    i = j + lit.end()
                    continue
                expr = re.match(r"[^,()]+", fragment[j:])
                out[key] = ('expr', (expr.group(0) if expr else '').strip())
                i = j + (expr.end() if expr else 0)
                continue
        i += 1
    return out


problems = 0
rendered = 0
for path, label in FILES:
    src = open(path, encoding='utf-8').read()
    for m in re.finditer(r'\$empty_state\s*=\s*array\(', src):
        line = src[:m.start()].count('\n') + 1
        frag = block_at(src, m.end() - 1)
        data = pairs(frag)
        stray = set(data) - KEYS
        title = data.get('title', ('', ''))[1].strip()
        message = data.get('message', ('', ''))[1].strip()
        icon = data.get('icon', ('', ''))[1].lower().strip() or 'fas fa-inbox'
        problems_here = []
        if stray:
            problems_here.append('kunci tidak dikenal: %s' % ', '.join(sorted(stray)))
        if data.get('title', ('', ''))[0] != 'str' or not title:
            problems_here.append('title kosong / bukan literal')
        if 'message' in data and data['message'][0] != 'str':
            problems_here.append('message bukan literal')
        if not ICON.match(icon):
            problems_here.append('ikon tidak sah: %r' % icon)
        action = {}
        if 'action' in data:
            if data['action'][0] != 'array':
                problems_here.append('action bukan array')
            else:
                action = pairs(data['action'][1])
                unknown = set(action) - ACTION_KEYS
                if unknown:
                    problems_here.append('kunci aksi tidak dikenal: %s' % ', '.join(sorted(unknown)))
                if not action.get('label', ('', ''))[1].strip():
                    problems_here.append('aksi tanpa label')
                if not action.get('href', ('', ''))[1].strip():
                    problems_here.append('aksi tanpa href')
                variant = action.get('variant', ('str', 'outline-primary'))[1]
                if variant not in VARIANTS:
                    problems_here.append('varian tidak sah: %r' % variant)
        if problems_here:
            problems += len(problems_here)
            print('CEK  %s:%d' % (label, line))
            for p in problems_here:
                print('       -', p)
            continue
        rendered += 1
        print('ok   %s:%-4d %-58s %s' % (label, line, title[:58],
                                         ('+aksi' if action else '')))
        if '<' in title or '<' in message:
            print('       (berisi "<" — akan di-escape menjadi &lt;)')

print('\n%d deskriptor dirender, %d masalah' % (rendered, problems))
sys.exit(0 if problems == 0 else 1)
