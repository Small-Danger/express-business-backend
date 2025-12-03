<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SystemSettingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SystemSetting::query();

            // Recherche par clé ou description
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('key', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filtre par type
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Filtre par statut actif
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $settings = $query->with('updatedBy')
                ->orderBy('key', 'asc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $settings,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des paramètres',
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
            'key' => 'required|string|max:255|unique:system_settings,key',
            'value' => 'required',
            'type' => 'required|string|in:decimal,integer,string,boolean',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Normaliser is_active : accepter boolean, string "true"/"false", ou 1/0
            $isActive = $request->has('is_active') 
                ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true
                : true;
            
            $setting = SystemSetting::create([
                'key' => $request->key,
                'value' => (string) $request->value,
                'type' => $request->type,
                'description' => $request->description,
                'is_active' => $isActive,
                'updated_by_user_id' => auth()->id() ?? 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paramètre créé avec succès',
                'data' => $setting,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du paramètre',
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
            $setting = SystemSetting::with('updatedBy')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $setting,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètre non trouvé',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Récupérer un paramètre par sa clé
     */
    public function getByKey(string $key): JsonResponse
    {
        try {
            $setting = SystemSetting::where('key', $key)
                ->where('is_active', true)
                ->firstOrFail();

            // Retourner la valeur typée selon le type
            $typedValue = match ($setting->type) {
                'decimal', 'float' => (float) $setting->value,
                'integer', 'int' => (int) $setting->value,
                'boolean', 'bool' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                default => $setting->value,
            };

            return response()->json([
                'success' => true,
                'data' => [
                    'key' => $setting->key,
                    'value' => $typedValue,
                    'type' => $setting->type,
                    'description' => $setting->description,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètre non trouvé',
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
            'key' => 'sometimes|required|string|max:255|unique:system_settings,key,' . $id,
            'value' => 'sometimes|required',
            'type' => 'sometimes|required|string|in:decimal,integer,string,boolean',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $setting = SystemSetting::findOrFail($id);

            $updateData = [];
            
            if ($request->has('key')) {
                $updateData['key'] = $request->key;
            }
            if ($request->has('type')) {
                $updateData['type'] = $request->type;
            }
            if ($request->has('description')) {
                $updateData['description'] = $request->description;
            }
            if ($request->has('value')) {
                $updateData['value'] = (string) $request->value;
            }
            if ($request->has('is_active')) {
                // Normaliser is_active : accepter boolean, string "true"/"false", ou 1/0
                $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $updateData['is_active'] = $isActive !== null ? $isActive : true;
            }

            $updateData['updated_by_user_id'] = auth()->id() ?? 1;

            $setting->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Paramètre mis à jour avec succès',
                'data' => $setting->fresh()->load('updatedBy'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du paramètre',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mettre à jour un paramètre par sa clé
     */
    public function updateByKey(Request $request, string $key): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'value' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $setting = SystemSetting::where('key', $key)->firstOrFail();

            $setting->update([
                'value' => (string) $request->value,
                'updated_by_user_id' => auth()->id() ?? 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Paramètre mis à jour avec succès',
                'data' => $setting->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du paramètre',
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
            $setting = SystemSetting::findOrFail($id);
            $setting->delete();

            return response()->json([
                'success' => true,
                'message' => 'Paramètre supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du paramètre',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir le taux de change MAD vers CFA
     */
    public function getExchangeRate(): JsonResponse
    {
        try {
            $rate = SystemSetting::get('exchange_rate_mad_to_cfa', 63.00);

            return response()->json([
                'success' => true,
                'data' => [
                    'rate' => $rate,
                    'from_currency' => 'MAD',
                    'to_currency' => 'CFA',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du taux de change',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir toutes les devises secondaires configurées avec leurs taux de change
     */
    public function getSecondaryCurrencies(): JsonResponse
    {
        try {
            // Récupérer tous les paramètres avec la clé exchange_rate_{CURRENCY}_to_cfa
            $settings = SystemSetting::where('key', 'like', 'exchange_rate_%_to_cfa')
                ->where('is_active', true)
                ->get();

            $currencies = [];
            foreach ($settings as $setting) {
                // Extraire le code de la devise depuis la clé (ex: exchange_rate_mad_to_cfa -> MAD)
                if (preg_match('/exchange_rate_([a-z]+)_to_cfa/i', $setting->key, $matches)) {
                    $currencyCode = strtoupper($matches[1]);
                    $rate = match ($setting->type) {
                        'decimal', 'float' => (float) $setting->value,
                        'integer', 'int' => (int) $setting->value,
                        default => (float) $setting->value,
                    };
                    
                    $currencies[] = [
                        'code' => $currencyCode,
                        'rate_to_cfa' => $rate, // 1 {CURRENCY} = X CFA
                        'rate_from_cfa' => 1 / $rate, // 1 CFA = X {CURRENCY}
                        'description' => $setting->description,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => $currencies,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des devises secondaires',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
