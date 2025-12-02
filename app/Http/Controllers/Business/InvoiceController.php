<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\BusinessOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    /**
     * Générer une facture PDF pour une commande Business
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
                    'address' => env('COMPANY_ADDRESS', ''),
                    'phone' => env('COMPANY_PHONE', ''),
                    'email' => env('COMPANY_EMAIL', ''),
                ],
            ];

            $pdf = Pdf::loadView('business.invoice', $data);
            
            return $pdf->download('facture-' . $order->reference . '.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération de la facture',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Afficher la facture dans le navigateur (prévisualisation)
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
                    'address' => env('COMPANY_ADDRESS', ''),
                    'phone' => env('COMPANY_PHONE', ''),
                    'email' => env('COMPANY_EMAIL', ''),
                ],
            ];

            $pdf = Pdf::loadView('business.invoice', $data);
            
            return $pdf->stream('facture-' . $order->reference . '.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération de la facture',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
