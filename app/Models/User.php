<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Relations avec les commandes Business créées
     */
    public function createdBusinessOrders()
    {
        return $this->hasMany(\App\Models\Business\BusinessOrder::class, 'created_by_user_id');
    }

    /**
     * Relations avec les commandes Business modifiées
     */
    public function updatedBusinessOrders()
    {
        return $this->hasMany(\App\Models\Business\BusinessOrder::class, 'updated_by_user_id');
    }

    /**
     * Relations avec les lignes de commande Business où l'utilisateur a confirmé la réception
     */
    public function receivedBusinessOrderItems()
    {
        return $this->hasMany(\App\Models\Business\BusinessOrderItem::class, 'received_by_user_id');
    }

    /**
     * Relations avec les colis Express créés
     */
    public function createdExpressParcels()
    {
        return $this->hasMany(\App\Models\Express\ExpressParcel::class, 'created_by_user_id');
    }

    /**
     * Relations avec les colis Express modifiés
     */
    public function updatedExpressParcels()
    {
        return $this->hasMany(\App\Models\Express\ExpressParcel::class, 'updated_by_user_id');
    }

    /**
     * Relations avec les colis Express remis par l'utilisateur
     */
    public function pickedUpExpressParcels()
    {
        return $this->hasMany(\App\Models\Express\ExpressParcel::class, 'picked_up_by_user_id');
    }

    /**
     * Relations avec l'historique des statuts de colis Express modifiés
     */
    public function expressParcelStatusHistory()
    {
        return $this->hasMany(\App\Models\Express\ExpressParcelStatusHistory::class, 'changed_by_user_id');
    }

    /**
     * Relations avec les paramètres système modifiés
     */
    public function updatedSystemSettings()
    {
        return $this->hasMany(\App\Models\SystemSetting::class, 'updated_by_user_id');
    }
}
