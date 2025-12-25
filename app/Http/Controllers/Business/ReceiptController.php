<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\BusinessOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReceiptController extends Controller
{
    /**
     * Générer un reçu PDF pour une commande Business
     */
    public function generate(string $id): Response|JsonResponse
    {
        try {
            $order = BusinessOrder::with([
                'client',
                'wave',
                'convoy',
                'items.product',
                'createdBy',
            ])->findOrFail($id);

            $data = [
                'order' => $order,
                'company' => [
                    'name' => 'BS INTERNATIONAL BUSINESS',
                    'address' => env('COMPANY_ADDRESS', 'Casablanca, Rue du Languedoc'),
                    'phone_maroc' => env('COMPANY_PHONE_MAROC', '+212 603 402577'),
                    'phone_burkina' => env('COMPANY_PHONE_BURKINA', '+226 04 03 42 42'),
                    'website' => env('COMPANY_WEBSITE', 'https://bsinternationales.com'),
                    'email' => env('COMPANY_EMAIL', ''),
                ],
            ];

            $pdf = Pdf::loadView('business.receipt', $data);
            
            return $pdf->download('recu-' . $order->reference . '.pdf');
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
            $order = BusinessOrder::with([
                'client',
                'wave',
                'convoy',
                'items.product',
                'createdBy',
            ])->findOrFail($id);

            $data = [
                'order' => $order,
                'company' => [
                    'name' => 'BS INTERNATIONAL BUSINESS',
                    'address' => env('COMPANY_ADDRESS', 'Casablanca, Rue du Languedoc'),
                    'phone_maroc' => env('COMPANY_PHONE_MAROC', '+212 603 402577'),
                    'phone_burkina' => env('COMPANY_PHONE_BURKINA', '+226 04 03 42 42'),
                    'website' => env('COMPANY_WEBSITE', 'https://bsinternationales.com'),
                    'email' => env('COMPANY_EMAIL', ''),
                ],
            ];

            $pdf = Pdf::loadView('business.receipt', $data);
            
            return $pdf->stream('recu-' . $order->reference . '.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du reçu',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

