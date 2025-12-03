<?php

namespace App\Services\Business;

use App\Models\Business\BusinessOrder;
use App\Models\Business\BusinessOrderItem;

class MarginCalculatorService
{
    /**
     * Calculer la marge pour une ligne de commande
     */
    public function calculateItemMargin(
        float $quantity,
        float $unitPrice,
        float $purchasePrice
    ): array {
        $totalPrice = $quantity * $unitPrice;
        $purchaseTotal = $quantity * $purchasePrice;
        $marginAmount = $totalPrice - $purchaseTotal;
        $marginRate = $totalPrice > 0 ? ($marginAmount / $totalPrice) * 100 : 0;

        return [
            'total_price' => round($totalPrice, 2),
            'purchase_total' => round($purchaseTotal, 2),
            'margin_amount' => round($marginAmount, 2),
            'margin_rate' => round($marginRate, 2),
        ];
    }

    /**
     * Recalculer les totaux d'une commande
     */
    public function recalculateOrderTotals(BusinessOrder $order): void
    {
        $items = $order->items;

        $totalAmount = $items->sum('total_price');
        $totalPurchaseCost = $items->sum('purchase_total');
        $totalMarginAmount = $totalAmount - $totalPurchaseCost;
        $marginRate = $totalAmount > 0 ? ($totalMarginAmount / $totalAmount) * 100 : 0;
        $isFullyReceived = $items->count() > 0 && $items->every(fn($item) => $item->is_received);

        $order->update([
            'total_amount' => round($totalAmount, 2),
            'total_purchase_cost' => round($totalPurchaseCost, 2),
            'total_margin_amount' => round($totalMarginAmount, 2),
            'margin_rate' => round($marginRate, 2),
            'is_fully_received' => $isFullyReceived,
        ]);
    }

    /**
     * Calculer les statistiques de marge pour une vague Business
     */
    public function calculateWaveMarginStats($wave): array
    {
        $currencyConverter = app(\App\Services\CurrencyConverterService::class);
        
        // Convertir tous les montants en MAD pour cohérence
        $totalRevenueMAD = 0;
        $totalPurchaseCostMAD = 0;
        $totalMarginMAD = 0;
        
        foreach ($wave->orders as $order) {
            if ($order->currency === 'MAD') {
                $totalRevenueMAD += $order->total_amount;
                $totalPurchaseCostMAD += $order->total_purchase_cost;
                $totalMarginMAD += $order->total_margin_amount;
            } else {
                // Convertir CFA en MAD
                $totalRevenueMAD += $currencyConverter->convertCfaToMad($order->total_amount);
                $totalPurchaseCostMAD += $currencyConverter->convertCfaToMad($order->total_purchase_cost);
                $totalMarginMAD += $currencyConverter->convertCfaToMad($order->total_margin_amount);
            }
        }
        
        // Convertir les frais de la vague en MAD
        $totalCostsMAD = 0;
        foreach ($wave->costs as $cost) {
            if ($cost->currency === 'MAD') {
                $totalCostsMAD += $cost->amount;
            } else {
                $totalCostsMAD += $currencyConverter->convertCfaToMad($cost->amount);
            }
        }
        
        // Convertir les frais des convois en MAD
        foreach ($wave->convoys as $convoy) {
            foreach ($convoy->costs as $cost) {
                if ($cost->currency === 'MAD') {
                    $totalCostsMAD += $cost->amount;
                } else {
                    $totalCostsMAD += $currencyConverter->convertCfaToMad($cost->amount);
                }
            }
        }
        
        $netProfit = $totalMarginMAD - $totalCostsMAD;

        return [
            'total_revenue' => round($totalRevenueMAD, 2),
            'total_purchase_cost' => round($totalPurchaseCostMAD, 2),
            'total_margin' => round($totalMarginMAD, 2),
            'total_costs' => round($totalCostsMAD, 2),
            'net_profit' => round($netProfit, 2),
            'profit_rate' => $totalRevenueMAD > 0 ? round(($netProfit / $totalRevenueMAD) * 100, 2) : 0,
        ];
    }
}

