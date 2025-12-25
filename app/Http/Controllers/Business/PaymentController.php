<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\BusinessOrder;
use App\Models\FinancialTransaction;
use App\Services\FinancialTransactionService;
use App\Services\CurrencyConverterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    protected $transactionService;
    protected $currencyConverter;

    public function __construct(FinancialTransactionService $transactionService, CurrencyConverterService $currencyConverter)
    {
        $this->transactionService = $transactionService;
        $this->currencyConverter = $currencyConverter;
    }

    /**
     * Récupérer l'historique des paiements d'une commande
     */
    public function index(string $orderId): JsonResponse
    {
        try {
            $order = BusinessOrder::findOrFail($orderId);

            // Récupérer toutes les transactions de paiement liées à cette commande
            $payments = FinancialTransaction::where('related_type', 'BusinessOrder')
                ->where('related_id', $order->id)
                ->whereIn('transaction_category', ['order_payment', 'order_pickup_payment'])
                ->where('transaction_type', 'credit')
                ->with(['account', 'createdBy'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $payments,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des paiements',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ajouter un paiement à une commande
     */
    public function store(Request $request, string $orderId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ], [
            'account_id.required' => 'Le compte est obligatoire. Veuillez sélectionner un compte.',
            'account_id.exists' => 'Le compte sélectionné n\'existe pas. Veuillez sélectionner un compte valide.',
            'amount.required' => 'Le montant est obligatoire. Veuillez saisir un montant.',
            'amount.numeric' => 'Le montant doit être un nombre valide.',
            'amount.min' => 'Le montant doit être supérieur à 0.',
            'notes.string' => 'Les notes doivent être une chaîne de caractères.',
            'notes.max' => 'Les notes ne peuvent pas dépasser 500 caractères.',
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
            $order = BusinessOrder::lockForUpdate()->findOrFail($orderId);
            $paymentAccount = \App\Models\Account::findOrFail($request->account_id);

            // Convertir le montant vers la devise du compte si nécessaire
            $paymentAmount = (float) $request->amount;
            // Le montant dans la requête est dans la devise de la commande
            $orderCurrency = $order->currency ?? 'CFA';
            $amountInOrderCurrency = (float) $request->amount;
            
            // Convertir le montant vers la devise du compte pour la transaction
            $paymentAmount = $amountInOrderCurrency;
            if ($orderCurrency !== $paymentAccount->currency) {
                if ($orderCurrency === 'MAD' && $paymentAccount->currency === 'CFA') {
                    // Convertir MAD vers CFA
                    $paymentAmount = $this->currencyConverter->convertMadToCfa($paymentAmount);
                } elseif ($orderCurrency === 'CFA' && $paymentAccount->currency === 'MAD') {
                    // Convertir CFA vers MAD
                    $paymentAmount = $this->currencyConverter->convertCfaToMad($paymentAmount);
                }
            }

            // Créer la transaction financière
            $description = "Paiement pour commande {$order->reference}";
            if ($request->notes) {
                $description .= " - " . $request->notes;
            }

            $transaction = $this->transactionService->createTransaction(
                $request->account_id,
                'credit',
                $paymentAmount,
                $paymentAccount->currency,
                'order_payment',
                'BusinessOrder',
                $order->id,
                $description
            );

            // Mettre à jour le total_paid de la commande (dans la devise de la commande)
            $newTotalPaid = $order->total_paid + $amountInOrderCurrency;
            $order->update([
                'total_paid' => $newTotalPaid,
                'has_debt' => $newTotalPaid < $order->total_amount,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'data' => $transaction->load(['account', 'createdBy']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement du paiement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

