<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaiki foreign key ke tabel `users` yang memakai cascadeOnDelete.
 *
 * Menghapus akun admin sebelumnya IKUT MENGHAPUS baris milik orang lain
 * (klaim toleransi karyawan, pengumuman) — kehilangan data yang tidak
 * disengaja. Diganti nullOnDelete: kaitan dilepas, baris tetap ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->swapToNullOnDelete('tolerance_claims', 'approver_id');
        $this->swapToNullOnDelete('announcements', 'created_by');
    }

    public function down(): void
    {
        Schema::table('tolerance_claims', function (Blueprint $table) {
            $table->dropForeign(['approver_id']);
        });
        Schema::table('tolerance_claims', function (Blueprint $table) {
            $table->unsignedBigInteger('approver_id')->nullable(false)->change();
            $table->foreign('approver_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });
        Schema::table('announcements', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    private function swapToNullOnDelete(string $table, string $column): void
    {
        Schema::table($table, function (Blueprint $t) use ($column) {
            $t->dropForeign([$column]);
        });

        Schema::table($table, function (Blueprint $t) use ($column) {
            $t->unsignedBigInteger($column)->nullable()->change();
        });

        Schema::table($table, function (Blueprint $t) use ($column) {
            $t->foreign($column)->references('id')->on('users')->nullOnDelete();
        });
    }
};
