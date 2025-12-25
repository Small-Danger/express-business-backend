<?php

namespace App\Http\Controllers\Express;

use App\Http\Controllers\Controller;
use App\Models\Express\ExpressTrip;
use App\Models\Express\ExpressTripCost;
use App\Models\Express\ExpressWave;
use App\Services\FinancialTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExpressTripController extends Controller
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
            $query = ExpressTrip::query();

            // Filtre par vague Express
            if ($request->has('express_wave_id')) {
                $query->where('express_wave_id', $request->express_wave_id);
            }

            // Filtre par direction
            if ($request->has('direction')) {
                $query->where('direction', $request->direction);
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

            $trips = $query->with(['wave', 'costs', 'parcels'])
                ->withCount('parcels')
                ->orderBy('planned_date', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $trips,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des trajets',
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
            'express_wave_id' => 'required|exists:express_waves,id',
            'name' => 'required|string|max:255',
            'direction' => 'required|string|in:A_to_B,B_to_A',
            'from_country' => 'required|string|max:255',
            'from_city' => 'required|string|max:255',
            'to_country' => 'required|string|max:255',
            'to_city' => 'required|string|max:255',
            'planned_date' => 'required|date',
            'actual_date' => 'nullable|date',
            'traveler_name' => 'required|string|max:255',
            'status' => 'sometimes|string|in:planned,in_transit,arrived,closed',
            'notes' => 'nullable|string',
        ], [
            'express_wave_id.required' => 'La vague est obligatoire. Veuillez sélectionner une vague.',
            'express_wave_id.exists' => 'La vague sélectionnée n\'existe pas. Veuillez sélectionner une vague valide.',
            'name.required' => 'Le nom du trajet est obligatoire.',
            'name.string' => 'Le nom du trajet doit être une chaîne de caractères.',
            'name.max' => 'Le nom du trajet ne peut pas dépasser 255 caractères.',
            'direction.required' => 'La direction est obligatoire. Veuillez sélectionner A vers B ou B vers A.',
            'direction.string' => 'La direction doit être une chaîne de caractères.',
            'direction.in' => 'La direction doit être "A vers B" ou "B vers A".',
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
            'planned_date.required' => 'La date prévue est obligatoire. Veuillez sélectionner une date.',
            'planned_date.date' => 'La date prévue doit être une date valide.',
            'actual_date.date' => 'La date réelle doit être une date valide.',
            'traveler_name.required' => 'Le nom du voyageur est obligatoire. Veuillez saisir le nom de la personne qui voyage.',
            'traveler_name.string' => 'Le nom du voyageur doit être une chaîne de caractères.',
            'traveler_name.max' => 'Le nom du voyageur ne peut pas dépasser 255 caractères.',
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
            ExpressWave::findOrFail($request->express_wave_id);

            $trip = ExpressTrip::create([
                'express_wave_id' => $request->express_wave_id,
                'name' => $request->name,
                'direction' => $request->direction,
                'from_country' => $request->from_country,
                'from_city' => $request->from_city,
                'to_country' => $request->to_country,
                'to_city' => $request->to_city,
                'planned_date' => $request->planned_date,
                'actual_date' => $request->actual_date,
                'traveler_name' => $request->traveler_name,
                'status' => $request->get('status', 'planned'),
                'notes' => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Trajet créé avec succès',
                'data' => $trip->load('wave'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du trajet',
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
            $trip = ExpressTrip::with(['wave', 'parcels.client', 'parcels.receiverClient', 'costs'])
                ->withCount('parcels')
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $trip,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Trajet non trouvé',
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
            'express_wave_id' => 'sometimes|required|exists:express_waves,id',
            'name' => 'sometimes|required|string|max:255',
            'direction' => 'sometimes|required|string|in:A_to_B,B_to_A',
            'from_country' => 'sometimes|required|string|max:255',
            'from_city' => 'sometimes|required|string|max:255',
            'to_country' => 'sometimes|required|string|max:255',
            'to_city' => 'sometimes|required|string|max:255',
            'planned_date' => 'sometimes|required|date',
            'actual_date' => 'nullable|date',
            'traveler_name' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|string|in:planned,in_transit,arrived,closed',
            'notes' => 'nullable|string',
            // end_date ne peut pas être modifié manuellement, il est défini automatiquement lors de la clôture
        ], [
            'express_wave_id.required' => 'La vague est obligatoire. Veuillez sélectionner une vague.',
            'express_wave_id.exists' => 'La vague sélectionnée n\'existe pas. Veuillez sélectionner une vague valide.',
            'name.required' => 'Le nom du trajet est obligatoire.',
            'name.string' => 'Le nom du trajet doit être une chaîne de caractères.',
            'name.max' => 'Le nom du trajet ne peut pas dépasser 255 caractères.',
            'direction.required' => 'La direction est obligatoire. Veuillez sélectionner A vers B ou B vers A.',
            'direction.string' => 'La direction doit être une chaîne de caractères.',
            'direction.in' => 'La direction doit être "A vers B" ou "B vers A".',
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
            'planned_date.required' => 'La date prévue est obligatoire. Veuillez sélectionner une date.',
            'planned_date.date' => 'La date prévue doit être une date valide.',
            'actual_date.date' => 'La date réelle doit être une date valide.',
            'traveler_name.required' => 'Le nom du voyageur est obligatoire. Veuillez saisir le nom de la personne qui voyage.',
            'traveler_name.string' => 'Le nom du voyageur doit être une chaîne de caractères.',
            'traveler_name.max' => 'Le nom du voyageur ne peut pas dépasser 255 caractères.',
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
            $trip = ExpressTrip::findOrFail($id);

            // Préparer les données à mettre à jour (sans end_date qui est géré automatiquement)
            $updateData = $request->only([
                'express_wave_id',
                'name',
                'direction',
                'from_country',
                'from_city',
                'to_country',
                'to_city',
                'planned_date',
                'actual_date',
                'traveler_name',
                'status',
                'notes',
            ]);
            
            // Si le statut passe à 'closed' et qu'il n'y a pas encore d'end_date, le définir automatiquement
            if (isset($updateData['status']) && $updateData['status'] === 'closed' && !$trip->end_date) {
                $updateData['end_date'] = now()->toDateString();
            }
            
            // Si le statut n'est pas 'closed', ne pas permettre de modifier end_date
            // (end_date ne peut être défini que lors de la clôture)
            if (!isset($updateData['status']) || $updateData['status'] !== 'closed') {
                unset($updateData['end_date']);
            }

            $trip->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Trajet mis à jour avec succès',
                'data' => $trip->fresh()->load('wave'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du trajet',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clôturer un trajet avec frais
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

            $trip = ExpressTrip::with('parcels')->findOrFail($id);

            // Vérifier qu'il n'y a pas de colis en transit
            $parcelsInTransit = $trip->parcels->where('status', 'in_transit');
            if ($parcelsInTransit->count() > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Impossible de clôturer le trajet. {$parcelsInTransit->count()} colis sont encore en transit.",
                ], 422);
            }

            // Vérifier que tous les colis sont livrés ou annulés
            $parcelsNotDelivered = $trip->parcels->whereNotIn('status', ['delivered', 'cancelled']);
            if ($parcelsNotDelivered->count() > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Impossible de clôturer le trajet. {$parcelsNotDelivered->count()} colis ne sont pas encore livrés ou annulés.",
                ], 422);
            }

            // Gérer les frais : créer les nouveaux et mettre à jour les existants
            foreach ($request->costs as $costData) {
                // Si un ID est fourni, c'est un frais existant à mettre à jour
                if (isset($costData['id']) && $costData['id']) {
                    $cost = ExpressTripCost::findOrFail($costData['id']);
                    
                    // Vérifier que le frais appartient bien à ce trajet
                    if ($cost->express_trip_id != $trip->id) {
                        continue;
                    }
                    
                    // Mettre à jour le frais (les transactions seront mises à jour automatiquement via ExpressTripCostController::update)
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
                    $cost = ExpressTripCost::create([
                        'express_trip_id' => $trip->id,
                        'type' => $costData['type'],
                        'label' => $costData['label'],
                        'amount' => $costData['amount'],
                        'currency' => $costData['currency'],
                        'account_id' => $costData['account_id'],
                        'notes' => $costData['notes'] ?? null,
                    ]);

                    // Créer la transaction DEBIT pour ce nouveau frais
                    // (les frais créés via ExpressTripCostController::store() auront déjà leur transaction)
                    // mais ici on crée directement via ExpressTripCost::create(), donc on doit créer la transaction manuellement
                    try {
                        $this->transactionService->createTransaction(
                            $costData['account_id'],
                            'debit',
                            $costData['amount'],
                            $costData['currency'],
                            'trip_cost',
                            'ExpressTripCost',
                            $cost->id,
                            "Frais trajet : {$costData['label']} - Trajet {$trip->name}"
                        );
                    } catch (\Exception $e) {
                        \Log::error("Erreur création transaction DEBIT pour frais trajet {$cost->id}: " . $e->getMessage());
                    }
                }
            }

            // Mettre à jour le trajet : statut = closed, end_date = aujourd'hui
            $trip->update([
                'status' => 'closed',
                'end_date' => now()->toDateString(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Trajet clôturé avec succès',
                'data' => $trip->fresh()->load(['wave', 'costs', 'parcels']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la clôture du trajet',
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
            $trip = ExpressTrip::findOrFail($id);

            // Supprimer les transactions financières associées aux colis du trajet
            $parcelIds = $trip->parcels()->pluck('id');
            if ($parcelIds->isNotEmpty()) {
                \App\Models\FinancialTransaction::where('related_type', 'ExpressParcel')
                    ->whereIn('related_id', $parcelIds)
                    ->whereIn('transaction_category', ['parcel_deposit', 'parcel_pickup_payment'])
                    ->delete();
            }

            // Supprimer les transactions financières associées aux frais du trajet
            $costIds = $trip->costs()->pluck('id');
            if ($costIds->isNotEmpty()) {
                \App\Models\FinancialTransaction::where('related_type', 'ExpressTripCost')
                    ->whereIn('related_id', $costIds)
                    ->where('transaction_category', 'trip_cost')
                    ->delete();
            }

            // Les colis et frais seront supprimés en cascade grâce aux contraintes foreign key
            $trip->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Trajet supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du trajet',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
