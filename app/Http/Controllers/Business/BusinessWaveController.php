<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\BusinessWave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusinessWaveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = BusinessWave::query();

            // Recherche par nom ou code
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            }

            // Filtre par statut
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $waves = $query->with(['convoys' => function($q) {
                    $q->select('id', 'business_wave_id', 'status');
                }])
                ->withCount(['orders', 'convoys', 'costs'])
                ->orderBy('start_date', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $waves,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des vagues',
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
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:business_waves,code',
            'start_date' => 'required|date',
            // end_date ne peut pas être définie à la création, elle est définie automatiquement lors de la clôture
            'status' => 'sometimes|string|in:draft,open,closed',
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
            $wave = BusinessWave::create([
                'name' => $request->name,
                'code' => $request->code,
                'start_date' => $request->start_date,
                // end_date n'est pas définie à la création, elle sera définie automatiquement lors de la clôture
                'end_date' => null,
                'status' => $request->get('status', 'open'),
                'notes' => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vague Business créée avec succès',
                'data' => $wave,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la vague',
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
            $wave = BusinessWave::with([
                'convoys' => function ($q) {
                    $q->withCount('orders');
                },
                'convoys.costs',
                'orders.client',
                'costs'
            ])
                ->withCount(['orders', 'convoys', 'costs'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $wave,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Vague non trouvée',
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
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:255|unique:business_waves,code,' . $id,
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'sometimes|string|in:draft,open,closed',
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
            $wave = BusinessWave::with('convoys')->findOrFail($id);

            // Préparer les données à mettre à jour
            $updateData = $request->only([
                'name',
                'code',
                'start_date',
                'status',
                'notes',
            ]);
            
            // Si le statut passe à 'closed' et qu'il n'y a pas encore d'end_date, le définir automatiquement
            if (isset($updateData['status']) && $updateData['status'] === 'closed' && !$wave->end_date) {
                $updateData['end_date'] = now()->toDateString();
            }
            
            // Si le statut n'est pas 'closed', ne pas permettre de modifier end_date
            // (end_date ne peut être défini que lors de la clôture)
            if (!isset($updateData['status']) || $updateData['status'] !== 'closed') {
                // Ne pas permettre de définir end_date manuellement si la vague n'est pas fermée
                // end_date doit être définie automatiquement lors de la clôture
            } else {
                // Si on ferme la vague et qu'il y a déjà une end_date dans la requête, on peut la garder
                if ($request->has('end_date')) {
                    $updateData['end_date'] = $request->end_date;
                }
            }

            $wave->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Vague mise à jour avec succès',
                'data' => $wave->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la vague',
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
            $wave = BusinessWave::findOrFail($id);

            // Vérifier si la vague a des commandes ou des convois
            if ($wave->orders()->count() > 0 || $wave->convoys()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer cette vague car elle contient des commandes ou des convois',
                ], 422);
            }

            $wave->delete();

            return response()->json([
                'success' => true,
                'message' => 'Vague supprimée avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la vague',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
