<?php

namespace App\Models\Business;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_order_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
        'currency',
        'purchase_price',
        'purchase_total',
        'margin_amount',
        'margin_rate',
        'is_received',
        'received_at',
        'received_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'purchase_total' => 'decimal:2',
            'margin_amount' => 'decimal:2',
            'margin_rate' => 'decimal:2',
            'is_received' => 'boolean',
            'received_at' => 'datetime',
        ];
    }

    /**
     * Relations avec la commande Business
     */
    public function order()
    {
        return $this->belongsTo(BusinessOrder::class, 'business_order_id');
    }

    /**
     * Relations avec le produit
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relations avec l'utilisateur qui a confirmé la réception
     */
    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}

