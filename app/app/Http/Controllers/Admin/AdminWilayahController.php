<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Region;
use App\Models\User;
use App\Support\AdminPresenter;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
            // Daftar karyawan untuk pilihan "tautkan ke karyawan". Super Admin
            // melihat semuanya; jumlahnya puluhan, jadi dikirim sekalian.
            'employees' => AdminPresenter::employeesFor(null),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureSuperAdmin();

        $data = $request->validate($this->aturan(null), $this->pesan());

        $employee = $this->karyawanTertaut($data['employee_id'] ?? null, null);
        $region = $this->region($data['region'] ?? null, $employee);

        $admin = User::create([
            'name' => $employee?->name ?? $data['nama'],
            'email' => $employee?->email ?? $data['email'],
            'role' => 'admin_wilayah',
            'region_id' => $region->id,
            'site_id' => null,
            // Akun tertaut memakai kata sandi karyawan. Nilai acak memastikan
            // kolom users.password tidak pernah bisa dipakai untuk masuk.
            'password' => $employee ? Str::random(40) : $data['password'],
            'employee_id' => $employee?->id,
            'is_active' => true,
        ]);

        Audit::log(
            'admin_wilayah.create',
            subject: $admin,
            label: $admin->name,
            description: "Tambah Admin Wilayah {$admin->name} untuk {$region->name}",
            meta: ['region_id' => $region->id, 'employee_id' => $employee?->id]
        );

        return back()->with('success', 'Admin Wilayah ditambahkan.');
    }

    public function update(Request $request, User $adminWilayah)
    {
        $this->ensureSuperAdmin();
        abort_if($adminWilayah->role !== 'admin_wilayah', 403, 'Hanya akun Admin Wilayah yang dapat dikelola.');

        $data = $request->validate($this->aturan($adminWilayah), $this->pesan());

        $employee = $this->karyawanTertaut($data['employee_id'] ?? null, $adminWilayah);
        $region = $this->region($data['region'] ?? null, $employee, $adminWilayah);

        // Melepas tautan berarti akun kembali memakai kata sandinya sendiri.
        // Tanpa sandi baru, akun itu tidak akan pernah bisa dibuka lagi.
        if ($adminWilayah->employee_id && ! $employee && empty($data['password'])) {
            throw ValidationException::withMessages([
                'password' => 'Isi kata sandi baru karena akun ini dilepas dari karyawan.',
            ]);
        }

        $payload = [
            'name' => $employee?->name ?? $data['nama'],
            'email' => $employee?->email ?? $data['email'],
            'region_id' => $region->id,
            'employee_id' => $employee?->id,
        ];

        if ($employee) {
            // Sandi mengikuti karyawan. Hanya diacak saat tautannya berubah,
            // supaya kolom users.password tidak bisa dipakai masuk.
            if ($adminWilayah->employee_id !== $employee->id) {
                $payload['password'] = Str::random(40);
            }
        } elseif (! empty($data['password'])) {
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
            meta: [
                'region_id' => $region->id,
                'employee_id' => $employee?->id,
                'password_changed' => ! empty($data['password']),
            ]
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

    /**
     * Aturan validasi bersama untuk tambah dan ubah.
     *
     * Nama, email, dan kata sandi hanya wajib bila akun TIDAK ditautkan ke
     * karyawan: saat tertaut, ketiganya mengikuti baris karyawan.
     */
    private function aturan(?User $adminWilayah): array
    {
        $unikEmail = Rule::unique('users', 'email')->ignore($adminWilayah?->id);
        $unikKaryawan = Rule::unique('users', 'employee_id')->ignore($adminWilayah?->id);

        return [
            'employee_id' => ['nullable', 'integer', Rule::exists('employees', 'id'), $unikKaryawan],
            'nama' => ['required_without:employee_id', 'nullable', 'string', 'max:150'],
            'email' => ['required_without:employee_id', 'nullable', 'email', $unikEmail],
            'region' => ['nullable', 'string', Rule::exists('regions', 'name')],
            'password' => ['nullable', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function pesan(): array
    {
        return [
            'employee_id.exists' => 'Karyawan yang dipilih tidak ditemukan.',
            'employee_id.unique' => 'Karyawan ini sudah punya akun admin.',
            'nama.required_without' => 'Nama wajib diisi.',
            'email.required_without' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah dipakai akun admin lain.',
            'region.exists' => 'Wilayah tidak ditemukan.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
        ];
    }

    /** Karyawan yang dipilih untuk ditautkan, bila ada. */
    private function karyawanTertaut(mixed $employeeId, ?User $adminWilayah): ?Employee
    {
        if (empty($employeeId)) {
            return null;
        }

        $employee = Employee::findOrFail((int) $employeeId);

        if (! $employee->email) {
            throw ValidationException::withMessages([
                'employee_id' => 'Karyawan ini belum punya email, jadi belum bisa dipakai untuk login.',
            ]);
        }

        // Email karyawan bisa saja sudah dipakai akun admin lain. Aturan
        // unique di atas tidak melihatnya karena saat tertaut kolom email
        // tidak dikirim dari formulir.
        $bentrok = User::whereRaw('LOWER(email) = ?', [mb_strtolower($employee->email, 'UTF-8')])
            ->when($adminWilayah, fn ($q) => $q->whereKeyNot($adminWilayah->id))
            ->exists();

        if ($bentrok) {
            throw ValidationException::withMessages([
                'employee_id' => 'Email karyawan ini sudah dipakai akun admin lain.',
            ]);
        }

        return $employee;
    }

    /**
     * Wilayah akun admin. Bila tidak dipilih, ikut wilayah karyawan yang
     * ditautkan supaya tidak jatuh ke wilayah acak.
     */
    private function region(?string $nama, ?Employee $employee, ?User $adminWilayah = null): Region
    {
        $nama ??= $employee?->region?->name ?? $adminWilayah?->region?->name;

        if (! $nama) {
            throw ValidationException::withMessages([
                'region' => 'Wilayah wajib dipilih.',
            ]);
        }

        return Region::where('name', $nama)->firstOrFail();
    }
}
