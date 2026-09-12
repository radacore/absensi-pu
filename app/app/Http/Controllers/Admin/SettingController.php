<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
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
            ] : ['jamMasuk' => '07:30', 'jamPulang' => '16:00', 'toleransi' => 15, 'loveMax' => 4],
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
        ]);

        if ($data['jamMasuk'] >= $data['jamPulang']) {
            return back()->withErrors(['jamMasuk' => 'Jam masuk harus sebelum jam pulang.']);
        }

        $s = AttendanceSetting::first() ?? new AttendanceSetting();
        $s->jam_masuk = $data['jamMasuk'] . ':00';
        $s->jam_pulang = $data['jamPulang'] . ':00';
        $s->toleransi_late_menit = $data['toleransi'];
        $s->love_max = $data['loveMax'];
        $s->updated_by = $a->id;
        $s->save();

        return back()->with('success', 'Pengaturan disimpan.');
    }
}
