<?php

namespace App\Services\Business;

use App\Models\Business\BusinessOrder;

class ReferenceGeneratorService
{
    /**
     * Générer une référence unique pour une commande Business
     */
    public function generateOrderReference(?string $customReference = null): string
    {
        if ($customReference) {
            return $customReference;
        }

        $date = date('Ymd');
        $count = BusinessOrder::whereDate('created_at', today())->count() + 1;
        
        return 'BUS-ORD-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}

