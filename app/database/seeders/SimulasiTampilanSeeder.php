<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\DinasClaim;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\ShiftPattern;
use App\Models\ShiftSchedule;
use App\Models\ToleranceClaim;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Data contoh satu bulan penuh untuk keperluan demo & pemeriksaan tampilan.
 *
 * Mengisi Agustus 2026 untuk dua karyawan (Andi = jam kerja tetap,
 * Budi = pekerja shift) dengan seluruh jenis baris rekap: tepat waktu,
 * terlambat, toleransi, PSW, lupa absen (dispensasi), cuti, dinas, libur
 * nasional, tanpa keterangan, dan catatan pekerjaan.
 *
 * Dijalankan manual — tidak dipanggil DatabaseSeeder:
 *   php artisan db:seed --class=SimulasiTampilanSeeder
 *
 * Hanya boleh jalan di lingkungan lokal/pengujian: seeder ini menulis data
 * absensi contoh, jadi sengaja ditolak di produksi.
 */
class SimulasiTampilanSeeder extends Seeder
{
    private const BULAN = '2026-08';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('SimulasiTampilanSeeder hanya untuk lokal/pengujian, bukan produksi.');

            return;
        }

        $andi = Employee::where('email', 'andi@bbws-pj.go.id')->firstOrFail();
        $budi = Employee::where('email', 'budi@bbws-pj.go.id')->firstOrFail();
        $admin = User::where('role', 'super_admin')->firstOrFail();
        $disk = config('filesystems.uploads.private_disk');

        $this->bersihkan($andi, $budi);

        $selfie = function (string $tgl, string $nama) use ($disk): string {
            $img = imagecreatetruecolor(240, 320);
            imagefilledrectangle($img, 0, 0, 240, 320, imagecolorallocate($img, 205, 222, 240));
            imagefilledellipse($img, 120, 120, 120, 120, imagecolorallocate($img, 140, 170, 205));
            ob_start();
            imagejpeg($img, null, 80);
            $j = (string) ob_get_clean();
            imagedestroy($img);
            $p = "selfie/{$tgl}/{$nama}.jpg";
            Storage::disk($disk)->put($p, $j);

            return $p;
        };

        $absen = function (Employee $e, int $hari, string $masuk, ?string $pulang, int $jarak, ?string $catatan, ?string $status = null, bool $gpsMasuk = true, bool $gpsPulang = true) use ($selfie): Attendance {
            $tgl = sprintf('%s-%02d', self::BULAN, $hari);

            return Attendance::create([
                'employee_id' => $e->id,
                'work_date' => $tgl,
                'clock_in_at' => $masuk,
                'clock_out_at' => $pulang,
                'status' => $status ?? 'on_time',
                'lat_in' => $gpsMasuk ? $e->site->lat : null,
                'lng_in' => $gpsMasuk ? $e->site->lng : null,
                'lat_out' => ($pulang && $gpsPulang) ? $e->site->lat + 0.0002 : null,
                'lng_out' => ($pulang && $gpsPulang) ? $e->site->lng : null,
                'distance_in_m' => $gpsMasuk ? $jarak : null,
                'selfie_url' => $gpsMasuk ? $selfie($tgl, 'masuk') : null,
                'selfie_pulang_url' => ($pulang && $gpsPulang) ? $selfie($tgl, 'pulang') : null,
                'catatan_pulang' => $catatan,
                'site_id' => $e->site_id,
                'region_id' => $e->region_id,
            ]);
        };

        // ── Andi: pekerja jam tetap, sebulan penuh ──────────────────
        $absen($andi, 3, '07:20:11', '16:05:40', 14, 'Laporan harian jalan');
        $absen($andi, 4, '07:50:02', '16:10:00', 18, null);
        $absen($andi, 5, '07:42:30', '16:02:10', 11, null);
        $absen($andi, 6, '07:25:00', '15:30:00', 9, 'Pulang untuk rapat luar');
        $absen($andi, 7, '07:28:45', '16:00:00', 13, null);
        $absen($andi, 10, '07:15:20', '16:10:30', 10, 'Rekap mingguan 1');
        // 11 Agustus kosong → tanpa keterangan
        $absen($andi, 12, '07:30:00', null, 15, null);

        Leave::create([
            'employee_id' => $andi->id, 'jenis' => 'Tahunan',
            'mulai' => '2026-08-13', 'selesai' => '2026-08-14',
            'alasan' => 'Cuti tahunan keluarga', 'status' => 'Disetujui', 'level' => 3,
        ]);

        // 18–19 dinas disetujui
        DinasClaim::create([
            'employee_id' => $andi->id, 'nomor_surat' => '094/BWSPJ/VIII/2026',
            'tanggal_mulai' => '2026-08-18', 'tanggal_selesai' => '2026-08-19',
            'tanggal_pengajuan' => '2026-08-12', 'keterangan' => 'Pendampingan teknis bendungan',
            'tujuan' => 'Kab. Bone', 'transportasi' => 'Mobil dinas',
            'pembebanan_anggaran' => 'DPA 2026 — Bidang Jalan', 'status' => 'Disetujui',
        ]);

        $absen($andi, 20, '07:20:05', '16:05:00', 12, 'Pengecekan saluran');

        // 21 lupa absen datang — jam masuk dari klaim, tanpa GPS
        $row21 = $absen($andi, 21, '07:25:00', '16:00:00', 0, 'Pengukuran debit sungai', 'excused_love', gpsMasuk: false);
        $klaim21 = ToleranceClaim::create([
            'employee_id' => $andi->id, 'jenis' => 'lupa_absen', 'claim_date' => '2026-08-21',
            'jam' => '07:25:00', 'alasan' => 'Lupa melakukan absen saat bertugas di lapangan',
            'site_id' => $andi->site_id, 'region_id' => $andi->region_id,
            'approver_id' => $admin->id, 'status' => 'approved',
        ]);
        $row21->update(['tolerance_claim_id' => $klaim21->id]);

        $absen($andi, 24, '08:25:00', '16:00:00', 17, 'Koordinasi lapangan');

        // 25 lupa absen pulang — jam pulang dari klaim, tanpa GPS
        $row25 = $absen($andi, 25, '07:20:30', '14:00:00', 10, null, 'early_leave', gpsPulang: false);
        $klaim25 = ToleranceClaim::create([
            'employee_id' => $andi->id, 'jenis' => 'lupa_pulang', 'claim_date' => '2026-08-25',
            'jam' => '14:00:00', 'alasan' => 'Lupa absen pulang karena kegiatan di lapangan',
            'site_id' => $andi->site_id, 'region_id' => $andi->region_id,
            'approver_id' => $admin->id, 'status' => 'approved',
        ]);
        $row25->update(['tolerance_claim_id' => $klaim25->id]);

        $absen($andi, 26, '07:22:10', '16:03:00', 8, 'Dokumentasi progres');
        $absen($andi, 27, '07:26:55', '16:04:20', 19, null);
        $absen($andi, 31, '07:18:40', '16:06:00', 11, 'Serah terima berkas');

        // ── Budi: pekerja shift, jadwal separuh bulan ───────────────
        $budi->update(['is_shift' => true, 'regu' => 'Regu B']);
        $pola = ShiftPattern::create([
            'nama' => 'Pola 3 Shift', 'jumlah_shift' => 3, 'jam_mulai_shift_1' => '07:00',
        ]);

        $jamMasuk = [1 => '06:55:00', 2 => '15:20:00', 3 => '22:55:00', 6 => '23:02:00'];
        $jamPulang = [1 => '15:05:00', 2 => '23:10:00', 3 => '07:05:00', 6 => '07:00:00'];

        for ($hari = 1; $hari <= 14; $hari++) {
            if ($hari % 4 === 0) {
                ShiftSchedule::create([
                    'employee_id' => $budi->id, 'tanggal' => sprintf('%s-%02d', self::BULAN, $hari),
                    'shift_ke' => null, 'shift_pattern_id' => $pola->id,
                ]);

                continue;
            }
            $shiftKe = match ($hari % 3) {
                1 => 1, 2 => 2, default => 3,
            };
            ShiftSchedule::create([
                'employee_id' => $budi->id, 'tanggal' => sprintf('%s-%02d', self::BULAN, $hari),
                'shift_ke' => $shiftKe, 'shift_pattern_id' => $pola->id,
            ]);
            if (isset($jamMasuk[$hari])) {
                $absen($budi, $hari, $jamMasuk[$hari], $jamPulang[$hari], 10, 'Pemeriksaan rutin');
            }
        }

        $this->command?->info('Data tampilan Agustus 2026 siap: Andi (jam tetap) & Budi (shift).');
    }

    /** Buang data seeder ini sebelumnya supaya aman dijalankan berulang. */
    private function bersihkan(Employee $andi, Employee $budi): void
    {
        $mulai = self::BULAN.'-01';
        $selesai = self::BULAN.'-31';

        foreach ([$andi, $budi] as $e) {
            Attendance::where('employee_id', $e->id)
                ->whereDate('work_date', '>=', $mulai)
                ->whereDate('work_date', '<=', $selesai)
                ->delete();
            Leave::where('employee_id', $e->id)->whereBetween('mulai', [$mulai, $selesai])->delete();
            DinasClaim::where('employee_id', $e->id)->whereBetween('tanggal_mulai', [$mulai, $selesai])->delete();
            ShiftSchedule::where('employee_id', $e->id)
                ->whereDate('tanggal', '>=', $mulai)
                ->whereDate('tanggal', '<=', $selesai)
                ->delete();
        }
        ToleranceClaim::whereBetween('claim_date', [$mulai, $selesai])->delete();
    }
}
