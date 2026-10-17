<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Region;
use App\Models\Site;
use App\Support\AdminPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SiteController extends Controller
{
    private function scopeRegion(): ?int
    {
        $user = Auth::guard('web')->user();

        return $user->role === 'super_admin' ? null : $user->region_id;
    }

    public function show(Region $region, Site $site)
    {
        $scope = $this->scopeRegion();
        abort_if($site->region_id !== $region->id, 404);
        abort_if($scope && $site->region_id !== $scope, 403, 'Titik ini di luar cakupan Anda.');

        // Data disaring sesuai cakupan. Sebelumnya kedua query ini tidak difilter
        // sama sekali, sehingga admin satu wilayah menerima seluruh wilayah dan
        // seluruh karyawan instansi (kebocoran NIK/NIP/email lintas wilayah).
        $regions = collect(Region::with('sites')
            ->when($scope, fn ($q) => $q->where('id', $scope))
            ->orderBy('id')
            ->get())
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'kantor' => $r->kantor_name,
                'locations' => $r->sites->sortBy('id')->values()->map(fn ($s) => [
                    'id' => $s->id,
                    'nama_lokasi' => $s->nama_lokasi,
                    'lat' => (float) $s->lat,
                    'lng' => (float) $s->lng,
                    'radius' => (int) $s->radius_m,
                    'address' => $s->address,
                ])->all(),
            ])
            ->all();

        $employees = Employee::with(['region', 'site', 'sites'])
            ->when($scope, fn ($q) => $q->where('region_id', $scope))
            ->orderBy('id')
            ->get()
            ->map(fn ($e) => AdminPresenter::employeeRow($e))
            ->values()->all();

        return Inertia::render('Admin/SiteDetail', [
            'regionId' => $region->id,
            'siteId' => $site->id,
            'regions' => $regions,
            'employees' => $employees,
            // Setelah pemeriksaan cakupan di atas, halaman ini hanya bisa dibuka
            // untuk wilayah milik aktor sendiri — jadi selalu bisa diedit.
            'readOnly' => false,
        ]);
    }

    public function store(Request $request, Region $region)
    {
        $scope = $this->scopeRegion();
        abort_if($scope && $region->id !== $scope, 403, 'Wilayah ini di luar cakupan Anda.');

        $data = $request->validate([
            'nama_lokasi' => ['required', 'string', 'max:150'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['required', 'integer', 'between:50,1000'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $dup = $region->sites()->whereRaw('lower(nama_lokasi) = ?', [mb_strtolower($data['nama_lokasi'])])->exists();
        if ($dup) {
            throw ValidationException::withMessages(['nama_lokasi' => 'Nama titik sudah ada di wilayah ini.']);
        }
        if ($region->sites()->count() >= 20) {
            throw ValidationException::withMessages(['nama_lokasi' => 'Maksimal 20 titik per wilayah.']);
        }

        $site = $region->sites()->create([
            'nama_lokasi' => $data['nama_lokasi'],
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'radius_m' => $data['radius'],
            'address' => $data['address'] ?? null,
        ]);

        $base = str_starts_with(request()->path(), 'super-admin') ? '/super-admin' : '/admin';

        return redirect("{$base}/regions/{$region->id}/sites/{$site->id}");
    }

    public function update(Request $request, Site $site)
    {
        $scope = $this->scopeRegion();
        abort_if($scope && $site->region_id !== $scope, 403, 'Titik ini di luar cakupan Anda.');

        $data = $request->validate([
            'nama_lokasi' => ['required', 'string', 'max:150'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['required', 'integer', 'between:50,1000'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $dup = $site->region->sites()
            ->where('id', '!=', $site->id)
            ->whereRaw('lower(nama_lokasi) = ?', [mb_strtolower($data['nama_lokasi'])])
            ->exists();
        if ($dup) {
            throw ValidationException::withMessages(['nama_lokasi' => 'Nama titik sudah ada di wilayah ini.']);
        }

        $site->update([
            'nama_lokasi' => $data['nama_lokasi'],
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'radius_m' => $data['radius'],
            'address' => $data['address'] ?? null,
        ]);

        return back()->with('success', 'Titik berhasil diperbarui.');
    }

    public function destroy(Site $site)
    {
        $scope = $this->scopeRegion();
        abort_if($scope && $site->region_id !== $scope, 403, 'Titik ini di luar cakupan Anda.');

        if ($site->region->sites()->count() <= 1) {
            return back()->with('error', 'Wilayah harus memiliki minimal satu titik.');
        }

        $members = Employee::where('site_id', $site->id)->get();
        $dipromosi = 0;
        $dipindah = 0;

        foreach ($members as $member) {
            // Utamakan titik tambahan karyawan itu sendiri: dia sudah terbiasa
            // absen di sana, jadi tidak perlu dipindah ke titik asing.
            $pengganti = $member->sites()->where('site_id', '!=', $site->id)->first();

            if ($pengganti) {
                $member->update(['site_id' => $pengganti->id]);
                $member->sites()->detach($pengganti->id);
                $dipromosi++;

                continue;
            }

            $other = $site->region->sites()->where('id', '!=', $site->id)->first();
            if (! $other) {
                return back()->with('error', 'Masih ada karyawan di titik ini — pindahkan ke titik lain dulu.');
            }

            $member->update(['site_id' => $other->id]);
            $dipindah++;
        }

        // Titik ini juga mungkin terdaftar sebagai titik tambahan karyawan lain.
        DB::table('employee_sites')->where('site_id', $site->id)->delete();

        $site->delete();

        $bagian = [];
        if ($dipromosi > 0) {
            $bagian[] = "{$dipromosi} karyawan memakai titik tambahannya sebagai titik utama";
        }
        if ($dipindah > 0) {
            $bagian[] = "{$dipindah} karyawan dipindah ke titik lain";
        }

        if ($bagian !== []) {
            return back()->with('success', 'Titik dihapus — '.implode(', ', $bagian).'.');
        }

        return back()->with('success', 'Titik berhasil dihapus.');
    }

    public function assignEmployees(Request $request, Site $site)
    {
        $scope = $this->scopeRegion();
        abort_if($scope && $site->region_id !== $scope, 403, 'Titik ini di luar cakupan Anda.');

        $data = $request->validate([
            'employee_ids' => ['required', 'array'],
            'employee_ids.*' => ['integer'],
        ]);

        $employees = Employee::whereIn('id', $data['employee_ids'])->get();
        if ($employees->count() !== count(array_unique($data['employee_ids']))) {
            throw ValidationException::withMessages(['employee_ids' => 'Karyawan tidak ditemukan.']);
        }

        foreach ($employees as $employee) {
            if ($employee->region_id !== $site->region_id) {
                throw ValidationException::withMessages(['employee_ids' => 'Karyawan dari wilayah lain tidak bisa ditugaskan ke titik ini.']);
            }

            // Titik utama tidak perlu disimpan di pivot.
            if ((int) $employee->site_id === (int) $site->id) {
                continue;
            }

            // Menambah anggota = menambah titik absensi, bukan memindahkan
            // titik utama. Karyawan tetap bisa absen di titik lamanya.
            $employee->sites()->syncWithoutDetaching([$site->id]);
        }

        return back()->with('success', 'Karyawan berhasil ditambahkan ke titik ini.');
    }

    /** Keluarkan karyawan dari satu titik (titik tambahan saja). */
    public function removeEmployee(Site $site, Employee $employee)
    {
        $scope = $this->scopeRegion();
        abort_if($scope && $site->region_id !== $scope, 403, 'Titik ini di luar cakupan Anda.');
        abort_if((int) $employee->region_id !== (int) $site->region_id, 403, 'Karyawan ini di luar wilayah titik.');

        if ((int) $employee->site_id === (int) $site->id) {
            // Titik utama tidak bisa dilepas tanpa pengganti: karyawan wajib
            // punya satu titik utama. Promosikan titik tambahan bila ada.
            $pengganti = $employee->sites()->where('site_id', '!=', $site->id)->first();

            if (! $pengganti) {
                return back()->with('error', "{$employee->name} memakai titik ini sebagai titik utama — pindahkan dulu ke titik lain.");
            }

            $employee->update(['site_id' => $pengganti->id]);
            $employee->sites()->detach($pengganti->id);

            return back()->with('success', "{$employee->name} dipindah ke {$pengganti->nama_lokasi}.");
        }

        $employee->sites()->detach($site->id);

        return back()->with('success', "{$employee->name} dikeluarkan dari titik ini.");
    }

    public function move(Request $request, Site $site)
    {
        $scope = $this->scopeRegion();
        abort_if($scope && $site->region_id !== $scope, 403, 'Titik ini di luar cakupan Anda.');

        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        if ($employee->region_id !== $site->region_id) {
            throw ValidationException::withMessages(['employee_id' => 'Karyawan dari wilayah lain tidak bisa dipindahkan ke titik ini.']);
        }

        $employee->update(['site_id' => $site->id]);
        // Titik utama tidak disimpan di pivot; bila titik ini sebelumnya
        // terdaftar sebagai titik tambahan, barisnya dibersihkan.
        $employee->sites()->detach($site->id);

        return back()->with('success', 'Karyawan dipindahkan.');
    }
}
