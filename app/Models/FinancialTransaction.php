<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'transaction_type',
        'amount',
        'currency',
        'reference',
        'transaction_category',
        'related_type',
        'related_id',
        'description',
        'exchange_rate_used',
        'transfer_reference',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'exchange_rate_used' => 'decimal:2',
        ];
    }

    /**
     * Relations avec le compte
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Relations avec l'utilisateur qui a créé la transaction
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Relation polymorphique avec l'entité liée
     */
    public function related()
    {
        return $this->morphTo('related');
    }

    /**
     * Scopes pour filtrer par type de transaction
     */
    public function scopeDebits($query)
    {
        return $query->whereIn('transaction_type', ['debit', 'transfer_out']);
    }

    public function scopeCredits($query)
    {
        return $query->whereIn('transaction_type', ['credit', 'transfer_in']);
    }

    /**
     * Scope pour filtrer par catégorie
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('transaction_category', $category);
    }

    /**
     * Scope pour filtrer par compte
     */
    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }
}

