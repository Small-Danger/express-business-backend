<?php

namespace App\Models\Business;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessConvoyCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_convoy_id',
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
     * Relations avec le convoi Business
     */
    public function convoy()
    {
        return $this->belongsTo(BusinessConvoy::class, 'business_convoy_id');
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

