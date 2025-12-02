<?php

namespace App\Models\Express;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpressTrip extends Model
{
    use HasFactory;

    protected $fillable = [
        'express_wave_id',
        'name',
        'direction',
        'from_country',
        'from_city',
        'to_country',
        'to_city',
        'planned_date',
        'end_date',
        'actual_date',
        'traveler_name',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_date' => 'date',
            'end_date' => 'date',
            'actual_date' => 'datetime',
        ];
    }

    /**
     * Relations avec la vague Express
     */
    public function wave()
    {
        return $this->belongsTo(ExpressWave::class, 'express_wave_id');
    }

    /**
     * Relations avec les colis Express
     */
    public function parcels()
    {
        return $this->hasMany(ExpressParcel::class);
    }

    /**
     * Relations avec les frais du trajet
     */
    public function costs()
    {
        return $this->hasMany(ExpressTripCost::class);
    }
}

