<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Izin pulang di titik yang berbeda dari titik masuk.
 *
 * Bawaannya MATI: karyawan hanya boleh absen pulang di titik tempat ia absen
 * masuk. Admin menyalakannya per karyawan bila memang pekerjaannya berpindah
 * lokasi dalam satu hari. Titik pulang tetap harus salah satu titik yang
 * terdaftar untuk karyawan tersebut — izin ini tidak membuka absen di
 * sembarang tempat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('boleh_pindah_titik')->default(false)->after('is_shift');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('boleh_pindah_titik');
        });
    }
};
