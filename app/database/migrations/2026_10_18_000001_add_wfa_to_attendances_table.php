<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis kerja WFO/WFA pada satu baris absensi.
 *
 * WFA dipilih karyawan saat absen masuk (C2), tetap merekam koordinat dan
 * swafoto (C1/C3), dan menunggu persetujuan atasan (C4). Radius kantor tidak
 * berlaku pada hari WFA (C1/C6). Pekerja shift tidak mengenal WFA (C8).
 *
 * `di_luar_titik` menandai absen WFO yang jatuh di luar radius semua titik
 * terdaftar. Absennya TETAP tercatat, hanya diberi keterangan (jawaban E6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('jenis_kerja', 3)->default('WFO')->after('shift_ke');
            $table->string('wfa_alasan', 500)->nullable()->after('jenis_kerja');
            $table->string('wfa_status', 12)->nullable()->after('wfa_alasan');
            $table->string('wfa_note', 500)->nullable()->after('wfa_status');
            $table->foreignId('wfa_approved_by')->nullable()->after('wfa_note')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('wfa_approved_at')->nullable()->after('wfa_approved_by');
            $table->boolean('di_luar_titik')->default(false)->after('distance_in_m');
            $table->boolean('di_luar_titik_pulang')->default(false)->after('di_luar_titik');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wfa_approved_by');
            $table->dropColumn([
                'jenis_kerja',
                'wfa_alasan',
                'wfa_status',
                'wfa_note',
                'wfa_approved_at',
                'di_luar_titik',
                'di_luar_titik_pulang',
            ]);
        });
    }
};
