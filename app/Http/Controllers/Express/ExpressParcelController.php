<?php

namespace App\Http\Controllers\Express;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Express\ExpressParcel;
use App\Models\Express\ExpressParcelStatusHistory;
use App\Models\Express\ExpressTrip;
use App\Services\FinancialTransactionService;
use App\Services\CurrencyConverterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExpressParcelController extends Controller
{
    protected $transactionService;
    protected $currencyConverter;

    public function __construct(FinancialTransactionService $transactionService, CurrencyConverterService $currencyConverter)
    {
        $this->transactionService = $transactionService;
        $this->currencyConverter = $currencyConverter;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ExpressParcel::query();

            // Filtre par trajet Express
            if ($request->has('express_trip_id')) {
                $query->where('express_trip_id', $request->express_trip_id);
            }

            // Filtre par client expéditeur
            if ($request->has('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            // Filtre par statut
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Recherche par référence
            if ($request->has('search')) {
                $query->where('reference', 'like', "%{$request->search}%");
            }

            $parcels = $query->with(['client', 'receiverClient', 'trip.wave', 'createdBy'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $parcels,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des colis',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'client_id' => 'required|exists:clients,id',
            'receiver_client_id' => 'nullable|exists:clients,id',
            'express_trip_id' => 'required|exists:express_trips,id',
            'reference' => 'nullable|string|max:255|unique:express_parcels,reference',
            'description' => 'nullable|string',
            'weight_kg' => 'required|numeric|min:0',
            'price_mad' => 'required|numeric|min:0',
            'price_cfa' => 'required|numeric|min:0',
            'total_paid' => 'sometimes|numeric|min:0',
            'payments' => 'sometimes|array', // Paiements fractionnés au dépôt
            'payments.*.account_id' => 'required_with:payments|exists:accounts,id',
            'payments.*.amount' => 'required_with:payments|numeric|min:0',
            'status' => 'sometimes|string|in:registered,ready_for_departure,loaded,in_transit,arrived,ready_for_pickup,delivered,cancelled',
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
            // Vérifier que le trajet existe
            ExpressTrip::findOrFail($request->express_trip_id);

            // Générer une référence si non fournie (avec verrou pour éviter les conflits)
            if (empty($request->reference)) {
                $datePrefix = date('Ymd');
                $baseRef = 'EXP-PARCEL-' . $datePrefix . '-';
                $startPos = strlen($baseRef) + 1;
                
                // Détecter le driver de base de données pour la syntaxe SQL appropriée
                $driver = DB::connection()->getDriverName();
                
                // Obtenir le dernier numéro du jour avec verrou
                if ($driver === 'pgsql') {
                    // Syntaxe PostgreSQL
                    $lastParcel = ExpressParcel::where('reference', 'like', $baseRef . '%')
                        ->lockForUpdate()
                        ->orderByRaw("CAST(SUBSTRING(\"reference\" FROM {$startPos}) AS INTEGER) DESC")
                        ->first();
                } else {
                    // Syntaxe MySQL
                    $lastParcel = ExpressParcel::where('reference', 'like', $baseRef . '%')
                        ->lockForUpdate()
                        ->orderByRaw("CAST(SUBSTRING(reference, {$startPos}) AS UNSIGNED) DESC")
                        ->first();
                }
                
                $lastNumber = 0;
                if ($lastParcel && preg_match('/-(\d+)$/', $lastParcel->reference, $matches)) {
                    $lastNumber = (int) $matches[1];
                }
                
                $reference = $baseRef . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                
                // Vérifier l'unicité avec verrou
                $attempts = 0;
                while (ExpressParcel::where('reference', $reference)->lockForUpdate()->exists() && $attempts < 100) {
                    $lastNumber++;
                    $reference = $baseRef . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                    $attempts++;
                }
            } else {
                $reference = $request->reference;
            }

            // Déterminer la devise principale et le montant principal
            // Règle : Si price_mad > 0, MAD est la devise principale, sinon CFA
            $priceMad = (float) ($request->price_mad ?? 0);
            $priceCfa = (float) ($request->price_cfa ?? 0);
            
            // Déterminer la devise principale
            $primaryCurrency = ($priceMad > 0) ? 'MAD' : 'CFA';
            $primaryAmount = ($primaryCurrency === 'MAD') ? $priceMad : $priceCfa;
            
            // Calculer l'équivalent dans l'autre devise avec le taux actuel pour stockage
            // (ceci est utilisé uniquement pour l'affichage, la devise principale est la source de vérité)
            $exchangeRate = $this->currencyConverter->getExchangeRate();
            if ($primaryCurrency === 'MAD') {
                // Si MAD est principal, calculer l'équivalent CFA
                $calculatedCfa = $this->currencyConverter->convertMadToCfa($primaryAmount);
                // Utiliser le prix CFA fourni s'il est proche du calculé (tolérance de 1%), sinon utiliser le calculé
                if ($priceCfa > 0 && abs($priceCfa - $calculatedCfa) / max($calculatedCfa, 1) < 0.01) {
                    $finalPriceCfa = $priceCfa;
                } else {
                    $finalPriceCfa = $calculatedCfa;
                }
                $finalPriceMad = $primaryAmount;
            } else {
                // Si CFA est principal, calculer l'équivalent MAD
                $calculatedMad = $this->currencyConverter->convertCfaToMad($primaryAmount);
                // Utiliser le prix MAD fourni s'il est proche du calculé, sinon utiliser le calculé
                if ($priceMad > 0 && abs($priceMad - $calculatedMad) / max($calculatedMad, 1) < 0.01) {
                    $finalPriceMad = $priceMad;
                } else {
                    $finalPriceMad = $calculatedMad;
                }
                $finalPriceCfa = $primaryAmount;
            }
            
            // Récupérer les paiements fractionnés si fournis
            $payments = $request->get('payments', []);
            $initialTotalPaid = 0;
            if (!empty($payments)) {
                $initialTotalPaid = array_sum(array_column($payments, 'amount'));
            } else {
                // Rétrocompatibilité : utiliser total_paid si pas de paiements fractionnés
                $initialTotalPaid = $request->get('total_paid', 0);
            }
            
            // Comparer le total payé avec le montant principal dans la même devise
            $hasDebt = $initialTotalPaid < $primaryAmount;

            // Créer le colis avec les prix calculés de manière cohérente
            $parcel = ExpressParcel::create([
                'client_id' => $request->client_id,
                'receiver_client_id' => $request->receiver_client_id,
                'express_trip_id' => $request->express_trip_id,
                'reference' => $reference,
                'description' => $request->description,
                'weight_kg' => $request->weight_kg,
                'price_mad' => $finalPriceMad,
                'price_cfa' => $finalPriceCfa,
                'total_paid' => $initialTotalPaid,
                'has_debt' => $hasDebt,
                'status' => $request->get('status', 'registered'),
                'created_by_user_id' => auth()->id() ?? 1,
            ]);

            // Créer l'entrée dans l'historique des statuts
            ExpressParcelStatusHistory::create([
                'express_parcel_id' => $parcel->id,
                'old_status' => null,
                'new_status' => $parcel->status,
                'changed_by_user_id' => auth()->id() ?? 1,
                'changed_at' => now(),
            ]);

            // Créer les transactions CREDIT pour les paiements fractionnés au dépôt
            if (!empty($payments)) {
                // Déterminer la devise principale du colis (utiliser l'accesseur du modèle)
                $parcelCurrency = $parcel->currency; // Utilise l'accesseur qui détermine la devise principale
                
                foreach ($payments as $payment) {
                    if (isset($payment['account_id']) && isset($payment['amount']) && $payment['amount'] > 0) {
                        try {
                            $paymentAccount = \App\Models\Account::findOrFail($payment['account_id']);
                            
                            // Convertir le montant vers la devise du compte si nécessaire
                            $paymentAmount = (float) $payment['amount'];
                            if ($parcelCurrency !== $paymentAccount->currency) {
                                if ($parcelCurrency === 'MAD' && $paymentAccount->currency === 'CFA') {
                                    // Convertir MAD vers CFA
                                    $paymentAmount = $this->currencyConverter->convertMadToCfa($paymentAmount);
                                } elseif ($parcelCurrency === 'CFA' && $paymentAccount->currency === 'MAD') {
                                    // Convertir CFA vers MAD
                                    $paymentAmount = $this->currencyConverter->convertCfaToMad($paymentAmount);
                                }
                            }
                            
                            $this->transactionService->createTransaction(
                                $payment['account_id'],
                                'credit',
                                $paymentAmount,
                                $paymentAccount->currency,
                                'parcel_deposit',
                                'ExpressParcel',
                                $parcel->id,
                                "Avance reçue pour dépôt colis {$parcel->reference}"
                            );
                        } catch (\Exception $e) {
                            \Log::error("Erreur création transaction CREDIT pour dépôt colis {$parcel->id}: " . $e->getMessage());
                        }
                    }
                }
            } elseif ($initialTotalPaid > 0) {
                // Rétrocompatibilité : si total_paid fourni mais pas de paiements fractionnés
                // Ne pas créer de transaction automatique (le système ne peut pas deviner le compte)
                \Log::warning("Colis {$parcel->id} créé avec total_paid mais sans paiements fractionnés. Aucune transaction créée.");
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Colis créé avec succès',
                'data' => $parcel->load(['client', 'receiverClient', 'trip', 'createdBy']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du colis',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $parcel = ExpressParcel::with([
                'client',
                'receiverClient',
                'trip.wave',
                'createdBy',
                'updatedBy',
                'pickedUpBy',
                'statusHistory.changedBy',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $parcel,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Colis non trouvé',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'receiver_client_id' => 'nullable|exists:clients,id',
            'express_trip_id' => 'sometimes|exists:express_trips,id',
            'description' => 'nullable|string',
            'weight_kg' => 'sometimes|numeric|min:0',
            'price_mad' => 'sometimes|numeric|min:0',
            'price_cfa' => 'sometimes|numeric|min:0',
            'total_paid' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|string|in:registered,ready_for_departure,loaded,in_transit,arrived,ready_for_pickup,delivered,cancelled',
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
            $parcel = ExpressParcel::findOrFail($id);
            $oldStatus = $parcel->status;

            $updateData = $request->only([
                'receiver_client_id',
                'express_trip_id',
                'description',
                'weight_kg',
                'price_mad',
                'price_cfa',
                'total_paid',
            ]);

            // Mettre à jour has_debt si total_paid ou prix sont modifiés
            if ($request->has('total_paid') || $request->has('price_mad') || $request->has('price_cfa')) {
                $priceMad = $request->has('price_mad') ? (float) $request->price_mad : (float) $parcel->price_mad;
                $priceCfa = $request->has('price_cfa') ? (float) $request->price_cfa : (float) $parcel->price_cfa;
                
                // Déterminer la devise principale (MAD si price_mad > 0, sinon CFA)
                $primaryCurrency = ($priceMad > 0) ? 'MAD' : 'CFA';
                $primaryAmount = ($primaryCurrency === 'MAD') ? $priceMad : $priceCfa;
                
                // Recalculer l'équivalent dans l'autre devise si nécessaire
                if ($request->has('price_mad') || $request->has('price_cfa')) {
                    if ($primaryCurrency === 'MAD') {
                        $calculatedCfa = $this->currencyConverter->convertMadToCfa($primaryAmount);
                        if (!isset($updateData['price_cfa']) || abs($priceCfa - $calculatedCfa) / max($calculatedCfa, 1) >= 0.01) {
                            $updateData['price_cfa'] = $calculatedCfa;
                        }
                        $updateData['price_mad'] = $primaryAmount;
                    } else {
                        $calculatedMad = $this->currencyConverter->convertCfaToMad($primaryAmount);
                        if (!isset($updateData['price_mad']) || abs($priceMad - $calculatedMad) / max($calculatedMad, 1) >= 0.01) {
                            $updateData['price_mad'] = $calculatedMad;
                        }
                        $updateData['price_cfa'] = $primaryAmount;
                    }
                }
                
                $totalPaid = $request->has('total_paid') ? (float) $request->total_paid : (float) $parcel->total_paid;
                $updateData['has_debt'] = $totalPaid < $primaryAmount;
            }

            // Si le statut change, enregistrer dans l'historique
            if ($request->has('status') && $request->status !== $oldStatus) {
                $updateData['status'] = $request->status;

                ExpressParcelStatusHistory::create([
                    'express_parcel_id' => $parcel->id,
                    'old_status' => $oldStatus,
                    'new_status' => $request->status,
                    'changed_by_user_id' => auth()->id() ?? 1,
                    'changed_at' => now(),
                ]);
            }

            $parcel->update($updateData);
            $parcel->updated_by_user_id = auth()->id() ?? 1;
            $parcel->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Colis mis à jour avec succès',
                'data' => $parcel->fresh()->load(['client', 'receiverClient', 'trip', 'createdBy', 'updatedBy']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du colis',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enregistrer la remise du colis (pickup)
     */
    public function pickup(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pickup_receiver_name' => 'required|string|max:255',
            'pickup_receiver_phone' => 'nullable|string|max:255',
            'pickup_receiver_id_number' => 'nullable|string|max:255',
            'pickup_receiver_note' => 'nullable|string',
            'amount_paid' => 'nullable|numeric|min:0', // Rétrocompatibilité
            'payments' => 'sometimes|array', // Paiements fractionnés lors du pickup
            'payments.*.account_id' => 'required_with:payments|exists:accounts,id',
            'payments.*.amount' => 'required_with:payments|numeric|min:0',
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
            $parcel = ExpressParcel::findOrFail($id);

            if ($parcel->status !== 'ready_for_pickup') {
                return response()->json([
                    'success' => false,
                    'message' => 'Le colis doit être en statut "ready_for_pickup" pour être remis',
                ], 422);
            }

            // Calculer le montant total du colis selon la devise d'enregistrement
            $totalAmount = 0;
            if ($parcel->price_mad > 0) {
                $totalAmount = $parcel->price_mad;
            } else {
                // Convertir CFA en MAD pour la comparaison
                $totalAmount = $parcel->price_cfa / 63.0; // 63 = taux de change par défaut
            }
            
            $currentTotalPaid = $parcel->total_paid ?? 0;
            
            // Gérer les paiements fractionnés ou le montant unique (rétrocompatibilité)
            $payments = $request->get('payments', []);
            $amountPaid = 0;
            if (!empty($payments)) {
                $amountPaid = array_sum(array_column($payments, 'amount'));
            } else {
                // Rétrocompatibilité : utiliser amount_paid si fourni
                $amountPaid = $request->get('amount_paid', 0);
            }
            
            $newTotalPaid = $currentTotalPaid + $amountPaid;
            $remainingDebt = $totalAmount - $newTotalPaid;

            // Vérifier que si il y a une dette, le montant payé doit couvrir complètement la dette
            if ($parcel->has_debt) {
                $remainingDebtBefore = $totalAmount - $currentTotalPaid;
                $tolerance = $remainingDebtBefore > 1000 ? 1 : 0.01;
                
                if ($remainingDebtBefore > $tolerance) {
                    if ($amountPaid <= 0) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Le montant payé est requis pour régler la dette',
                            'errors' => ['amount_paid' => ['Le montant payé est requis pour régler la dette']],
                        ], 422);
                    }
                    
                    if ($remainingDebt > $tolerance) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Le montant payé doit couvrir complètement la dette restante',
                            'errors' => ['amount_paid' => ['Le montant payé doit couvrir complètement la dette restante']],
                        ], 422);
                    }
                }
            }

            $oldStatus = $parcel->status;

            // Mettre à jour le colis
            $updateData = [
                'status' => 'delivered',
                'pickup_receiver_name' => $request->pickup_receiver_name,
                'pickup_receiver_phone' => $request->pickup_receiver_phone,
                'pickup_receiver_id_number' => $request->pickup_receiver_id_number,
                'pickup_receiver_note' => $request->pickup_receiver_note,
                'picked_up_at' => now(),
                'picked_up_by_user_id' => auth()->id() ?? 1,
            ];

            // Mettre à jour le paiement si un montant est payé
            if ($amountPaid > 0) {
                $updateData['total_paid'] = min($newTotalPaid, $totalAmount);
                $updateData['has_debt'] = $updateData['total_paid'] < $totalAmount;
            }

            $parcel->update($updateData);

            // Créer les transactions CREDIT pour les paiements fractionnés lors du pickup
            if ($amountPaid > 0) {
                // Déterminer la devise principale du colis (utiliser l'accesseur du modèle)
                $parcelCurrency = $parcel->currency; // Utilise l'accesseur qui détermine la devise principale
                
                if (!empty($payments)) {
                    // Paiements fractionnés
                    foreach ($payments as $payment) {
                        if (isset($payment['account_id']) && isset($payment['amount']) && $payment['amount'] > 0) {
                            try {
                                $paymentAccount = \App\Models\Account::findOrFail($payment['account_id']);
                                
                                // Convertir le montant vers la devise du compte si nécessaire
                                $paymentAmount = (float) $payment['amount'];
                                if ($parcelCurrency !== $paymentAccount->currency) {
                                    if ($parcelCurrency === 'MAD' && $paymentAccount->currency === 'CFA') {
                                        // Convertir MAD vers CFA
                                        $paymentAmount = $this->currencyConverter->convertMadToCfa($paymentAmount);
                                    } elseif ($parcelCurrency === 'CFA' && $paymentAccount->currency === 'MAD') {
                                        // Convertir CFA vers MAD
                                        $paymentAmount = $this->currencyConverter->convertCfaToMad($paymentAmount);
                                    }
                                }
                                
                                $this->transactionService->createTransaction(
                                    $payment['account_id'],
                                    'credit',
                                    $paymentAmount,
                                    $paymentAccount->currency,
                                    'parcel_pickup_payment',
                                    'ExpressParcel',
                                    $parcel->id,
                                    "Paiement dette pour récupération colis {$parcel->reference}"
                                );
                            } catch (\Exception $e) {
                                \Log::error("Erreur création transaction CREDIT pour pickup colis {$parcel->id}: " . $e->getMessage());
                            }
                        }
                    }
                } elseif ($request->has('amount_paid')) {
                    // Rétrocompatibilité : si amount_paid fourni mais pas de paiements fractionnés
                    // Ne pas créer de transaction automatique (le système ne peut pas deviner le compte)
                    \Log::warning("Pickup colis {$parcel->id} avec amount_paid mais sans paiements fractionnés. Aucune transaction créée.");
                }
            }

            // Enregistrer dans l'historique
            ExpressParcelStatusHistory::create([
                'express_parcel_id' => $parcel->id,
                'old_status' => $oldStatus,
                'new_status' => 'delivered',
                'changed_by_user_id' => auth()->id() ?? 1,
                'changed_at' => now(),
                'comment' => 'Colis remis à ' . $request->pickup_receiver_name,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Colis remis avec succès',
                'data' => $parcel->fresh()->load(['client', 'receiverClient', 'trip', 'pickedUpBy']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la remise du colis',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $parcel = ExpressParcel::findOrFail($id);

            // Vérifier si le colis peut être supprimé (seulement si status = registered ou cancelled)
            if (!in_array($parcel->status, ['registered', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer un colis qui n\'est pas enregistré ou annulé',
                ], 422);
            }

            // Supprimer toutes les transactions financières associées à ce colis
            \App\Models\FinancialTransaction::where('related_type', 'ExpressParcel')
                ->where('related_id', $parcel->id)
                ->whereIn('transaction_category', ['parcel_deposit', 'parcel_pickup_payment'])
                ->delete();

            $parcel->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Colis supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du colis',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
