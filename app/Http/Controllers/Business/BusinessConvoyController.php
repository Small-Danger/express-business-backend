<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Business\BusinessConvoy;
use App\Models\Business\BusinessConvoyCost;
use App\Models\Business\BusinessWave;
use App\Services\FinancialTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BusinessConvoyController extends Controller
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
            $query = BusinessConvoy::query();

            // Filtre par vague Business
            if ($request->has('business_wave_id')) {
                $query->where('business_wave_id', $request->business_wave_id);
            }

            // Recherche par nom ou voyageur
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('traveler_name', 'like', "%{$search}%");
                });
            }

            // Filtre par statut
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filtre par pays de départ
            if ($request->has('from_country')) {
                $query->where('from_country', $request->from_country);
            }

            // Filtre par pays d'arrivée
            if ($request->has('to_country')) {
                $query->where('to_country', $request->to_country);
            }

            $convoys = $query->with(['wave', 'costs'])
                ->withCount('orders')
                ->orderBy('planned_departure_date', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $convoys,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des convois',
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
            'name' => 'required|string|max:255',
            'traveler_name' => 'required|string|max:255',
            'from_country' => 'required|string|max:255',
            'from_city' => 'required|string|max:255',
            'to_country' => 'required|string|max:255',
            'to_city' => 'required|string|max:255',
            'planned_departure_date' => 'required|date',
            'planned_arrival_date' => 'nullable|date|after_or_equal:planned_departure_date',
            'actual_departure_date' => 'nullable|date',
            'actual_arrival_date' => 'nullable|date',
            'status' => 'sometimes|string|in:planned,in_transit,arrived,closed',
            'notes' => 'nullable|string',
        ], [
            'business_wave_id.required' => 'La vague est obligatoire. Veuillez sélectionner une vague.',
            'business_wave_id.exists' => 'La vague sélectionnée n\'existe pas. Veuillez sélectionner une vague valide.',
            'name.required' => 'Le nom du convoi est obligatoire.',
            'name.string' => 'Le nom du convoi doit être une chaîne de caractères.',
            'name.max' => 'Le nom du convoi ne peut pas dépasser 255 caractères.',
            'traveler_name.required' => 'Le nom du voyageur est obligatoire. Veuillez saisir le nom de la personne qui transporte les marchandises.',
            'traveler_name.string' => 'Le nom du voyageur doit être une chaîne de caractères.',
            'traveler_name.max' => 'Le nom du voyageur ne peut pas dépasser 255 caractères.',
            'from_country.required' => 'Le pays de départ est obligatoire.',
            'from_country.string' => 'Le pays de départ doit être une chaîne de caractères.',
            'from_country.max' => 'Le pays de départ ne peut pas dépasser 255 caractères.',
            'from_city.required' => 'La ville de départ est obligatoire.',
            'from_city.string' => 'La ville de départ doit être une chaîne de caractères.',
            'from_city.max' => 'La ville de départ ne peut pas dépasser 255 caractères.',
            'to_country.required' => 'Le pays d\'arrivée est obligatoire.',
            'to_country.string' => 'Le pays d\'arrivée doit être une chaîne de caractères.',
            'to_country.max' => 'Le pays d\'arrivée ne peut pas dépasser 255 caractères.',
            'to_city.required' => 'La ville d\'arrivée est obligatoire.',
            'to_city.string' => 'La ville d\'arrivée doit être une chaîne de caractères.',
            'to_city.max' => 'La ville d\'arrivée ne peut pas dépasser 255 caractères.',
            'planned_departure_date.required' => 'La date de départ prévue est obligatoire. Veuillez sélectionner une date.',
            'planned_departure_date.date' => 'La date de départ prévue doit être une date valide.',
            'planned_arrival_date.date' => 'La date d\'arrivée prévue doit être une date valide.',
            'planned_arrival_date.after_or_equal' => 'La date d\'arrivée prévue doit être supérieure ou égale à la date de départ.',
            'actual_departure_date.date' => 'La date de départ réelle doit être une date valide.',
            'actual_arrival_date.date' => 'La date d\'arrivée réelle doit être une date valide.',
            'status.string' => 'Le statut doit être une chaîne de caractères.',
            'status.in' => 'Le statut doit être l\'un des suivants : planifié, en transit, arrivé, clôturé.',
            'notes.string' => 'Les notes doivent être une chaîne de caractères.',
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

            $convoy = BusinessConvoy::create([
                'business_wave_id' => $request->business_wave_id,
                'name' => $request->name,
                'traveler_name' => $request->traveler_name,
                'from_country' => $request->from_country,
                'from_city' => $request->from_city,
                'to_country' => $request->to_country,
                'to_city' => $request->to_city,
                'planned_departure_date' => $request->planned_departure_date,
                'planned_arrival_date' => $request->planned_arrival_date,
                'actual_departure_date' => $request->actual_departure_date,
                'actual_arrival_date' => $request->actual_arrival_date,
                'status' => $request->get('status', 'planned'),
                'notes' => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Convoi créé avec succès',
                'data' => $convoy->load('wave'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du convoi',
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
            $convoy = BusinessConvoy::with(['wave', 'orders.client', 'orders.items.product', 'costs'])
                ->withCount('orders')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $convoy,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Convoi non trouvé',
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
            'name' => 'sometimes|required|string|max:255',
            'traveler_name' => 'sometimes|required|string|max:255',
            'from_country' => 'sometimes|required|string|max:255',
            'from_city' => 'sometimes|required|string|max:255',
            'to_country' => 'sometimes|required|string|max:255',
            'to_city' => 'sometimes|required|string|max:255',
            'planned_departure_date' => 'sometimes|required|date',
            'planned_arrival_date' => 'nullable|date|after_or_equal:planned_departure_date',
            'actual_departure_date' => 'nullable|date',
            'actual_arrival_date' => 'nullable|date',
            'status' => 'sometimes|string|in:planned,in_transit,arrived,closed',
            'notes' => 'nullable|string',
            // end_date ne peut pas être modifié manuellement, il est défini automatiquement lors de la clôture
        ], [
            'business_wave_id.required' => 'La vague est obligatoire. Veuillez sélectionner une vague.',
            'business_wave_id.exists' => 'La vague sélectionnée n\'existe pas. Veuillez sélectionner une vague valide.',
            'name.required' => 'Le nom du convoi est obligatoire.',
            'name.string' => 'Le nom du convoi doit être une chaîne de caractères.',
            'name.max' => 'Le nom du convoi ne peut pas dépasser 255 caractères.',
            'traveler_name.required' => 'Le nom du voyageur est obligatoire. Veuillez saisir le nom de la personne qui transporte les marchandises.',
            'traveler_name.string' => 'Le nom du voyageur doit être une chaîne de caractères.',
            'traveler_name.max' => 'Le nom du voyageur ne peut pas dépasser 255 caractères.',
            'from_country.required' => 'Le pays de départ est obligatoire.',
            'from_country.string' => 'Le pays de départ doit être une chaîne de caractères.',
            'from_country.max' => 'Le pays de départ ne peut pas dépasser 255 caractères.',
            'from_city.required' => 'La ville de départ est obligatoire.',
            'from_city.string' => 'La ville de départ doit être une chaîne de caractères.',
            'from_city.max' => 'La ville de départ ne peut pas dépasser 255 caractères.',
            'to_country.required' => 'Le pays d\'arrivée est obligatoire.',
            'to_country.string' => 'Le pays d\'arrivée doit être une chaîne de caractères.',
            'to_country.max' => 'Le pays d\'arrivée ne peut pas dépasser 255 caractères.',
            'to_city.required' => 'La ville d\'arrivée est obligatoire.',
            'to_city.string' => 'La ville d\'arrivée doit être une chaîne de caractères.',
            'to_city.max' => 'La ville d\'arrivée ne peut pas dépasser 255 caractères.',
            'planned_departure_date.required' => 'La date de départ prévue est obligatoire. Veuillez sélectionner une date.',
            'planned_departure_date.date' => 'La date de départ prévue doit être une date valide.',
            'planned_arrival_date.date' => 'La date d\'arrivée prévue doit être une date valide.',
            'planned_arrival_date.after_or_equal' => 'La date d\'arrivée prévue doit être supérieure ou égale à la date de départ.',
            'actual_departure_date.date' => 'La date de départ réelle doit être une date valide.',
            'actual_arrival_date.date' => 'La date d\'arrivée réelle doit être une date valide.',
            'status.string' => 'Le statut doit être une chaîne de caractères.',
            'status.in' => 'Le statut doit être l\'un des suivants : planifié, en transit, arrivé, clôturé.',
            'notes.string' => 'Les notes doivent être une chaîne de caractères.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $convoy = BusinessConvoy::findOrFail($id);

            // Préparer les données à mettre à jour (sans end_date qui est géré automatiquement)
            $updateData = $request->only([
                'business_wave_id',
                'name',
                'traveler_name',
                'from_country',
                'from_city',
                'to_country',
                'to_city',
                'planned_departure_date',
                'planned_arrival_date',
                'actual_departure_date',
                'actual_arrival_date',
                'status',
                'notes',
            ]);
            
            // Si le statut passe à 'closed' et qu'il n'y a pas encore d'end_date, le définir automatiquement
            if (isset($updateData['status']) && $updateData['status'] === 'closed' && !$convoy->end_date) {
                $updateData['end_date'] = now()->toDateString();
            }
            
            // Si le statut n'est pas 'closed', ne pas permettre de modifier end_date
            // (end_date ne peut être défini que lors de la clôture)
            if (!isset($updateData['status']) || $updateData['status'] !== 'closed') {
                unset($updateData['end_date']);
            }

            $convoy->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Convoi mis à jour avec succès',
                'data' => $convoy->fresh()->load('wave'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du convoi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clôturer un convoi avec frais
     */
    public function close(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'costs' => 'required|array|min:1',
            'costs.*.type' => 'required|string|max:50',
            'costs.*.label' => 'required|string|max:255',
            'costs.*.amount' => 'required|numeric|min:0',
            'costs.*.currency' => 'required|string|max:10',
            'costs.*.account_id' => 'required|exists:accounts,id',
            'costs.*.notes' => 'nullable|string',
        ], [
            'costs.required' => 'Au moins un frais est obligatoire. Veuillez ajouter au moins un frais.',
            'costs.array' => 'Les frais doivent être une liste.',
            'costs.min' => 'Au moins un frais est obligatoire. Veuillez ajouter au moins un frais.',
            'costs.*.type.required' => 'Le type de frais est obligatoire pour chaque frais.',
            'costs.*.type.string' => 'Le type de frais doit être une chaîne de caractères.',
            'costs.*.type.max' => 'Le type de frais ne peut pas dépasser 50 caractères.',
            'costs.*.label.required' => 'Le libellé du frais est obligatoire pour chaque frais.',
            'costs.*.label.string' => 'Le libellé du frais doit être une chaîne de caractères.',
            'costs.*.label.max' => 'Le libellé du frais ne peut pas dépasser 255 caractères.',
            'costs.*.amount.required' => 'Le montant est obligatoire pour chaque frais.',
            'costs.*.amount.numeric' => 'Le montant doit être un nombre.',
            'costs.*.amount.min' => 'Le montant ne peut pas être négatif.',
            'costs.*.currency.required' => 'La devise est obligatoire pour chaque frais.',
            'costs.*.currency.string' => 'La devise doit être une chaîne de caractères.',
            'costs.*.currency.max' => 'La devise ne peut pas dépasser 10 caractères.',
            'costs.*.account_id.required' => 'Le compte est obligatoire pour chaque frais.',
            'costs.*.account_id.exists' => 'Le compte sélectionné n\'existe pas. Veuillez sélectionner un compte valide.',
            'costs.*.notes.string' => 'Les notes doivent être une chaîne de caractères.',
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

            $convoy = BusinessConvoy::with('orders')->findOrFail($id);

            // Vérifier qu'il n'y a pas de commandes en transit
            $ordersInTransit = $convoy->orders->where('status', 'in_transit');
            if ($ordersInTransit->count() > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Impossible de clôturer le convoi. {$ordersInTransit->count()} commande(s) sont encore en transit.",
                ], 422);
            }

            // Vérifier que toutes les commandes sont livrées ou annulées
            $ordersNotDelivered = $convoy->orders->whereNotIn('status', ['delivered', 'cancelled']);
            if ($ordersNotDelivered->count() > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Impossible de clôturer le convoi. {$ordersNotDelivered->count()} commande(s) ne sont pas encore livrées ou annulées.",
                ], 422);
            }

            // Gérer les frais : créer les nouveaux et mettre à jour les existants
            foreach ($request->costs as $costData) {
                // Si un ID est fourni, c'est un frais existant à mettre à jour
                if (isset($costData['id']) && $costData['id']) {
                    $cost = BusinessConvoyCost::findOrFail($costData['id']);
                    
                    // Vérifier que le frais appartient bien à ce convoi
                    if ($cost->business_convoy_id != $convoy->id) {
                        continue;
                    }
                    
                    // Mettre à jour le frais (les transactions seront mises à jour automatiquement via BusinessConvoyCostController::update)
                    $cost->update([
                        'type' => $costData['type'],
                        'label' => $costData['label'],
                        'amount' => $costData['amount'],
                        'currency' => $costData['currency'],
                        'account_id' => $costData['account_id'],
                        'notes' => $costData['notes'] ?? null,
                    ]);
                } else {
                    // Nouveau frais à créer
                    $cost = BusinessConvoyCost::create([
                        'business_convoy_id' => $convoy->id,
                        'type' => $costData['type'],
                        'label' => $costData['label'],
                        'amount' => $costData['amount'],
                        'currency' => $costData['currency'],
                        'account_id' => $costData['account_id'],
                        'notes' => $costData['notes'] ?? null,
                    ]);

                    // Créer la transaction DEBIT pour ce nouveau frais
                    // (les frais créés via BusinessConvoyCostController::store() auront déjà leur transaction)
                    // mais ici on crée directement via BusinessConvoyCost::create(), donc on doit créer la transaction manuellement
                    try {
                        $this->transactionService->createTransaction(
                            $costData['account_id'],
                            'debit',
                            $costData['amount'],
                            $costData['currency'],
                            'convoy_cost',
                            'BusinessConvoyCost',
                            $cost->id,
                            "Frais convoi : {$costData['label']} - Convoi {$convoy->name}"
                        );
                    } catch (\Exception $e) {
                        \Log::error("Erreur création transaction DEBIT pour frais convoi {$cost->id}: " . $e->getMessage());
                    }
                }
            }

            // Mettre à jour le convoi : statut = closed, end_date = aujourd'hui
            $convoy->update([
                'status' => 'closed',
                'end_date' => now()->toDateString(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Convoi clôturé avec succès',
                'data' => $convoy->fresh()->load(['wave', 'costs', 'orders']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la clôture du convoi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $convoy = BusinessConvoy::findOrFail($id);

            // Supprimer les transactions financières associées aux frais du convoi
            $costIds = $convoy->costs()->pluck('id');
            if ($costIds->isNotEmpty()) {
                \App\Models\FinancialTransaction::where('related_type', 'BusinessConvoyCost')
                    ->whereIn('related_id', $costIds)
                    ->where('transaction_category', 'convoy_cost')
                    ->delete();
            }

            // Les frais seront supprimés en cascade grâce aux contraintes foreign key
            // Les commandes garderont leur référence mais business_convoy_id sera mis à null (nullOnDelete)
            $convoy->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Convoi supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du convoi',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
