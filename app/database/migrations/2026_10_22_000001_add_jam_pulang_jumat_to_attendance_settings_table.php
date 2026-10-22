<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            // Jam pulang khusus Jumat. Null = ikut jam_pulang global, jadi
            // bawaan tidak mengubah perilaku yang sudah berjalan. Dipakai
            // App\Models\AttendanceSetting::jamPulangPada() supaya rekap PSW,
            // status early_leave, dan tampilan jadwal memakai jam yang sama.
            $table->time('jam_pulang_jumat')->nullable()->after('jam_pulang');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('jam_pulang_jumat');
        });
    }
};
