<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ToleranceClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'jenis',
        'claim_date',
        'jam',
        'alasan',
        'site_id',
        'region_id',
        'approver_id',
        'status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'claim_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function attendance(): HasOne
    {
        return $this->hasOne(Attendance::class, 'tolerance_claim_id');
    }
}
