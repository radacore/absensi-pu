<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Buku catatan pekerjaan: karyawan mengisi ringkasan pekerjaannya saat
     * absen pulang. Kolom nullable agar baris absensi lama (dan absen yang
     * tidak lewat jalur baru) tetap valid.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('catatan_pulang', 500)->nullable()->after('selfie_pulang_url');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['catatan_pulang']);
        });
    }
};
