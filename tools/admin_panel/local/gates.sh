#!/bin/sh
#
# Semua gerbang panel admin dalam satu perintah.
#
# Tujuannya bukan menggantikan perintah individual, melainkan menghapus
# ketergantungan pada ingatan: urutan gerbang, variabel lingkungan fixture, dan
# jalur relatif yang mudah salah ketik semuanya ada di sini.
#
# Dua cara pakai:
#
#   1. Di dalam kontainer (Docker hidup):
#        docker exec doclinc-app /var/www/html/tools/admin_panel/local/gates.sh \
#          --seed
#
#      Di dalam kontainer Apache mendengar di port 80; 8080 hanyalah pemetaan
#      di mesin. Skrip mendeteksinya sendiri (lihat BASE_URL di bawah), jadi
#      perintah di atas tidak perlu variabel apa pun.
#
#   2. Tanpa Docker, dari akar repo, dengan PHP + database lokal:
#        ADMIN_DB_HOST=127.0.0.1 ADMIN_DB_PORT=3307 \
#        ADMIN_DB_NAME=doclinc_stage_local_test \
#        ADMIN_DB_USER=doclinc ADMIN_DB_PASS=doclinclokal \
#        PHP=/path/ke/php BASE_URL=http://127.0.0.1:8080/ \
#          tools/admin_panel/local/gates.sh
#
# Opsi:
#   --seed            jalankan bootstrap.php --fresh lebih dulu (butuh
#                     ALLOW_LOCAL_FIXTURE=1 dan database yang namanya memuat
#                     "stage" serta "test")
#   --with-browser    ikut menjalankan Playwright: sapuan visual (19 rute x 2
#                     layar + uji panel geser ponsel) dan gerbang
#                     aksesibilitas (19 rute x 2 tema: kontras WCAG AA,
#                     landmark, urutan judul, scope <th>, ikon ber-glyph,
#                     subresource same-origin). Butuh fixture hidup dan
#                     browser yang sudah diunduh.
#                     Rute uji browser 19, sapuan rute 18: rute lama
#                     `kelola_staff_puskesmas` masih dipotret karena CRUD
#                     stafnya hidup dan ditautkan dari detail Puskesmas,
#                     sedangkan `master_puskesmas` hanya mengalihkan sehingga
#                     tidak dipotret dan tidak disapu.
#   --php=CMD         PHP yang dipakai (default: php)
#   --base=URL        basis URL untuk sapuan rute
#                     (default: lihat BASE_URL di bawah)
#
# Yang tidak dijalankan dari kontainer: lima pemeriksa Python (butuh python3 +
# riwayat git) dan Playwright (butuh Node). Keduanya dihitung "dilewati" bila
# diminta di sana - dan baris ringkasan akhir menyebutkan berapa yang dilewati,
# supaya hijau di kontainer tidak pernah terbaca sebagai hijau menyeluruh.
#
# Semua gerbang dijalankan walau ada yang gagal, supaya satu kali jalan
# menghasilkan gambaran lengkap; kode keluar 1 bila ada yang gagal.

set -u

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/../../.." && pwd) || exit 1
PHP=${PHP:-php}
# Di dalam kontainer Apache mendengar di port 80; 8080 hanya pemetaan di mesin.
# Tanpa pembedaan ini, sapuan rute di kontainer selalu "tidak menjawab" -
# persis kegagalan yang terlihat pada 2026-10-25. Nilai dari lingkungan atau
# --base= tetap menang.
if [ -n "${BASE_URL:-}" ]; then
	:
elif [ -f /.dockerenv ]; then
	BASE_URL=http://127.0.0.1/admin_menu/
else
	BASE_URL=http://127.0.0.1:8080/admin_menu/
fi
RUN_SEED=0
RUN_BROWSER=0

for arg in "$@"; do
	case "$arg" in
		--seed) RUN_SEED=1 ;;
		--with-browser) RUN_BROWSER=1 ;;
		--php=*) PHP=${arg#--php=} ;;
		--base=*) BASE_URL=${arg#--base=} ;;
		*)
			echo "argumen tidak dikenal: $arg" >&2
			exit 2
			;;
	esac
done

failures=0
skipped=0
run_gate() {
	label=$1
	shift
	echo
	echo "== ${label} =="
	if "$@"; then
		echo "-- ${label}: lulus"
	else
		echo "-- ${label}: GAGAL" >&2
		failures=$((failures + 1))
	fi
}

command -v "$PHP" >/dev/null 2>&1 || {
	echo "PHP tidak ditemukan: $PHP (setel --php=/jalur/ke/php)" >&2
	exit 2
}

if [ "$RUN_SEED" -eq 1 ]; then
	run_gate 'fixture' env ALLOW_LOCAL_FIXTURE=1 \
		"$PHP" "$ROOT/tools/admin_panel/local/bootstrap.php" --fresh
fi

# L0 statis: tidak butuh database maupun server.
run_gate 'kebersihan tata letak' \
	"$PHP" "$ROOT/tools/admin_panel/tests/copy_hygiene_unit.php"
run_gate 'kebersihan ikon' \
	"$PHP" "$ROOT/tools/admin_panel/tests/icon_hygiene_unit.php"
run_gate 'kontrak dasbor monitoring' \
	"$PHP" "$ROOT/tools/admin_panel/tests/monitoring_dashboard_unit.php"
run_gate 'IA navigasi samping' \
	"$PHP" "$ROOT/tools/admin_panel/tests/sidebar_ia_unit.php"
run_gate 'kontrak hub Puskesmas' \
	"$PHP" "$ROOT/tools/admin_panel/tests/puskesmas_hub_unit.php"
run_gate 'kontrak arsip rekam medis' \
	"$PHP" "$ROOT/tools/admin_panel/tests/record_archive_unit.php"

# L2 rute: butuh aplikasi hidup di BASE_URL dan fixture yang sudah diisi.
#
# Kesiapan diperiksa dengan PHP, bukan dengan perintah `curl`: image harness
# hanya memasang ekstensi curl untuk PHP, bukan biner curl. Dulu pemeriksaan
# memakai biner itu, sehingga di kontainer hasilnya selalu "tidak menjawab"
# walaupun aplikasi hidup - dan sapuan 18 rute ikut gagal tanpa sebab nyata.
echo
echo "== sapuan 18 rute =="
echo "-- basis URL: $BASE_URL"
probe=$("$PHP" -r '
	$url = $argv[1];
	$ctx = stream_context_create(array("http" => array(
		"method" => "GET", "timeout" => 5, "ignore_errors" => true,
		"follow_location" => 0,
	)));
	$body = @file_get_contents($url, false, $ctx);
	if ($body === false) {
		echo "TIDAK-TERJANGKAU";
		exit(1);
	}
	if (!empty($http_response_header[0])
		&& preg_match("/\\s(\\d{3})\\s/", $http_response_header[0], $m)) {
		echo $m[1];
		exit(0);
	}
	echo "TANPA-STATUS";
	exit(0);
' "$BASE_URL" 2>/dev/null)

case $probe in
	TIDAK-TERJANGKAU)
		echo "-- DILEWATI: tidak ada yang menjawab di $BASE_URL" >&2
		echo "   aplikasi mati, atau port salah (di kontainer: 80, bukan 8080)" >&2
		failures=$((failures + 1))
		;;
	5*)
		echo "-- GAGAL: $BASE_URL menjawab HTTP $probe" >&2
		failures=$((failures + 1))
		;;
	*)
		if BASE_URL="$BASE_URL" "$PHP" "$ROOT/tools/admin_panel/local/sweep.php"; then
			echo "-- sapuan 18 rute: lulus"
		else
			echo "-- sapuan 18 rute: GAGAL" >&2
			failures=$((failures + 1))
		fi
		;;
esac

# Pemeriksa Python tetap dijalankan: cepat, dan menangkap penyimpangan partial
# sebelum gerbang PHP menyala. Tiga dari lima membaca `git show HEAD:`, jadi
# hanya bermakna di mesin pengembang - image harness sengaja tanpa python3 dan
# git. Bila tidak bisa dijalankan, ia dihitung "dilewati", bukan lulus, supaya
# ringkasan akhir tidak pernah mengaku memeriksa lebih banyak dari kenyataan.
if command -v python3 >/dev/null 2>&1 && command -v git >/dev/null 2>&1; then
	for script in copy_hygiene_port.py head_verify.py copy_verbatim.py empty_sim.py list_card_sim.py; do
		run_gate "python: {$script}" python3 "$ROOT/tools/admin_panel/local/checks/$script"
	done
else
	echo
	echo "== pemeriksa Python =="
	if [ -f /.dockerenv ]; then
		echo "-- DILEWATI: pemeriksa Python butuh python3 + riwayat git, jadi dijalankan dari mesin" >&2
	else
		echo "-- DILEWATI: butuh python3 dan git di PATH (tiga pemeriksa membaca 'git show HEAD:')" >&2
	fi
	skipped=$((skipped + 1))
fi

if [ "$RUN_BROWSER" -eq 1 ]; then
	echo
	echo "== playwright uji visual =="
	if ! command -v npx >/dev/null 2>&1; then
		if [ -f /.dockerenv ]; then
			# Bukan kelalaian: image harness sengaja hanya PHP + Apache, dan uji
			# browser menunjuk port 8080 yang di dalam kontainer tidak ada.
			echo "-- DILEWATI: Node tidak ada di kontainer (memang; uji browser dari mesin)" >&2
			echo "   jalankan dari akar repo:" >&2
			echo "     cd tools/task11_browser && TASK11_BASE_URL=http://127.0.0.1:8080/admin_menu \\" >&2
			echo "       npx playwright test --config=playwright.admin.config.js --reporter=line" >&2
			skipped=$((skipped + 1))
		else
			echo "-- GAGAL: npx tidak ditemukan (butuh Node untuk uji browser)" >&2
			failures=$((failures + 1))
		fi
	elif [ ! -d "$ROOT/tools/task11_browser/node_modules" ]; then
		echo "-- GAGAL: node_modules Playwright tidak ada: jalankan npm install di tools/task11_browser" >&2
		failures=$((failures + 1))
	else
		run_gate 'playwright uji visual' sh -c '
			cd "$1/tools/task11_browser" || exit 1
			TASK11_BASE_URL="$2" BASELINE_DIR="${BASELINE_DIR:-/tmp/doclinc-baseline/after}" \
				STRICT_HEADINGS=1 npx playwright test --config=playwright.admin.config.js --reporter=line
		' sh "$ROOT" "${BASE_URL%/}"
	fi
fi

echo
if [ "$failures" -eq 0 ] && [ "$skipped" -eq 0 ]; then
	echo "== hasil: semua gerbang lulus =="
	exit 0
fi
if [ "$failures" -eq 0 ]; then
	echo "== hasil: semua gerbang yang dijalankan lulus, ${skipped} dilewati (lihat catatan di atas) =="
	exit 0
fi
echo "== hasil: ${failures} gerbang gagal, ${skipped} dilewati ==" >&2
exit 1
