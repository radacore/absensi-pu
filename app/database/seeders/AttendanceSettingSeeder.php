<?php

namespace Database\Seeders;

use App\Models\AttendanceSetting;
use Illuminate\Database\Seeder;

class AttendanceSettingSeeder extends Seeder
{
    public function run(): void
    {
        AttendanceSetting::updateOrCreate(
            ['id' => 1],
            [
                'jam_masuk' => '07:30',
                'jam_pulang' => '16:00',
                'toleransi_late_menit' => 15,
                'love_max' => 4,
                'hari_kerja' => ['1', '2', '3', '4', '5'],
                'timezone' => 'Asia/Makassar',
            ]
        );
    }
}
