<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gerbang absensi di luar hari kerja (akhir pekan / hari libur nasional).
     *
     * absen_libur_aktif = false → perilaku lama: absen akhir pekan selalu dicatat
     *                             seperti hari kerja biasa (tanpa status khusus).
     * absen_libur_aktif = true  → absen_libur_mode menentukan:
     *   'tolak' → absen di luar hari kerja ditolak (422).
     *   'catat' → absen tetap dicatat, statusnya 'libur'.
     */
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->boolean('absen_libur_aktif')->default(false)->after('love_max');
            $table->enum('absen_libur_mode', ['tolak', 'catat'])->default('tolak')->after('absen_libur_aktif');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn(['absen_libur_aktif', 'absen_libur_mode']);
        });
    }
};
