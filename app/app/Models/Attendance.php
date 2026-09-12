<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'work_date',
        'clock_in_at',
        'clock_out_at',
        'status',
        'lat_in',
        'lng_in',
        'lat_out',
        'lng_out',
        'distance_in_m',
        'selfie_url',
        'site_id',
        'region_id',
        'tolerance_claim_id',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function toleranceClaim(): BelongsTo
    {
        return $this->belongsTo(ToleranceClaim::class);
    }
}
