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
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->time('jam_masuk')->default('07:30:00');
            $table->time('jam_pulang')->default('16:00:00');
            $table->integer('toleransi_late_menit')->default(15);
            $table->json('hari_kerja')->nullable(); // ["Senin","Selasa","Rabu","Kamis","Jumat"]
            $table->string('timezone')->default('Asia/Makassar');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
