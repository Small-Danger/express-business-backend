<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\BusinessOrder;
use App\Models\Business\BusinessOrderItem;
use App\Models\Business\BusinessConvoy;
use App\Models\Client;
use App\Models\Product;
use App\Services\FinancialTransactionService;
use App\Services\CurrencyConverterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BusinessOrderController extends Controller
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
            $query = BusinessOrder::query();

            // Filtre par vague Business
            if ($request->has('business_wave_id')) {
                $query->where('business_wave_id', $request->business_wave_id);
            }

            // Filtre par convoi Business
            if ($request->has('business_convoy_id')) {
                $query->where('business_convoy_id', $request->business_convoy_id);
            }

            // Filtre par client
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

            $orders = $query->with(['client', 'wave', 'convoy', 'items.product', 'items.receivedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $orders,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des commandes',
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
            'business_wave_id' => 'required|exists:business_waves,id',
            'business_convoy_id' => 'nullable|exists:business_convoys,id',
            'reference' => 'nullable|string|max:255|unique:business_orders,reference',
            'status' => 'sometimes|string|in:pending,confirmed,in_transit,arrived,ready_for_pickup,delivered,cancelled',
            'currency' => 'required|string|max:10',
            'total_paid' => 'sometimes|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|required_without:items.*.product_name|exists:products,id',
            'items.*.product_name' => 'nullable|required_without:items.*.product_id|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.purchase_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Validation supplémentaire : vérifier que le convoi appartient à la vague
        // Ne vérifier que si business_convoy_id est fourni et non null
        if ($request->filled('business_convoy_id') && $request->business_convoy_id !== null && $request->business_convoy_id !== '') {
            $convoy = BusinessConvoy::find($request->business_convoy_id);
            if (!$convoy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => ['business_convoy_id' => ['Le convoi spécifié n\'existe pas.']],
                ], 422);
            }
            
            if ($convoy->business_wave_id != $request->business_wave_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => ['business_convoy_id' => ['Le convoi spécifié n\'appartient pas à la vague indiquée.']],
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            // Générer une référence si non fournie (avec verrou pour éviter les conflits)
            if (empty($request->reference)) {
                $baseRef = 'CMD-BUS-';
                
                // Obtenir le dernier numéro avec verrou
                // Utiliser une syntaxe compatible avec PostgreSQL et MySQL
                $dbDriver = DB::connection()->getDriverName();
                
                if ($dbDriver === 'pgsql') {
                    // PostgreSQL : utiliser SUBSTRING avec position et CAST en INTEGER
                    $lastOrder = BusinessOrder::where('reference', 'like', $baseRef . '%')
                        ->lockForUpdate()
                        ->orderByRaw("CAST(SUBSTRING(reference FROM " . (strlen($baseRef) + 1) . ") AS INTEGER) DESC")
                        ->first();
                } else {
                    // MySQL : utiliser SUBSTRING avec position et CAST en UNSIGNED
                    $lastOrder = BusinessOrder::where('reference', 'like', $baseRef . '%')
                        ->lockForUpdate()
                        ->orderByRaw("CAST(SUBSTRING(reference, " . (strlen($baseRef) + 1) . ") AS UNSIGNED) DESC")
                        ->first();
                }
                
                $lastNumber = 0;
                if ($lastOrder && preg_match('/CMD-BUS-(\d+)$/', $lastOrder->reference, $matches)) {
                    $lastNumber = (int) $matches[1];
                }
                
                $reference = $baseRef . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
                
                // Vérifier l'unicité avec verrou
                $attempts = 0;
                while (BusinessOrder::where('reference', $reference)->lockForUpdate()->exists() && $attempts < 100) {
                    $lastNumber++;
                    $reference = $baseRef . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                    $attempts++;
                }
            } else {
                $reference = $request->reference;
            }

            // Récupérer les paiements fractionnés si fournis
            $payments = $request->get('payments', []);
            $initialTotalPaid = 0;
            if (!empty($payments)) {
                $initialTotalPaid = array_sum(array_column($payments, 'amount'));
            } else {
                // Si pas de paiements fractionnés, utiliser total_paid (rétrocompatibilité)
                $initialTotalPaid = $request->get('total_paid', 0);
            }
            
            // Créer la commande
            $order = BusinessOrder::create([
                'client_id' => $request->client_id,
                'business_wave_id' => $request->business_wave_id,
                'business_convoy_id' => $request->business_convoy_id,
                'reference' => $reference,
                'status' => $request->get('status', 'pending'),
                'currency' => $request->currency,
                'purchase_account_id' => $request->purchase_account_id,
                'total_amount' => 0,
                'total_paid' => $initialTotalPaid,
                'total_purchase_cost' => 0,
                'total_margin_amount' => 0,
                'has_debt' => false, // Sera recalculé après le calcul des totaux
                'is_fully_received' => false,
                'created_by_user_id' => auth()->id() ?? 1, // TODO: utiliser auth()->id() quand l'auth sera configurée
            ]);

            $totalAmount = 0;
            $totalPurchaseCost = 0;

            // Créer les items de la commande
            foreach ($request->items as $itemData) {
                $product = null;

                // Si product_id fourni, utiliser le produit existant
                if (isset($itemData['product_id'])) {
                    $product = Product::findOrFail($itemData['product_id']);
                } else {
                    // Sinon, créer un nouveau produit
                    $product = Product::create([
                        'name' => $itemData['product_name'],
                        'purchase_price' => $itemData['purchase_price'],
                        'sale_price' => $itemData['unit_price'],
                        'currency' => $request->currency,
                        'is_active' => true,
                    ]);
                }

                $quantity = $itemData['quantity'];
                $unitPrice = $itemData['unit_price'];
                $purchasePrice = $itemData['purchase_price'] ?? $product->purchase_price;
                $totalPrice = $quantity * $unitPrice;
                $purchaseTotal = $quantity * $purchasePrice;
                $marginAmount = $totalPrice - $purchaseTotal;
                $marginRate = $totalPrice > 0 ? ($marginAmount / $totalPrice) * 100 : 0;

                BusinessOrderItem::create([
                    'business_order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'currency' => $request->currency,
                    'purchase_price' => $purchasePrice,
                    'purchase_total' => $purchaseTotal,
                    'margin_amount' => $marginAmount,
                    'margin_rate' => $marginRate,
                ]);

                $totalAmount += $totalPrice;
                $totalPurchaseCost += $purchaseTotal;
            }

            // Calculer les totaux de la commande (recharger avec verrou pour éviter les conflits)
            $order = BusinessOrder::lockForUpdate()->findOrFail($order->id);
            $totalMarginAmount = $totalAmount - $totalPurchaseCost;
            $marginRate = $totalAmount > 0 ? ($totalMarginAmount / $totalAmount) * 100 : 0;
            
            // Calculer has_debt : si total_paid < total_amount, il y a une dette
            // Utiliser le total_paid existant ou celui fourni dans la requête
            $currentTotalPaid = $order->total_paid ?? $initialTotalPaid;
            $hasDebt = $currentTotalPaid < $totalAmount;

            $order->update([
                'total_amount' => $totalAmount,
                'total_purchase_cost' => $totalPurchaseCost,
                'total_margin_amount' => $totalMarginAmount,
                'margin_rate' => $marginRate,
                'total_paid' => $currentTotalPaid,
                'has_debt' => $hasDebt,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande créée avec succès',
                'data' => $order->load(['client', 'wave', 'convoy', 'items.product']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la commande',
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
            $order = BusinessOrder::with(['client', 'wave', 'convoy', 'items.product', 'items.receivedBy', 'createdBy', 'updatedBy', 'pickedUpBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Commande non trouvée',
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
            'business_convoy_id' => 'nullable|exists:business_convoys,id',
            'status' => 'sometimes|string|in:pending,confirmed,in_transit,arrived,ready_for_pickup,delivered,cancelled',
            'total_paid' => 'sometimes|numeric|min:0',
            'payments' => 'sometimes|array', // Paiements fractionnés lors du pickup
            'payments.*.account_id' => 'required_with:payments|exists:accounts,id',
            'payments.*.amount' => 'required_with:payments|numeric|min:0',
            'pickup_receiver_name' => 'nullable|string|max:255',
            'pickup_receiver_phone' => 'nullable|string|max:255',
            'pickup_receiver_id_number' => 'nullable|string|max:255',
            'pickup_receiver_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $order = BusinessOrder::findOrFail($id);

            $updateData = $request->only([
                'business_convoy_id',
                'status',
                'pickup_receiver_name',
                'pickup_receiver_phone',
                'pickup_receiver_id_number',
                'pickup_receiver_note',
            ]);

            // Mettre à jour le paiement si fourni
            $additionalPaid = 0;
            if ($request->has('payments') && !empty($request->payments)) {
                // Paiements fractionnés
                $additionalPaid = array_sum(array_column($request->payments, 'amount'));
                $newTotalPaid = $order->total_paid + $additionalPaid;
                $updateData['total_paid'] = $newTotalPaid;
                $updateData['has_debt'] = $newTotalPaid < $order->total_amount;
            } elseif ($request->has('total_paid')) {
                // Rétrocompatibilité : si total_paid fourni directement
                $updateData['total_paid'] = $request->total_paid;
                $updateData['has_debt'] = $request->total_paid < $order->total_amount;
                $additionalPaid = $request->total_paid - $order->total_paid;
            }

            // Gérer la récupération (pickup)
            $isPickup = false;
            if ($request->has('pickup_receiver_name') && !empty($request->pickup_receiver_name)) {
                $updateData['picked_up_at'] = now();
                $updateData['picked_up_by_user_id'] = auth()->id() ?? 1;
                $isPickup = true;
                // Si on confirme la récupération, passer le statut à delivered
                if ($order->status === 'ready_for_pickup') {
                    $updateData['status'] = 'delivered';
                }
            }

            DB::beginTransaction();
            try {
                $order->update($updateData);
                $order->updated_by_user_id = auth()->id() ?? 1; // TODO: utiliser auth()->id()
                $order->save();

                // Créer les transactions CREDIT pour les paiements fractionnés lors du pickup
                if ($isPickup && $request->has('payments') && !empty($request->payments) && $additionalPaid > 0) {
                    // Déterminer la devise du montant saisi (basé sur la devise de la commande)
                    $orderCurrency = $order->currency ?? 'MAD';
                    
                    foreach ($request->payments as $payment) {
                        if (isset($payment['account_id']) && isset($payment['amount']) && $payment['amount'] > 0) {
                            try {
                                $paymentAccount = \App\Models\Account::findOrFail($payment['account_id']);
                                
                                // Convertir le montant vers la devise du compte si nécessaire
                                $paymentAmount = (float) $payment['amount'];
                                if ($orderCurrency !== $paymentAccount->currency) {
                                    if ($orderCurrency === 'MAD' && $paymentAccount->currency === 'CFA') {
                                        // Convertir MAD vers CFA
                                        $paymentAmount = $this->currencyConverter->convertMadToCfa($paymentAmount);
                                    } elseif ($orderCurrency === 'CFA' && $paymentAccount->currency === 'MAD') {
                                        // Convertir CFA vers MAD
                                        $paymentAmount = $this->currencyConverter->convertCfaToMad($paymentAmount);
                                    }
                                }
                                
                                $this->transactionService->createTransaction(
                                    $payment['account_id'],
                                    'credit',
                                    $paymentAmount,
                                    $paymentAccount->currency,
                                    'order_pickup_payment',
                                    'BusinessOrder',
                                    $order->id,
                                    "Paiement dette pour récupération commande {$order->reference}"
                                );
                            } catch (\Exception $e) {
                                \Log::error("Erreur création transaction CREDIT pour pickup commande {$order->id}: " . $e->getMessage());
                            }
                        }
                    }
                } elseif ($isPickup && $request->has('total_paid') && $additionalPaid > 0) {
                    // Rétrocompatibilité : si total_paid fourni directement (paiement sur un seul compte par défaut)
                    // Utiliser le compte d'achat comme compte de paiement par défaut
                    if ($order->purchase_account_id) {
                        try {
                            $paymentAccount = \App\Models\Account::findOrFail($order->purchase_account_id);
                            
                            // Déterminer la devise du montant saisi (basé sur la devise de la commande)
                            $orderCurrency = $order->currency ?? 'MAD';
                            
                            // Convertir le montant vers la devise du compte si nécessaire
                            $paymentAmount = (float) $additionalPaid;
                            if ($orderCurrency !== $paymentAccount->currency) {
                                if ($orderCurrency === 'MAD' && $paymentAccount->currency === 'CFA') {
                                    // Convertir MAD vers CFA
                                    $paymentAmount = $this->currencyConverter->convertMadToCfa($paymentAmount);
                                } elseif ($orderCurrency === 'CFA' && $paymentAccount->currency === 'MAD') {
                                    // Convertir CFA vers MAD
                                    $paymentAmount = $this->currencyConverter->convertCfaToMad($paymentAmount);
                                }
                            }
                            
                            $this->transactionService->createTransaction(
                                $order->purchase_account_id,
                                'credit',
                                $paymentAmount,
                                $paymentAccount->currency,
                                'order_pickup_payment',
                                'BusinessOrder',
                                $order->id,
                                "Paiement dette pour récupération commande {$order->reference}"
                            );
                        } catch (\Exception $e) {
                            \Log::error("Erreur création transaction CREDIT pour pickup commande {$order->id}: " . $e->getMessage());
                        }
                    }
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return response()->json([
                'success' => true,
                'message' => 'Commande mise à jour avec succès',
                'data' => $order->fresh()->load(['client', 'wave', 'convoy', 'items.product', 'items.receivedBy', 'pickedUpBy']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la commande',
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
            $order = BusinessOrder::findOrFail($id);

            // Vérifier si la commande peut être supprimée (seulement si status = pending ou cancelled)
            if (!in_array($order->status, ['pending', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer une commande qui n\'est pas en attente ou annulée',
                ], 422);
            }

            // Supprimer toutes les transactions financières associées à cette commande
            \App\Models\FinancialTransaction::where('related_type', 'BusinessOrder')
                ->where('related_id', $order->id)
                ->whereIn('transaction_category', ['order_purchase', 'order_payment', 'order_pickup_payment'])
                ->delete();

            $order->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Commande supprimée avec succès',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la commande',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
