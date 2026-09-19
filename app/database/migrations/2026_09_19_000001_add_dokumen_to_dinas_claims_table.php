<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dinas_claims', function (Blueprint $table) {
            $table->string('dokumen_path', 255)->nullable()->after('note');
            $table->string('dokumen_nama', 255)->nullable()->after('dokumen_path');
            $table->string('dokumen_mime', 100)->nullable()->after('dokumen_nama');
            $table->unsignedBigInteger('dokumen_size')->nullable()->after('dokumen_mime');
        });
    }

    public function down(): void
    {
        Schema::table('dinas_claims', function (Blueprint $table) {
            $table->dropColumn(['dokumen_path', 'dokumen_nama', 'dokumen_mime', 'dokumen_size']);
        });
    }
};
