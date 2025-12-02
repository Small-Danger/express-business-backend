<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'sku',
        'description',
        'purchase_price',
        'sale_price',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relations avec les lignes de commande Business
     */
    public function businessOrderItems()
    {
        return $this->hasMany(Business\BusinessOrderItem::class);
    }
}

