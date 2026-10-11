<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            // Kuota cuti tahunan (hari kerja) per karyawan per tahun kalender.
            // Dibaca & ditegakkan App\Support\CutiKuota; diubah Super Admin
            // dari /super-admin/settings.
            $table->unsignedSmallInteger('cuti_tahunan_max')->default(12)->after('love_max');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('cuti_tahunan_max');
        });
    }
};
