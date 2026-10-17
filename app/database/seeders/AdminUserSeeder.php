<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Super Admin Pusat', 'email' => 'pusat@bbws-pj.go.id', 'role' => 'super_admin', 'region_id' => null, 'site_id' => null],
            ['name' => 'Admin Wilayah Gowa', 'email' => 'admin.gowa@bbws-pj.go.id', 'role' => 'admin_wilayah', 'region_id' => 2, 'site_id' => null],
            ['name' => 'Admin Wilayah Bone', 'email' => 'admin.bone@bbws-pj.go.id', 'role' => 'admin_wilayah', 'region_id' => 4, 'site_id' => null],
        ];

        foreach ($users as $u) {
            // firstOrCreate: jangan timpa kata sandi akun admin yang sudah
            // ada. Password default hanya dipakai saat akun pertama dibuat.
            $user = User::firstOrCreate(
                ['email' => $u['email']],
                array_merge($u, ['password' => env('SEED_ADMIN_PASSWORD', 'password123')])
            );

            // Admin wilayah juga terdaftar sebagai karyawan (lihat
            // EmployeeSeeder). Tautkan supaya keduanya satu orang dengan satu
            // kata sandi: sandi karyawan yang berlaku, bukan users.password.
            if ($u['role'] === 'admin_wilayah' && $user->employee_id === null) {
                $karyawan = Employee::where('email', $u['email'])->first();

                if ($karyawan) {
                    $user->forceFill([
                        'employee_id' => $karyawan->id,
                        // Sandi acak: kolom users.password tidak boleh bisa
                        // dipakai masuk selama akun tertaut.
                        'password' => Str::random(40),
                    ])->save();
                }
            }
        }
    }
}
