<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            ['nik' => '7371001234567890', 'nip' => '198501012010011001', 'name' => 'Andi Saputra', 'golongan' => 'III/a', 'jabatan' => 'Staff Teknik', 'unit_kerja' => 'Bidang Jalan', 'status_kepegawaian' => 'PNS', 'region_id' => 2, 'site_id' => 201, 'email' => 'andi@bbws-pj.go.id'],
            ['nik' => '7371001234567891', 'nip' => '199002022015022002', 'name' => 'Siti Rahma', 'golongan' => 'III/b', 'jabatan' => 'Analis Data', 'unit_kerja' => 'Bidang Air', 'status_kepegawaian' => 'PPPK', 'region_id' => 1, 'site_id' => 101, 'email' => 'siti@bbws-pj.go.id'],
            ['nik' => '7371001234567892', 'nip' => null, 'name' => 'Budi Santoso', 'golongan' => null, 'jabatan' => 'Operator', 'unit_kerja' => 'Bidang Jalan', 'status_kepegawaian' => 'Kontrak', 'region_id' => 2, 'site_id' => 202, 'email' => 'budi@bbws-pj.go.id'],
            ['nik' => '7371001234567893', 'nip' => null, 'name' => 'Rina Wati', 'golongan' => null, 'jabatan' => 'Admin', 'unit_kerja' => 'Bidang Umum', 'status_kepegawaian' => 'Kontrak', 'region_id' => 2, 'site_id' => 201, 'email' => 'rina@bbws-pj.go.id'],
            ['nik' => '7371001234567894', 'nip' => null, 'name' => 'Dewi Lestari', 'golongan' => null, 'jabatan' => 'Staff', 'unit_kerja' => 'Bidang Jalan', 'status_kepegawaian' => 'PNS', 'region_id' => 3, 'site_id' => 301, 'email' => 'dewi@bbws-pj.go.id'],
            // Dua baris berikut adalah orang yang sama dengan akun Admin
            // Wilayah di AdminUserSeeder. Satu orang, satu kata sandi, dua
            // peran: admin panel dan aplikasi karyawan.
            ['nik' => '7371001234567895', 'nip' => null, 'name' => 'Admin Wilayah Gowa', 'golongan' => null, 'jabatan' => 'Admin Wilayah', 'unit_kerja' => 'Bidang Umum', 'status_kepegawaian' => 'PNS', 'region_id' => 2, 'site_id' => 201, 'email' => 'admin.gowa@bbws-pj.go.id'],
            ['nik' => '7371001234567896', 'nip' => null, 'name' => 'Admin Wilayah Bone', 'golongan' => null, 'jabatan' => 'Admin Wilayah', 'unit_kerja' => 'Bidang Umum', 'status_kepegawaian' => 'PNS', 'region_id' => 4, 'site_id' => 401, 'email' => 'admin.bone@bbws-pj.go.id'],
        ];

        foreach ($employees as $e) {
            // firstOrCreate, BUKAN updateOrCreate: seeder tidak boleh menimpa
            // kata sandi karyawan yang sudah diganti. Versi lama mereset
            // semuanya ke 'password123' setiap kali dijalankan.
            Employee::firstOrCreate(
                ['nik' => $e['nik']],
                array_merge($e, [
                    'password' => env('SEED_EMPLOYEE_PASSWORD', 'password123'),
                    'must_change_password' => false,
                ])
            );
        }
    }
}
