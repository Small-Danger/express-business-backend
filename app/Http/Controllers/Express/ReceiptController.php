<?php

namespace App\Http\Controllers\Express;

use App\Http\Controllers\Controller;
use App\Models\Express\ExpressParcel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReceiptController extends Controller
{
    /**
     * Générer un reçu PDF pour un colis Express
     */
    public function generate(string $id): Response|JsonResponse
    {
        try {
            $parcel = ExpressParcel::with([
                'client',
                'receiverClient',
                'trip.wave',
                'createdBy',
            ])->findOrFail($id);

            $data = [
                'parcel' => $parcel,
                'company' => [
                    'name' => 'BS INTERNATIONAL EXPRESS',
                    'address' => env('COMPANY_ADDRESS', 'Casablanca, Rue du Languedoc'),
                    'phone_maroc' => env('COMPANY_PHONE_MAROC', '+212 603 402577'),
                    'phone_burkina' => env('COMPANY_PHONE_BURKINA', '+226 04 03 42 42'),
                    'website' => env('COMPANY_WEBSITE', 'https://bsinternationales.com'),
                    'email' => env('COMPANY_EMAIL', ''),
                ],
            ];

            $pdf = Pdf::loadView('express.receipt', $data);
            
            return $pdf->download('recu-' . $parcel->reference . '.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du reçu',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher le reçu dans le navigateur (prévisualisation)
     */
    public function preview(string $id): Response|JsonResponse
    {
        try {
            $parcel = ExpressParcel::with([
                'client',
                'receiverClient',
                'trip.wave',
                'createdBy',
            ])->findOrFail($id);

            $data = [
                'parcel' => $parcel,
                'company' => [
                    'name' => 'BS INTERNATIONAL EXPRESS',
                    'address' => env('COMPANY_ADDRESS', 'Casablanca, Rue du Languedoc'),
                    'phone_maroc' => env('COMPANY_PHONE_MAROC', '+212 603 402577'),
                    'phone_burkina' => env('COMPANY_PHONE_BURKINA', '+226 04 03 42 42'),
                    'website' => env('COMPANY_WEBSITE', 'https://bsinternationales.com'),
                    'email' => env('COMPANY_EMAIL', ''),
                ],
            ];

            $pdf = Pdf::loadView('express.receipt', $data);
            
            return $pdf->stream('recu-' . $parcel->reference . '.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du reçu',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
