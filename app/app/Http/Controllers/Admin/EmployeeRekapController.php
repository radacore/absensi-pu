<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\Audit;
use App\Support\RekapPdf;
use App\Support\RekapPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EmployeeRekapController extends Controller
{
    public function show(Request $request, Employee $employee)
    {
        $u = Auth::guard('web')->user();
        $scope = $u->role === 'super_admin' ? null : $u->region_id;
        abort_if($scope !== null && (int) $employee->region_id !== $scope, 403, 'Karyawan ini di luar cakupan Anda.');

        $employee->load(['region', 'site']);

        $data = $request->validate([
            'bulan' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $bulan = $data['bulan'] ?? now('Asia/Makassar')->format('Y-m');
        [$year, $month] = array_map('intval', explode('-', $bulan));

        $rekap = RekapPresenter::buildRekap($employee, $year, $month);

        Audit::log(
            'employee.rekap.view',
            subject: $employee,
            label: $employee->name,
            description: "Buka rekap presensi {$employee->name} untuk {$rekap['periode']['label']}",
            meta: ['periode' => $bulan]
        );

        return Inertia::render('Admin/EmployeeRekap', [
            'rekap' => $rekap,
        ]);
    }

    /**
     * Unduh rekap presensi perorangan satu bulan sebagai berkas CSV.
     *
     * Pemisah kolom memakai titik-koma karena Excel berbahasa Indonesia
     * memakai ';' sebagai pemisah daftar. Ubah CSV_SEPARATOR bila perlu.
     */
    private const CSV_SEPARATOR = ';';

    public function download(Request $request, Employee $employee)
    {
        $u = Auth::guard('web')->user();
        $scope = $u->role === 'super_admin' ? null : $u->region_id;
        abort_if($scope !== null && (int) $employee->region_id !== $scope, 403, 'Karyawan ini di luar cakupan Anda.');

        $employee->load(['region', 'site']);

        $data = $request->validate([
            'bulan' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $bulan = $data['bulan'] ?? now('Asia/Makassar')->format('Y-m');
        [$year, $month] = array_map('intval', explode('-', $bulan));

        $rekap = RekapPresenter::buildRekap($employee, $year, $month);

        Audit::log(
            'employee.rekap.download',
            subject: $employee,
            label: $employee->name,
            description: "Unduh rekap presensi {$employee->name} untuk {$rekap['periode']['label']}",
            meta: ['periode' => $bulan]
        );

        $namaBerkas = 'rekap-presensi-'.$employee->nik.'-'.$bulan.'.csv';

        return response()->streamDownload(function () use ($rekap) {
            $out = fopen('php://output', 'w');

            // BOM supaya Excel membaca aksara ber-aksen/UTF-8 dengan benar.
            fwrite($out, "\xEF\xBB\xBF");

            $tulis = function (array $kolom) use ($out) {
                fputcsv($out, $kolom, self::CSV_SEPARATOR);
            };

            $k = $rekap['karyawan'];
            $p = $rekap['periode'];
            $d = $rekap['distribusi'];
            $m = $rekap['metrikHadir'];

            $tulis(['Rekap Presensi Perorangan']);
            $tulis([]);
            $tulis(['Nama', $k['nama']]);
            $tulis(['NIK', $k['nik']]);
            $tulis(['NIP', $k['nip']]);
            $tulis(['Jabatan', $k['jabatan']]);
            $tulis(['Golongan', $k['golongan']]);
            $tulis(['Status kepegawaian', $k['status_kepegawaian']]);
            $tulis(['Unit kerja', $k['unit_kerja']]);
            $tulis(['Wilayah', $k['region']]);
            $tulis(['Lokasi kerja', $k['site']]);
            $tulis(['Periode', $p['label']]);
            $tulis([]);

            $tulis(['Ringkasan']);
            $tulis(['Hadir', $d['hadir']]);
            $tulis(['Dinas', $d['dinas']]);
            $tulis(['Cuti', $d['cuti']]);
            $tulis(['Tanpa keterangan', $d['tanpa_keterangan']]);
            $tulis([]);

            $tulis(['Rincian kehadiran']);
            $tulis(['Tepat waktu', $m['tepat_waktu']]);
            $tulis(['Toleransi terlambat', $m['toleransi_terlambat']]);
            $tulis(['Terlambat', $m['terlambat']]);
            $tulis(['Total hadir', $m['total']]);
            $tulis([]);

            // Kolom Shift hanya muncul untuk pekerja shift, sama seperti di
            // layar dan di PDF, supaya berkas tidak berbeda isi.
            $isShift = (bool) $k['is_shift'];
            $tulis(array_merge(['Tanggal', 'Hari'], $isShift ? ['Shift'] : [], ['Jam masuk', 'Jam pulang', 'Keterangan', 'Catatan pekerjaan']));
            foreach ($rekap['harian'] as $hari) {
                $shift = $hari['is_libur_jadwal'] ? 'Libur' : ($hari['shift'] ?? 'Belum dijadwalkan');
                $tulis(array_merge(
                    [$hari['iso'], $hari['hari']],
                    $isShift ? [$shift] : [],
                    [$hari['jam_masuk'], $hari['jam_pulang'], (string) ($hari['catatan'] ?? ''), (string) ($hari['catatan_pulang'] ?? '')],
                ));
            }

            fclose($out);
        }, $namaBerkas, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Unduh rekap presensi perorangan satu bulan sebagai berkas PDF (A4 landscape).
     *
     * Berbeda dari tombol "Cetak" di layar (yang memakai dialog cetak peramban),
     * endpoint ini menghasilkan berkas PDF asli di server sehingga bisa
     * dilampirkan langsung tanpa langkah "Simpan sebagai PDF".
     */
    public function pdf(Request $request, Employee $employee)
    {
        $u = Auth::guard('web')->user();
        $scope = $u->role === 'super_admin' ? null : $u->region_id;
        abort_if($scope !== null && (int) $employee->region_id !== $scope, 403, 'Karyawan ini di luar cakupan Anda.');

        $employee->load(['region', 'site']);

        $data = $request->validate([
            'bulan' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $bulan = $data['bulan'] ?? now('Asia/Makassar')->format('Y-m');
        [$year, $month] = array_map('intval', explode('-', $bulan));

        $rekap = RekapPresenter::buildRekap($employee, $year, $month);

        Audit::log(
            'employee.rekap.pdf',
            subject: $employee,
            label: $employee->name,
            description: "Unduh PDF rekap presensi {$employee->name} untuk {$rekap['periode']['label']}",
            meta: ['periode' => $bulan]
        );

        return RekapPdf::unduh($rekap, $bulan);
    }
}
