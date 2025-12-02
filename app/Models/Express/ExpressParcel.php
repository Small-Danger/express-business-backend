<?php

namespace App\Models\Express;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpressParcel extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'receiver_client_id',
        'express_trip_id',
        'reference',
        'description',
        'weight_kg',
        'price_mad',
        'price_cfa',
        'total_paid',
        'has_debt',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
        'pickup_receiver_name',
        'pickup_receiver_phone',
        'pickup_receiver_id_number',
        'pickup_receiver_note',
        'picked_up_at',
        'picked_up_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:3',
            'price_mad' => 'decimal:2',
            'price_cfa' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'has_debt' => 'boolean',
            'picked_up_at' => 'datetime',
        ];
    }

    /**
     * Relations avec le client expéditeur
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * Relations avec le client receveur officiel
     */
    public function receiverClient()
    {
        return $this->belongsTo(Client::class, 'receiver_client_id');
    }

    /**
     * Relations avec le trajet Express
     */
    public function trip()
    {
        return $this->belongsTo(ExpressTrip::class, 'express_trip_id');
    }

    /**
     * Relations avec l'utilisateur qui a créé le colis
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Relations avec l'utilisateur qui a modifié le colis
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Relations avec l'utilisateur qui a validé la remise du colis
     */
    public function pickedUpBy()
    {
        return $this->belongsTo(User::class, 'picked_up_by_user_id');
    }

    /**
     * Relations avec l'historique des statuts
     */
    public function statusHistory()
    {
        return $this->hasMany(ExpressParcelStatusHistory::class);
    }

    /**
     * Accesseur : Déterminer la devise principale du colis
     * La devise principale est celle avec un montant > 0
     */
    public function getCurrencyAttribute(): string
    {
        // Si price_mad > 0, la devise principale est MAD
        if ($this->price_mad > 0) {
            return 'MAD';
        }
        // Sinon, la devise principale est CFA
        return 'CFA';
    }

    /**
     * Accesseur : Obtenir le montant principal dans la devise d'origine
     */
    public function getPriceAttribute(): float
    {
        return $this->currency === 'MAD' ? (float) $this->price_mad : (float) $this->price_cfa;
    }

    /**
     * Accesseur : Obtenir le prix équivalent en MAD (calculé si nécessaire)
     */
    public function getPriceInMadAttribute(): float
    {
        if ($this->currency === 'MAD') {
            return (float) $this->price_mad;
        }
        // Convertir CFA en MAD avec le taux actuel (on devrait utiliser le taux stocké mais pour compatibilité on utilise le service)
        $currencyConverter = app(\App\Services\CurrencyConverterService::class);
        return $currencyConverter->convertCfaToMad((float) $this->price_cfa);
    }

    /**
     * Accesseur : Obtenir le prix équivalent en CFA (calculé si nécessaire)
     */
    public function getPriceInCfaAttribute(): float
    {
        if ($this->currency === 'CFA') {
            return (float) $this->price_cfa;
        }
        // Convertir MAD en CFA avec le taux actuel
        $currencyConverter = app(\App\Services\CurrencyConverterService::class);
        return $currencyConverter->convertMadToCfa((float) $this->price_mad);
    }
}

