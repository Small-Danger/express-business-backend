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
        $currencyConverter = app(\App\Services\CurrencyConverterService::class);
        
        // Total encaissé : utiliser la devise principale de chaque colis et convertir en MAD
        $totalRevenueMAD = 0;
        $parcels = $wave->trips->flatMap->parcels;
        
        foreach ($parcels as $parcel) {
            // Utiliser l'accesseur du modèle pour obtenir le prix en MAD
            $totalRevenueMAD += $parcel->price_in_mad;
        }

        // Total des frais de la vague (convertir en MAD pour cohérence)
        $totalCostsMAD = 0;
        foreach ($wave->costs as $cost) {
            if ($cost->currency === 'MAD') {
                $totalCostsMAD += $cost->amount;
            } else {
                $totalCostsMAD += $currencyConverter->convertCfaToMad($cost->amount);
            }
        }

        // Bénéfice net
        $netProfit = $totalRevenueMAD - $totalCostsMAD;

        // Statistiques supplémentaires
        $totalParcels = $parcels->count();
        $totalWeight = $parcels->sum('weight_kg');

        return [
            'total_parcels' => $totalParcels,
            'total_weight_kg' => round($totalWeight, 3),
            'total_revenue_mad' => round($totalRevenueMAD, 2),
            'total_costs' => round($totalCostsMAD, 2),
            'net_profit' => round($netProfit, 2),
            'profit_rate' => $totalRevenueMAD > 0 ? round(($netProfit / $totalRevenueMAD) * 100, 2) : 0,
            'is_profitable' => $netProfit > 0,
        ];
    }
}

