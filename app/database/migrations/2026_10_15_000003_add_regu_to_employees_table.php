<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4 — regu kerja bersifat BEBAS, bukan angka tetap.
 *
 * Label regu (mis. "Regu A", "Tim Malam") hanya dipakai untuk mengelompokkan
 * dan mengisi jadwal shift secara massal. Null = tidak ikut regu mana pun,
 * jadi jumlah regu tidak perlu ditentukan lebih dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('regu', 30)->nullable()->after('hari_kerja');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('regu');
        });
    }
};
