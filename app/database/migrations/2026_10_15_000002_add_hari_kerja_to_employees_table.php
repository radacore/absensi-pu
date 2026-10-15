<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B3 — hari kerja boleh berbeda per orang.
 *
 * Null = ikut pengaturan umum (`attendance_settings.hari_kerja`), sehingga
 * tidak ada perubahan perilaku bagi karyawan yang tidak dikecualikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->json('hari_kerja')->nullable()->after('is_shift');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('hari_kerja');
        });
    }
};
