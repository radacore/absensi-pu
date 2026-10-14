<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengajuan pulang cepat (P2/P5).
     *
     * Pekerja shift tidak bisa pulang sebelum jam shift berakhir kecuali
     * pengajuannya sudah disetujui Admin Wilayah. Status memakai istilah
     * Indonesia, mengikuti tabel `leaves`.
     */
    public function up(): void
    {
        Schema::create('early_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('tanggal');
            $table->time('jam_rencana');
            $table->text('alasan');
            // nullOnDelete: menghapus akun admin tidak boleh menghapus
            // pengajuan karyawan — hanya melepas kaitannya.
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['Menunggu', 'Disetujui', 'Ditolak'])->default('Menunggu');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('site_id')->nullable();
            $table->unsignedBigInteger('region_id')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'tanggal']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('early_leave_requests');
    }
};
