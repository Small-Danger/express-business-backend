<?php

namespace App\Models\Express;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpressTripCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'express_trip_id',
        'account_id',
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
     * Relations avec le trajet Express
     */
    public function trip()
    {
        return $this->belongsTo(ExpressTrip::class, 'express_trip_id');
    }

    /**
     * Relations avec le compte
     */
    public function account()
    {
        return $this->belongsTo(\App\Models\Account::class);
    }

    /**
     * Relations avec les transactions financières
     */
    public function transactions()
    {
        return $this->morphMany(\App\Models\FinancialTransaction::class, 'related');
    }
}

