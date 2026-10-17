<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Satu orang boleh memegang dua peran sekaligus: satu baris di
            // `employees` (identitas absensi) dan satu akun admin yang menunjuk
            // ke baris itu. Kolom inilah yang memastikan keduanya orang yang
            // sama, bukan dua akun terpisah dengan dua kata sandi.
            $table->foreignId('employee_id')
                ->nullable()
                ->unique()
                ->constrained('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['employee_id']);
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
