<?php

namespace App\Services\Express;

use App\Models\Express\ExpressParcel;

class ReferenceGeneratorService
{
    /**
     * Générer une référence unique pour un colis Express
     */
    public function generateParcelReference(?string $customReference = null): string
    {
        if ($customReference) {
            return $customReference;
        }

        $date = date('Ymd');
        $count = ExpressParcel::whereDate('created_at', today())->count() + 1;
        
        return 'EXP-PARCEL-' . $date . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}

