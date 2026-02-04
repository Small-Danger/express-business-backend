<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\FinancialTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    protected $transactionService;

    public function __construct(FinancialTransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Liste des comptes avec leurs soldes
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $accounts = Account::where('is_active', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get()
                ->map(function ($account) {
                    try {
                        $balance = $this->transactionService->getAccountBalance($account->id);
                    } catch (\Exception $e) {
                        // Si le calcul du solde échoue, utiliser le solde initial
                        \Log::warning("Erreur calcul solde pour compte {$account->id}: " . $e->getMessage());
                        $balance = (float) $account->initial_balance;
                    }
                    return [
                        'id' => $account->id,
                        'name' => $account->name,
                        'account_number' => $account->account_number,
                        'bank_name' => $account->bank_name,
                        'type' => $account->type,
                        'currency' => $account->currency,
                        'initial_balance' => (float) $account->initial_balance,
                        'current_balance' => $balance,
                        'notes' => $account->notes,
                        'is_active' => $account->is_active,
                        'has_transactions' => $account->transactions()->exists(),
                        'created_at' => $account->created_at,
                        'updated_at' => $account->updated_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $accounts,
            ]);
        } catch (\Exception $e) {
            \Log::error("Erreur lors de la récupération des comptes: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des comptes',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Détails d'un compte avec son solde et ses transactions récentes
     */
    public function show(string $id, Request $request): JsonResponse
    {
        try {
            $account = Account::findOrFail($id);
            $balance = $this->transactionService->getAccountBalance($account->id);
            
            // Transactions récentes (optionnel, limité à 10)
            $recentTransactions = $account->transactions()
                ->with(['createdBy'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'account' => $account,
                    'current_balance' => $balance,
                    'recent_transactions' => $recentTransactions,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du compte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Créer un nouveau compte
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'type' => 'required|string|in:orange_money,cih_bank',
            'currency' => 'required|string|in:CFA,MAD',
            'initial_balance' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ], [
            'name.required' => 'Le nom du compte est obligatoire.',
            'name.string' => 'Le nom du compte doit être une chaîne de caractères.',
            'name.max' => 'Le nom du compte ne peut pas dépasser 255 caractères.',
            'account_number.string' => 'Le numéro de compte doit être une chaîne de caractères.',
            'account_number.max' => 'Le numéro de compte ne peut pas dépasser 255 caractères.',
            'bank_name.string' => 'Le nom de la banque doit être une chaîne de caractères.',
            'bank_name.max' => 'Le nom de la banque ne peut pas dépasser 255 caractères.',
            'type.required' => 'Le type de compte est obligatoire. Veuillez sélectionner Orange Money ou CIH Bank.',
            'type.string' => 'Le type de compte doit être une chaîne de caractères.',
            'type.in' => 'Le type de compte doit être "Orange Money" ou "CIH Bank".',
            'currency.required' => 'La devise est obligatoire. Veuillez sélectionner CFA ou MAD.',
            'currency.string' => 'La devise doit être une chaîne de caractères.',
            'currency.in' => 'La devise doit être "CFA" ou "MAD".',
            'initial_balance.numeric' => 'Le solde initial doit être un nombre.',
            'initial_balance.min' => 'Le solde initial ne peut pas être négatif.',
            'notes.string' => 'Les notes doivent être une chaîne de caractères.',
            'is_active.boolean' => 'Le statut actif doit être vrai ou faux.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $account = Account::create([
                'name' => $request->name,
                'account_number' => $request->account_number,
                'bank_name' => $request->bank_name,
                'type' => $request->type,
                'currency' => $request->currency,
                'initial_balance' => $request->get('initial_balance', 0),
                'current_balance' => $request->get('initial_balance', 0), // Initialiser le solde courant avec le solde initial
                'notes' => $request->notes,
                'is_active' => $request->get('is_active', true),
                'created_by_user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Compte créé avec succès',
                'data' => $account,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du compte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour un compte
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'type' => 'sometimes|string|in:orange_money,cih_bank',
            'currency' => 'sometimes|string|in:CFA,MAD',
            'initial_balance' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ], [
            'name.string' => 'Le nom du compte doit être une chaîne de caractères.',
            'name.max' => 'Le nom du compte ne peut pas dépasser 255 caractères.',
            'account_number.string' => 'Le numéro de compte doit être une chaîne de caractères.',
            'account_number.max' => 'Le numéro de compte ne peut pas dépasser 255 caractères.',
            'bank_name.string' => 'Le nom de la banque doit être une chaîne de caractères.',
            'bank_name.max' => 'Le nom de la banque ne peut pas dépasser 255 caractères.',
            'type.string' => 'Le type de compte doit être une chaîne de caractères.',
            'type.in' => 'Le type de compte doit être "Orange Money" ou "CIH Bank".',
            'currency.string' => 'La devise doit être une chaîne de caractères.',
            'currency.in' => 'La devise doit être "CFA" ou "MAD".',
            'initial_balance.numeric' => 'Le solde initial doit être un nombre.',
            'initial_balance.min' => 'Le solde initial ne peut pas être négatif.',
            'notes.string' => 'Les notes doivent être une chaîne de caractères.',
            'is_active.boolean' => 'Le statut actif doit être vrai ou faux.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $account = Account::findOrFail($id);
            if ($account->transactions()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce compte ne peut pas être modifié car des transactions existent déjà.',
                ], 422);
            }

            $account->update($request->only([
                'name',
                'account_number',
                'bank_name',
                'type',
                'currency',
                'initial_balance',
                'notes',
                'is_active',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Compte mis à jour avec succès',
                'data' => $account,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du compte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Supprimer un compte
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $account = Account::findOrFail($id);
            if ($account->transactions()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ce compte ne peut pas être supprimé car des transactions existent déjà.',
                ], 422);
            }

            $account->delete();

            return response()->json([
                'success' => true,
                'message' => 'Compte supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du compte',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir le solde d'un compte
     */
    public function getBalance(string $id): JsonResponse
    {
        try {
            $account = Account::findOrFail($id);
            $balance = $this->transactionService->getAccountBalance($account->id);
            
            // Solde en devise alternative (pour affichage)
            $alternativeCurrency = $account->currency === 'CFA' ? 'MAD' : 'CFA';
            $balanceInAlternative = $this->transactionService->getAccountBalanceInCurrency($account->id, $alternativeCurrency);

            return response()->json([
                'success' => true,
                'data' => [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'currency' => $account->currency,
                    'balance' => $balance,
                    'balance_in_' . strtolower($alternativeCurrency) => $balanceInAlternative,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul du solde',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les transactions d'un compte
     */
    public function getTransactions(string $id, Request $request): JsonResponse
    {
        try {
            $account = Account::findOrFail($id);
            
            $filters = [
                'transaction_type' => $request->get('transaction_type'),
                'transaction_category' => $request->get('transaction_category'),
                'start_date' => $request->get('start_date'),
                'end_date' => $request->get('end_date'),
                'per_page' => $request->get('per_page', 15),
            ];
            
            $transactions = $this->transactionService->getTransactionsByAccount($account->id, $filters);

            return response()->json([
                'success' => true,
                'data' => $transactions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des transactions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

