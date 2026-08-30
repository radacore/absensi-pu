<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['name' => 'Kota Makassar', 'slug' => 'kota-makassar', 'kantor_name' => 'Kantor Pusat', 'tipe' => 'pusat', 'lat' => -5.1477, 'lng' => 119.4327, 'radius_m' => 300, 'address' => 'Jl. AP Pettarani No.1 — Makassar'],
            ['name' => 'Kab. Gowa', 'slug' => 'kab-gowa', 'kantor_name' => 'Kantor Wilayah Gowa', 'tipe' => 'cabang', 'lat' => -5.3114, 'lng' => 119.4200, 'radius_m' => 200, 'address' => 'Jl. Poros Malino — Gowa'],
            ['name' => 'Kab. Maros', 'slug' => 'kab-maros', 'kantor_name' => 'Kantor Wilayah Maros', 'tipe' => 'cabang', 'lat' => -5.0050, 'lng' => 119.5800, 'radius_m' => 200, 'address' => 'Jl. Poros Maros'],
            ['name' => 'Kab. Bone', 'slug' => 'kab-bone', 'kantor_name' => 'Kantor Wilayah Bone', 'tipe' => 'cabang', 'lat' => -4.5400, 'lng' => 120.3300, 'radius_m' => 150, 'address' => 'Jl. Ahmad Yani — Bone'],
            ['name' => 'Kota Parepare', 'slug' => 'kota-parepare', 'kantor_name' => 'Kantor Wilayah Parepare', 'tipe' => 'cabang', 'lat' => -4.0148, 'lng' => 119.6250, 'radius_m' => 200, 'address' => 'Jl. Andi Makkasau — Parepare'],
            ['name' => 'Kota Palopo', 'slug' => 'kota-palopo', 'kantor_name' => 'Kantor Wilayah Palopo', 'tipe' => 'cabang', 'lat' => -3.0014, 'lng' => 120.1920, 'radius_m' => 200, 'address' => 'Jl. Andi Djemma — Palopo'],
            ['name' => 'Kab. Bantaeng', 'slug' => 'kab-bantaeng', 'kantor_name' => 'Kantor Wilayah Bantaeng', 'tipe' => 'cabang', 'lat' => -5.5400, 'lng' => 119.9300, 'radius_m' => 180, 'address' => 'Jl. Andi Mannappiang — Bantaeng'],
            ['name' => 'Kab. Barru', 'slug' => 'kab-barru', 'kantor_name' => 'Kantor Wilayah Barru', 'tipe' => 'cabang', 'lat' => -4.4200, 'lng' => 119.6800, 'radius_m' => 180, 'address' => 'Jl. Sultan Hasanuddin — Barru'],
            ['name' => 'Kab. Bulukumba', 'slug' => 'kab-bulukumba', 'kantor_name' => 'Kantor Wilayah Bulukumba', 'tipe' => 'cabang', 'lat' => -5.5600, 'lng' => 120.1900, 'radius_m' => 200, 'address' => 'Jl. Sam Ratulangi — Bulukumba'],
            ['name' => 'Kab. Enrekang', 'slug' => 'kab-enrekang', 'kantor_name' => 'Kantor Wilayah Enrekang', 'tipe' => 'cabang', 'lat' => -3.5800, 'lng' => 119.7700, 'radius_m' => 200, 'address' => 'Jl. Pahlawan — Enrekang'],
            ['name' => 'Kab. Jeneponto', 'slug' => 'kab-jeneponto', 'kantor_name' => 'Kantor Wilayah Jeneponto', 'tipe' => 'cabang', 'lat' => -5.6600, 'lng' => 119.7300, 'radius_m' => 200, 'address' => 'Jl. Pahlawan — Jeneponto'],
            ['name' => 'Kab. Kepulauan Selayar', 'slug' => 'kab-selayar', 'kantor_name' => 'Kantor Wilayah Selayar', 'tipe' => 'cabang', 'lat' => -6.1200, 'lng' => 120.4500, 'radius_m' => 250, 'address' => 'Jl. Ahmad Yani — Benteng Selayar'],
            ['name' => 'Kab. Luwu', 'slug' => 'kab-luwu', 'kantor_name' => 'Kantor Wilayah Luwu', 'tipe' => 'cabang', 'lat' => -3.3900, 'lng' => 120.3800, 'radius_m' => 200, 'address' => 'Jl. Trans Sulawesi — Belopa'],
            ['name' => 'Kab. Luwu Timur', 'slug' => 'kab-luwu-timur', 'kantor_name' => 'Kantor Wilayah Luwu Timur', 'tipe' => 'cabang', 'lat' => -2.6000, 'lng' => 121.1000, 'radius_m' => 200, 'address' => 'Jl. Soekarno Hatta — Malili'],
            ['name' => 'Kab. Luwu Utara', 'slug' => 'kab-luwu-utara', 'kantor_name' => 'Kantor Wilayah Luwu Utara', 'tipe' => 'cabang', 'lat' => -2.7700, 'lng' => 120.1000, 'radius_m' => 200, 'address' => 'Jl. Simpurusiang — Masamba'],
            ['name' => 'Kab. Pangkajene dan Kepulauan', 'slug' => 'kab-pangkep', 'kantor_name' => 'Kantor Wilayah Pangkep', 'tipe' => 'cabang', 'lat' => -4.8400, 'lng' => 119.5400, 'radius_m' => 200, 'address' => 'Jl. H. Abd. Rahman — Pangkajene'],
            ['name' => 'Kab. Pinrang', 'slug' => 'kab-pinrang', 'kantor_name' => 'Kantor Wilayah Pinrang', 'tipe' => 'cabang', 'lat' => -3.7900, 'lng' => 119.6500, 'radius_m' => 200, 'address' => 'Jl. Bintang — Pinrang'],
            ['name' => 'Kab. Sinjai', 'slug' => 'kab-sinjai', 'kantor_name' => 'Kantor Wilayah Sinjai', 'tipe' => 'cabang', 'lat' => -5.1200, 'lng' => 120.2500, 'radius_m' => 200, 'address' => 'Jl. Persatuan Raya — Sinjai'],
            ['name' => 'Kab. Soppeng', 'slug' => 'kab-soppeng', 'kantor_name' => 'Kantor Wilayah Soppeng', 'tipe' => 'cabang', 'lat' => -4.3500, 'lng' => 119.8800, 'radius_m' => 200, 'address' => 'Jl. Lamumpatue — Watansoppeng'],
            ['name' => 'Kab. Takalar', 'slug' => 'kab-takalar', 'kantor_name' => 'Kantor Wilayah Takalar', 'tipe' => 'cabang', 'lat' => -5.4100, 'lng' => 119.4400, 'radius_m' => 200, 'address' => 'Jl. Syekh Yusuf — Takalar'],
            ['name' => 'Kab. Tana Toraja', 'slug' => 'kab-tana-toraja', 'kantor_name' => 'Kantor Wilayah Tana Toraja', 'tipe' => 'cabang', 'lat' => -3.0400, 'lng' => 119.8400, 'radius_m' => 200, 'address' => 'Jl. Pongtiku — Makale'],
            ['name' => 'Kab. Toraja Utara', 'slug' => 'kab-toraja-utara', 'kantor_name' => 'Kantor Wilayah Toraja Utara', 'tipe' => 'cabang', 'lat' => -3.0500, 'lng' => 119.8100, 'radius_m' => 200, 'address' => 'Jl. Poros Rantepao — Rantepao'],
            ['name' => 'Kab. Wajo', 'slug' => 'kab-wajo', 'kantor_name' => 'Kantor Wilayah Wajo', 'tipe' => 'cabang', 'lat' => -4.1200, 'lng' => 120.0300, 'radius_m' => 200, 'address' => 'Jl. Andi Paddanguri — Sengkang'],
            ['name' => 'Kab. Sidrap', 'slug' => 'kab-sidrap', 'kantor_name' => 'Kantor Wilayah Sidrap', 'tipe' => 'cabang', 'lat' => -3.9400, 'lng' => 119.7900, 'radius_m' => 200, 'address' => 'Jl. Jenderal Sudirman — Pangkajene Sidenreng'],
        ];

        foreach ($regions as $r) {
            DB::table('regions')->updateOrInsert(
                ['slug' => $r['slug']],
                array_merge($r, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
