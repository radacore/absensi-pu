<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda "karyawan ini pekerja shift" (K4: per orang saja).
     *
     * Hanya karyawan dengan is_shift = true yang punya jadwal shift dan
     * absennya mengikuti jam shift, bukan jam masuk/pulang global.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_shift')->default(false)->after('status_kepegawaian');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('is_shift');
        });
    }
};
