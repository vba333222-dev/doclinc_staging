#!/bin/sh
#
# Menyajikan panel admin dengan `php -S` + `router.php`, tanpa Apache/Docker.
#
# Tata letaknya sengaja sama dengan produksi: docroot adalah akar repo, panel
# ada di `/admin_menu/`, dan token desain di `/assets/css/`. Lihat komentar
# `router.php` untuk alasan detailnya.
#
# Pemakaian (dari akar repo, fixture sudah dijalankan):
#
#   ADMIN_BASE_URL=http://127.0.0.1:8080/admin_menu/ \
#   ADMIN_DB_HOST=127.0.0.1 ADMIN_DB_PORT=3307 \
#   ADMIN_DB_NAME=doclinc_stage_local_test \
#   ADMIN_DB_USER=doclinc ADMIN_DB_PASS=doclinclokal \
#   DOCLINC_SESSION_PATH=$HOME/.local/doclinc-sessions \
#   PHP=/path/ke/php tools/admin_panel/local/serve.sh
#
# PORT dapat diubah lewat variabel PORT (default 8080); HOST default 127.0.0.1
# dan router menolak database nonlokal, jadi penyaji ini tidak bisa dipakai
# membuka fixture ke jaringan.

set -eu

PHP=${PHP:-php}
HOST=${HOST:-127.0.0.1}
PORT=${PORT:-8080}

DOCROOT=$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd) || exit 1

command -v "$PHP" >/dev/null 2>&1 || {
	echo "PHP tidak ditemukan: $PHP (setel PHP=/jalur/ke/php)" >&2
	exit 2
}

echo "panel: http://${HOST}:${PORT}/admin_menu/  (docroot: ${DOCROOT})" >&2
exec "$PHP" -S "${HOST}:${PORT}" -t "$DOCROOT" "$DOCROOT/tools/admin_panel/local/router.php"
