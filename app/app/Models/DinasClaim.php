<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DinasClaim extends Model
{
    protected $fillable = [
        'employee_id',
        'nomor_surat',
        'tanggal_mulai',
        'tanggal_selesai',
        'tanggal_pengajuan',
        'keterangan',
        'tujuan',
        'transportasi',
        'pembebanan_anggaran',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'tanggal_pengajuan' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
