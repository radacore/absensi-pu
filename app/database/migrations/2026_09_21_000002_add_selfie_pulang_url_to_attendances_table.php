<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bukti foto wajib untuk absen pulang (kebijakan: foto live saat
     * datang DAN pulang). Kolom nullable agar baris lama tetap valid.
     */
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('selfie_pulang_url')->nullable()->after('selfie_url');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['selfie_pulang_url']);
        });
    }
};
