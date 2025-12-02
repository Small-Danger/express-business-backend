<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FinancialTransaction;
use App\Services\CurrencyConverterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancialTransactionService
{
    protected $currencyConverter;

    public function __construct(CurrencyConverterService $currencyConverter)
    {
        $this->currencyConverter = $currencyConverter;
    }

    /**
     * Générer une référence unique de transaction
     */
    protected function generateReference(): string
    {
        $datePrefix = date('Ymd');
        $baseRef = 'TXN-' . $datePrefix . '-';
        
        // Détecter le driver de base de données
        $driver = DB::connection()->getDriverName();
        $startPos = strlen($baseRef) + 1;
        
        // Obtenir le dernier numéro du jour avec verrou
        if ($driver === 'pgsql') {
            $lastTransaction = FinancialTransaction::where('reference', 'like', $baseRef . '%')
                ->lockForUpdate()
                ->orderByRaw("CAST(SUBSTRING(\"reference\" FROM {$startPos}) AS INTEGER) DESC")
                ->first();
        } else {
            $lastTransaction = FinancialTransaction::where('reference', 'like', $baseRef . '%')
                ->lockForUpdate()
                ->orderByRaw("CAST(SUBSTRING(reference, {$startPos}) AS UNSIGNED) DESC")
                ->first();
        }
        
        $lastNumber = 0;
        if ($lastTransaction && preg_match('/-(\d+)$/', $lastTransaction->reference, $matches)) {
            $lastNumber = (int) $matches[1];
        }
        
        $reference = $baseRef . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        
        // Vérifier l'unicité avec verrou
        $attempts = 0;
        while (FinancialTransaction::where('reference', $reference)->lockForUpdate()->exists() && $attempts < 100) {
            $lastNumber++;
            $reference = $baseRef . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
            $attempts++;
        }
        
        return $reference;
    }

    /**
     * Créer une transaction financière
     */
    public function createTransaction(
        int $accountId,
        string $transactionType,
        float $amount,
        string $currency,
        string $category,
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?string $description = null,
        ?float $exchangeRate = null,
        ?string $transferReference = null
    ): FinancialTransaction {
        $account = Account::findOrFail($accountId);

        // Vérifier que la devise correspond au compte
        if ($account->currency !== $currency) {
            throw new \Exception("La devise de la transaction ({$currency}) ne correspond pas à la devise du compte ({$account->currency})");
        }

        $reference = $this->generateReference();

        return FinancialTransaction::create([
            'account_id' => $accountId,
            'transaction_type' => $transactionType,
            'amount' => $amount,
            'currency' => $currency,
            'reference' => $reference,
            'transaction_category' => $category,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'description' => $description,
            'exchange_rate_used' => $exchangeRate,
            'transfer_reference' => $transferReference,
            'created_by_user_id' => auth()->id() ?? 1,
        ]);
    }

    /**
     * Créer un transfert inter-comptes avec conversion
     */
    public function createTransfer(
        int $sourceAccountId,
        int $destinationAccountId,
        float $sourceAmount,
        string $sourceCurrency,
        float $destinationAmount,
        string $destinationCurrency,
        float $exchangeRate,
        string $description = null
    ): array {
        $transferReference = $this->generateReference();

        DB::beginTransaction();
        try {
            // Transaction DEBIT sur compte source
            $debitTransaction = $this->createTransaction(
                $sourceAccountId,
                'transfer_out',
                $sourceAmount,
                $sourceCurrency,
                'transfer_conversion',
                'Transfer',
                null,
                $description ?? "Transfert vers {$destinationAccountId}",
                $exchangeRate,
                $transferReference
            );

            // Transaction CREDIT sur compte destination
            $creditTransaction = $this->createTransaction(
                $destinationAccountId,
                'transfer_in',
                $destinationAmount,
                $destinationCurrency,
                'transfer_conversion',
                'Transfer',
                null,
                $description ?? "Transfert depuis {$sourceAccountId}",
                $exchangeRate,
                $transferReference
            );

            DB::commit();

            return [
                'debit_transaction' => $debitTransaction,
                'credit_transaction' => $creditTransaction,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Calculer le solde d'un compte (dynamique)
     */
    public function getAccountBalance(int $accountId): float
    {
        $account = Account::findOrFail($accountId);
        
        $credits = FinancialTransaction::where('account_id', $accountId)
            ->whereIn('transaction_type', ['credit', 'transfer_in'])
            ->sum('amount');
            
        $debits = FinancialTransaction::where('account_id', $accountId)
            ->whereIn('transaction_type', ['debit', 'transfer_out'])
            ->sum('amount');
        
        return $account->initial_balance + $credits - $debits;
    }

    /**
     * Obtenir le solde d'un compte dans une devise cible (avec conversion)
     */
    public function getAccountBalanceInCurrency(int $accountId, string $targetCurrency): float
    {
        $account = Account::findOrFail($accountId);
        $balance = $this->getAccountBalance($accountId);
        
        if ($account->currency === $targetCurrency) {
            return $balance;
        }
        
        // Convertir selon le taux actuel dans les paramètres
        if ($account->currency === 'CFA' && $targetCurrency === 'MAD') {
            return $this->currencyConverter->convertFromCfa($balance, 'MAD');
        } elseif ($account->currency === 'MAD' && $targetCurrency === 'CFA') {
            return $this->currencyConverter->convertToCfa($balance, 'MAD');
        }
        
        return $balance;
    }

    /**
     * Obtenir le total des soldes de tous les comptes dans une devise
     */
    public function getTotalBalance(string $targetCurrency): float
    {
        $accounts = Account::where('is_active', true)->get();
        $total = 0;
        
        foreach ($accounts as $account) {
            $total += $this->getAccountBalanceInCurrency($account->id, $targetCurrency);
        }
        
        return $total;
    }

    /**
     * Obtenir les transactions d'un compte avec filtres
     */
    public function getTransactionsByAccount(int $accountId, array $filters = [])
    {
        $query = FinancialTransaction::where('account_id', $accountId)
            ->with(['account', 'createdBy'])
            ->orderBy('created_at', 'desc');
        
        if (isset($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }
        
        if (isset($filters['transaction_category'])) {
            $query->where('transaction_category', $filters['transaction_category']);
        }
        
        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
        
        return $query->paginate($filters['per_page'] ?? 15);
    }
}

