<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah nilai enum `libur` pada attendances.status.
     *
     * Dipakai ketika Super Admin mengaktifkan absen di luar hari kerja dengan
     * mode 'catat' — baris absensi tetap tersimpan tetapi ditandai 'libur'
     * supaya tidak ikut dihitung sebagai kehadiran hari kerja.
     */
    private const STATUS = ['on_time', 'late', 'excused_love', 'early_leave', 'libur'];

    private const STATUS_LAMA = ['on_time', 'late', 'excused_love', 'early_leave'];

    public function up(): void
    {
        $this->ubahEnum(self::STATUS);
    }

    public function down(): void
    {
        // Baris berstatus 'libur' tidak muat di enum lama → kembalikan ke 'on_time'
        // agar tidak ada data yang terpotong.
        DB::table('attendances')->where('status', 'libur')->update(['status' => 'on_time']);

        $this->ubahEnum(self::STATUS_LAMA);
    }

    /**
     * MySQL/MariaDB: MODIFY ENUM langsung (paling andal untuk kolom enum).
     * Driver lain (SQLite dipakai test suite): pakai ->change() agar Laravel
     * yang menyusun ulang definisi kolomnya.
     */
    private function ubahEnum(array $nilai): void
    {
        $daftar = implode(',', array_map(fn (string $v) => "'{$v}'", $nilai));

        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE attendances MODIFY status ENUM({$daftar}) NULL");

            return;
        }

        Schema::table('attendances', function (Blueprint $table) use ($nilai) {
            $table->enum('status', $nilai)->nullable()->change();
        });
    }
};
