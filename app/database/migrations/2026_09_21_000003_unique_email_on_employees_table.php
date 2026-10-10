<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Email adalah satu-satunya kredensial login karyawan, jadi harus unik.
     * Kolom tetap nullable (beberapa baris lama mungkin kosong) — baris
     * tanpa email tidak bisa login sampai dilengkapi via admin/profil.
     * MySQL mengizinkan banyak NULL dalam unique index.
     */
    public function up(): void
    {
        // Gagal cepat dengan pesan yang jelas, bukan error SQL mentah, kalau
        // data lama sudah punya email ganda. Login memakai LOWER(email), jadi
        // duplikat dicek tanpa peduli besar-kecil huruf.
        $duplikat = DB::table('employees')
            ->selectRaw('LOWER(email) AS email, COUNT(*) AS jumlah')
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->groupBy('email')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('email');

        if ($duplikat->isNotEmpty()) {
            throw new \RuntimeException(
                'Tidak bisa memasang unique index email: email ganda ditemukan → '
                .$duplikat->implode(', ')
                .'. Perbaiki datanya dulu (gabung/hapus duplikat) lalu jalankan ulang migrasi ini.'
            );
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
    }
};
