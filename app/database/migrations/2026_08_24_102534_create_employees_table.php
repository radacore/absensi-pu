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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 16)->unique();
            $table->string('nip')->nullable()->unique();
            $table->string('name');
            $table->string('golongan')->nullable();
            $table->string('jabatan');
            $table->string('unit_kerja');
            $table->enum('status_kepegawaian', ['PNS', 'PPPK', 'Kontrak', 'Honorer']);
            $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
            $table->string('password');
            $table->string('foto_url')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
