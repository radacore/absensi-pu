<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dua perubahan, keduanya karena data asli di lapangan.
     *
     * 1. `nik` dilonggarkan. Berkas data dari PPK memuat NIK yang panjangnya
     *    tidak selalu 16 digit, dan ada pegawai yang NIK-nya memang belum
     *    terisi. Kolomnya tetap unik — nilai kosong disimpan sebagai NULL, dan
     *    NULL boleh berulang di MySQL maupun SQLite.
     *
     * 2. `ppk` ditambahkan. Berkas yang sama memuat kolom PPK, dan itu data
     *    kepegawaian yang nyata, jadi disimpan apa adanya sebagai label.
     *    PPK tidak dipakai sebagai batas hak akses.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('nik', 30)->nullable()->change();
            $table->string('ppk', 150)->nullable()->after('unit_kerja');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('ppk');
        });
    }
};
