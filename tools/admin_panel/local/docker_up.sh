#!/bin/sh
#
# Satu perintah untuk menghidupkan harness panel admin di Docker, dipakai
# berulang kali tanpa langkah manual.
#
#   tools/admin_panel/local/docker_up.sh              # flag klinis MATI
#   tools/admin_panel/local/docker_up.sh --clinical   # flag klinis MENYALA
#   tools/admin_panel/local/docker_up.sh --revoke     # cabut kapabilitas saja
#   tools/admin_panel/local/docker_up.sh --down       # hentikan, data tetap ada
#
# Yang dikerjakan berurutan, semuanya idempoten:
#
#   1. `docker compose up -d --build` (kontainer doclinc-db + doclinc-app,
#      volume `doclinc-local_db_data` membuat data tetap ada antar-jalan);
#   2. tunggu Apache menjawab di http://127.0.0.1:8080/admin_menu/login;
#   3. isi fixture dengan `bootstrap.php --fresh` HANYA bila tabel
#      `medicalrecords` belum ada — jadi menjalankan skrip ini lagi tidak
#      menghapus data yang sedang Anda pakai;
#   4. `fixture_clinical.php` untuk tabel `clinical_access_audit` dan
#      kapabilitas `clinical_audit` (butir ini yang membuat "Buka rekam"
#      berhenti menjawab 403).
#
# Port sengaja sama dengan `docker-compose.yml`: aplikasi 8080, database 3307,
# keduanya terikat ke 127.0.0.1. Bila `serve.sh` (PHP `-S`) atau MariaDB lokal
# masih memakai port itu, matikan dulu — skrip ini akan menyebutkannya.

set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd) || exit 1
LOCAL="$ROOT/tools/admin_panel/local"
BASE_URL="http://127.0.0.1:8080/admin_menu"
# Host satunya yang juga sah: compose tidak lagi memaku ADMIN_BASE_URL, jadi
# tautan mengikuti host yang dipakai peramban dan POST formulir tetap sesama
# asal (cookie CSRF ikut terkirim). Memaku satu host membuat host lain dijawab
# 403 oleh proteksi CSRF.
BASE_URL_ALT="http://localhost:8080/admin_menu"
DB_NAME="doclinc_stage_local_test"
DB_USER="doclinc"
DB_PASS="doclinclokal"

WITH_CLINICAL=0
REVOKE_ONLY=0
DOWN=0
for arg in "$@"; do
	case "$arg" in
		--clinical) WITH_CLINICAL=1 ;;
		--no-clinical) WITH_CLINICAL=0 ;;
		--revoke) REVOKE_ONLY=1 ;;
		--down) DOWN=1 ;;
		-h|--help) sed -n '2,26p' "$0" | sed 's/^# \{0,1\}//' ; exit 0 ;;
		*) echo "argumen tidak dikenal: $arg (pakai --help)" >&2 ; exit 2 ;;
	esac
done

command -v docker >/dev/null 2>&1 || {
	echo "docker tidak ada di PATH; jalur tanpa Docker: tools/admin_panel/local/serve.sh" >&2
	exit 2
}
docker info >/dev/null 2>&1 || {
	echo "daemon Docker tidak menjawab (Docker Desktop sudah dijalankan?)" >&2
	exit 2
}

COMPOSE="docker compose -f $LOCAL/docker-compose.yml"
if [ "$WITH_CLINICAL" = "1" ] || [ "$REVOKE_ONLY" = "1" ]; then
	COMPOSE="$COMPOSE -f $LOCAL/docker-compose.clinical.yml"
fi

if [ "$DOWN" = "1" ]; then
	$COMPOSE down
	echo "kontainer dihentikan; volume doclinc-local_db_data tetap ada"
	exit 0
fi

echo "-- menaikkan compose (build hanya bila berubah)"
$COMPOSE up -d --build

echo "-- menunggu ${BASE_URL}/login"
tries=0
while [ "$tries" -lt 60 ]; do
	code=$(curl -s -o /dev/null -m 2 -w '%{http_code}' "$BASE_URL/login" 2>/dev/null || true)
	if [ "$code" = "200" ]; then
		break
	fi
	tries=$((tries + 1))
	sleep 2
done
if [ "$code" != "200" ]; then
	echo "GAGAL: ${BASE_URL}/login menjawab '${code:-tidak ada jawaban}' setelah 120 detik" >&2
	echo "cek: docker compose -f $LOCAL/docker-compose.yml logs --tail=40 app" >&2
	exit 1
fi

# Fixture hanya diisi bila belum pernah diisi. `--fresh` menghapus tabel lebih
# dulu, jadi memanggilnya setiap kali akan membuang rekam medis yang baru saja
# Anda buka.
seeded=$(docker exec doclinc-db mariadb -u"$DB_USER" -p"$DB_PASS" -N -B \
	-e "SHOW TABLES LIKE 'medicalrecords'" "$DB_NAME" 2>/dev/null || true)
if [ "$seeded" = "" ]; then
	echo "-- fixture kosong: bootstrap.php --fresh"
	docker exec -e ALLOW_LOCAL_FIXTURE=1 doclinc-app \
		php /var/www/html/tools/admin_panel/local/bootstrap.php --fresh >/dev/null
else
	echo "-- fixture sudah ada: bootstrap dilewati"
fi

if [ "$REVOKE_ONLY" = "1" ]; then
	echo "-- mencabut kapabilitas clinical_audit"
	docker exec -e ALLOW_LOCAL_FIXTURE=1 doclinc-app \
		php /var/www/html/tools/admin_panel/local/fixture_clinical.php --revoke
else
	echo "-- fixture akses klinis"
	docker exec -e ALLOW_LOCAL_FIXTURE=1 doclinc-app \
		php /var/www/html/tools/admin_panel/local/fixture_clinical.php
fi

records=$(docker exec doclinc-db mariadb -u"$DB_USER" -p"$DB_PASS" -N -B \
	-e "SELECT COUNT(*) FROM medicalrecords" "$DB_NAME" 2>/dev/null || echo '?')

cat <<EOF

panel     : ${BASE_URL}/
          juga ${BASE_URL_ALT}/ (host mana pun yang menunjuk ke mesin ini)
masuk     : admin@lokal.test / Lokal#2026!
database  : 127.0.0.1:3307 ${DB_NAME} (${DB_USER} / ${DB_PASS})
rekam     : ${records} baris
uji browser:
  cd tools/task11_browser && TASK11_BASE_URL=${BASE_URL} \\
    npx playwright test --config=playwright.admin.config.js --reporter=line
gerbang di kontainer:
  docker exec doclinc-app /var/www/html/tools/admin_panel/local/gates.sh
EOF

if [ "$WITH_CLINICAL" = "1" ]; then
	echo "flag klinis : MENYALA (runtime environment staging)"
else
	echo "flag klinis : mati — jalankan ulang dengan --clinical untuk modal Buka rekam"
fi
