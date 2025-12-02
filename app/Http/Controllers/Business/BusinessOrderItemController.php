<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\BusinessOrder;
use App\Models\Business\BusinessOrderItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BusinessOrderItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = BusinessOrderItem::query();

            // Filtre par commande Business
            if ($request->has('business_order_id')) {
                $query->where('business_order_id', $request->business_order_id);
            }

            // Filtre par produit
            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            // Filtre par statut de réception
            if ($request->has('is_received')) {
                $query->where('is_received', $request->boolean('is_received'));
            }

            $items = $query->with(['order.client', 'product', 'receivedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $items,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des lignes de commande',
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
            'business_order_id' => 'required|exists:business_orders,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'purchase_price' => 'required|numeric|min:0',
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
            $order = BusinessOrder::findOrFail($request->business_order_id);
            $product = Product::findOrFail($request->product_id);

            $quantity = $request->quantity;
            $unitPrice = $request->unit_price;
            $purchasePrice = $request->purchase_price ?? $product->purchase_price;
            $totalPrice = $quantity * $unitPrice;
            $purchaseTotal = $quantity * $purchasePrice;
            $marginAmount = $totalPrice - $purchaseTotal;
            $marginRate = $totalPrice > 0 ? ($marginAmount / $totalPrice) * 100 : 0;

            $item = BusinessOrderItem::create([
                'business_order_id' => $request->business_order_id,
                'product_id' => $request->product_id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'currency' => $order->currency,
                'purchase_price' => $purchasePrice,
                'purchase_total' => $purchaseTotal,
                'margin_amount' => $marginAmount,
                'margin_rate' => $marginRate,
            ]);

            // Recalculer les totaux de la commande
            $this->recalculateOrderTotals($order);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ligne de commande créée avec succès',
                'data' => $item->load(['order', 'product']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la ligne de commande',
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
            $item = BusinessOrderItem::with(['order.client', 'product', 'receivedBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $item,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ligne de commande non trouvée',
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
            'quantity' => 'sometimes|required|integer|min:1',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'purchase_price' => 'sometimes|required|numeric|min:0',
            'is_received' => 'boolean',
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
            $item = BusinessOrderItem::findOrFail($id);
            $order = $item->order;

            $updateData = [];

            if ($request->has('quantity') || $request->has('unit_price') || $request->has('purchase_price')) {
                $quantity = $request->get('quantity', $item->quantity);
                $unitPrice = $request->get('unit_price', $item->unit_price);
                $purchasePrice = $request->get('purchase_price', $item->purchase_price);

                $totalPrice = $quantity * $unitPrice;
                $purchaseTotal = $quantity * $purchasePrice;
                $marginAmount = $totalPrice - $purchaseTotal;
                $marginRate = $totalPrice > 0 ? ($marginAmount / $totalPrice) * 100 : 0;

                $updateData = [
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'purchase_price' => $purchasePrice,
                    'purchase_total' => $purchaseTotal,
                    'margin_amount' => $marginAmount,
                    'margin_rate' => $marginRate,
                ];
            }

            if ($request->has('is_received')) {
                $updateData['is_received'] = $request->boolean('is_received');
                if ($request->boolean('is_received')) {
                    $updateData['received_at'] = now();
                    $updateData['received_by_user_id'] = auth()->id() ?? 1;
                } else {
                    $updateData['received_at'] = null;
                    $updateData['received_by_user_id'] = null;
                }
            }

            $item->update($updateData);

            // Recalculer les totaux de la commande
            $this->recalculateOrderTotals($order);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ligne de commande mise à jour avec succès',
                'data' => $item->fresh()->load(['order', 'product', 'receivedBy']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la ligne de commande',
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
            $item = BusinessOrderItem::findOrFail($id);
            $order = $item->order;

            $item->delete();

            // Recalculer les totaux de la commande
            $this->recalculateOrderTotals($order);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ligne de commande supprimée avec succès',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la ligne de commande',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Recalculer les totaux d'une commande
     * Utilise un verrou pour éviter les conflits de concurrence
     */
    private function recalculateOrderTotals(BusinessOrder $order): void
    {
        // Recharger la commande avec verrou pour éviter les modifications simultanées
        $order = BusinessOrder::lockForUpdate()->findOrFail($order->id);
        $items = $order->items()->lockForUpdate()->get();

        $totalAmount = $items->sum('total_price');
        $totalPurchaseCost = $items->sum('purchase_total');
        $totalMarginAmount = $totalAmount - $totalPurchaseCost;
        $marginRate = $totalAmount > 0 ? ($totalMarginAmount / $totalAmount) * 100 : 0;
        $isFullyReceived = $items->count() > 0 && $items->every(fn($item) => $item->is_received);

        $order->update([
            'total_amount' => $totalAmount,
            'total_purchase_cost' => $totalPurchaseCost,
            'total_margin_amount' => $totalMarginAmount,
            'margin_rate' => $marginRate,
            'is_fully_received' => $isFullyReceived,
        ]);
    }
}
