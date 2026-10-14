<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan pulang cepat (P2/P5).
 *
 * Pekerja shift tidak bisa absen pulang sebelum jam shift berakhir kecuali
 * pengajuan tanggal tersebut sudah disetujui Admin Wilayah.
 */
class EarlyLeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'tanggal',
        'jam_rencana',
        'alasan',
        'approver_id',
        'approved_by',
        'status',
        'note',
        'site_id',
        'region_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Admin yang ditunjuk karyawan sebagai approver. */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /** Admin yang menekan setujui/tolak. */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
