<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            // Seberapa dalam pola shift dicari (K5). Nilai:
            //   bertingkat → lokasi, lalu wilayah, lalu global (bawaan)
            //   wilayah    → wilayah, lalu global (pola per lokasi diabaikan)
            //   global     → hanya pola global
            // Dibaca App\Support\ShiftResolver supaya keputusan client soal
            // cakupan bisa diubah lewat pengaturan, tanpa ubah kode.
            $table->string('cakupan_pola_shift')->default('bertingkat')->after('cuti_tahunan_max');

            // Jatah cuti tahunan khusus pekerja shift (P3). 0 = ikut kuota umum,
            // jadi bawaan tidak mengubah perilaku yang sudah ada.
            $table->unsignedSmallInteger('cuti_shift_max')->default(0)->after('cakupan_pola_shift');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn(['cakupan_pola_shift', 'cuti_shift_max']);
        });
    }
};
