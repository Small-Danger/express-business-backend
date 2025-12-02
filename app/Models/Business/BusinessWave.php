<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessWave extends Model
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
     * Relations avec les convois Business
     */
    public function convoys()
    {
        return $this->hasMany(BusinessConvoy::class);
    }

    /**
     * Relations avec les commandes Business
     */
    public function orders()
    {
        return $this->hasMany(BusinessOrder::class);
    }

    /**
     * Relations avec les frais de la vague
     */
    public function costs()
    {
        return $this->hasMany(BusinessWaveCost::class);
    }
}

