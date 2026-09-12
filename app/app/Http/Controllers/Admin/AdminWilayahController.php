<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\User;
use App\Support\AdminPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AdminWilayahController extends Controller
{
    private function ensureSuperAdmin(): void
    {
        abort_if(Auth::guard('web')->user()->role !== 'super_admin', 403, 'Menu ini hanya untuk Super Admin.');
    }

    public function index()
    {
        $this->ensureSuperAdmin();

        return Inertia::render('Admin/AdminWilayah', [
            'admins' => AdminPresenter::adminsFor(),
            'regionNames' => AdminPresenter::regionNames(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureSuperAdmin();

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'region' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $region = Region::where('name', $data['region'])->firstOrFail();

        User::create([
            'name' => $data['nama'],
            'email' => $data['email'],
            'role' => 'admin_wilayah',
            'region_id' => $region->id,
            'site_id' => null,
            'password' => $data['password'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Admin Wilayah ditambahkan.');
    }

    public function update(Request $request, User $adminWilayah)
    {
        $this->ensureSuperAdmin();
        abort_if($adminWilayah->role !== 'admin_wilayah', 403, 'Hanya akun Admin Wilayah yang dapat dikelola.');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($adminWilayah->id)],
            'region' => ['required', 'string', 'max:120'],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $region = Region::where('name', $data['region'])->firstOrFail();

        $payload = [
            'name' => $data['nama'],
            'email' => $data['email'],
            'region_id' => $region->id,
        ];
        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }
        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = $data['is_active'];
        }

        $adminWilayah->update($payload);

        return back()->with('success', 'Admin Wilayah diperbarui.');
    }

    public function toggle(Request $request, User $adminWilayah)
    {
        $this->ensureSuperAdmin();
        abort_if($adminWilayah->role !== 'admin_wilayah', 403, 'Hanya akun Admin Wilayah yang dapat dikelola.');

        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $adminWilayah->update(['is_active' => $data['is_active']]);

        return back()->with('success', $data['is_active'] ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }

    public function destroy(User $adminWilayah)
    {
        $this->ensureSuperAdmin();
        abort_if($adminWilayah->role !== 'admin_wilayah', 403, 'Hanya akun Admin Wilayah yang dapat dikelola.');

        $adminWilayah->delete();

        return back()->with('success', 'Admin Wilayah dihapus.');
    }
}
