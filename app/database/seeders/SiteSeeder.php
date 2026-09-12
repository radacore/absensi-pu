<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\Site;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        $bySlug = Region::pluck('id', 'slug');

        // [slug, id, nama_lokasi, lat, lng, radius_m, address]
        $sites = [
            ['kota-makassar', 101, 'Bendungan Tallo — Makassar', -5.1477, 119.4327, 300, 'Jl. AP Pettarani No.1'],
            ['kota-makassar', 102, 'Jembatan Pettarani', -5.156, 119.44, 200, 'Jl. Pettarani'],
            ['kab-gowa', 201, 'Bendungan Bili-Bili', -5.3114, 119.42, 200, 'Jl. Poros Malino'],
            ['kab-gowa', 202, 'Jembatan Pampang', -5.32, 119.45, 150, 'Jl. Pampang'],
            ['kab-maros', 301, 'Kantor Maros', -5.005, 119.58, 200, 'Jl. Poros Maros'],
            ['kab-bone', 401, 'Kantor Bone', -4.54, 120.33, 150, 'Jl. Ahmad Yani — Bone'],
            ['kota-parepare', 501, 'Kantor Parepare', -4.0148, 119.625, 200, 'Jl. Andi Makkasau — Parepare'],
            ['kota-palopo', 601, 'Kantor Palopo', -3.0014, 120.192, 200, 'Jl. Andi Djemma — Palopo'],
            ['kab-bantaeng', 701, 'Kantor Bantaeng', -5.54, 119.93, 180, 'Jl. Andi Mannappiang — Bantaeng'],
            ['kab-barru', 801, 'Kantor Barru', -4.42, 119.68, 180, 'Jl. Sultan Hasanuddin — Barru'],
            ['kab-bulukumba', 901, 'Kantor Bulukumba', -5.56, 120.19, 200, 'Jl. Sam Ratulangi — Bulukumba'],
            ['kab-enrekang', 1001, 'Kantor Enrekang', -3.58, 119.77, 200, 'Jl. Pahlawan — Enrekang'],
            ['kab-jeneponto', 1101, 'Kantor Jeneponto', -5.66, 119.73, 200, 'Jl. Pahlawan — Jeneponto'],
            ['kab-selayar', 1201, 'Kantor Selayar', -6.12, 120.45, 250, 'Jl. Ahmad Yani — Benteng Selayar'],
            ['kab-luwu', 1301, 'Kantor Luwu', -3.39, 120.38, 200, 'Jl. Trans Sulawesi — Belopa'],
            ['kab-luwu-timur', 1401, 'Kantor Luwu Timur', -2.6, 121.1, 200, 'Jl. Soekarno Hatta — Malili'],
            ['kab-luwu-utara', 1501, 'Kantor Luwu Utara', -2.77, 120.1, 200, 'Jl. Simpurusiang — Masamba'],
            ['kab-pangkep', 1601, 'Kantor Pangkep', -4.84, 119.54, 200, 'Jl. H. Abd. Rahman — Pangkajene'],
            ['kab-pinrang', 1701, 'Kantor Pinrang', -3.79, 119.65, 200, 'Jl. Bintang — Pinrang'],
            ['kab-sinjai', 1801, 'Kantor Sinjai', -5.12, 120.25, 200, 'Jl. Persatuan Raya — Sinjai'],
            ['kab-soppeng', 1901, 'Kantor Soppeng', -4.35, 119.88, 200, 'Jl. Lamumpatue — Watansoppeng'],
            ['kab-takalar', 2001, 'Kantor Takalar', -5.41, 119.44, 200, 'Jl. Syekh Yusuf — Takalar'],
            ['kab-tana-toraja', 2101, 'Kantor Tana Toraja', -3.04, 119.84, 200, 'Jl. Pongtiku — Makale'],
            ['kab-toraja-utara', 2201, 'Kantor Toraja Utara', -3.05, 119.81, 200, 'Jl. Poros Rantepao — Rantepao'],
            ['kab-wajo', 2301, 'Kantor Wajo', -4.12, 120.03, 200, 'Jl. Andi Paddanguri — Sengkang'],
            ['kab-sidrap', 2401, 'Kantor Sidrap', -3.94, 119.79, 200, 'Jl. Jenderal Sudirman — Pangkajene Sidenreng'],
        ];

        foreach ($sites as [$slug, $id, $nama, $lat, $lng, $radius, $address]) {
            Site::create([
                'id' => $id,
                'region_id' => $bySlug[$slug],
                'nama_lokasi' => $nama,
                'lat' => $lat,
                'lng' => $lng,
                'radius_m' => $radius,
                'address' => $address,
            ]);
        }
    }
}
