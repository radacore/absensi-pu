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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('work_date');
            $table->time('clock_in_at')->nullable();
            $table->time('clock_out_at')->nullable();
            $table->enum('status', ['on_time', 'late', 'excused_love', 'early_leave'])->nullable();
            $table->decimal('lat_in', 10, 8)->nullable();
            $table->decimal('lng_in', 11, 8)->nullable();
            $table->decimal('lat_out', 10, 8)->nullable();
            $table->decimal('lng_out', 11, 8)->nullable();
            $table->unsignedInteger('distance_in_m')->nullable();
            $table->string('selfie_url')->nullable();
            $table->unsignedBigInteger('site_id');
            $table->unsignedBigInteger('region_id');
            $table->foreignId('tolerance_claim_id')->nullable()->constrained('tolerance_claims')->nullOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
            $table->index('work_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
