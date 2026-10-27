#!/bin/sh
#
# Memeriksa jalur panggilan suara/video (LiveKit) dari ujung ke ujung, tanpa
# peramban: masuk sebagai nakes, mulai panggilan, masuk sebagai warga, jawab,
# lalu validasi token yang diterbitkan aplikasi ke server LiveKit.
#
#   tools/local_stack/check_calls.sh
#   tools/local_stack/check_calls.sh --request-id 201 --call-type audio
#
# Kenapa tanpa peramban: titik gagal yang paling sering sudah terlihat di sini -
# `LIVEKIT_ENABLED` tidak sampai ke kontainer (503 "Panggilan belum aktif"),
# kunci app tidak sama dengan kunci server (token ditolak), skema
# `call_sessions` kurang kolom (500 tanpa badan), atau permintaan tidak berstatus
# Accepted (403). Yang tidak diperiksa di sini hanyalah pengambilan gambar/suara
# dari kamera dan mikrofon; itu baru muncul saat dua jendela peramban dibuka.
#
# Status akhir: `CALL_CHECK_RESULT=PASS` dan exit 0, atau pesan GAGAL + exit 1.
# Panggilan selalu diakhiri di akhir pemeriksaan, jadi aman dijalankan ulang.

set -u

PORTAL=${PORTAL_URL:-http://127.0.0.1:8080}
LIVEKIT=${LIVEKIT_URL:-http://127.0.0.1:7880}
DB_NAME="doclinc_stage_local_test"
DB_USER="doclinc"
DB_PASS="doclinclokal"
PASSWORD='Lokal#2026!'

CALL_TYPE='video'
REQUEST_ID=''

while [ $# -gt 0 ]; do
	case "$1" in
		--request-id) REQUEST_ID=${2:-} ; shift 2 ;;
		--call-type) CALL_TYPE=${2:-video} ; shift 2 ;;
		-h|--help) sed -n '2,21p' "$0" | sed 's/^# \{0,1\}//' ; exit 0 ;;
		*) echo "argumen tidak dikenal: $1" >&2 ; exit 2 ;;
	esac
done

fail() {
	echo "GAGAL: $*" >&2
	echo "CALL_CHECK_RESULT=FAIL"
	exit 1
}

sql() {
	docker exec doclinc-db mariadb -u"$DB_USER" -p"$DB_PASS" -N -B -e "$1" "$DB_NAME" 2>/dev/null
}

if ! curl -s -o /dev/null -m 5 "$LIVEKIT/" 2>/dev/null; then
	fail "LiveKit tidak menjawab di ${LIVEKIT} (jalankan up.sh tanpa --no-calls)"
fi

# Permintaan uji: milik warga ber-NIK, berstatus Accepted, dengan nakes
# penanggung jawab. Fixture portal membuat satu bila belum ada.
if [ -z "$REQUEST_ID" ]; then
	REQUEST_ID=$(sql "SELECT request_id FROM requests
		WHERE request_status='Accepted' AND responsible_doctor_user_id IS NOT NULL
		ORDER BY request_id DESC LIMIT 1")
fi
[ -n "$REQUEST_ID" ] || fail "tidak ada permintaan Accepted; jalankan tools/local_stack/up.sh"

ROW=$(sql "SELECT u.username, d.username
	FROM requests r
	JOIN users u ON u.userId = r.user_id
	LEFT JOIN users d ON d.userId = r.responsible_doctor_user_id
	WHERE r.request_id = ${REQUEST_ID} AND r.request_status='Accepted'")
[ -n "$ROW" ] || fail "permintaan #${REQUEST_ID} tidak ditemukan atau tidak Accepted"
WARGA=$(printf '%s' "$ROW" | cut -f1)
NAKES=$(printf '%s' "$ROW" | cut -f2)
[ -n "$WARGA" ] && [ -n "$NAKES" ] || fail "permintaan #${REQUEST_ID} belum punya warga/nakes penanggung jawab"


# login <halaman masuk> <path auth> <username> <jar> -> jawaban auth ('1' = sukses)
login() {
	login_path=$1
	auth_path=$2
	username=$3
	jar=$4
	page=$(mktemp)
	curl -s -m 15 -c "$jar" -b "$jar" "${PORTAL}${login_path}" -o "$page" || true
	token=$(awk '$6 == "doclinc_csrf_cookie" {print $7}' "$jar" | tail -1)
	if [ -z "$token" ]; then
		rm -f "$page"
		echo 'no-csrf'
		return 0
	fi
	answer=$(curl -s -m 20 -c "$jar" -b "$jar" \
		--data-urlencode "username=$username" \
		--data-urlencode "password=$PASSWORD" \
		--data-urlencode "doclinc_csrf_token=$token" \
		"${PORTAL}${auth_path}" 2>/dev/null || true)
	rm -f "$page"
	echo "$answer"
}

# csrf <jar> <halaman> -> token dari cookie (double-submit) atau meta halaman
csrf() {
	jar=$1
	page=$2
	token=$(awk '$6 == "doclinc_csrf_cookie" {print $7}' "$jar" | tail -1)
	if [ -n "$token" ]; then
		echo "$token"
		return 0
	fi
	grep -o 'name="doclinc-csrf-token" content="[a-f0-9]*"' "$page" 2>/dev/null \
		| head -1 | sed 's/.*content="//;s/"//'
}

jar_nakes=$(mktemp)
jar_warga=$(mktemp)
page=$(mktemp)

echo "-- masuk sebagai nakes (${NAKES}) dan warga (${WARGA}) untuk permintaan #${REQUEST_ID}"
[ "$(login /login-nakes /login-nakes/auth "$NAKES" "$jar_nakes")" = "1" ] || fail "gagal masuk sebagai ${NAKES}"
[ "$(login /login /login/auth "$WARGA" "$jar_warga")" = "1" ] || fail "gagal masuk sebagai ${WARGA}"

curl -s -m 20 -c "$jar_nakes" -b "$jar_nakes" "${PORTAL}/chat?request_id=${REQUEST_ID}" -o "$page" || true
token_nakes=$(csrf "$jar_nakes" "$page")
curl -s -m 20 -c "$jar_warga" -b "$jar_warga" "${PORTAL}/chat?request_id=${REQUEST_ID}" -o "$page" || true
token_warga=$(csrf "$jar_warga" "$page")
[ -n "$token_nakes" ] && [ -n "$token_warga" ] || fail "token CSRF tidak ditemukan di halaman chat"

echo "-- nakes memulai panggilan ${CALL_TYPE}"
start=$(curl -s -m 20 -c "$jar_nakes" -b "$jar_nakes" \
	--data-urlencode "request_id=${REQUEST_ID}" \
	--data-urlencode "call_type=${CALL_TYPE}" \
	--data-urlencode "doclinc_csrf_token=${token_nakes}" \
	"${PORTAL}/home_nakes/start_livekit_call" 2>/dev/null || true)
case "$start" in
	*'"success":true'*) ;;
	*) fail "start_livekit_call menjawab: $(printf '%s' "$start" | head -c 240)" ;;
esac

call_id=$(printf '%s' "$start" | sed -n 's/.*"call_id":\([0-9]*\).*/\1/p')
token=$(printf '%s' "$start" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
[ -n "$call_id" ] || fail "jawaban start_livekit_call tanpa call_id"
[ -n "$token" ] || fail "jawaban start_livekit_call tanpa token LiveKit"
echo "   call#${call_id} dibuat, token nakes diterbitkan"

validate_token() {
	label=$1
	jwt=$2
	answer=$(curl -s -m 10 --get --data-urlencode "access_token=$jwt" "${LIVEKIT}/rtc/validate" 2>/dev/null || true)
	case "$answer" in
		*success*) echo "   token ${label}: diterima LiveKit" ;;
		*) fail "token ${label} ditolak LiveKit: $(printf '%s' "$answer" | head -c 200)" ;;
	esac
}
validate_token "nakes" "$token"

echo "-- warga memeriksa panggilan masuk"
incoming=$(curl -s -m 15 -c "$jar_warga" -b "$jar_warga" \
	"${PORTAL}/home/livekit_incoming_call?request_id=${REQUEST_ID}" 2>/dev/null || true)
case "$incoming" in
	*'"has_incoming":true'*) echo "   panggilan masuk terlihat oleh warga" ;;
	*'"status":"answered"'*) echo "   panggilan sudah terjawab dari pemeriksaan sebelumnya" ;;
	*) fail "livekit_incoming_call menjawab: $(printf '%s' "$incoming" | head -c 240)" ;;
esac

case "$incoming" in
	*'"has_incoming":true'*)
		echo "-- warga menjawab"
		answer=$(curl -s -m 20 -c "$jar_warga" -b "$jar_warga" \
			--data-urlencode "call_id=${call_id}" \
			--data-urlencode "request_id=${REQUEST_ID}" \
			--data-urlencode "doclinc_csrf_token=${token_warga}" \
			"${PORTAL}/home/answer_livekit_call" 2>/dev/null || true)
		case "$answer" in
			*'"success":true'*) ;;
			*) fail "answer_livekit_call menjawab: $(printf '%s' "$answer" | head -c 240)" ;;
		esac
		token=$(printf '%s' "$answer" | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
		[ -n "$token" ] || fail "jawaban answer_livekit_call tanpa token LiveKit"
		validate_token "warga" "$token"
		;;
esac

echo "-- nakes mengakhiri panggilan"
end=$(curl -s -m 20 -c "$jar_nakes" -b "$jar_nakes" \
	--data-urlencode "call_id=${call_id}" \
	--data-urlencode "request_id=${REQUEST_ID}" \
	--data-urlencode "doclinc_csrf_token=${token_nakes}" \
	"${PORTAL}/home_nakes/end_livekit_call" 2>/dev/null || true)
case "$end" in
	*'"status":"ended"'*|*'"status":"missed"'*) echo "   panggilan ditutup" ;;
	*) echo "   peringatan: end_livekit_call menjawab $(printf '%s' "$end" | head -c 200)" >&2 ;;
esac

rm -f "$jar_nakes" "$jar_warga" "$page"
echo "CALL_CHECK_RESULT=PASS"
exit 0

