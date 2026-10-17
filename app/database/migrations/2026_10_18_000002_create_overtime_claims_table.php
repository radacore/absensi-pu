<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lembur dihitung tersendiri (A8).
 *
 * Jam lembur tidak digabung ke jam kerja biasa. Karyawan mengajukan tanggal,
 * jam mulai, dan jam selesai; Admin Wilayah menyetujui. Rekap menampilkan
 * menit lembur yang sudah disetujui sebagai angka terpisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedBigInteger('region_id');
            $table->unsignedBigInteger('site_id')->nullable();
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->unsignedInteger('menit')->default(0);
            $table->string('keterangan', 1000);
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12)->default('Menunggu');
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'tanggal']);
            $table->index(['region_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_claims');
    }
};
