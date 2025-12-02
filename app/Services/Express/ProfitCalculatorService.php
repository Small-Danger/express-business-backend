<?php

namespace App\Services\Express;

use App\Models\Express\ExpressWave;

class ProfitCalculatorService
{
    /**
     * Calculer le bénéfice d'une vague Express
     */
    public function calculateWaveProfit(ExpressWave $wave): array
    {
        // Total encaissé (somme des prix des colis en MAD)
        $totalRevenue = $wave->trips->flatMap->parcels->sum('price_mad');

        // Total des frais de la vague
        $totalCosts = $wave->costs->sum('amount');

        // Bénéfice net
        $netProfit = $totalRevenue - $totalCosts;

        // Statistiques supplémentaires
        $totalParcels = $wave->trips->flatMap->parcels->count();
        $totalWeight = $wave->trips->flatMap->parcels->sum('weight_kg');

        return [
            'total_parcels' => $totalParcels,
            'total_weight_kg' => round($totalWeight, 3),
            'total_revenue_mad' => round($totalRevenue, 2),
            'total_costs' => round($totalCosts, 2),
            'net_profit' => round($netProfit, 2),
            'profit_rate' => $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0,
            'is_profitable' => $netProfit > 0,
        ];
    }
}

