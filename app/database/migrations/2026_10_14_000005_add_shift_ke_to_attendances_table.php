<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot shift yang berlaku saat karyawan absen masuk.
     *
     * Disimpan di baris absensi supaya rekap tetap benar walaupun jadwal shift
     * diedit belakangan. Null = karyawan bukan pekerja shift (atau tidak punya
     * jadwal shift pada tanggal itu).
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->unsignedTinyInteger('shift_ke')->nullable()->after('clock_out_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('shift_ke');
        });
    }
};
