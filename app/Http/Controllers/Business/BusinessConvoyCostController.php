<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\BusinessConvoy;
use App\Models\Business\BusinessConvoyCost;
use App\Services\FinancialTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class BusinessConvoyCostController extends Controller
{
    protected $transactionService;

    public function __construct(FinancialTransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = BusinessConvoyCost::query();

            // Filtre par convoi Business
            if ($request->has('business_convoy_id')) {
                $query->where('business_convoy_id', $request->business_convoy_id);
            }

            // Filtre par type de frais
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            $costs = $query->with(['convoy', 'account'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $costs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des frais',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'business_convoy_id' => 'required|exists:business_convoys,id',
            'type' => 'required|string|max:50',
            'label' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'account_id' => 'required|exists:accounts,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Vérifier que le convoi existe
            $convoy = BusinessConvoy::findOrFail($request->business_convoy_id);

            $cost = BusinessConvoyCost::create([
                'business_convoy_id' => $request->business_convoy_id,
                'type' => $request->type,
                'label' => $request->label,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'account_id' => $request->account_id,
                'notes' => $request->notes,
            ]);

            // Créer la transaction DEBIT pour ce frais
            try {
                $this->transactionService->createTransaction(
                    $request->account_id,
                    'debit',
                    $request->amount,
                    $request->currency,
                    'convoy_cost',
                    'BusinessConvoyCost',
                    $cost->id,
                    "Frais convoi : {$request->label} - Convoi {$convoy->name}"
                );
            } catch (\Exception $e) {
                \Log::error("Erreur création transaction DEBIT pour frais convoi {$cost->id}: " . $e->getMessage());
                throw $e;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Frais créé avec succès',
                'data' => $cost->fresh()->load(['convoy', 'account']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du frais',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store multiple costs at once.
     */
    public function storeMultiple(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'business_convoy_id' => 'required|exists:business_convoys,id',
            'costs' => 'required|array|min:1',
            'costs.*.type' => 'required|string|max:50',
            'costs.*.label' => 'required|string|max:255',
            'costs.*.amount' => 'required|numeric|min:0',
            'costs.*.currency' => 'required|string|max:10',
            'costs.*.notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $convoy = BusinessConvoy::findOrFail($request->business_convoy_id);
            $costs = [];

            foreach ($request->costs as $costData) {
                $costs[] = BusinessConvoyCost::create([
                    'business_convoy_id' => $request->business_convoy_id,
                    'type' => $costData['type'],
                    'label' => $costData['label'],
                    'amount' => $costData['amount'],
                    'currency' => $costData['currency'],
                    'notes' => $costData['notes'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => count($costs) . ' frais créés avec succès',
                'data' => $costs,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création des frais',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $cost = BusinessConvoyCost::with(['convoy', 'account'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $cost,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Frais non trouvé',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'business_convoy_id' => 'sometimes|required|exists:business_convoys,id',
            'type' => 'sometimes|required|string|max:50',
            'label' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'currency' => 'sometimes|required|string|max:10',
            'account_id' => 'sometimes|required|exists:accounts,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            $cost = BusinessConvoyCost::with('convoy')->findOrFail($id);
            
            // Récupérer l'ancienne transaction pour la supprimer si nécessaire
            $oldAccountId = $cost->account_id;
            $oldAmount = $cost->amount;
            $oldCurrency = $cost->currency;
            
            $newAccountId = $request->account_id ?? $oldAccountId;
            $newAmount = $request->amount ?? $oldAmount;
            $newCurrency = $request->currency ?? $oldCurrency;

            // Mettre à jour le frais
            $cost->update($request->only([
                'business_convoy_id',
                'type',
                'label',
                'amount',
                'currency',
                'account_id',
                'notes',
            ]));

            // Si le compte, le montant ou la devise a changé, mettre à jour la transaction
            if ($oldAccountId != $newAccountId || $oldAmount != $newAmount || $oldCurrency != $newCurrency) {
                // Supprimer l'ancienne transaction
                \App\Models\FinancialTransaction::where('related_type', 'BusinessConvoyCost')
                    ->where('related_id', $cost->id)
                    ->where('transaction_category', 'convoy_cost')
                    ->delete();

                // Créer une nouvelle transaction avec les nouvelles valeurs
                try {
                    $convoy = $cost->convoy ?? BusinessConvoy::find($cost->business_convoy_id);
                    $convoyName = $convoy ? $convoy->name : 'N/A';
                    
                    $this->transactionService->createTransaction(
                        $newAccountId,
                        'debit',
                        $newAmount,
                        $newCurrency,
                        'convoy_cost',
                        'BusinessConvoyCost',
                        $cost->id,
                        "Frais convoi : {$cost->label} - Convoi {$convoyName}"
                    );
                } catch (\Exception $e) {
                    \Log::error("Erreur mise à jour transaction DEBIT pour frais convoi {$cost->id}: " . $e->getMessage());
                    throw $e;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Frais mis à jour avec succès',
                'data' => $cost->fresh()->load(['convoy', 'account']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du frais',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $cost = BusinessConvoyCost::findOrFail($id);

            // Supprimer la transaction associée
            \App\Models\FinancialTransaction::where('related_type', 'BusinessConvoyCost')
                ->where('related_id', $cost->id)
                ->where('transaction_category', 'convoy_cost')
                ->delete();

            $cost->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Frais supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du frais',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

