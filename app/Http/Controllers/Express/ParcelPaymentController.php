<?php

namespace App\Http\Controllers\Express;

use App\Http\Controllers\Controller;
use App\Models\Express\ExpressParcel;
use App\Models\FinancialTransaction;
use App\Services\FinancialTransactionService;
use App\Services\CurrencyConverterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ParcelPaymentController extends Controller
{
    protected $transactionService;
    protected $currencyConverter;

    public function __construct(FinancialTransactionService $transactionService, CurrencyConverterService $currencyConverter)
    {
        $this->transactionService = $transactionService;
        $this->currencyConverter = $currencyConverter;
    }

    /**
     * Récupérer l'historique des paiements d'un colis
     */
    public function index(string $parcelId): JsonResponse
    {
        try {
            $parcel = ExpressParcel::findOrFail($parcelId);

            // Récupérer toutes les transactions de paiement liées à ce colis
            $payments = FinancialTransaction::where('related_type', 'ExpressParcel')
                ->where('related_id', $parcel->id)
                ->whereIn('transaction_category', ['parcel_deposit', 'parcel_pickup_payment'])
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
     * Ajouter un paiement à un colis
     */
    public function store(Request $request, string $parcelId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|in:MAD,CFA',
            'notes' => 'nullable|string|max:500',
        ], [
            'account_id.required' => 'Le compte est obligatoire. Veuillez sélectionner un compte.',
            'account_id.exists' => 'Le compte sélectionné n\'existe pas. Veuillez sélectionner un compte valide.',
            'amount.required' => 'Le montant est obligatoire. Veuillez saisir un montant.',
            'amount.numeric' => 'Le montant doit être un nombre valide.',
            'amount.min' => 'Le montant doit être supérieur à 0.',
            'currency.required' => 'La devise est obligatoire. Veuillez sélectionner une devise (MAD ou CFA).',
            'currency.in' => 'La devise doit être MAD ou CFA.',
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
            $parcel = ExpressParcel::lockForUpdate()->findOrFail($parcelId);
            $paymentAccount = \App\Models\Account::findOrFail($request->account_id);

            // Convertir le montant vers la devise du compte si nécessaire
            $paymentAmount = (float) $request->amount;
            $paymentCurrency = $request->currency;
            
            if ($paymentCurrency !== $paymentAccount->currency) {
                if ($paymentCurrency === 'MAD' && $paymentAccount->currency === 'CFA') {
                    // Convertir MAD vers CFA
                    $paymentAmount = $this->currencyConverter->convertMadToCfa($paymentAmount);
                } elseif ($paymentCurrency === 'CFA' && $paymentAccount->currency === 'MAD') {
                    // Convertir CFA vers MAD
                    $paymentAmount = $this->currencyConverter->convertCfaToMad($paymentAmount);
                }
            }

            // Créer la transaction financière
            $description = "Paiement pour colis {$parcel->reference}";
            if ($request->notes) {
                $description .= " - " . $request->notes;
            }

            $transaction = $this->transactionService->createTransaction(
                $request->account_id,
                'credit',
                $paymentAmount,
                $paymentAccount->currency,
                'parcel_deposit',
                'ExpressParcel',
                $parcel->id,
                $description
            );

            // Mettre à jour le total_paid du colis
            // Le colis a price_mad et price_cfa, donc on doit mettre à jour le bon champ
            $newTotalPaid = $parcel->total_paid;
            if ($paymentCurrency === 'MAD') {
                // Ajouter le montant au total_paid en MAD (price_mad est utilisé pour total_paid)
                $newTotalPaid = $parcel->total_paid + (float) $request->amount;
            } else {
                // Pour CFA, on doit convertir en MAD car total_paid est basé sur price_mad
                $amountInMad = $this->currencyConverter->convertCfaToMad((float) $request->amount);
                $newTotalPaid = $parcel->total_paid + $amountInMad;
            }

            $hasDebt = false;
            // Comparer avec le prix MAD car total_paid est en MAD
            if ($parcel->price_mad > 0) {
                $hasDebt = $newTotalPaid < $parcel->price_mad;
            }

            $parcel->update([
                'total_paid' => $newTotalPaid,
                'has_debt' => $hasDebt,
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

