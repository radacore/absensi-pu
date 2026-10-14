<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\ShiftPattern;
use App\Models\Site;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Pola shift: jumlah shift + jam mulai shift pertama.
 *
 * Panjang tiap shift tidak diisi manual — selalu 24 jam ÷ jumlah shift
 * (2 shift → 12 jam, 3 shift → 8 jam, 4 shift → 6 jam).
 */
class ShiftPatternController extends Controller
{
    /** null = Super Admin (semua wilayah), selain itu id wilayah admin. */
    private function scope(): ?int
    {
        $u = Auth::guard('web')->user();

        return $u->role === 'super_admin' ? null : $u->region_id;
    }

    public function index()
    {
        $scope = $this->scope();

        $patterns = ShiftPattern::with(['region', 'site'])
            ->when($scope, fn ($q) => $q->where(function ($qq) use ($scope) {
                // Admin wilayah melihat pola global + pola wilayahnya sendiri.
                $qq->whereNull('region_id')->orWhere('region_id', $scope);
            }))
            ->orderBy('id')
            ->get()
            ->map(fn (ShiftPattern $p) => [
                'id' => $p->id,
                'nama' => $p->nama,
                'jumlahShift' => (int) $p->jumlah_shift,
                'jamMulaiShift1' => substr((string) $p->jam_mulai_shift_1, 0, 5),
                'durasiJam' => intdiv($p->durasiMenit(), 60),
                'ringkasan' => $p->ringkasan(),
                'regionId' => $p->region_id,
                'regionNama' => $p->region?->name ?? '',
                'siteId' => $p->site_id,
                'siteNama' => $p->site?->nama_lokasi ?? '',
                'cakupan' => $p->site_id ? 'lokasi' : ($p->region_id ? 'wilayah' : 'global'),
                'isActive' => (bool) $p->is_active,
                'shiftLabels' => collect(range(1, (int) $p->jumlah_shift))
                    ->map(fn ($n) => [
                        'ke' => $n,
                        'label' => $p->labelShift($n),
                        'mulai' => $p->jamMulaiShift($n),
                    ])->all(),
            ])->values()->all();

        $regions = Region::with('sites')
            ->when($scope, fn ($q) => $q->where('id', $scope))
            ->orderBy('id')
            ->get()
            ->map(fn (Region $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'sites' => $r->sites->sortBy('id')->map(fn ($s) => [
                    'id' => $s->id,
                    'nama' => $s->nama_lokasi,
                ])->values()->all(),
            ])->values()->all();

        return Inertia::render('Admin/PolaShift', [
            'patterns' => $patterns,
            'regions' => $regions,
            'isSuperAdmin' => $scope === null,
        ]);
    }

    public function store(Request $request)
    {
        [$data, $regionId, $siteId] = $this->payload($request);

        $this->cekBentrok($regionId, $siteId);

        $p = ShiftPattern::create([
            'nama' => $data['nama'],
            'jumlah_shift' => (int) $data['jumlahShift'],
            'jam_mulai_shift_1' => $data['jamMulaiShift1'].':00',
            'region_id' => $regionId,
            'site_id' => $siteId,
            'is_active' => true,
        ]);

        Audit::log(
            'shift.pattern.create',
            subject: $p,
            label: $p->nama,
            description: "Tambah pola shift {$p->nama} ({$p->ringkasan()})",
            meta: ['region_id' => $regionId, 'site_id' => $siteId, 'jumlah_shift' => $p->jumlah_shift]
        );

        return back()->with('success', 'Pola shift disimpan.');
    }

    public function update(Request $request, ShiftPattern $pola)
    {
        $this->authorizeRow($pola);

        [$data, $regionId, $siteId] = $this->payload($request);

        $this->cekBentrok($regionId, $siteId, $pola->id);

        $pola->update([
            'nama' => $data['nama'],
            'jumlah_shift' => (int) $data['jumlahShift'],
            'jam_mulai_shift_1' => $data['jamMulaiShift1'].':00',
            'region_id' => $regionId,
            'site_id' => $siteId,
        ]);

        Audit::log(
            'shift.pattern.update',
            subject: $pola,
            label: $pola->nama,
            description: "Ubah pola shift {$pola->nama} ({$pola->ringkasan()})",
            meta: ['region_id' => $regionId, 'site_id' => $siteId, 'jumlah_shift' => $pola->jumlah_shift]
        );

        return back()->with('success', 'Pola shift diperbarui.');
    }

    public function toggle(ShiftPattern $pola)
    {
        $this->authorizeRow($pola);

        $pola->is_active = ! $pola->is_active;
        $pola->save();

        Audit::log(
            'shift.pattern.toggle',
            subject: $pola,
            label: $pola->nama,
            description: ($pola->is_active ? 'Aktifkan' : 'Nonaktifkan')." pola shift {$pola->nama}",
            meta: ['is_active' => $pola->is_active]
        );

        return back()->with('success', $pola->is_active ? 'Pola shift diaktifkan.' : 'Pola shift dinonaktifkan.');
    }

    public function destroy(ShiftPattern $pola)
    {
        $this->authorizeRow($pola);

        $snapshot = ['nama' => $pola->nama, 'ringkasan' => $pola->ringkasan()];
        $pola->delete();

        Audit::log(
            'shift.pattern.delete',
            subject: null,
            label: $snapshot['nama'],
            description: "Hapus pola shift {$snapshot['nama']}",
            meta: $snapshot
        );

        return back()->with('success', 'Pola shift dihapus.');
    }

    /** Cakupan tidak boleh diubah admin wilayah di luar wilayahnya. */
    private function authorizeRow(ShiftPattern $pola): void
    {
        $scope = $this->scope();
        abort_if($scope !== null && (int) $pola->region_id !== $scope, 403, 'Pola ini di luar cakupan Anda.');
    }

    /**
     * @return array{0: array, 1: ?int, 2: ?int} data tervalidasi, region_id, site_id
     */
    private function payload(Request $request): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'min:2', 'max:60'],
            'jumlahShift' => ['required', 'integer', 'min:'.ShiftPattern::MIN_SHIFT, 'max:'.ShiftPattern::MAX_SHIFT],
            'jamMulaiShift1' => ['required', 'date_format:H:i'],
            'cakupan' => ['required', 'in:global,wilayah,lokasi'],
            'regionId' => ['nullable', 'integer', 'exists:regions,id'],
            'siteId' => ['nullable', 'integer', 'exists:sites,id'],
        ]);

        $regionId = null;
        $siteId = null;

        if ($data['cakupan'] === 'wilayah') {
            $regionId = (int) ($data['regionId'] ?? 0);
            if (! $regionId) {
                throw ValidationException::withMessages(['regionId' => 'Pilih wilayah dulu.']);
            }
        } elseif ($data['cakupan'] === 'lokasi') {
            $site = Site::find((int) ($data['siteId'] ?? 0));
            if (! $site) {
                throw ValidationException::withMessages(['siteId' => 'Pilih lokasi dulu.']);
            }
            $siteId = (int) $site->id;
            $regionId = (int) $site->region_id;
        }

        $scope = $this->scope();
        if ($scope !== null && (int) $regionId !== $scope) {
            // Admin wilayah tidak boleh menyusun pola global atau pola wilayah lain.
            throw ValidationException::withMessages([
                'cakupan' => 'Anda hanya bisa mengatur pola shift untuk wilayah Anda.',
            ]);
        }

        return [$data, $regionId, $siteId];
    }

    private function cekBentrok(?int $regionId, ?int $siteId, ?int $kecualiId = null): void
    {
        $ada = ShiftPattern::where('region_id', $regionId)
            ->where('site_id', $siteId)
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->exists();

        if ($ada) {
            throw ValidationException::withMessages([
                'cakupan' => 'Sudah ada pola untuk cakupan ini — ubah pola yang sudah ada saja.',
            ]);
        }
    }
}
