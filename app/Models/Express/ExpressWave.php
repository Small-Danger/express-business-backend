<?php

namespace App\Models\Express;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpressWave extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Relations avec les trajets Express
     */
    public function trips()
    {
        return $this->hasMany(ExpressTrip::class);
    }

    /**
     * Relations avec les frais de la vague
     */
    public function costs()
    {
        return $this->hasMany(ExpressWaveCost::class);
    }
}

