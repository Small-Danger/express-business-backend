<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Product::query();

            // Recherche par nom, SKU, description
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filtre par statut actif
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filtre par devise
            if ($request->has('currency')) {
                $query->where('currency', $request->currency);
            }

            $products = $query->orderBy('name', 'asc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $products,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des produits',
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
            'sku' => 'nullable|string|max:255|unique:products,sku',
            'description' => 'nullable|string',
            'purchase_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:10',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $sku = $request->sku;
            if (empty($sku)) {
                // Génération automatique du SKU
                \DB::transaction(function () use ($request, &$sku) {
                    $currencyPrefix = strtoupper($request->currency);
                    $prefix = 'PROD-' . $currencyPrefix . '-';
                    
                    // Détecter le driver de la base de données pour utiliser la bonne syntaxe
                    $dbDriver = \DB::connection()->getDriverName();
                    $startPos = strlen($prefix) + 1;

                    if ($dbDriver === 'pgsql') {
                        // PostgreSQL : utiliser SUBSTRING avec FROM et CAST en INTEGER
                        $lastProduct = Product::where('sku', 'like', $prefix . '%')
                            ->lockForUpdate()
                            ->orderByRaw("CAST(SUBSTRING(sku FROM {$startPos}) AS INTEGER) DESC")
                            ->first();
                    } else {
                        // MySQL : utiliser SUBSTRING avec position et CAST en UNSIGNED
                        $lastProduct = Product::where('sku', 'like', $prefix . '%')
                            ->lockForUpdate()
                            ->orderByRaw("CAST(SUBSTRING(sku, {$startPos}) AS UNSIGNED) DESC")
                            ->first();
                    }

                    $lastNumber = 0;
                    if ($lastProduct && preg_match('/-(\d+)$/', $lastProduct->sku, $matches)) {
                        $lastNumber = (int) $matches[1];
                    }

                    $sku = $prefix . str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);

                    $attempts = 0;
                    while (Product::where('sku', $sku)->lockForUpdate()->exists() && $attempts < 100) {
                        $lastNumber++;
                        $sku = $prefix . str_pad($lastNumber, 4, '0', STR_PAD_LEFT);
                        $attempts++;
                    }
                });
            }

            $product = Product::create([
                'name' => $request->name,
                'sku' => $sku,
                'description' => $request->description,
                'purchase_price' => $request->purchase_price,
                'sale_price' => $request->sale_price,
                'currency' => $request->currency,
                'is_active' => $request->get('is_active', true),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produit créé avec succès',
                'data' => $product,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du produit',
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
            $product = Product::withCount('businessOrderItems')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $product,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Produit non trouvé',
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
            'sku' => 'nullable|string|max:255|unique:products,sku,' . $id,
            'description' => 'nullable|string',
            'purchase_price' => 'sometimes|required|numeric|min:0',
            'sale_price' => 'sometimes|required|numeric|min:0',
            'currency' => 'sometimes|required|string|max:10',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $product = Product::findOrFail($id);

            $product->update($request->only([
                'name',
                'sku',
                'description',
                'purchase_price',
                'sale_price',
                'currency',
                'is_active',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Produit mis à jour avec succès',
                'data' => $product->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du produit',
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
            $product = Product::findOrFail($id);

            // Vérifier si le produit est utilisé dans des commandes
            if ($product->businessOrderItems()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer ce produit car il est utilisé dans des commandes',
                ], 422);
            }

            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Produit supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du produit',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
