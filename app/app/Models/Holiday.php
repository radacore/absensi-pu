<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = [
        'tanggal',
        'nama',
        'cuti_bersama',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'cuti_bersama' => 'boolean',
        ];
    }
}
