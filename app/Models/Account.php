<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'account_number',
        'bank_name',
        'type',
        'currency',
        'initial_balance',
        'current_balance',
        'notes',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relations avec les transactions financières
     */
    public function transactions()
    {
        return $this->hasMany(FinancialTransaction::class);
    }

    /**
     * Transactions de débit
     */
    public function debits()
    {
        return $this->hasMany(FinancialTransaction::class)
            ->whereIn('transaction_type', ['debit', 'transfer_out']);
    }

    /**
     * Transactions de crédit
     */
    public function credits()
    {
        return $this->hasMany(FinancialTransaction::class)
            ->whereIn('transaction_type', ['credit', 'transfer_in']);
    }
}

