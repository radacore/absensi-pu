<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadwal shift mingguan yang disusun MANUAL oleh Admin Wilayah (K6–K9).
     *
     * Satu baris = satu karyawan pada satu tanggal. `shift_ke` null berarti
     * karyawan libur pada tanggal itu (baris sengaja disimpan supaya admin
     * bisa menandai libur secara eksplisit).
     */
    public function up(): void
    {
        Schema::create('shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('tanggal');
            $table->unsignedTinyInteger('shift_ke')->nullable();
            $table->foreignId('shift_pattern_id')->nullable()->constrained('shift_patterns')->nullOnDelete();
            $table->string('catatan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'tanggal']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_schedules');
    }
};
