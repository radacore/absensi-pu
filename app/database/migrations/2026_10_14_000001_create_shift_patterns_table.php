<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pola shift kerja.
     *
     * Yang diisi admin hanyalah `jam_mulai_shift_1`; panjang setiap shift
     * DITURUNKAN dari jumlah shift (24 jam ÷ jumlah shift). Jadi 2 shift →
     * 12 jam/shift, 3 shift → 8 jam/shift, 4 shift → 6 jam/shift.
     *
     * Cakupan (K5): baris tanpa region_id & site_id = global; hanya region_id =
     * per wilayah; hanya site_id = per lokasi. Yang paling spesifik menang.
     */
    public function up(): void
    {
        Schema::create('shift_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->unsignedTinyInteger('jumlah_shift');
            $table->time('jam_mulai_shift_1');
            $table->foreignId('region_id')->nullable()->constrained('regions')->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained('sites')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['region_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_patterns');
    }
};
