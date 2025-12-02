<?php

namespace App\Models\Business;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'business_wave_id',
        'business_convoy_id',
        'reference',
        'status',
        'total_amount',
        'total_paid',
        'currency',
        'has_debt',
        'is_fully_received',
        'total_purchase_cost',
        'total_margin_amount',
        'margin_rate',
        'purchase_account_id',
        'pickup_receiver_name',
        'pickup_receiver_phone',
        'pickup_receiver_id_number',
        'pickup_receiver_note',
        'picked_up_at',
        'picked_up_by_user_id',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'total_purchase_cost' => 'decimal:2',
            'total_margin_amount' => 'decimal:2',
            'margin_rate' => 'decimal:2',
            'has_debt' => 'boolean',
            'is_fully_received' => 'boolean',
        ];
    }

    /**
     * Relations avec le client
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Relations avec la vague Business
     */
    public function wave()
    {
        return $this->belongsTo(BusinessWave::class, 'business_wave_id');
    }

    /**
     * Relations avec le convoi Business
     */
    public function convoy()
    {
        return $this->belongsTo(BusinessConvoy::class, 'business_convoy_id');
    }

    /**
     * Relations avec les lignes de commande
     */
    public function items()
    {
        return $this->hasMany(BusinessOrderItem::class);
    }

    /**
     * Relations avec l'utilisateur qui a créé la commande
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Relations avec l'utilisateur qui a modifié la commande
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Relations avec l'utilisateur qui a validé la récupération
     */
    public function pickedUpBy()
    {
        return $this->belongsTo(User::class, 'picked_up_by_user_id');
    }

    /**
     * Relations avec le compte utilisé pour l'achat
     */
    public function purchaseAccount()
    {
        return $this->belongsTo(\App\Models\Account::class, 'purchase_account_id');
    }

    /**
     * Relations avec les transactions financières
     */
    public function transactions()
    {
        return $this->morphMany(\App\Models\FinancialTransaction::class, 'related');
    }
}

