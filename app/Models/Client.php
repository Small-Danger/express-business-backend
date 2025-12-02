<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'first_name',
        'last_name',
        'phone',
        'whatsapp_phone',
        'email',
        'country',
        'city',
        'address',
        'is_business_client',
        'is_express_client',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_business_client' => 'boolean',
            'is_express_client' => 'boolean',
        ];
    }

    /**
     * Relations avec les commandes Business
     */
    public function businessOrders()
    {
        return $this->hasMany(Business\BusinessOrder::class);
    }

    /**
     * Relations avec les colis Express (en tant qu'expéditeur)
     */
    public function expressParcels()
    {
        return $this->hasMany(Express\ExpressParcel::class, 'client_id');
    }

    /**
     * Relations avec les colis Express (en tant que receveur)
     */
    public function receivedExpressParcels()
    {
        return $this->hasMany(Express\ExpressParcel::class, 'receiver_client_id');
    }

    /**
     * Accessor pour le nom complet
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}

