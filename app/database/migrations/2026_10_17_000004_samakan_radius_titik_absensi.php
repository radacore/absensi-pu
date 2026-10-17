<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Permintaan client: seluruh titik absen memakai radius 200 meter.
     * Seeder sudah disamakan, migrasi ini menyamakan data yang sudah ada
     * di server. Hanya berjalan sekali, jadi perubahan radius lewat halaman
     * Wilayah sesudah migrasi ini tidak ikut ditimpa.
     */
    public function up(): void
    {
        DB::table('sites')
            ->where('radius_m', '!=', 200)
            ->update(['radius_m' => 200]);
    }

    /**
     * Radius lama tidak disimpan, jadi tidak ada nilai yang bisa dikembalikan.
     * Mengembalikan ke angka baru hasil tebakan justru menyesatkan.
     */
    public function down(): void
    {
        //
    }
};
