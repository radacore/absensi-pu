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
        'dokumen_path',
        'dokumen_nama',
        'dokumen_mime',
        'dokumen_size',
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

    /** Dokumen pendukung (surat tugas) tersimpan atau tidak. */
    public function hasDokumen(): bool
    {
        return ! empty($this->dokumen_path);
    }

    /** Dokumen berupa gambar (bisa dipratinjau langsung di browser). */
    public function dokumenIsImage(): bool
    {
        return str_starts_with((string) $this->dokumen_mime, 'image/');
    }

    /** Ukuran dokumen dalam format ramah baca, mis. "1.4 MB". */
    public function dokumenSizeLabel(): string
    {
        $bytes = (int) $this->dokumen_size;

        if ($bytes <= 0) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);

        return number_format($bytes / (1024 ** $i), $i === 0 ? 0 : 1).' '.$units[$i];
    }
}
