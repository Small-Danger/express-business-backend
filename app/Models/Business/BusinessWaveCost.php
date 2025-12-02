<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessWaveCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_wave_id',
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
     * Relations avec la vague Business
     */
    public function wave()
    {
        return $this->belongsTo(BusinessWave::class, 'business_wave_id');
    }
}

