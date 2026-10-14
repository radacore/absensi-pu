<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use App\Support\CutiKuota;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function index()
    {
        $s = AttendanceSetting::first();
        $a = Auth::guard('web')->user();
        $isWilayah = $a?->role === 'admin_wilayah';

        return Inertia::render('Admin/Settings', [
            'settings' => $s ? [
                'jamMasuk' => substr((string) $s->jam_masuk, 0, 5),
                'jamPulang' => substr((string) $s->jam_pulang, 0, 5),
                'toleransi' => (int) $s->toleransi_late_menit,
                'loveMax' => (int) $s->love_max,
                'cutiTahunanMax' => CutiKuota::max($s),
                'cakupanPolaShift' => $s->cakupanPola(),
                'cutiShiftMax' => CutiKuota::maxShift($s),
                'absenLiburAktif' => (bool) $s->absen_libur_aktif,
                'absenLiburMode' => $s->absen_libur_mode ?: 'tolak',
                'hariKerja' => $s->hari_kerja ?? ['1', '2', '3', '4', '5'],
            ] : ['jamMasuk' => '07:30', 'jamPulang' => '16:00', 'toleransi' => 15, 'loveMax' => 4, 'cutiTahunanMax' => AttendanceSetting::CUTI_TAHUNAN_DEFAULT, 'cakupanPolaShift' => AttendanceSetting::CAKUPAN_POLA_DEFAULT, 'cutiShiftMax' => AttendanceSetting::CUTI_SHIFT_DEFAULT, 'absenLiburAktif' => false, 'absenLiburMode' => 'tolak', 'hariKerja' => ['1', '2', '3', '4', '5']],
            'cakupanPola' => AttendanceSetting::CAKUPAN_POLA,
            'readOnly' => $isWilayah,
        ]);
    }

    public function update(Request $request)
    {
        $a = Auth::guard('web')->user();
        abort_if($a->role !== 'super_admin', 403, 'Hanya Super Admin bisa mengubah pengaturan.');

        $data = $request->validate([
            'jamMasuk' => ['required', 'date_format:H:i'],
            'jamPulang' => ['required', 'date_format:H:i'],
            'toleransi' => ['required', 'integer', 'min:0', 'max:60'],
            'loveMax' => ['required', 'integer', 'min:1', 'max:10'],
            // Kuota cuti tahunan — opsional supaya pemanggil lama tetap valid.
            'cutiTahunanMax' => ['sometimes', 'integer', 'min:1', 'max:60'],
            // Cakupan pola shift (K5) — opsional supaya pemanggil lama tetap valid.
            'cakupanPolaShift' => ['sometimes', 'in:'.implode(',', AttendanceSetting::CAKUPAN_POLA)],
            // Jatah cuti pekerja shift (P3); 0 = ikut kuota umum.
            'cutiShiftMax' => ['sometimes', 'integer', 'min:0', 'max:60'],
            // Gerbang hari libur — opsional supaya pemanggil lama tetap valid.
            'absenLiburAktif' => ['sometimes', 'boolean'],
            'absenLiburMode' => ['sometimes', 'in:tolak,catat'],
            // Daftar hari kerja (1 = Senin … 7 = Minggu) — minimal satu hari.
            'hariKerja' => ['sometimes', 'array', 'min:1'],
            'hariKerja.*' => ['in:1,2,3,4,5,6,7'],
        ]);

        if ($data['jamMasuk'] >= $data['jamPulang']) {
            return back()->withErrors(['jamMasuk' => 'Jam masuk harus sebelum jam pulang.']);
        }

        $s = AttendanceSetting::first() ?? new AttendanceSetting;
        $s->jam_masuk = $data['jamMasuk'].':00';
        $s->jam_pulang = $data['jamPulang'].':00';
        $s->toleransi_late_menit = $data['toleransi'];
        $s->love_max = $data['loveMax'];
        if (array_key_exists('cutiTahunanMax', $data)) {
            $s->cuti_tahunan_max = (int) $data['cutiTahunanMax'];
        }
        if (array_key_exists('cakupanPolaShift', $data)) {
            $s->cakupan_pola_shift = $data['cakupanPolaShift'];
        }
        if (array_key_exists('cutiShiftMax', $data)) {
            $s->cuti_shift_max = (int) $data['cutiShiftMax'];
        }
        if (array_key_exists('absenLiburAktif', $data)) {
            $s->absen_libur_aktif = (bool) $data['absenLiburAktif'];
        }
        if (array_key_exists('absenLiburMode', $data)) {
            $s->absen_libur_mode = $data['absenLiburMode'];
        }
        if (array_key_exists('hariKerja', $data)) {
            // Simpan urut dan tanpa duplikat supaya perbandingan & tampilan konsisten.
            $hari = array_values(array_unique(array_map('strval', $data['hariKerja'])));
            sort($hari);
            $s->hari_kerja = $hari;
        }
        $s->updated_by = $a->id;
        $s->save();

        return back()->with('success', 'Pengaturan disimpan.');
    }
}
