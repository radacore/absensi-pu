<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        // Libur nasional Indonesia 2026 — reference umum (bisa diubah admin lewat UI)
        $holidays = [
            ['tanggal' => '2026-01-01', 'nama' => 'Tahun Baru Masehi'],
            ['tanggal' => '2026-02-17', 'nama' => 'Tahun Baru Imlek 2577'],
            ['tanggal' => '2026-03-19', 'nama' => 'Hari Raya Nyepi'],
            ['tanggal' => '2026-03-20', 'nama' => 'Isra Mikraj Nabi Muhammad SAW'],
            ['tanggal' => '2026-03-21', 'nama' => 'Hari Raya Idulfitri', 'cuti_bersama' => false],
            ['tanggal' => '2026-04-03', 'nama' => 'Wafat Isa Almasih'],
            ['tanggal' => '2026-05-01', 'nama' => 'Hari Buruh Internasional'],
            ['tanggal' => '2026-05-14', 'nama' => 'Kenaikan Isa Almasih'],
            ['tanggal' => '2026-05-31', 'nama' => 'Hari Raya Waisak'],
            ['tanggal' => '2026-06-01', 'nama' => 'Hari Lahir Pancasila'],
            ['tanggal' => '2026-08-17', 'nama' => 'Proklamasi Kemerdekaan RI'],
            ['tanggal' => '2026-08-28', 'nama' => 'Maulid Nabi Muhammad SAW'],
            ['tanggal' => '2026-12-25', 'nama' => 'Hari Raya Natal'],
        ];

        foreach ($holidays as $h) {
            Holiday::updateOrCreate(
                ['tanggal' => $h['tanggal']],
                ['nama' => $h['nama'], 'cuti_bersama' => (bool) ($h['cuti_bersama'] ?? false)]
            );
        }
    }
}
