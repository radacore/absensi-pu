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
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->enum('type', ['in', 'out']);
            $table->timestamp('timestamp'); // WITA
            $table->decimal('lat', 10, 8);
            $table->decimal('lng', 11, 8);
            $table->string('selfie_url');
            $table->enum('status', ['on_time', 'late', 'out_of_range', 'early_leave']);
            $table->integer('distance_m')->nullable();
            $table->string('device_info')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'timestamp']);
            $table->index(['region_id', 'timestamp']);
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
