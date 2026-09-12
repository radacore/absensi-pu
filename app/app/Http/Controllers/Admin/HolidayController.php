<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class HolidayController extends Controller
{
    private function ensureSuperAdmin(): void
    {
        abort_if(Auth::guard('web')->user()->role !== 'super_admin', 403, 'Menu ini hanya untuk Super Admin.');
    }

    public function index(Request $request)
    {
        $this->ensureSuperAdmin();

        $year = (int) $request->input('year', now('Asia/Makassar')->year);
        $holidays = Holiday::whereYear('tanggal', $year)
            ->orderBy('tanggal')
            ->get()
            ->map(fn (Holiday $h) => [
                'id' => $h->id,
                'tanggal' => $h->tanggal->format('Y-m-d'),
                'nama' => $h->nama,
                'cuti_bersama' => $h->cuti_bersama,
            ])->all();

        return Inertia::render('Admin/Holidays', [
            'holidays' => $holidays,
            'year' => $year,
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureSuperAdmin();

        $data = $request->validate([
            'tanggal' => ['required', 'date', Rule::unique('holidays', 'tanggal')],
            'nama' => ['required', 'string', 'max:200'],
            'cuti_bersama' => ['nullable', 'boolean'],
        ]);

        $h = Holiday::create($data + ['cuti_bersama' => (bool) ($data['cuti_bersama'] ?? false)]);

        Audit::log(
            'holiday.create',
            subject: $h,
            label: $h->nama,
            description: "Tambah libur {$h->nama} ({$h->tanggal->format('Y-m-d')})"
        );

        return back()->with('success', 'Hari libur ditambahkan.');
    }

    public function update(Request $request, Holiday $holiday)
    {
        $this->ensureSuperAdmin();

        $data = $request->validate([
            'tanggal' => ['required', 'date', Rule::unique('holidays', 'tanggal')->ignore($holiday->id)],
            'nama' => ['required', 'string', 'max:200'],
            'cuti_bersama' => ['nullable', 'boolean'],
        ]);

        $holiday->update($data + ['cuti_bersama' => (bool) ($data['cuti_bersama'] ?? false)]);

        Audit::log(
            'holiday.update',
            subject: $holiday,
            label: $holiday->nama,
            description: "Perbarui libur {$holiday->nama}"
        );

        return back()->with('success', 'Hari libur diperbarui.');
    }

    public function destroy(Holiday $holiday)
    {
        $this->ensureSuperAdmin();
        $snap = ['tanggal' => $holiday->tanggal->format('Y-m-d'), 'nama' => $holiday->nama];
        $holiday->delete();

        Audit::log(
            'holiday.delete',
            subject: null,
            label: $snap['nama'],
            description: "Hapus libur {$snap['nama']} ({$snap['tanggal']})",
            meta: $snap
        );

        return back()->with('success', 'Hari libur dihapus.');
    }
}
