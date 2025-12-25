<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogService
{
    /**
     * Enregistrer une activité
     */
    public function log(
        string $actionType,
        string $category,
        string $description,
        ?string $relatedType = null,
        ?int $relatedId = null,
        ?array $metadata = null,
        ?int $userId = null,
        ?Request $request = null
    ): ActivityLog {
        $data = [
            'user_id' => $userId ?? auth()->id(),
            'action_type' => $actionType,
            'category' => $category,
            'description' => $description,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'metadata' => $metadata,
        ];

        // Ajouter les informations de la requête si disponibles
        if ($request) {
            $data['ip_address'] = $request->ip();
            $data['user_agent'] = $request->userAgent();
        } else {
            $data['ip_address'] = request()->ip();
            $data['user_agent'] = request()->userAgent();
        }

        return ActivityLog::create($data);
    }

    /**
     * Logger une connexion
     */
    public function logLogin(int $userId, Request $request): ActivityLog
    {
        $user = \App\Models\User::find($userId);
        return $this->log(
            'login',
            'auth',
            "Connexion de l'utilisateur {$user->name} ({$user->email})",
            'User',
            $userId,
            ['email' => $user->email],
            $userId,
            $request
        );
    }

    /**
     * Logger une déconnexion
     */
    public function logLogout(int $userId, Request $request = null): ActivityLog
    {
        $user = \App\Models\User::find($userId);
        return $this->log(
            'logout',
            'auth',
            "Déconnexion de l'utilisateur {$user->name} ({$user->email})",
            'User',
            $userId,
            ['email' => $user->email],
            $userId,
            $request
        );
    }

    /**
     * Logger une création
     */
    public function logCreate(string $category, string $relatedType, int $relatedId, string $description, ?array $metadata = null): ActivityLog
    {
        return $this->log('create', $category, $description, $relatedType, $relatedId, $metadata);
    }

    /**
     * Logger une modification
     */
    public function logUpdate(string $category, string $relatedType, int $relatedId, string $description, ?array $metadata = null): ActivityLog
    {
        return $this->log('update', $category, $description, $relatedType, $relatedId, $metadata);
    }

    /**
     * Logger une suppression
     */
    public function logDelete(string $category, string $relatedType, int $relatedId, string $description, ?array $metadata = null): ActivityLog
    {
        return $this->log('delete', $category, $description, $relatedType, $relatedId, $metadata);
    }

    /**
     * Logger un paiement
     */
    public function logPayment(string $relatedType, int $relatedId, string $description, float $amount, string $currency, ?array $metadata = null): ActivityLog
    {
        $metadata = array_merge([
            'amount' => $amount,
            'currency' => $currency,
        ], $metadata ?? []);
        
        return $this->log('payment', 'payment', $description, $relatedType, $relatedId, $metadata);
    }

    /**
     * Logger une transaction financière
     */
    public function logFinancialTransaction(int $transactionId, string $description, string $type, float $amount, string $currency, ?array $metadata = null): ActivityLog
    {
        $metadata = array_merge([
            'transaction_type' => $type,
            'amount' => $amount,
            'currency' => $currency,
        ], $metadata ?? []);
        
        return $this->log('transaction', 'transaction', $description, 'FinancialTransaction', $transactionId, $metadata);
    }
}

