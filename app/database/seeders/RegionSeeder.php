<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        $regions = [
            ['name' => 'Kota Makassar', 'slug' => 'kota-makassar', 'kantor_name' => 'Kantor Pusat', 'tipe' => 'pusat', 'address' => 'Jl. AP Pettarani No.1 — Makassar'],
            ['name' => 'Kab. Gowa', 'slug' => 'kab-gowa', 'kantor_name' => 'Kantor Wilayah Gowa', 'tipe' => 'cabang', 'address' => 'Jl. Poros Malino — Gowa'],
            ['name' => 'Kab. Maros', 'slug' => 'kab-maros', 'kantor_name' => 'Kantor Wilayah Maros', 'tipe' => 'cabang', 'address' => 'Jl. Poros Maros'],
            ['name' => 'Kab. Bone', 'slug' => 'kab-bone', 'kantor_name' => 'Kantor Wilayah Bone', 'tipe' => 'cabang', 'address' => 'Jl. Ahmad Yani — Bone'],
            ['name' => 'Kota Parepare', 'slug' => 'kota-parepare', 'kantor_name' => 'Kantor Wilayah Parepare', 'tipe' => 'cabang', 'address' => 'Jl. Andi Makkasau — Parepare'],
            ['name' => 'Kota Palopo', 'slug' => 'kota-palopo', 'kantor_name' => 'Kantor Wilayah Palopo', 'tipe' => 'cabang', 'address' => 'Jl. Andi Djemma — Palopo'],
            ['name' => 'Kab. Bantaeng', 'slug' => 'kab-bantaeng', 'kantor_name' => 'Kantor Wilayah Bantaeng', 'tipe' => 'cabang', 'address' => 'Jl. Andi Mannappiang — Bantaeng'],
            ['name' => 'Kab. Barru', 'slug' => 'kab-barru', 'kantor_name' => 'Kantor Wilayah Barru', 'tipe' => 'cabang', 'address' => 'Jl. Sultan Hasanuddin — Barru'],
            ['name' => 'Kab. Bulukumba', 'slug' => 'kab-bulukumba', 'kantor_name' => 'Kantor Wilayah Bulukumba', 'tipe' => 'cabang', 'address' => 'Jl. Sam Ratulangi — Bulukumba'],
            ['name' => 'Kab. Enrekang', 'slug' => 'kab-enrekang', 'kantor_name' => 'Kantor Wilayah Enrekang', 'tipe' => 'cabang', 'address' => 'Jl. Pahlawan — Enrekang'],
            ['name' => 'Kab. Jeneponto', 'slug' => 'kab-jeneponto', 'kantor_name' => 'Kantor Wilayah Jeneponto', 'tipe' => 'cabang', 'address' => 'Jl. Pahlawan — Jeneponto'],
            ['name' => 'Kab. Kepulauan Selayar', 'slug' => 'kab-selayar', 'kantor_name' => 'Kantor Wilayah Selayar', 'tipe' => 'cabang', 'address' => 'Jl. Ahmad Yani — Benteng Selayar'],
            ['name' => 'Kab. Luwu', 'slug' => 'kab-luwu', 'kantor_name' => 'Kantor Wilayah Luwu', 'tipe' => 'cabang', 'address' => 'Jl. Trans Sulawesi — Belopa'],
            ['name' => 'Kab. Luwu Timur', 'slug' => 'kab-luwu-timur', 'kantor_name' => 'Kantor Wilayah Luwu Timur', 'tipe' => 'cabang', 'address' => 'Jl. Soekarno Hatta — Malili'],
            ['name' => 'Kab. Luwu Utara', 'slug' => 'kab-luwu-utara', 'kantor_name' => 'Kantor Wilayah Luwu Utara', 'tipe' => 'cabang', 'address' => 'Jl. Simpurusiang — Masamba'],
            ['name' => 'Kab. Pangkajene dan Kepulauan', 'slug' => 'kab-pangkep', 'kantor_name' => 'Kantor Wilayah Pangkep', 'tipe' => 'cabang', 'address' => 'Jl. H. Abd. Rahman — Pangkajene'],
            ['name' => 'Kab. Pinrang', 'slug' => 'kab-pinrang', 'kantor_name' => 'Kantor Wilayah Pinrang', 'tipe' => 'cabang', 'address' => 'Jl. Bintang — Pinrang'],
            ['name' => 'Kab. Sinjai', 'slug' => 'kab-sinjai', 'kantor_name' => 'Kantor Wilayah Sinjai', 'tipe' => 'cabang', 'address' => 'Jl. Persatuan Raya — Sinjai'],
            ['name' => 'Kab. Soppeng', 'slug' => 'kab-soppeng', 'kantor_name' => 'Kantor Wilayah Soppeng', 'tipe' => 'cabang', 'address' => 'Jl. Lamumpatue — Watansoppeng'],
            ['name' => 'Kab. Takalar', 'slug' => 'kab-takalar', 'kantor_name' => 'Kantor Wilayah Takalar', 'tipe' => 'cabang', 'address' => 'Jl. Syekh Yusuf — Takalar'],
            ['name' => 'Kab. Tana Toraja', 'slug' => 'kab-tana-toraja', 'kantor_name' => 'Kantor Wilayah Tana Toraja', 'tipe' => 'cabang', 'address' => 'Jl. Pongtiku — Makale'],
            ['name' => 'Kab. Toraja Utara', 'slug' => 'kab-toraja-utara', 'kantor_name' => 'Kantor Wilayah Toraja Utara', 'tipe' => 'cabang', 'address' => 'Jl. Poros Rantepao — Rantepao'],
            ['name' => 'Kab. Wajo', 'slug' => 'kab-wajo', 'kantor_name' => 'Kantor Wilayah Wajo', 'tipe' => 'cabang', 'address' => 'Jl. Andi Paddanguri — Sengkang'],
            ['name' => 'Kab. Sidrap', 'slug' => 'kab-sidrap', 'kantor_name' => 'Kantor Wilayah Sidrap', 'tipe' => 'cabang', 'address' => 'Jl. Jenderal Sudirman — Pangkajene Sidenreng'],
        ];

        // ID wilayah sengaja dibuat DETERMINISTIK (1..24 mengikuti urutan
        // daftar di atas). Seeder lain dan uji merujuk ID wilayah secara
        // langsung (mis. region_id 2 = Kab. Gowa).
        //
        // Tanpa ini penyemaian gagal di MySQL: ROLLBACK tidak mengembalikan
        // counter AUTO_INCREMENT, jadi penyemaian kedua dalam satu proses
        // (mis. tiap tes yang dibungkus transaksi oleh RefreshDatabase)
        // menghasilkan ID 25..48 — dan semua rujukan ke region_id 1..24 patah.
        // SQLite tidak memperlihatkan gejala ini.
        foreach ($regions as $i => $r) {
            $region = Region::firstOrNew(['slug' => $r['slug']]);
            $region->fill($r);

            if (! $region->exists) {
                $region->id = $i + 1;
            }

            $region->save();
        }
    }
}
