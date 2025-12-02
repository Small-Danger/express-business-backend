<?php

namespace App\Http\Controllers;

use App\Models\Business\BusinessOrder;
use App\Models\Express\ExpressParcel;
use App\Models\FinancialTransaction;
use App\Models\Account;
use App\Services\FinancialTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    protected $transactionService;

    public function __construct(FinancialTransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    /**
     * Récupérer toutes les données du dashboard en une seule requête
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $startDate = $request->get('start_date', now()->startOfMonth());
            $endDate = $request->get('end_date', now()->endOfMonth());

            // Charger toutes les données en parallèle
            $data = [
                'business_stats' => $this->getBusinessStats($startDate, $endDate),
                'express_stats' => $this->getExpressStats($startDate, $endDate),
                'treasury_summary' => $this->getTreasurySummary(),
                'revenue_evolution' => $this->getRevenueEvolution(),
                'treasury_evolution' => $this->getTreasuryEvolution(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données du dashboard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Statistiques Business
     */
    private function getBusinessStats($startDate, $endDate): array
    {
        $totalOrders = BusinessOrder::whereBetween('created_at', [$startDate, $endDate])->count();
        $totalRevenue = BusinessOrder::whereBetween('created_at', [$startDate, $endDate])->sum('total_amount');
        $totalPurchaseCost = BusinessOrder::whereBetween('created_at', [$startDate, $endDate])->sum('total_purchase_cost');
        $totalMargin = BusinessOrder::whereBetween('created_at', [$startDate, $endDate])->sum('total_margin_amount');
        $averageMarginRate = BusinessOrder::whereBetween('created_at', [$startDate, $endDate])
            ->where('total_amount', '>', 0)
            ->avg('margin_rate');
        $totalPaid = BusinessOrder::whereBetween('created_at', [$startDate, $endDate])->sum('total_paid');
        $totalUnpaid = $totalRevenue - $totalPaid;

        // Top 5 clients
        $topClients = DB::table('clients')
            ->join('business_orders', 'clients.id', '=', 'business_orders.client_id')
            ->whereBetween('business_orders.created_at', [$startDate, $endDate])
            ->select(
                'clients.id',
                'clients.first_name',
                'clients.last_name',
                DB::raw('SUM(business_orders.total_amount) as total_revenue')
            )
            ->groupBy('clients.id', 'clients.first_name', 'clients.last_name')
            ->orderBy('total_revenue', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($client) {
                return [
                    'id' => $client->id,
                    'first_name' => $client->first_name,
                    'last_name' => $client->last_name,
                    'full_name' => $client->first_name . ' ' . $client->last_name,
                    'total_revenue' => (float) $client->total_revenue,
                ];
            });

        // Top 5 produits
        $topProducts = DB::table('products')
            ->join('business_order_items', 'products.id', '=', 'business_order_items.product_id')
            ->join('business_orders', 'business_order_items.business_order_id', '=', 'business_orders.id')
            ->whereBetween('business_orders.created_at', [$startDate, $endDate])
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(business_order_items.quantity) as total_quantity')
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_quantity', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'total_quantity' => (int) $product->total_quantity,
                ];
            });

        return [
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
        ];
    }

    /**
     * Statistiques Express
     */
    private function getExpressStats($startDate, $endDate): array
    {
        $parcels = ExpressParcel::whereBetween('created_at', [$startDate, $endDate])->get();

        $totalParcels = $parcels->count();
        $totalRevenue = $parcels->sum('price_mad');
        $inTransit = $parcels->where('status', 'in_transit')->count();
        $delivered = $parcels->where('status', 'delivered')->count();
        $readyForPickup = $parcels->where('status', 'ready_for_pickup')->count();
        $totalWeight = $parcels->sum('weight_kg');
        $totalPaid = $parcels->sum('total_paid');
        $totalDebt = $totalRevenue - $totalPaid;

        // Top destinations
        $topDestinations = DB::table('express_parcels')
            ->join('express_trips', 'express_parcels.express_trip_id', '=', 'express_trips.id')
            ->whereBetween('express_parcels.created_at', [$startDate, $endDate])
            ->select(
                'express_trips.to_country',
                'express_trips.to_city',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('express_trips.to_country', 'express_trips.to_city')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $destination = $item->to_country;
                if ($item->to_city) {
                    $destination .= ' - ' . $item->to_city;
                }
                return [
                    'name' => $destination,
                    'count' => (int) $item->count,
                ];
            });

        return [
            'total_parcels' => $totalParcels,
            'total_revenue' => (float) $totalRevenue,
            'in_transit' => $inTransit,
            'delivered' => $delivered,
            'ready_for_pickup' => $readyForPickup,
            'total_weight' => (float) $totalWeight,
            'total_paid' => (float) $totalPaid,
            'total_debt' => (float) $totalDebt,
            'top_destinations' => $topDestinations,
        ];
    }

    /**
     * Résumé de la trésorerie
     */
    private function getTreasurySummary(): array
    {
        $accounts = Account::where('is_active', true)->get();
        
        $totalBalanceCfa = 0;
        $totalBalanceMad = 0;

        foreach ($accounts as $account) {
            $balance = $this->transactionService->getAccountBalance($account->id);
            if ($account->currency === 'CFA') {
                $totalBalanceCfa += $balance;
            } else {
                $totalBalanceMad += $balance;
            }
        }

        // Convertir MAD en CFA (taux de change depuis les paramètres système)
        $exchangeRate = DB::table('system_settings')
            ->where('key', 'exchange_rate_mad_to_cfa')
            ->value('value');
        $exchangeRate = $exchangeRate ? (float) $exchangeRate : 63;

        $totalBalanceCfa += $totalBalanceMad * $exchangeRate;

        return [
            'total_balance_cfa' => (float) $totalBalanceCfa,
            'total_balance_mad' => (float) $totalBalanceMad,
            'accounts_count' => $accounts->count(),
        ];
    }

    /**
     * Évolution du CA sur 12 mois
     */
    private function getRevenueEvolution(): array
    {
        $now = Carbon::now();
        $labels = [];
        $businessData = [];
        $expressData = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $startDate = $date->copy()->startOfMonth();
            $endDate = $date->copy()->endOfMonth();

            $labels[] = $date->locale('fr')->shortMonthName;

            // CA Business pour ce mois
            $businessRevenue = BusinessOrder::whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_amount');
            $businessData[] = (float) $businessRevenue;

            // CA Express pour ce mois
            $expressRevenue = ExpressParcel::whereBetween('created_at', [$startDate, $endDate])
                ->sum('price_mad');
            $expressData[] = (float) $expressRevenue;
        }

        return [
            'labels' => $labels,
            'business' => $businessData,
            'express' => $expressData,
        ];
    }

    /**
     * Évolution de la trésorerie sur 30 jours
     */
    private function getTreasuryEvolution(): array
    {
        $now = Carbon::now();
        $labels = [];
        $balances = [];

        $accounts = Account::where('is_active', true)->get();

        for ($i = 29; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $labels[] = $date->format('d/m');

            // Calculer le solde total à cette date
            $totalBalance = 0;
            foreach ($accounts as $account) {
                $balance = $this->getAccountBalanceAtDate($account->id, $date);
                if ($account->currency === 'CFA') {
                    $totalBalance += $balance;
                } else {
                    // Convertir MAD en CFA
                    $exchangeRate = DB::table('system_settings')
                        ->where('key', 'exchange_rate_mad_to_cfa')
                        ->value('value');
                    $exchangeRate = $exchangeRate ? (float) $exchangeRate : 63;
                    $totalBalance += $balance * $exchangeRate;
                }
            }
            $balances[] = (float) $totalBalance;
        }

        return [
            'labels' => $labels,
            'balances' => $balances,
        ];
    }

    /**
     * Calculer le solde d'un compte à une date donnée
     */
    private function getAccountBalanceAtDate($accountId, $date): float
    {
        $account = Account::find($accountId);
        if (!$account) {
            return 0;
        }

        $balance = $account->initial_balance;

        // Ajouter les crédits jusqu'à cette date
        $credits = FinancialTransaction::where('account_id', $accountId)
            ->whereIn('transaction_type', ['credit', 'transfer_in'])
            ->where('created_at', '<=', $date->endOfDay())
            ->sum('amount');

        // Soustraire les débits jusqu'à cette date
        $debits = FinancialTransaction::where('account_id', $accountId)
            ->whereIn('transaction_type', ['debit', 'transfer_out'])
            ->where('created_at', '<=', $date->endOfDay())
            ->sum('amount');

        return $balance + $credits - $debits;
    }
}

