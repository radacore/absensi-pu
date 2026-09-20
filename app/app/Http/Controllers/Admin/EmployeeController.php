<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Region;
use App\Support\AdminPresenter;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmployeeController extends Controller
{
    private function scopeRegion(): ?int
    {
        $user = Auth::guard('web')->user();

        return $user->role === 'super_admin' ? null : $user->region_id;
    }

    public function index()
    {
        $scope = $this->scopeRegion();

        return Inertia::render('Admin/Employees', [
            'regions' => AdminPresenter::regionsFor($scope),
            'employees' => AdminPresenter::employeesFor($scope),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateEmployee($request, null);
        $employee = $this->createFromPayload($data);

        Audit::log(
            'employee.create',
            subject: $employee,
            label: $employee->name,
            description: "Tambah karyawan baru {$employee->name} (NIK {$employee->nik})",
            meta: ['region_id' => $employee->region_id, 'site_id' => $employee->site_id]
        );

        return back()->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function update(Request $request, Employee $employee)
    {
        // Otorisasi dijalankan SEBELUM validasi. Bila urutannya dibalik, aturan
        // `unique` pada NIK/NIP/email akan membocorkan keberadaan data milik
        // wilayah lain lewat pesan galat (existence oracle).
        $scope = $this->scopeRegion();
        abort_if($scope && $employee->region_id !== $scope, 403, 'Karyawan ini di luar cakupan Anda.');

        $data = $this->validateEmployee($request, $employee->id);

        $region = Region::where('name', $data['region'])->firstOrFail();
        $this->assertSameRegion($region);
        $this->assertSiteInRegion($region, (int) $data['office_location_id']);

        $employee->update([
            'nik' => $data['nik'],
            'nip' => $data['nip'] ?: null,
            'name' => $data['nama'],
            'email' => $data['email'],
            'golongan' => $data['gol'] === '-' ? null : $data['gol'],
            'jabatan' => $data['jabatan'],
            'unit_kerja' => $data['unit'],
            'status_kepegawaian' => $data['status'],
            'region_id' => $region->id,
            'site_id' => $data['office_location_id'],
        ]);

        Audit::log(
            'employee.update',
            subject: $employee,
            label: $employee->name,
            description: "Perbarui data karyawan {$employee->name}",
            meta: ['region_id' => $employee->region_id, 'site_id' => $employee->site_id]
        );

        return back()->with('success', 'Karyawan berhasil diperbarui.');
    }

    public function destroy(Employee $employee)
    {
        $scope = $this->scopeRegion();
        abort_if($scope && $employee->region_id !== $scope, 403, 'Karyawan ini di luar cakupan Anda.');

        $snapshot = ['nik' => $employee->nik, 'nip' => $employee->nip, 'region_id' => $employee->region_id, 'site_id' => $employee->site_id];
        $name = $employee->name;
        $id = $employee->id;
        $employee->delete();

        Audit::log(
            'employee.delete',
            subject: null,
            label: $name,
            description: "Hapus karyawan {$name}",
            meta: array_merge($snapshot, ['employee_id' => $id])
        );

        return back()->with('success', 'Karyawan dihapus.');
    }

    public function resetPassword(Employee $employee)
    {
        $scope = $this->scopeRegion();
        abort_if($scope && $employee->region_id !== $scope, 403, 'Karyawan ini di luar cakupan Anda.');

        $employee->update([
            'password' => $employee->nik,
            'must_change_password' => true,
        ]);

        Audit::log(
            'employee.reset_password',
            subject: $employee,
            label: $employee->name,
            description: "Reset kata sandi karyawan {$employee->name} ke NIK",
            meta: ['nik_length' => strlen($employee->nik)]
        );

        return back()->with([
            'success' => "Kata sandi {$employee->name} direset ke NIK. Karyawan wajib ganti kata sandi saat login.",
            'reset_password' => [
                'employee_id' => $employee->id,
                'nama' => $employee->name,
                'nik' => $employee->nik,
            ],
        ]);
    }

    private function validateEmployee(Request $request, ?int $ignoreId): array
    {
        return $request->validate([
            'nik' => ['required', 'digits:16', Rule::unique('employees', 'nik')->ignore($ignoreId)],
            'nip' => ['nullable', 'string', Rule::unique('employees', 'nip')->ignore($ignoreId)],
            'nama' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('employees', 'email')->ignore($ignoreId)],
            'gol' => ['required', 'string', 'max:10'],
            'jabatan' => ['required', 'string', 'max:150'],
            'unit' => ['required', 'string', 'max:150'],
            'status' => ['required', 'in:PNS,PPPK,Kontrak,Honorer'],
            'region' => ['required', 'string', 'max:120'],
            'office_location_id' => ['required', 'integer'],
        ]);
    }

    private function createFromPayload(array $data): Employee
    {
        $region = Region::where('name', $data['region'])->firstOrFail();
        $this->assertSameRegion($region);
        $this->assertSiteInRegion($region, (int) $data['office_location_id']);

        return Employee::create([
            'nik' => $data['nik'],
            'nip' => $data['nip'] ?: null,
            'name' => $data['nama'],
            'email' => $data['email'],
            'golongan' => $data['gol'] === '-' ? null : $data['gol'],
            'jabatan' => $data['jabatan'],
            'unit_kerja' => $data['unit'],
            'status_kepegawaian' => $data['status'],
            'region_id' => $region->id,
            'site_id' => $data['office_location_id'],
            'password' => $data['nik'],
            'must_change_password' => true,
        ]);
    }

    private function assertSameRegion(Region $region): void
    {
        $scope = $this->scopeRegion();
        abort_if($scope && $region->id !== $scope, 403, 'Region ini di luar cakupan Anda.');
    }

    /**
     * Titik kerja wajib benar-benar milik wilayah yang dipilih.
     *
     * Dipakai bersama oleh store dan update. Sebelumnya hanya `store` yang
     * memeriksanya, sehingga `update` bisa memindahkan karyawan ke titik milik
     * wilayah lain sementara region-nya tetap wilayah sendiri.
     */
    private function assertSiteInRegion(Region $region, int $siteId): void
    {
        if (! $region->sites()->where('id', $siteId)->exists()) {
            throw ValidationException::withMessages([
                'office_location_id' => 'Titik tidak sesuai wilayah.',
            ]);
        }
    }
}
