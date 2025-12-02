<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\BusinessWave;
use App\Models\Business\BusinessWaveCost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BusinessWaveCostController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = BusinessWaveCost::query();

            // Filtre par vague Business
            if ($request->has('business_wave_id')) {
                $query->where('business_wave_id', $request->business_wave_id);
            }

            // Filtre par type de frais
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            $costs = $query->with('wave')
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
            'business_wave_id' => 'required|exists:business_waves,id',
            'type' => 'required|string|max:50',
            'label' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
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
            // Vérifier que la vague existe
            BusinessWave::findOrFail($request->business_wave_id);

            $cost = BusinessWaveCost::create([
                'business_wave_id' => $request->business_wave_id,
                'type' => $request->type,
                'label' => $request->label,
                'amount' => $request->amount,
                'currency' => $request->currency,
                'notes' => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Frais créé avec succès',
                'data' => $cost->load('wave'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du frais',
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
            $cost = BusinessWaveCost::with('wave')->findOrFail($id);

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
            'business_wave_id' => 'sometimes|required|exists:business_waves,id',
            'type' => 'sometimes|required|string|max:50',
            'label' => 'sometimes|required|string|max:255',
            'amount' => 'sometimes|required|numeric|min:0',
            'currency' => 'sometimes|required|string|max:10',
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
            $cost = BusinessWaveCost::findOrFail($id);

            $cost->update($request->only([
                'business_wave_id',
                'type',
                'label',
                'amount',
                'currency',
                'notes',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Frais mis à jour avec succès',
                'data' => $cost->fresh()->load('wave'),
            ]);
        } catch (\Exception $e) {
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
            $cost = BusinessWaveCost::findOrFail($id);
            $cost->delete();

            return response()->json([
                'success' => true,
                'message' => 'Frais supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du frais',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
