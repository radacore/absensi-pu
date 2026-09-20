<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\ToleranceClaim;
use App\Support\AdminPresenter;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LoveController extends Controller
{
    private function scope(): ?int
    {
        $u = Auth::guard('web')->user();

        return $u->role === 'super_admin' ? null : $u->region_id;
    }

    public function index()
    {
        $scope = $this->scope();

        return Inertia::render('Admin/Love', [
            'regions' => AdminPresenter::regionsFor($scope),
            'claims' => AdminPresenter::lovesFor($scope),
            'settings' => AdminPresenter::settingsFor(),
        ]);
    }

    public function approve(ToleranceClaim $love)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $love->region_id !== $scope, 403);
        if ($love->status !== 'pending') {
            return back()->with('error', 'Hanya pending bisa di-approve.');
        }

        // Approve + tautkan ke baris absensi tanggal klaim (atomik).
        $row = DB::transaction(function () use ($love) {
            $love->status = 'approved';
            $love->save();

            return $this->tautkanKeAbsensi($love);
        });

        $empName = $love->employee?->name ?? '-';
        Audit::log(
            'love.approve',
            subject: $love,
            label: "Toleransi #{$love->id} {$empName}",
            description: "Approve klaim toleransi {$empName} ({$love->jenis})",
            meta: [
                'jenis' => $love->jenis,
                'claim_date' => (string) $love->claim_date,
                'attendance_id' => $row->id,
                'attendance_status' => $row->status,
            ]
        );

        return back()->with('success', "Toleransi disetujui — tertaut ke absensi {$love->claim_date->format('d/m/Y')} (status: {$row->status})");
    }

    public function reject(Request $request, ToleranceClaim $love)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $love->region_id !== $scope, 403);
        if ($love->status !== 'pending') {
            return back()->with('error', 'Hanya pending bisa ditolak.');
        }
        $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);

        DB::transaction(function () use ($request, $love) {
            $love->status = 'rejected';
            $love->note = $request->input('note');
            $love->save();

            // Jangan tinggalkan tautan ke klaim yang ditolak.
            $this->lepasDariAbsensi($love);
        });

        $empName = $love->employee?->name ?? '-';
        Audit::log(
            'love.reject',
            subject: $love,
            label: "Toleransi #{$love->id} {$empName}",
            description: "Tolak klaim toleransi {$empName}",
            meta: ['note' => $love->note, 'jenis' => $love->jenis]
        );

        return back()->with('success', 'Toleransi ditolak');
    }

    public function destroy(ToleranceClaim $love)
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $love->region_id !== $scope, 403);
        $empName = $love->employee?->name ?? '-';
        $snapshot = ['jenis' => $love->jenis, 'status' => $love->status, 'claim_date' => (string) $love->claim_date];

        DB::transaction(function () use ($love) {
            // Lepas dulu (FK nullOnDelete bekerja di level DB dan TIDAK memicu
            // event model, jadi status absensi harus dirapikan manual di sini).
            $this->lepasDariAbsensi($love);
            $love->delete();
        });

        Audit::log(
            'love.delete',
            subject: null,
            label: "Toleransi {$empName}",
            description: "Hapus klaim toleransi {$empName}",
            meta: $snapshot
        );

        return back()->with('success', 'Klaim toleransi dihapus');
    }

    // ── Penautan klaim toleransi ↔ baris absensi ────────────────────────────

    /**
     * Klaim yang disetujui mengisi baris absensi tanggal klaim:
     *  - lupa_absen  → isi clock_in_at dari jam klaim, status 'excused_love'.
     *  - lupa_pulang → isi clock_out_at dari jam klaim; status 'early_leave'
     *                  bila jam klaim lebih awal dari jam_pulang.
     * Kalau baris absensi belum ada (karyawan benar-benar lupa absen),
     * baris dibuat supaya persetujuan punya jejak di rekap.
     */
    private function tautkanKeAbsensi(ToleranceClaim $love): Attendance
    {
        $row = Attendance::where('employee_id', $love->employee_id)
            ->whereDate('work_date', $love->claim_date)
            ->first();

        if (! $row) {
            $row = new Attendance([
                'employee_id' => $love->employee_id,
                'work_date' => $love->claim_date,
                'site_id' => $love->site_id,
                'region_id' => $love->region_id,
            ]);
        }

        $jam = $love->jam ? substr((string) $love->jam, 0, 5).':00' : null;

        if ($love->jenis === 'lupa_absen') {
            if (! $row->clock_in_at && $jam) {
                $row->clock_in_at = $jam;
            }
            $row->status = 'excused_love';
        } else {
            if (! $row->clock_out_at && $jam) {
                $row->clock_out_at = $jam;
            }
            $jamPulang = substr((string) (AttendanceSetting::first()?->jam_pulang ?? '16:00:00'), 0, 5);
            $keluar = substr((string) $row->clock_out_at, 0, 5);
            $row->status = ($keluar !== '' && $keluar < $jamPulang)
                ? 'early_leave'
                : ($row->status ?: 'on_time');
        }

        $row->tolerance_claim_id = $love->id;
        $row->save();

        return $row;
    }

    /**
     * Kebalikan dari tautkanKeAbsensi(): hapus tautan dan pulihkan status.
     * Baris yang lahir semata-mata dari approve klaim (tanpa GPS/selfie) ikut dihapus.
     */
    private function lepasDariAbsensi(ToleranceClaim $love): void
    {
        $row = Attendance::where('tolerance_claim_id', $love->id)->first();
        if (! $row) {
            return;
        }

        if ($this->barisSintetis($row)) {
            $row->delete();

            return;
        }

        $row->tolerance_claim_id = null;
        $row->status = $this->statusDariJamMasuk($row);
        $row->save();
    }

    /**
     * Baris absensi yang dibuat oleh approve klaim tidak punya data GPS
     * (lat_in/lng_in/distance_in_m selalu terisi pada clock-in asli).
     */
    private function barisSintetis(Attendance $row): bool
    {
        return $row->lat_in === null && $row->lng_in === null && $row->distance_in_m === null;
    }

    /** Hitung ulang on_time/late dari clock_in_at + pengaturan global. */
    private function statusDariJamMasuk(Attendance $row): string
    {
        if (! $row->clock_in_at) {
            return 'on_time';
        }

        $s = AttendanceSetting::first();
        $jamMasuk = substr((string) ($s?->jam_masuk ?? '07:30:00'), 0, 5);
        $tol = (int) ($s?->toleransi_late_menit ?? 15);

        [$h, $m] = array_map('intval', explode(':', $jamMasuk));
        $cutoff = $h * 60 + $m + $tol;

        $masuk = substr((string) $row->clock_in_at, 0, 5);
        [$ih, $im] = array_map('intval', explode(':', $masuk));

        return ($ih * 60 + $im) > $cutoff ? 'late' : 'on_time';
    }
}
