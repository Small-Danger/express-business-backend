<?php

namespace App\Services;

use App\Models\SystemSetting;

class CurrencyConverterService
{
    /**
     * Convertir un montant de MAD vers CFA
     */
    public function convertMadToCfa(float $amountMad): float
    {
        $rate = SystemSetting::get('exchange_rate_mad_to_cfa', 63.00);
        return round($amountMad * $rate, 2);
    }

    /**
     * Convertir un montant de CFA vers MAD
     */
    public function convertCfaToMad(float $amountCfa): float
    {
        $rate = SystemSetting::get('exchange_rate_mad_to_cfa', 63.00);
        return round($amountCfa / $rate, 2);
    }

    /**
     * Convertir un montant depuis une devise vers CFA
     */
    public function convertToCfa(float $amount, string $fromCurrency): float
    {
        if ($fromCurrency === 'CFA') {
            return $amount;
        }
        
        $rate = SystemSetting::get('exchange_rate_' . strtolower($fromCurrency) . '_to_cfa', null);
        if ($rate === null) {
            return $amount; // Si pas de taux, retourner tel quel
        }
        
        return round($amount * $rate, 2);
    }

    /**
     * Convertir un montant de CFA vers une devise
     */
    public function convertFromCfa(float $amountCfa, string $toCurrency): float
    {
        if ($toCurrency === 'CFA') {
            return $amountCfa;
        }
        
        $rate = SystemSetting::get('exchange_rate_' . strtolower($toCurrency) . '_to_cfa', null);
        if ($rate === null) {
            return $amountCfa; // Si pas de taux, retourner tel quel
        }
        
        return round($amountCfa / $rate, 2);
    }

    /**
     * Obtenir le taux de change actuel
     */
    public function getExchangeRate(): float
    {
        return SystemSetting::get('exchange_rate_mad_to_cfa', 63.00);
    }
}

