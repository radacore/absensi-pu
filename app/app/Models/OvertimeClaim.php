<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan lembur.
 *
 * Lembur dicatat terpisah dari jam kerja biasa (jawaban A8). Karyawan
 * mengajukan tanggal, jam mulai, dan jam selesai; Admin Wilayah menyetujui
 * atau menolak. Hanya pengajuan yang disetujui yang masuk hitungan rekap.
 */
class OvertimeClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'region_id',
        'site_id',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'menit',
        'keterangan',
        'approver_id',
        'approved_by',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'menit' => 'integer',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
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
