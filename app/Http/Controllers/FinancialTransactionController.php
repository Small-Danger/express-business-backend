<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\FinancialTransaction;
use App\Services\FinancialTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class FinancialTransactionController extends Controller
{
    protected $transactionService;

    public function __construct(FinancialTransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Liste de toutes les transactions avec filtres
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = FinancialTransaction::with(['account', 'createdBy'])
                ->orderBy('created_at', 'desc');

            // Filtres
            if ($request->has('account_id')) {
                $query->where('account_id', $request->account_id);
            }

            if ($request->has('transaction_type')) {
                $query->where('transaction_type', $request->transaction_type);
            }

            if ($request->has('transaction_category')) {
                $query->where('transaction_category', $request->transaction_category);
            }

            if ($request->has('related_type')) {
                $query->where('related_type', $request->related_type);
                if ($request->has('related_id')) {
                    $query->where('related_id', $request->related_id);
                }
            }

            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->end_date);
            }

            $transactions = $query->paginate($request->get('per_page', 15));

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

    /**
     * Détails d'une transaction
     */
    public function show(string $id): JsonResponse
    {
        try {
            $transaction = FinancialTransaction::with(['account', 'createdBy', 'related'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $transaction,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de la transaction',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Effectuer un transfert inter-comptes avec conversion
     */
    public function transfer(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_account_id' => 'required|exists:accounts,id',
            'destination_account_id' => 'required|exists:accounts,id|different:source_account_id',
            'source_amount' => 'required|numeric|min:0',
            'source_currency' => 'required|string|in:CFA,MAD',
            'destination_amount' => 'required|numeric|min:0',
            'destination_currency' => 'required|string|in:CFA,MAD',
            'exchange_rate' => 'required|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $sourceAccount = Account::findOrFail($request->source_account_id);
            $destinationAccount = Account::findOrFail($request->destination_account_id);

            // Vérifier que les devises correspondent
            if ($sourceAccount->currency !== $request->source_currency) {
                return response()->json([
                    'success' => false,
                    'message' => "La devise source ({$request->source_currency}) ne correspond pas à la devise du compte source ({$sourceAccount->currency})",
                ], 422);
            }

            if ($destinationAccount->currency !== $request->destination_currency) {
                return response()->json([
                    'success' => false,
                    'message' => "La devise destination ({$request->destination_currency}) ne correspond pas à la devise du compte destination ({$destinationAccount->currency})",
                ], 422);
            }

            $result = $this->transactionService->createTransfer(
                $request->source_account_id,
                $request->destination_account_id,
                $request->source_amount,
                $request->source_currency,
                $request->destination_amount,
                $request->destination_currency,
                $request->exchange_rate,
                $request->description
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transfert effectué avec succès',
                'data' => $result,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du transfert',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Résumé de la trésorerie (soldes totaux par devise)
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $totalCFA = $this->transactionService->getTotalBalance('CFA');
            $totalMAD = $this->transactionService->getTotalBalance('MAD');

            // Convertir MAD en CFA pour avoir le total global en CFA
            $currencyConverter = app(\App\Services\CurrencyConverterService::class);
            $exchangeRate = $currencyConverter->getExchangeRate();
            $totalMADInCFA = $currencyConverter->convertMadToCfa($totalMAD);
            $totalGlobalCFA = $totalCFA + $totalMADInCFA;

            // Convertir CFA en MAD pour avoir le total global en MAD
            $totalCFAInMAD = $currencyConverter->convertCfaToMad($totalCFA);
            $totalGlobalMAD = $totalMAD + $totalCFAInMAD;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_cfa' => $totalCFA,
                    'total_mad' => $totalMAD,
                    'total_global_cfa' => $totalGlobalCFA,
                    'total_global_mad' => $totalGlobalMAD,
                    'exchange_rate' => $exchangeRate,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul du résumé',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

