#!/usr/bin/env bash
#
# Simulasi HTTP end-to-end — BBWS Pompengan Jeneberang (Absensi & HR)
#
# Berbeda dari `php artisan test` (yang memakai kernel tes), skrip ini menembak
# server yang benar-benar berjalan lewat HTTP: middleware, CSRF, sesi, Inertia,
# MySQL, dan object storage S3 semuanya ikut teruji apa adanya.
#
# Pakai:
#   1. Siapkan basis data bersih:
#        DB_CONNECTION=mysql DB_DATABASE=absensi_sim DB_USERNAME=absensi_pu \
#        DB_PASSWORD=absensi_dev_pass php artisan migrate:fresh --seed --force
#   2. Jalankan server:
#        DB_CONNECTION=mysql DB_DATABASE=absensi_sim DB_USERNAME=absensi_pu \
#        DB_PASSWORD=absensi_dev_pass php artisan serve --host=127.0.0.1 --port=8392 &
#   3. Jalankan skrip:
#        bash scripts/simulasi-http.sh
#
# Variabel opsional: BASE (default http://127.0.0.1:8392), DB (default absensi_sim)

set -uo pipefail

BASE="${BASE:-http://127.0.0.1:8392}"
DB="${DB:-absensi_sim}"
DB_USER="${DB_USER:-absensi_pu}"
DB_PASS="${DB_PASS:-absensi_dev_pass}"
MYSQL="${MYSQL:-/usr/local/bin/mysql}"
PY="${PY:-/Users/rada/.workbuddy-ai/binaries/python/versions/3.13.12/bin/python3}"

JAR="$(mktemp)"
BODY="$(mktemp)"
PDF="$(mktemp -u).pdf"
PASS=0
FAIL=0

cleanup() { rm -f "$JAR" "$BODY" "$PDF"; }
trap cleanup EXIT

sql() { "$MYSQL" -h 127.0.0.1 -u "$DB_USER" -p"$DB_PASS" --skip-ssl -N -e "$1" 2>/dev/null; }

csrf() {
  grep XSRF-TOKEN "$JAR" 2>/dev/null | awk '{print $NF}' | tail -1 | \
    "$PY" -c 'import sys,urllib.parse;print(urllib.parse.unquote(sys.stdin.read().strip()))'
}

# req METHOD PATH [curl args...] → mencetak kode status HTTP
# CATATAN CSRF: cukup pakai header X-XSRF-TOKEN berisi nilai cookie XSRF-TOKEN
# yang sudah di-urldecode. JANGAN menambahkan field `_token` dengan nilai yang
# sama — Laravel membaca `_token` APA ADANYA (tanpa dekripsi), sedangkan nilai
# cookie itu terenkripsi, sehingga hasilnya 419 CSRF mismatch.

req() {
  local method="$1" path="$2"; shift 2
  curl -s -o "$BODY" -w '%{http_code}' --noproxy '*' \
    -b "$JAR" -c "$JAR" -X "$method" \
    -H "X-XSRF-TOKEN: $(csrf)" \
    -H "X-Requested-With: XMLHttpRequest" \
    "$@" "$BASE$path"
}

check() { # label harapan dapat
  if [ "$2" = "$3" ]; then
    printf '  \033[32mOK\033[0m   %-58s %s\n' "$1" "$3"; PASS=$((PASS+1))
  else
    printf '  \033[31mFAIL\033[0m %-58s harap %s, dapat %s\n' "$1" "$2" "$3"; FAIL=$((FAIL+1))
  fi
}

note() { printf '  \033[90m---  %s\033[0m\n' "$1"; }

# Berkas PDF minimal yang sah (dideteksi finfo sebagai application/pdf).
make_pdf() {
  cat > "$PDF" <<'PDF'
%PDF-1.4
1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj
3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 300 300]>>endobj
trailer<</Root 1 0 R>>
%%EOF
PDF
}

echo
echo "==================================================================="
echo " SIMULASI HTTP — $BASE  (DB: $DB)"
echo "==================================================================="

# ------------------------------------------------------------------
echo
echo "[1] Halaman publik & login"
# ------------------------------------------------------------------
code=$(curl -s -o /dev/null -w '%{http_code}' --noproxy '*' -c "$JAR" "$BASE/")
check "GET /" 200 "$code"
code=$(curl -s -o /dev/null -w '%{http_code}' --noproxy '*' -b "$JAR" -c "$JAR" "$BASE/karyawan/login")
check "GET /karyawan/login" 200 "$code"
code=$(curl -s -o /dev/null -w '%{http_code}' --noproxy '*' -b "$JAR" -c "$JAR" "$BASE/admin/login")
check "GET /admin/login" 200 "$code"
code=$(curl -s -o /dev/null -w '%{http_code}' --noproxy '*' -b "$JAR" -c "$JAR" "$BASE/super-admin/login")
check "GET /super-admin/login" 200 "$code"

code=$(req POST /karyawan/login \
  -d "login=7371001234567890" -d "password=password123")
check "POST /karyawan/login (NIK + sandi benar)" 302 "$code"

code=$(req GET /karyawan)
check "GET /karyawan (dasbor karyawan)" 200 "$code"
code=$(req GET /karyawan/absensi)
check "GET /karyawan/absensi" 200 "$code"
code=$(req GET /karyawan/cuti)
check "GET /karyawan/cuti" 200 "$code"
code=$(req GET /karyawan/love)
check "GET /karyawan/love" 200 "$code"
code=$(req GET /karyawan/dinas)
check "GET /karyawan/dinas" 200 "$code"
code=$(req GET /karyawan/pengumuman)
check "GET /karyawan/pengumuman" 200 "$code"
code=$(req GET /karyawan/profil)
check "GET /karyawan/profil" 200 "$code"
code=$(req GET /karyawan/rekap)
check "GET /karyawan/rekap" 200 "$code"

# ------------------------------------------------------------------
echo
echo "[2] Absensi GPS"
# ------------------------------------------------------------------
read -r SITE_LAT SITE_LNG SITE_RADIUS <<< "$(sql "SELECT lat, lng, radius_m FROM $DB.sites WHERE id = 201")"
note "titik 201: lat=$SITE_LAT lng=$SITE_LNG radius=${SITE_RADIUS}m"

# Hari ini mungkin sudah terisi dari simulasi sebelumnya → bersihkan dulu.
sql "DELETE FROM $DB.attendances WHERE employee_id = 1 AND work_date = CURDATE();" >/dev/null

code=$(req POST /karyawan/absensi/clock-in \
  -d "lat=$SITE_LAT" -d "lng=$SITE_LNG")
check "POST clock-in di dalam radius" 302 "$code"
rows=$(sql "SELECT COUNT(*) FROM $DB.attendances WHERE employee_id = 1 AND work_date = CURDATE();")
check "baris absensi terbentuk" 1 "$rows"

code=$(req POST /karyawan/absensi/clock-in \
  -d "lat=$(awk -v v="$SITE_LAT" 'BEGIN{printf "%.8f", v+1}')" -d "lng=$SITE_LNG")
# Request XHR yang gagal validasi membalas 422 (bukan 302), sesuai perilaku Laravel.
check "POST clock-in di luar radius ditolak" 422 "$code"
rows=$(sql "SELECT COUNT(*) FROM $DB.attendances WHERE employee_id = 1 AND work_date = CURDATE();")
check "tidak ada baris ganda" 1 "$rows"

code=$(req POST /karyawan/absensi/clock-out \
  -d "lat=$SITE_LAT" -d "lng=$SITE_LNG")
check "POST clock-out" 302 "$code"
pulang=$(sql "SELECT COUNT(*) FROM $DB.attendances WHERE employee_id = 1 AND work_date = CURDATE() AND clock_out_at IS NOT NULL;")
check "jam pulang tercatat" 1 "$pulang"

# ------------------------------------------------------------------
echo
echo "[2b] Gerbang absen di luar hari kerja (kebijakan Super Admin)"
# ------------------------------------------------------------------
sql "DELETE FROM $DB.attendances WHERE employee_id = 1 AND work_date = CURDATE();" >/dev/null
LIBUR_SEBELUM=$(sql "SELECT COUNT(*) FROM $DB.holidays WHERE tanggal = CURDATE();")
sql "INSERT INTO $DB.holidays (tanggal, nama, cuti_bersama, created_at, updated_at)
     VALUES (CURDATE(), 'Libur Uji HTTP', 0, NOW(), NOW())
     ON DUPLICATE KEY UPDATE nama = 'Libur Uji HTTP';" >/dev/null

sql "UPDATE $DB.attendance_settings SET absen_libur_aktif = 1, absen_libur_mode = 'tolak';" >/dev/null
code=$(req POST /karyawan/absensi/clock-in -d "lat=$SITE_LAT" -d "lng=$SITE_LNG")
check "mode tolak: clock-in di hari libur ditolak" 422 "$code"
rows=$(sql "SELECT COUNT(*) FROM $DB.attendances WHERE employee_id = 1 AND work_date = CURDATE();")
check "mode tolak: tidak ada baris absensi dibuat" 0 "$rows"

sql "UPDATE $DB.attendance_settings SET absen_libur_mode = 'catat';" >/dev/null
code=$(req POST /karyawan/absensi/clock-in -d "lat=$SITE_LAT" -d "lng=$SITE_LNG")
check "mode catat: clock-in tetap diterima" 302 "$code"
status=$(sql "SELECT status FROM $DB.attendances WHERE employee_id = 1 AND work_date = CURDATE() ORDER BY id DESC LIMIT 1;")
check "mode catat: status khusus 'libur'" "libur" "$status"

# Kembalikan pengaturan & bersihkan sisa uji
sql "UPDATE $DB.attendance_settings SET absen_libur_aktif = 0, absen_libur_mode = 'tolak';" >/dev/null
if [ "$LIBUR_SEBELUM" = "0" ]; then sql "DELETE FROM $DB.holidays WHERE tanggal = CURDATE();" >/dev/null; fi
sql "DELETE FROM $DB.attendances WHERE employee_id = 1 AND work_date = CURDATE();" >/dev/null

# ------------------------------------------------------------------
echo
echo "[3] Cuti"
# ------------------------------------------------------------------
sql "DELETE FROM $DB.leaves WHERE employee_id = 1;" >/dev/null
MULAI=$(date -v+3d +%Y-%m-%d 2>/dev/null || date -d '+3 days' +%Y-%m-%d)
SELESAI=$(date -v+5d +%Y-%m-%d 2>/dev/null || date -d '+5 days' +%Y-%m-%d)
APPROVER_GOWA=$(sql "SELECT id FROM $DB.users WHERE email = 'admin.gowa@bbws-pj.go.id' LIMIT 1;")
APPROVER_BONE=$(sql "SELECT id FROM $DB.users WHERE email = 'admin.bone@bbws-pj.go.id' LIMIT 1;")

# Approver cuti wajib akun admin yang berwenang di wilayah karyawan (kebijakan "admin saja").
code=$(req POST /karyawan/cuti \
  -d "jenis=Tahunan" -d "mulai=$MULAI" -d "selesai=$SELESAI" \
  -d "alasan=Uji approver admin luar wilayah" -d "approver_id=$APPROVER_BONE")
check "POST /karyawan/cuti approver admin luar wilayah ditolak" 422 "$code"

code=$(req POST /karyawan/cuti \
  -d "jenis=Tahunan" -d "mulai=$MULAI" -d "selesai=$SELESAI" \
  -d "alasan=Simulasi pengajuan cuti tahunan" -d "approver_id=$APPROVER_GOWA")
check "POST /karyawan/cuti (approver admin wilayah sendiri)" 302 "$code"
rows=$(sql "SELECT COUNT(*) FROM $DB.leaves WHERE employee_id = 1 AND status = 'Menunggu';")
check "cuti tersimpan sebagai Menunggu" 1 "$rows"
taut=$(sql "SELECT COUNT(*) FROM $DB.leaves WHERE employee_id = 1 AND approver_id = $APPROVER_GOWA;")
check "approver tercatat di baris cuti" 1 "$taut"

# ------------------------------------------------------------------
echo
echo "[4] Dinas + unggah dokumen (menulis ke object storage sungguhan)"
# ------------------------------------------------------------------
sql "DELETE FROM $DB.dinas_claims WHERE employee_id = 1;" >/dev/null
make_pdf
code=$(req POST /karyawan/dinas \
  -F "nomor_surat=SIM/HTTP/2026" \
  -F "tanggal_mulai=$MULAI" -F "tanggal_selesai=$SELESAI" \
  -F "keterangan=Simulasi unggah dokumen surat tugas" \
  -F "tujuan=Kota Makassar" -F "transportasi=Darat" \
  -F "pembebanan_anggaran=Simulasi" \
  -F "dokumen=@$PDF;type=application/pdf")
check "POST /karyawan/dinas (unggah PDF)" 302 "$code"

DINAS_ID=$(sql "SELECT id FROM $DB.dinas_claims WHERE employee_id = 1 ORDER BY id DESC LIMIT 1;")
DOK_PATH=$(sql "SELECT dokumen_path FROM $DB.dinas_claims WHERE id = ${DINAS_ID:-0};")
note "pengajuan id=$DINAS_ID path=$DOK_PATH"

if [ -n "${DINAS_ID:-}" ]; then
  code=$(req GET "/karyawan/dinas/$DINAS_ID/dokumen")
  check "GET unduh dokumen (pemilik)" 200 "$code"
  ctype=$(grep -i '^content-type' "$BODY" 2>/dev/null | head -1)
  note "dokumen tersimpan & tersaji (ukuran body: $(wc -c < "$BODY") byte)"
else
  check "pengajuan dinas terbentuk" "ada" "tidak ada"
fi

# ------------------------------------------------------------------
echo
echo "[5] Batas peran (karyawan tidak boleh menyentuh area admin)"
# ------------------------------------------------------------------
code=$(curl -s -o /dev/null -w '%{http_code}' --noproxy '*' -b "$JAR" "$BASE/admin")
check "GET /admin sebagai karyawan → dialihkan" 302 "$code"
code=$(curl -s -o /dev/null -w '%{http_code}' --noproxy '*' -b "$JAR" "$BASE/super-admin")
check "GET /super-admin sebagai karyawan → dialihkan" 302 "$code"

# ------------------------------------------------------------------
echo
echo "[6] Logout & login admin"
# ------------------------------------------------------------------
code=$(req POST /karyawan/logout)
check "POST /karyawan/logout" 302 "$code"

code=$(req POST /admin/login \
  -d "email=admin.gowa@bbws-pj.go.id" -d "password=password123")
check "POST /admin/login (admin wilayah)" 302 "$code"

for p in /admin /admin/employees /admin/regions /admin/attendances /admin/cuti \
         /admin/dinas /admin/love /admin/pengumuman /admin/settings; do
  code=$(req GET "$p")
  check "GET $p" 200 "$code"
done

code=$(req GET /admin/audit-log)
check "GET /admin/audit-log (khusus super admin) ditolak" 403 "$code"

# ------------------------------------------------------------------
echo
echo "[7] Approve cuti oleh admin (3 level)"
# ------------------------------------------------------------------
CUTI_ID=$(sql "SELECT id FROM $DB.leaves WHERE employee_id = 1 ORDER BY id DESC LIMIT 1;")
if [ -n "${CUTI_ID:-}" ]; then
  for lvl in 1 2 3; do
    code=$(req PUT "/admin/cuti/$CUTI_ID/approve")
    check "PUT approve cuti level $lvl" 302 "$code"
  done
  status=$(sql "SELECT status FROM $DB.leaves WHERE id = $CUTI_ID;")
  check "status cuti akhir" "Disetujui" "$status"
  pemutus=$(sql "SELECT COUNT(*) FROM $DB.leaves WHERE id = $CUTI_ID AND approved_by IS NOT NULL;")
  check "admin pemutus tercatat (approved_by)" 1 "$pemutus"
else
  check "cuti tersedia" "ada" "tidak ada"
fi

# ------------------------------------------------------------------
echo
echo "[7b] Klaim toleransi (love) → tertaut ke baris absensi"
# ------------------------------------------------------------------
EMP_ID=$(sql "SELECT id FROM $DB.employees WHERE region_id = 2 ORDER BY id LIMIT 1;")
LOVE_DATE=$(sql "SELECT DATE_FORMAT(CURDATE() - INTERVAL 3 DAY, '%Y-%m-%d');")
sql "DELETE FROM $DB.attendances WHERE employee_id = $EMP_ID AND work_date = '$LOVE_DATE';" >/dev/null
sql "DELETE FROM $DB.tolerance_claims WHERE employee_id = $EMP_ID;" >/dev/null
sql "INSERT INTO $DB.tolerance_claims
       (employee_id, jenis, claim_date, jam, alasan, site_id, region_id, approver_id, status, created_at, updated_at)
     SELECT e.id, 'lupa_absen', '$LOVE_DATE', '07:35:00', 'Uji tautan absensi',
            e.site_id, e.region_id, $APPROVER_GOWA, 'pending', NOW(), NOW()
       FROM $DB.employees e WHERE e.id = $EMP_ID;" >/dev/null
LOVE_ID=$(sql "SELECT id FROM $DB.tolerance_claims WHERE employee_id = $EMP_ID ORDER BY id DESC LIMIT 1;")
note "klaim id=$LOVE_ID tanggal=$LOVE_DATE (sebelum approve: $(sql "SELECT COUNT(*) FROM $DB.attendances WHERE employee_id = $EMP_ID AND work_date = '$LOVE_DATE';") baris absensi)"

code=$(req PUT "/admin/love/${LOVE_ID}/approve")
check "PUT approve klaim toleransi" 302 "$code"

status=$(sql "SELECT status FROM $DB.attendances WHERE employee_id = $EMP_ID AND work_date = '$LOVE_DATE' LIMIT 1;")
check "baris absensi dibuat dengan status excused_love" "excused_love" "$status"
taut=$(sql "SELECT COUNT(*) FROM $DB.attendances WHERE tolerance_claim_id = ${LOVE_ID:-0};")
check "klaim tertaut ke baris absensi (tolerance_claim_id)" 1 "$taut"

code=$(req DELETE "/admin/love/${LOVE_ID}")
check "DELETE klaim (baris sintetis ikut dibersihkan)" 302 "$code"
sisa=$(sql "SELECT COUNT(*) FROM $DB.attendances WHERE employee_id = $EMP_ID AND work_date = '$LOVE_DATE';")
check "baris sintetis terhapus, tidak jadi absensi palsu" 0 "$sisa"

# ------------------------------------------------------------------
echo
echo "[8] Super admin"
# ------------------------------------------------------------------
code=$(req POST /admin/logout)
check "POST /admin/logout" 302 "$code"

code=$(req POST /super-admin/login \
  -d "email=pusat@bbws-pj.go.id" -d "password=password123")
check "POST /super-admin/login" 302 "$code"

for p in /super-admin /super-admin/employees /super-admin/regions \
         /super-admin/holidays /super-admin/settings /super-admin/admin-wilayah \
         /super-admin/audit-log /super-admin/attendances; do
  code=$(req GET "$p")
  check "GET $p" 200 "$code"
done

# ------------------------------------------------------------------
echo
echo "[9] Membersihkan data simulasi"
# ------------------------------------------------------------------
code=$(req DELETE "/super-admin/dinas/$DINAS_ID")
check "DELETE pengajuan dinas (sekaligus objek S3)" 302 "$code"
sisa=$(sql "SELECT COUNT(*) FROM $DB.dinas_claims WHERE id = ${DINAS_ID:-0};")
check "pengajuan dinas terhapus dari basis data" 0 "$sisa"

echo
echo "==================================================================="
printf " SELESAI — \033[32m%d lulus\033[0m, \033[31m%d gagal\033[0m\n" "$PASS" "$FAIL"
echo "==================================================================="
echo

[ "$FAIL" -eq 0 ] || exit 1
