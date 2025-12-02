<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\BusinessOrder;
use App\Models\Business\BusinessWave;
use App\Models\Client;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Statistiques générales du module Business
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $startDate = $request->get('start_date', now()->startOfMonth());
            $endDate = $request->get('end_date', now()->endOfMonth());

            // Total des commandes
            $totalOrders = BusinessOrder::whereBetween('created_at', [$startDate, $endDate])->count();

            // Chiffre d'affaires total
            $totalRevenue = BusinessOrder::whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_amount');

            // Coût total d'achat
            $totalPurchaseCost = BusinessOrder::whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_purchase_cost');

            // Marge totale
            $totalMargin = BusinessOrder::whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_margin_amount');

            // Taux de marge moyen
            $averageMarginRate = BusinessOrder::whereBetween('created_at', [$startDate, $endDate])
                ->where('total_amount', '>', 0)
                ->avg('margin_rate');

            // Total payé
            $totalPaid = BusinessOrder::whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_paid');

            // Total impayé
            $totalUnpaid = $totalRevenue - $totalPaid;

            // Top 5 clients par CA
            $topClients = Client::whereHas('businessOrders', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
                ->withSum(['businessOrders as total_revenue' => function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                }], 'total_amount')
                ->orderBy('total_revenue', 'desc')
                ->limit(5)
                ->get();

            // Top 5 produits par quantité vendue
            $topProducts = Product::whereHas('businessOrderItems.order', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
                ->withSum(['businessOrderItems as total_quantity' => function ($query) use ($startDate, $endDate) {
                    $query->whereHas('order', function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate]);
                    });
                }], 'quantity')
                ->orderBy('total_quantity', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                    'summary' => [
                        'total_orders' => $totalOrders,
                        'total_revenue' => (float) $totalRevenue,
                        'total_purchase_cost' => (float) $totalPurchaseCost,
                        'total_margin' => (float) $totalMargin,
                        'average_margin_rate' => round((float) $averageMarginRate, 2),
                        'total_paid' => (float) $totalPaid,
                        'total_unpaid' => (float) $totalUnpaid,
                    ],
                    'top_clients' => $topClients,
                    'top_products' => $topProducts,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Statistiques par vague Business
     */
    public function waveStats(string $waveId): JsonResponse
    {
        try {
            $wave = BusinessWave::with(['orders', 'costs'])->findOrFail($waveId);

            $totalRevenue = $wave->orders->sum('total_amount');
            $totalPurchaseCost = $wave->orders->sum('total_purchase_cost');
            $totalMargin = $wave->orders->sum('total_margin_amount');
            $totalCosts = $wave->costs->sum('amount');
            $netProfit = $totalMargin - $totalCosts;

            return response()->json([
                'success' => true,
                'data' => [
                    'wave' => $wave,
                    'statistics' => [
                        'total_orders' => $wave->orders->count(),
                        'total_revenue' => (float) $totalRevenue,
                        'total_purchase_cost' => (float) $totalPurchaseCost,
                        'total_margin' => (float) $totalMargin,
                        'total_costs' => (float) $totalCosts,
                        'net_profit' => (float) $netProfit,
                        'profit_rate' => $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques de la vague',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Statistiques par client
     */
    public function clientStats(string $clientId, Request $request): JsonResponse
    {
        try {
            $client = Client::findOrFail($clientId);
            $startDate = $request->get('start_date', now()->startOfYear());
            $endDate = $request->get('end_date', now()->endOfYear());

            $orders = $client->businessOrders()
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $totalOrders = $orders->count();
            $totalRevenue = $orders->sum('total_amount');
            $totalPaid = $orders->sum('total_paid');
            $totalUnpaid = $totalRevenue - $totalPaid;

            return response()->json([
                'success' => true,
                'data' => [
                    'client' => $client,
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ],
                    'statistics' => [
                        'total_orders' => $totalOrders,
                        'total_revenue' => (float) $totalRevenue,
                        'total_paid' => (float) $totalPaid,
                        'total_unpaid' => (float) $totalUnpaid,
                        'average_order_value' => $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques du client',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
