<?php

namespace App\Models\Express;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpressWaveCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'express_wave_id',
        'type',
        'label',
        'amount',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Relations avec la vague Express
     */
    public function wave()
    {
        return $this->belongsTo(ExpressWave::class, 'express_wave_id');
    }
}

