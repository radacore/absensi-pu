<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Titik absensi TAMBAHAN per karyawan.
     *
     * `employees.site_id` tetap menjadi titik utama dan TIDAK diduplikasi di
     * sini. Tabel ini hanya menyimpan titik lain yang juga sah untuk absen,
     * sehingga satu karyawan bisa absen di lebih dari satu lokasi tanpa
     * mengubah arti kolom lama (pola shift, impor, dan approver admin wilayah
     * tetap membacanya sebagai titik utama).
     */
    public function up(): void
    {
        Schema::create('employee_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained('sites')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'site_id']);
            $table->index('site_id');
        });

        // Titik absen pulang boleh berbeda dari titik absen masuk. `site_id`
        // tetap berarti titik masuk supaya laporan lama tidak berubah arti.
        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('site_out_id')->nullable()->after('site_id')
                ->constrained('sites')->nullOnDelete();
        });

        // Data lama hanya punya satu titik, jadi pivot memang kosong. Baris di
        // bawah membersihkan sisa titik utama bila tabel pernah diisi manual
        // sebelum migrasi ini (mis. saat uji coba).
        DB::table('employees')->orderBy('id')->each(function ($employee) {
            DB::table('employee_sites')
                ->where('employee_id', $employee->id)
                ->where('site_id', $employee->site_id)
                ->delete();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_out_id');
        });

        Schema::dropIfExists('employee_sites');
    }
};
