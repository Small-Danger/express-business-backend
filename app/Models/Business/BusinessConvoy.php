<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessConvoy extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_wave_id',
        'name',
        'traveler_name',
        'from_country',
        'from_city',
        'to_country',
        'to_city',
        'planned_departure_date',
        'planned_arrival_date',
        'end_date',
        'actual_departure_date',
        'actual_arrival_date',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_departure_date' => 'date',
            'planned_arrival_date' => 'date',
            'end_date' => 'date',
            'actual_departure_date' => 'datetime',
            'actual_arrival_date' => 'datetime',
        ];
    }

    /**
     * Relations avec la vague Business
     */
    public function wave()
    {
        return $this->belongsTo(BusinessWave::class, 'business_wave_id');
    }

    /**
     * Relations avec les commandes Business
     */
    public function orders()
    {
        return $this->hasMany(BusinessOrder::class);
    }

    /**
     * Relations avec les frais du convoi
     */
    public function costs()
    {
        return $this->hasMany(BusinessConvoyCost::class);
    }
}

