<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\User;
use App\Support\AdminPresenter;
use App\Support\Audit;
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

        $admin = User::create([
            'name' => $data['nama'],
            'email' => $data['email'],
            'role' => 'admin_wilayah',
            'region_id' => $region->id,
            'site_id' => null,
            'password' => $data['password'],
            'is_active' => true,
        ]);

        Audit::log(
            'admin_wilayah.create',
            subject: $admin,
            label: $admin->name,
            description: "Tambah Admin Wilayah {$admin->name} untuk {$region->name}",
            meta: ['region_id' => $region->id]
        );

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

        Audit::log(
            'admin_wilayah.update',
            subject: $adminWilayah,
            label: $adminWilayah->name,
            description: "Perbarui Admin Wilayah {$adminWilayah->name}",
            meta: ['region_id' => $region->id, 'password_changed' => ! empty($data['password'])]
        );

        return back()->with('success', 'Admin Wilayah diperbarui.');
    }

    public function toggle(Request $request, User $adminWilayah)
    {
        $this->ensureSuperAdmin();
        abort_if($adminWilayah->role !== 'admin_wilayah', 403, 'Hanya akun Admin Wilayah yang dapat dikelola.');

        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $adminWilayah->update(['is_active' => $data['is_active']]);

        Audit::log(
            $data['is_active'] ? 'admin_wilayah.activate' : 'admin_wilayah.deactivate',
            subject: $adminWilayah,
            label: $adminWilayah->name,
            description: $data['is_active']
                ? "Aktifkan Admin Wilayah {$adminWilayah->name}"
                : "Nonaktifkan Admin Wilayah {$adminWilayah->name}"
        );

        return back()->with('success', $data['is_active'] ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }

    public function destroy(User $adminWilayah)
    {
        $this->ensureSuperAdmin();
        abort_if($adminWilayah->role !== 'admin_wilayah', 403, 'Hanya akun Admin Wilayah yang dapat dikelola.');

        $name = $adminWilayah->name;
        $email = $adminWilayah->email;
        $regionId = $adminWilayah->region_id;
        $adminWilayah->delete();

        Audit::log(
            'admin_wilayah.delete',
            subject: null,
            label: $name,
            description: "Hapus Admin Wilayah {$name}",
            meta: ['email' => $email, 'region_id' => $regionId]
        );

        return back()->with('success', 'Admin Wilayah dihapus.');
    }
}
