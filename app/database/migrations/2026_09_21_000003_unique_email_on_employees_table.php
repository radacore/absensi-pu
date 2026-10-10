<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Email adalah satu-satunya kredensial login karyawan, jadi harus unik.
     * Kolom tetap nullable (beberapa baris lama mungkin kosong) — baris
     * tanpa email tidak bisa login sampai dilengkapi via admin/profil.
     * MySQL mengizinkan banyak NULL dalam unique index.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['email']);
        });
    }
};
