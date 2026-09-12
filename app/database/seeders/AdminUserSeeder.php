<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

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
            User::updateOrCreate(
                ['email' => $u['email']],
                array_merge($u, ['password' => 'password123'])
            );
        }
    }
}
