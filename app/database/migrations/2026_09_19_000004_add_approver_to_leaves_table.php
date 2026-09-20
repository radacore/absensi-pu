<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approver cuti hanya boleh akun admin.
     *
     * approver_id  → admin yang ditunjuk karyawan sebagai approver level 1 (opsional).
     * approved_by  → admin yang benar-benar menekan approve terakhir kali (jejak audit).
     */
    public function up(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->foreignId('approver_id')->nullable()->after('level')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('approver_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approver_id');
            $table->dropConstrainedForeignId('approved_by');
        });
    }
};
