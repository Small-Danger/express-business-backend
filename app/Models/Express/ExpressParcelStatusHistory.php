<?php

namespace App\Models\Express;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpressParcelStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'express_parcel_id',
        'old_status',
        'new_status',
        'changed_by_user_id',
        'changed_at',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    /**
     * Relations avec le colis Express
     */
    public function parcel()
    {
        return $this->belongsTo(ExpressParcel::class, 'express_parcel_id');
    }

    /**
     * Relations avec l'utilisateur qui a changé le statut
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}

