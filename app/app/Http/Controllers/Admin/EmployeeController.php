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
            // B3: hari kerja umum dipakai sebagai acuan/preset di formulir.
            'settings' => AdminPresenter::settingsFor(),
            // Ditampilkan di dialog reset supaya admin tahu sandi mana yang
            // harus diberitahukan ke karyawan.
            'sandiBawaan' => Employee::SANDI_BAWAAN,
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
            description: 'Tambah karyawan baru '.$employee->name.' ('.$this->penanda($employee).')',
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

        $payload = [
            'nik' => $data['nik'] ?: null,
            'nip' => $data['nip'] ?: null,
            'name' => $data['nama'],
            'email' => $data['email'],
            'golongan' => $data['gol'] === '-' ? null : $data['gol'],
            'jabatan' => $data['jabatan'],
            'unit_kerja' => $data['unit'],
            'status_kepegawaian' => $data['status'],
            'region_id' => $region->id,
            'site_id' => $data['office_location_id'],
            'is_shift' => (bool) ($data['isShift'] ?? false),
        ];

        // PPK dari berkas PPK; hanya ditulis bila memang dikirim supaya
        // pemanggil lama tidak menghapus label yang sudah ada.
        if (array_key_exists('ppk', $data)) {
            $payload['ppk'] = $this->ppkPayload($data);
        }

        // B3: hanya diubah bila memang dikirim, supaya pemanggil lama tidak
        // menghapus pengecualian hari kerja yang sudah diatur.
        if (array_key_exists('hariKerja', $data)) {
            $payload['hari_kerja'] = $this->hariKerjaPayload($data);
        }

        // F4: sama seperti hari kerja — absen dari pemanggil lama tidak
        // menghapus regu yang sudah diatur.
        if (array_key_exists('regu', $data)) {
            $payload['regu'] = $this->reguPayload($data);
        }

        $employee->update($payload);

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

        // Semua akun karyawan memakai satu sandi bawaan yang sama, baik saat
        // dibuat manual, diimpor, maupun direset di sini. Karena sandinya
        // seragam, flag wajib ganti selalu dinyalakan.
        $sandi = Employee::SANDI_BAWAAN;

        $employee->update([
            'password' => $sandi,
            'must_change_password' => true,
        ]);

        Audit::log(
            'employee.reset_password',
            subject: $employee,
            label: $employee->name,
            description: "Reset kata sandi karyawan {$employee->name} ke sandi bawaan",
            meta: ['dasar' => 'sandi bawaan', 'panjang' => strlen($sandi)]
        );

        return back()->with([
            'success' => "Kata sandi {$employee->name} direset ke sandi bawaan. Karyawan wajib ganti kata sandi saat login.",
            'reset_password' => [
                'employee_id' => $employee->id,
                'nama' => $employee->name,
                'nik' => $employee->nik,
                'sandi' => $sandi,
                'dasar' => 'sandi bawaan',
            ],
        ]);
    }

    private function validateEmployee(Request $request, ?int $ignoreId): array
    {
        return $request->validate([
            // NIK/NIP boleh kosong dan boleh kurang dari 16 digit — berkas PPK
            // memang begitu. Disimpan apa adanya, jadi tidak ada `digits:16`.
            // Karakter yang diizinkan sama persis dengan yang dipakai impor,
            // supaya hasil impor selalu bisa disunting ulang lewat formulir ini.
            'nik' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z.\-\/]+$/', Rule::unique('employees', 'nik')->ignore($ignoreId)],
            'nip' => ['nullable', 'string', Rule::unique('employees', 'nip')->ignore($ignoreId)],
            'nama' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('employees', 'email')->ignore($ignoreId)],
            'gol' => ['required', 'string', 'max:10'],
            'jabatan' => ['required', 'string', 'max:150'],
            'unit' => ['required', 'string', 'max:150'],
            'status' => ['required', 'in:PNS,PPPK,Kontrak,Honorer'],
            'region' => ['required', 'string', 'max:120'],
            'office_location_id' => ['required', 'integer'],
            // Label PPK dari berkas PPK. Murni keterangan, jadi bebas diisi.
            'ppk' => ['sometimes', 'nullable', 'string', 'max:150'],
            // Penanda pekerja shift (K4: per orang). Opsional supaya pemanggil
            // lama tetap valid.
            'isShift' => ['sometimes', 'boolean'],
            // B3: hari kerja khusus per orang. Null/kosong = ikut pengaturan umum.
            'hariKerja' => ['sometimes', 'nullable', 'array', 'min:1'],
            'hariKerja.*' => ['in:1,2,3,4,5,6,7'],
            // F4: label regu bebas. Kosong = tanpa regu.
            'regu' => ['sometimes', 'nullable', 'string', 'max:30'],
        ], [
            // Pesan Indonesia. Tanpa ini Laravel menjawab dalam bahasa Inggris
            // ("The jabatan field is required."), dan itulah yang muncul di
            // notifikasi admin.
            'nik.max' => 'NIK/NIP maksimal 30 karakter.',
            'nik.regex' => 'NIK/NIP hanya boleh angka, huruf, titik, strip, atau garis miring.',
            'nik.unique' => 'NIK/NIP ini sudah terdaftar.',
            'nip.unique' => 'NIP ini sudah terdaftar.',
            'nama.required' => 'Nama lengkap wajib diisi.',
            'nama.max' => 'Nama lengkap maksimal 150 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah dipakai karyawan lain.',
            'gol.required' => 'Golongan wajib dipilih.',
            'jabatan.required' => 'Jabatan wajib diisi.',
            'unit.required' => 'Unit wajib diisi.',
            'status.required' => 'Status kepegawaian wajib dipilih.',
            'status.in' => 'Status kepegawaian tidak dikenal.',
            'region.required' => 'Wilayah wajib dipilih.',
            'office_location_id.required' => 'Titik proyek wajib dipilih.',
            'office_location_id.integer' => 'Titik proyek tidak valid.',
            'ppk.max' => 'PPK maksimal 150 karakter.',
            'regu.max' => 'Regu maksimal 30 karakter.',
            'hariKerja.min' => 'Pilih minimal satu hari kerja.',
        ]);
    }

    /**
     * B3: hari kerja khusus karyawan, tersimpan urut tanpa duplikat.
     * Kosong/null → null, yang berarti ikut pengaturan umum.
     */
    private function hariKerjaPayload(array $data): ?array
    {
        $hari = $data['hariKerja'] ?? null;

        if (! is_array($hari) || $hari === []) {
            return null;
        }

        $hari = array_values(array_unique(array_map('strval', $hari)));
        sort($hari);

        return $hari;
    }

    /**
     * F4: label regu bebas, tanpa daftar tertutup. Kosong → null (tanpa regu).
     */
    private function reguPayload(array $data): ?string
    {
        $regu = trim((string) ($data['regu'] ?? ''));

        return $regu === '' ? null : $regu;
    }

    /**
     * Label PPK bebas. Kosong → null (tanpa label).
     */
    private function ppkPayload(array $data): ?string
    {
        $ppk = trim((string) ($data['ppk'] ?? ''));

        return $ppk === '' ? null : $ppk;
    }

    /**
     * Penanda karyawan untuk catatan audit. NIK boleh kosong, jadi email
     * dipakai sebagai gantinya supaya catatan tidak pernah berakhir "NIK ".
     */
    private function penanda(Employee $employee): string
    {
        return $employee->nik ?: $employee->email;
    }

    private function createFromPayload(array $data): Employee
    {
        $region = Region::where('name', $data['region'])->firstOrFail();
        $this->assertSameRegion($region);
        $this->assertSiteInRegion($region, (int) $data['office_location_id']);

        return Employee::create([
            'nik' => $data['nik'] ?: null,
            'nip' => $data['nip'] ?: null,
            'name' => $data['nama'],
            'email' => $data['email'],
            'golongan' => $data['gol'] === '-' ? null : $data['gol'],
            'jabatan' => $data['jabatan'],
            'unit_kerja' => $data['unit'],
            'status_kepegawaian' => $data['status'],
            'ppk' => $this->ppkPayload($data),
            'region_id' => $region->id,
            'site_id' => $data['office_location_id'],
            'is_shift' => (bool) ($data['isShift'] ?? false),
            'hari_kerja' => $this->hariKerjaPayload($data),
            'regu' => $this->reguPayload($data),
            // Sandi bawaan yang sama untuk semua akun baru; karyawan wajib
            // menggantinya saat login pertama.
            'password' => Employee::SANDI_BAWAAN,
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
