<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->enum('jenis', ['Tahunan', 'Sakit', 'Besar', 'Melahirkan']);
            $table->date('mulai');
            $table->date('selesai');
            $table->text('alasan');
            $table->string('dokumen_path')->nullable();
            $table->enum('status', ['Menunggu', 'Disetujui', 'Ditolak'])->default('Menunggu');
            $table->unsignedTinyInteger('level')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'mulai', 'selesai']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
