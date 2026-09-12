<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Support\AdminPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RegionController extends Controller
{
    private function scopeRegion(): ?int
    {
        $user = Auth::guard('web')->user();

        return $user->role === 'super_admin' ? null : $user->region_id;
    }

    public function index()
    {
        $scope = $this->scopeRegion();

        return Inertia::render('Admin/Regions', [
            'regions' => AdminPresenter::regionsFor($scope),
            'employees' => AdminPresenter::employeesFor($scope),
        ]);
    }

    public function store(Request $request)
    {
        abort_if($this->scopeRegion(), 403, 'Hanya Super Admin yang dapat menambah wilayah.');

        $data = $this->validateRegion($request);
        $this->assertPusatUnique($data['tipe'], null);
        $this->assertNameUnique($data['name'], null);

        Region::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'kantor_name' => $data['kantor'],
            'tipe' => $data['tipe'],
            'address' => $data['address'] ?? null,
        ]);

        return back()->with('success', 'Wilayah berhasil ditambahkan.');
    }

    public function update(Request $request, Region $region)
    {
        $scope = $this->scopeRegion();
        abort_if($scope && $region->id !== $scope, 403, 'Wilayah ini di luar cakupan Anda.');

        $data = $this->validateRegion($request);
        $this->assertPusatUnique($data['tipe'], $region->id);
        $this->assertNameUnique($data['name'], $region->id);

        $region->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'kantor_name' => $data['kantor'],
            'tipe' => $data['tipe'],
            'address' => $data['address'] ?? null,
        ]);

        return back()->with('success', 'Wilayah berhasil diperbarui.');
    }

    public function destroy(Region $region)
    {
        abort_if($this->scopeRegion(), 403, 'Hanya Super Admin yang dapat menghapus wilayah.');

        $region->delete();

        return back()->with('success', 'Wilayah beserta data terkait dihapus.');
    }

    private function validateRegion(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kantor' => ['required', 'string', 'max:120'],
            'tipe' => ['required', 'in:pusat,cabang'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function assertNameUnique(string $name, ?int $ignoreId): void
    {
        $q = Region::query();
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }
        if ($q->whereRaw('lower(name) = ?', [Str::lower($name)])->exists()) {
            throw ValidationException::withMessages(['name' => 'Nama wilayah sudah dipakai.']);
        }
    }

    private function assertPusatUnique(string $tipe, ?int $ignoreId): void
    {
        if ($tipe !== 'pusat') {
            return;
        }
        $q = Region::where('tipe', 'pusat');
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }
        if ($q->exists()) {
            throw ValidationException::withMessages(['tipe' => 'Hanya boleh ada satu wilayah tipe Pusat.']);
        }
    }
}
