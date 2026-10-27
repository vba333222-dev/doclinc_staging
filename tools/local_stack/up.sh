#!/bin/sh
#
# Menyiapkan DocLink siap uji LOKAL untuk SEMUA peran, bukan hanya panel admin:
#
#   warga / pasien   http://127.0.0.1:8080/login
#   nakes / dokter   http://127.0.0.1:8080/login-nakes
#   puskesmas        http://127.0.0.1:8080/login-nakes      (akun koordinator)
#   admin panel      http://127.0.0.1:8080/admin_menu/
#   beranda publik   http://127.0.0.1:8080/               (landing + daftar)
#
# Pemakaian:
#
#   tools/local_stack/up.sh                 # naikkan, lengkapi fixture, uji masuk
#   tools/local_stack/up.sh --start         # NYALAKAN saja (kontainer sudah ada)
#   tools/local_stack/up.sh --fresh         # hapus & isi ulang fixture (data demo hilang)
#   tools/local_stack/up.sh --no-clinical   # panel tanpa arsip rekam medis
#   tools/local_stack/up.sh --no-calls      # tanpa LiveKit (chat tetap bisa diuji)
#   tools/local_stack/up.sh --no-verify     # lewati uji masuk otomatis
#   tools/local_stack/up.sh --down          # hentikan kontainer, data tetap ada
#
# Panggilan suara/video MENYALA secara default: `--calls` diteruskan ke
# `docker_up.sh`, yang menambah kontainer `doclinc-livekit` di 127.0.0.1:7880
# (mode `--dev`, kunci `devkey`/`secret`) dan meneruskan `LIVEKIT_*` ke app.
# Tanpa itu halaman chat tetap terbuka tetapi tombol panggilan dijawab 503
# "Panggilan belum aktif". Pemeriksa jalur panggilan tanpa peramban:
#   tools/local_stack/check_calls.sh
#
# Satu konsultasi `Accepted` juga dibuat fixture (`warga1` <-> `nakes1`) supaya
# utas `/chat?request_id=...` punya isi yang bisa dikirim, dan alur kunjungan
# (`visit_status=not_started`) bisa didemokan dari awal.
#
# Sekali pasang, sesudahnya cukup dinyalakan:
#   - setelah pemakaian pertama berhasil, kedua servis memakai
#     `restart: unless-stopped` dan seluruh data tinggal di volume; menyalakan
#     Docker Desktop (atau menekan ▶ pada grup `doclinc-local`) membuatnya
#     jalan sendiri, data fixture utuh;
#   - `--start` adalah jalur yang sama tetapi dengan pemeriksaan: ia menyalakan
#     kontainer yang SUDAH ADA, menunggu MariaDB siap sebelum Apache menjawab
#     (Docker Desktop bisa menyalakan app lebih dulu), menjalankan ulang
#     `fixture_portal.php` yang idempoten, lalu menguji masuk tiap peran.
#     Tanpa build, tanpa menghapus data, selesai dalam hitungan detik.
#
# Kenapa skrip ini ada di samping tools/admin_panel/local/docker_up.sh:
# `docker_up.sh` menyiapkan panel admin saja, dan `bootstrap.php` sengaja
# membuat skema yang lebih ramping daripada produksi. Portal warga/nakes di akar
# repo membaca kolom yang tidak dibuat bootstrap - `patients`,
# `medicalrecords.patient_id`, `medicalrecords.clinical_finalized_at`,
# `request_events.domain_event_key` - sehingga `/home_nakes` berakhir HTTP 500
# tanpa badan. Skrip ini TIDAK menyalin logika `docker_up.sh`; ia memanggilnya
# apa adanya, lalu:
#
#   1. menjalankan `tools/local_stack/fixture_portal.php` (skema portal +
#      akun koordinator puskesmas yang tidak ada di bootstrap);
#   2. menunggu `/login` (portal) dan `/admin_menu/login` (panel) menjawab 200;
#   3. menguji masuk warga, nakes, dan koordinator puskesmas lewat HTTP -
#      kata sandi yang salah, sesi yang tidak tertulis, atau CSRF yang menolak
#      akan terlihat di sini, bukan saat presentasi;
#   4. mencetak meja peran: URL, akun, dan halaman tujuannya.
#
# Membuka ke LAN (mis. presentasi dari laptop lain) berarti mengubah
# `127.0.0.1:8080:80` menjadi `8080:80` di
# tools/admin_panel/local/docker-compose.yml lalu menjalankan ulang skrip ini.
# Risikonya nyata: fixture berisi akun contoh dengan kata sandi tertulis di
# layar ini, jadi jangan lakukan di jaringan yang tidak Anda percaya.
#
# Tanpa Docker: hanya panel yang punya jalur alternatif
# (`tools/admin_panel/local/serve.sh`, PHP `-S` + router.php). Portal butuh
# Apache/mod_rewrite dengan DocumentRoot di akar repo seperti vhost harness ini.

set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd) || exit 1
LOCAL="$ROOT/tools/admin_panel/local"
HOST="127.0.0.1:8080"
PORTAL="http://$HOST"
PANEL="http://$HOST/admin_menu"
DB_NAME="doclinc_stage_local_test"
DB_USER="doclinc"
DB_PASS="doclinclokal"
PASSWORD='Lokal#2026!'

WITH_CLINICAL=1
WITH_CALLS=1
FRESH=0
VERIFY=1
DOWN=0
START_ONLY=0

for arg in "$@"; do
	case "$arg" in
		--start) START_ONLY=1 ;;
		--fresh) FRESH=1 ;;
		--no-clinical) WITH_CLINICAL=0 ;;
		--clinical) WITH_CLINICAL=1 ;;
		--calls) WITH_CALLS=1 ;;
		--no-calls) WITH_CALLS=0 ;;
		--no-verify) VERIFY=0 ;;
		--down) DOWN=1 ;;
		-h|--help) sed -n '2,69p' "$0" | sed 's/^# \{0,1\}//' ; exit 0 ;;
		*) echo "argumen tidak dikenal: $arg (pakai --help)" >&2 ; exit 2 ;;
	esac
done

if [ "$DOWN" = "1" ]; then
	"$LOCAL/docker_up.sh" --down
	echo "kontainer dihentikan dan dihapus; volume doclinc-local_db_data tetap ada"
	echo "pasang lagi (data tidak diisi ulang): tools/local_stack/up.sh"
	echo "berhenti sementara tanpa menghapus kontainer: docker stop doclinc-app doclinc-db doclinc-livekit"
	exit 0
fi

if [ "$START_ONLY" = "1" ] && [ "$FRESH" = "1" ]; then
	echo "--start tidak menggabung dengan --fresh: --fresh membuang data, --start hanya menyalakan" >&2
	exit 2
fi

command -v docker >/dev/null 2>&1 || {
	echo "docker tidak ada di PATH; jalur tanpa Docker hanya untuk panel: tools/admin_panel/local/serve.sh" >&2
	exit 2
}
docker info >/dev/null 2>&1 || {
	echo "daemon Docker tidak menjawab (Docker Desktop sudah dijalankan?)" >&2
	exit 2
}

if [ "$START_ONLY" = "1" ]; then
	echo "== 1/5 menyalakan kontainer yang sudah ada (tanpa build, tanpa hapus data)"
	for container in doclinc-db doclinc-app; do
		if ! docker inspect "$container" >/dev/null 2>&1; then
			echo "GAGAL: kontainer $container belum pernah dibuat." >&2
			echo "sekali pasang dulu: tools/local_stack/up.sh  (atau --no-clinical bila flag klinis tidak dipakai)" >&2
			exit 2
		fi
	done
	# MariaDB dulu, baru Apache: app menyalakan dirinya lebih cepat daripada DB
	# siap, dan halaman yang dibuka pada detik itu hanya menampilkan galat SQL.
	docker start doclinc-db >/dev/null
	tries=0
	db_ready=0
	while [ "$tries" -lt 30 ]; do
		if docker exec doclinc-db mariadb -u"$DB_USER" -p"$DB_PASS" -N -B \
			-e 'SELECT 1' "$DB_NAME" >/dev/null 2>&1; then
			db_ready=1
			break
		fi
		tries=$((tries + 1))
		sleep 2
	done
	if [ "$db_ready" != "1" ]; then
		echo "GAGAL: MariaDB belum menerima koneksi setelah 60 detik" >&2
		echo "cek: docker logs --tail=40 doclinc-db" >&2
		exit 1
	fi
	echo "   doclinc-db: siap"
	docker start doclinc-app >/dev/null
	echo "   doclinc-app: dinyalakan"
	# LiveKit hanya ada bila stack dipasang dengan panggilan menyala; jangan
	# gagal bila memang tidak dipakai, tapi sebutkan supaya tidak bingung saat
	# tombol panggilan menjawab 503.
	if [ "$WITH_CALLS" = "1" ]; then
		if docker inspect doclinc-livekit >/dev/null 2>&1; then
			docker start doclinc-livekit >/dev/null
			echo "   doclinc-livekit: dinyalakan"
		else
			echo "   doclinc-livekit: belum pernah dibuat (pasang dengan --calls untuk uji panggilan)"
		fi
	else
		echo "   doclinc-livekit: dilewati (--no-calls)"
	fi
else
	echo "== 1/5 infrastruktur: compose + fixture panel"
	calls_flag="--no-calls"
	if [ "$WITH_CALLS" = "1" ]; then
		calls_flag="--calls"
	fi
	if [ "$WITH_CLINICAL" = "1" ]; then
		"$LOCAL/docker_up.sh" --clinical "$calls_flag"
	else
		"$LOCAL/docker_up.sh" --no-clinical "$calls_flag"
	fi
fi

if [ "$FRESH" = "1" ]; then
	echo "== 2/5 --fresh: mengosongkan lalu mengisi ulang fixture"
	docker exec -e ALLOW_LOCAL_FIXTURE=1 doclinc-app \
		php /var/www/html/tools/admin_panel/local/bootstrap.php --fresh >/dev/null
	if [ "$WITH_CLINICAL" = "1" ]; then
		docker exec -e ALLOW_LOCAL_FIXTURE=1 doclinc-app \
			php /var/www/html/tools/admin_panel/local/fixture_clinical.php
	fi
else
	echo "== 2/5 fixture dibiarkan apa adanya (pakai --fresh untuk mengisi ulang)"
fi

echo "== 3/5 fixture portal: skema warga/nakes + akun koordinator puskesmas"
docker exec -e ALLOW_LOCAL_FIXTURE=1 doclinc-app \
	php /var/www/html/tools/local_stack/fixture_portal.php

echo "== 4/5 menunggu portal dan panel menjawab"
wait_http() {
	url="$1"
	label="$2"
	tries=0
	code=""
	while [ "$tries" -lt 45 ]; do
		code=$(curl -s -o /dev/null -m 3 -w '%{http_code}' "$url" 2>/dev/null || true)
		if [ "$code" = "200" ]; then
			echo "   ${label}: ${url} -> 200"
			return 0
		fi
		tries=$((tries + 1))
		sleep 2
	done
	echo "GAGAL: ${label} (${url}) menjawab '${code:-tidak ada jawaban}' setelah 90 detik" >&2
	echo "cek: docker logs --tail=40 doclinc-app" >&2
	return 1
}

wait_http "$PORTAL/login" "portal warga"
wait_http "$PORTAL/login-nakes" "portal nakes"
wait_http "$PANEL/login" "panel admin"

if [ "$VERIFY" = "1" ]; then
	echo "== 5/5 uji masuk tiap peran, halaman chat, dan jalur panggilan"
	verify_login() {
		label="$1"
		login_path="$2"
		auth_path="$3"
		username="$4"
		after_path="$5"
		expect_title="$6"

		jar=$(mktemp)
		page=$(mktemp)

		curl -s -m 15 -c "$jar" -b "$jar" "$PORTAL$login_path" -o "$page" || true
		token=$(awk '$6 == "doclinc_csrf_cookie" {print $7}' "$jar" | tail -1)
		if [ -z "$token" ]; then
			echo "   ${label}: GAGAL - cookie CSRF tidak muncul di ${login_path}" >&2
			rm -f "$jar" "$page"
			return 1
		fi

		answer=$(curl -s -m 20 -c "$jar" -b "$jar" \
			--data-urlencode "username=$username" \
			--data-urlencode "password=$PASSWORD" \
			--data-urlencode "doclinc_csrf_token=$token" \
			"$PORTAL$auth_path" 2>/dev/null || true)
		if [ "$answer" != "1" ]; then
			echo "   ${label}: GAGAL - ${auth_path} menjawab '${answer}', seharusnya '1'" >&2
			rm -f "$jar" "$page"
			return 1
		fi

		code=$(curl -s -m 30 -c "$jar" -b "$jar" -o "$page" -w '%{http_code}' "$PORTAL$after_path" 2>/dev/null || true)
		title=$(grep -o '<title>[^<]*' "$page" 2>/dev/null | head -1 | sed 's/<title>//' || true)
		rm -f "$jar" "$page"

		case "$title" in
			*"$expect_title"*) ;;
			*)
				echo "   ${label}: GAGAL - ${after_path} menjawab ${code} berjudul '${title}', seharusnya memuat '${expect_title}'" >&2
				return 1
				;;
		esac
		if [ "$code" != "200" ]; then
			echo "   ${label}: GAGAL - ${after_path} menjawab ${code}" >&2
			return 1
		fi
		echo "   ${label}: ${username} -> ${after_path} 200 (\"${title}\")"
	}

	verify_login "warga" "/login" "/login/auth" "warga1" "/home" "Beranda"
	verify_login "nakes" "/login-nakes" "/login-nakes/auth" "nakes1" "/home_nakes" "Nakes"
	verify_login "puskesmas" "/login-nakes" "/login-nakes/auth" "kord1" "/home_nakes" "Puskesmas"

	# Halaman chat hanya 200 bila skema `consultation_messages` lengkap (lihat
	# bagian 1b fixture portal), dan utasnya hanya terbuka untuk permintaan yang
	# sudah diterima. Nomor permintaan dibaca dari database, bukan dipaku: nomor
	# bergantung pada isi fixture.
	chat_row=$(docker exec doclinc-db mariadb -u"$DB_USER" -p"$DB_PASS" -N -B \
		-e "SELECT r.request_id, u.username FROM requests r
			JOIN users u ON u.userId = r.user_id
			WHERE r.request_status='Accepted' AND r.responsible_doctor_user_id IS NOT NULL
			ORDER BY r.request_id DESC LIMIT 1" "$DB_NAME" 2>/dev/null || true)
	chat_request=$(printf '%s' "$chat_row" | cut -f1)
	chat_user=$(printf '%s' "$chat_row" | cut -f2)
	if [ -n "$chat_request" ] && [ -n "$chat_user" ]; then
		jar=$(mktemp)
		page=$(mktemp)
		curl -s -m 15 -c "$jar" -b "$jar" "$PORTAL/login" -o "$page" || true
		token=$(awk '$6 == "doclinc_csrf_cookie" {print $7}' "$jar" | tail -1)
		curl -s -m 20 -c "$jar" -b "$jar" \
			--data-urlencode "username=$chat_user" \
			--data-urlencode "password=$PASSWORD" \
			--data-urlencode "doclinc_csrf_token=$token" \
			"$PORTAL/login/auth" >/dev/null 2>&1 || true
		code=$(curl -s -m 30 -c "$jar" -b "$jar" -o "$page" -w '%{http_code}' \
			"$PORTAL/chat?request_id=$chat_request" 2>/dev/null || true)
		title=$(grep -o '<title>[^<]*' "$page" 2>/dev/null | head -1 | sed 's/<title>//' || true)
		rm -f "$jar" "$page"
		case "$title" in
			*"Chat Konsultasi"*) ;;
			*)
				echo "   chat: GAGAL - /chat?request_id=${chat_request} menjawab ${code} berjudul '${title}'" >&2
				exit 1
				;;
		esac
		if [ "$code" != "200" ]; then
			echo "   chat: GAGAL - /chat?request_id=${chat_request} menjawab ${code}" >&2
			exit 1
		fi
		echo "   chat: ${chat_user} /chat?request_id=${chat_request} -> 200 (\"Chat Konsultasi\")"
	else
		echo "   chat: dilewati - tidak ada permintaan Accepted" >&2
	fi

	if [ "$WITH_CALLS" = "1" ]; then
		if ! docker inspect doclinc-livekit >/dev/null 2>&1; then
			echo "   panggilan: dilewati - kontainer doclinc-livekit belum ada" >&2
			echo "   pasang sekali dengan: tools/local_stack/up.sh --calls" >&2
		else
			livekit_code=$(curl -s -o /dev/null -m 5 -w '%{http_code}' "http://127.0.0.1:7880/" 2>/dev/null || true)
			if [ "$livekit_code" != "200" ]; then
				echo "   panggilan: GAGAL - http://127.0.0.1:7880 menjawab '${livekit_code:-tidak ada jawaban}'" >&2
				echo "   cek: docker logs --tail=30 doclinc-livekit" >&2
				exit 1
			fi
			echo "   livekit: http://127.0.0.1:7880 -> 200 (devkey/secret)"
			calls_log=$(mktemp)
			if "$ROOT/tools/local_stack/check_calls.sh" > "$calls_log" 2>&1; then
				sed 's/^/   /' "$calls_log"
			else
				sed 's/^/   /' "$calls_log" >&2
				rm -f "$calls_log"
				echo "   panggilan: GAGAL - lihat pesan di atas" >&2
				exit 1
			fi
			rm -f "$calls_log"
		fi
	fi
else
	echo "== 5/5 uji masuk dilewati (--no-verify)"
fi

stats=$(docker exec doclinc-db mariadb -u"$DB_USER" -p"$DB_PASS" -N -B \
	-e "SELECT (SELECT COUNT(*) FROM patients), (SELECT COUNT(*) FROM medicalrecords), (SELECT COUNT(*) FROM requests), (SELECT COUNT(*) FROM puskesmas_staff WHERE status='aktif'), (SELECT COUNT(*) FROM users WHERE username LIKE 'kord%')" \
	"$DB_NAME" 2>/dev/null || echo "? ? ? ? ?")
# shellcheck disable=SC2086
set -- $stats
patients=${1:-?}
records=${2:-?}
requests=${3:-?}
staff=${4:-?}
coordinators=${5:-?}

cat <<EOF

------------------------------ meja peran ------------------------------
warga / pasien
  masuk   ${PORTAL}/login
  akun    warga1 .. warga40  /  ${PASSWORD}
  tujuan  /home  (beranda pasien, permintaan, konsultasi, notifikasi)

nakes / dokter
  masuk   ${PORTAL}/login-nakes
  akun    nakes1 .. nakes9   /  ${PASSWORD}
  tujuan  /home_nakes  (antrean visit, rekam medis, presence)

puskesmas (koordinator / command center)
  masuk   ${PORTAL}/login-nakes
  akun    kord1 .. kord9     /  ${PASSWORD}   (satu per puskesmas)
  tujuan  /home_nakes  (dasbor Puskesmas: staf, mutasi, arsip)

chat, kunjungan, panggilan  (satu konsultasi Accepted sudah disiapkan fixture)
  chat    buka kartu permintaan di beranda warga, atau langsung
          ${PORTAL}/chat?request_id=<id permintaan Accepted>
  peran   jendela 1: warga1   jendela 2: nakes1   (dua jendela/peramban)
  alur    kunjungan: nakes1 -> antrean visit -> en_route/arrived/in_service
  panggil nakes1 menekan tombol panggilan di halaman chat, warga1 menjawab
  cek     tools/local_stack/check_calls.sh   (tanpa peramban, hasil PASS/FAIL)
  livekit ws://127.0.0.1:7880  (devkey/secret, hanya loopback)

admin panel
  masuk   ${PANEL}/
  akun    admin@lokal.test   /  ${PASSWORD}
  akun    verifikator@lokal.test / ${PASSWORD}
  isi     kelola_dokter_nakes = hub Puskesmas (akun + staf + sandi koordinator)

database  127.0.0.1:3307  ${DB_NAME}  (${DB_USER} / ${DB_PASS})
isi       ${patients} pasien, ${records} rekam medis, ${requests} permintaan,
          ${staff} staf puskesmas aktif, ${coordinators} akun koordinator
------------------------------------------------------------------------

ulang dengan data bersih : tools/local_stack/up.sh --fresh
nyalakan lagi (tanpa build): tools/local_stack/up.sh --start
hentikan               : tools/local_stack/up.sh --down
cek panggilan          : tools/local_stack/check_calls.sh
gerbang panel          : docker exec doclinc-app /var/www/html/tools/admin_panel/local/gates.sh
log aplikasi           : docker logs --tail=50 doclinc-app
log livekit            : docker logs --tail=50 doclinc-livekit
EOF

