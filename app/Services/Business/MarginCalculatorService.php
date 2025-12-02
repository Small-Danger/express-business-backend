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
        $totalRevenue = $wave->orders->sum('total_amount');
        $totalPurchaseCost = $wave->orders->sum('total_purchase_cost');
        $totalMargin = $wave->orders->sum('total_margin_amount');
        $totalCosts = $wave->costs->sum('amount');
        $netProfit = $totalMargin - $totalCosts;

        return [
            'total_revenue' => round($totalRevenue, 2),
            'total_purchase_cost' => round($totalPurchaseCost, 2),
            'total_margin' => round($totalMargin, 2),
            'total_costs' => round($totalCosts, 2),
            'net_profit' => round($netProfit, 2),
            'profit_rate' => $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0,
        ];
    }
}

