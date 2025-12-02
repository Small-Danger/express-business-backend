<?php

namespace App\Http\Controllers\Express;

use App\Http\Controllers\Controller;
use App\Models\Express\ExpressParcel;
use App\Models\Express\ExpressWave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    /**
     * Obtenir la liste des colis prêts pour récupération
     */
    public function readyForPickup(Request $request): JsonResponse
    {
        try {
            $query = ExpressParcel::where('status', 'ready_for_pickup');

            // Filtre par client
            if ($request->has('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            // Recherche par référence
            if ($request->has('search')) {
                $query->where('reference', 'like', "%{$request->search}%");
            }

            $parcels = $query->with(['client', 'receiverClient', 'trip'])
                ->orderBy('created_at', 'asc')
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
     * Obtenir les colis d'un client prêts pour récupération
     */
    public function clientParcels(string $clientId): JsonResponse
    {
        try {
            $parcels = ExpressParcel::where('client_id', $clientId)
                ->where('status', 'ready_for_pickup')
                ->with(['trip', 'receiverClient'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $parcels,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des colis du client',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Vérifier si un client a des impayés avant de remettre les colis
     */
    public function checkClientPayment(string $clientId): JsonResponse
    {
        try {
            $parcels = ExpressParcel::where('client_id', $clientId)
                ->where('status', 'ready_for_pickup')
                ->get();

            $totalAmount = $parcels->sum('price_mad');
            $totalPaid = 0; // TODO: Implémenter la gestion des paiements si nécessaire

            $hasUnpaid = $totalPaid < $totalAmount;

            return response()->json([
                'success' => true,
                'data' => [
                    'client_id' => $clientId,
                    'total_parcels' => $parcels->count(),
                    'total_amount_mad' => $totalAmount,
                    'total_paid' => $totalPaid,
                    'has_unpaid' => $hasUnpaid,
                    'unpaid_amount' => max(0, $totalAmount - $totalPaid),
                    'can_deliver' => !$hasUnpaid,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification des paiements',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculer le bénéfice d'une vague Express après déduction des frais
     */
    public function calculateWaveProfit(string $waveId): JsonResponse
    {
        try {
            $wave = ExpressWave::with(['trips.parcels', 'costs'])->findOrFail($waveId);

            // Total encaissé (somme des prix des colis)
            $totalRevenue = $wave->trips->flatMap->parcels->sum('price_mad');

            // Total des frais de la vague
            $totalCosts = $wave->costs->sum('amount');

            // Bénéfice net
            $netProfit = $totalRevenue - $totalCosts;

            // Statistiques supplémentaires
            $totalParcels = $wave->trips->flatMap->parcels->count();
            $totalWeight = $wave->trips->flatMap->parcels->sum('weight_kg');

            return response()->json([
                'success' => true,
                'data' => [
                    'wave' => $wave,
                    'statistics' => [
                        'total_parcels' => $totalParcels,
                        'total_weight_kg' => round($totalWeight, 3),
                        'total_revenue_mad' => round($totalRevenue, 2),
                        'total_costs' => round($totalCosts, 2),
                        'net_profit' => round($netProfit, 2),
                        'profit_rate' => $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0,
                        'is_profitable' => $netProfit > 0,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul du bénéfice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
