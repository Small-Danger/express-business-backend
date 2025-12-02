<?php

namespace App\Http\Controllers\Express;

use App\Http\Controllers\Controller;
use App\Models\Express\ExpressParcel;
use App\Models\Express\ExpressTrip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Confirmer l'embarquement de colis pour un trajet
     */
    public function confirmLoading(Request $request, string $tripId): JsonResponse
    {
        try {
            $trip = ExpressTrip::findOrFail($tripId);

            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'parcel_ids' => 'required|array',
                'parcel_ids.*' => 'exists:express_parcels,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors(),
                ], 422);
            }

            \Illuminate\Support\Facades\DB::beginTransaction();

            $parcels = ExpressParcel::whereIn('id', $request->parcel_ids)
                ->where('express_trip_id', $tripId)
                ->where('status', 'ready_for_departure')
                ->get();

            foreach ($parcels as $parcel) {
                $oldStatus = $parcel->status;
                $parcel->update(['status' => 'loaded']);

                \App\Models\Express\ExpressParcelStatusHistory::create([
                    'express_parcel_id' => $parcel->id,
                    'old_status' => $oldStatus,
                    'new_status' => 'loaded',
                    'changed_by_user_id' => auth()->id() ?? 1,
                    'changed_at' => now(),
                    'comment' => 'Colis embarqué par le voyageur',
                ]);
            }

            // Mettre à jour le statut du trajet
            if ($trip->status === 'planned') {
                $trip->update([
                    'status' => 'in_transit',
                    'actual_date' => now(),
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($parcels) . ' colis embarqués avec succès',
                'data' => [
                    'trip' => $trip->fresh(),
                    'parcels_loaded' => count($parcels),
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation de l\'embarquement',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirmer la réception de colis arrivés
     */
    public function confirmArrival(Request $request): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'parcel_ids' => 'required|array',
                'parcel_ids.*' => 'exists:express_parcels,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors(),
                ], 422);
            }

            \Illuminate\Support\Facades\DB::beginTransaction();

            $parcels = ExpressParcel::whereIn('id', $request->parcel_ids)
                ->where('status', 'in_transit')
                ->get();

            foreach ($parcels as $parcel) {
                $oldStatus = $parcel->status;
                $parcel->update(['status' => 'arrived']);

                \App\Models\Express\ExpressParcelStatusHistory::create([
                    'express_parcel_id' => $parcel->id,
                    'old_status' => $oldStatus,
                    'new_status' => 'arrived',
                    'changed_by_user_id' => auth()->id() ?? 1,
                    'changed_at' => now(),
                    'comment' => 'Colis arrivé et réceptionné',
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($parcels) . ' colis réceptionnés avec succès',
                'data' => [
                    'parcels_received' => count($parcels),
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation de la réception',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marquer les colis comme prêts pour récupération
     */
    public function markReadyForPickup(Request $request): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'parcel_ids' => 'required|array',
                'parcel_ids.*' => 'exists:express_parcels,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $validator->errors(),
                ], 422);
            }

            \Illuminate\Support\Facades\DB::beginTransaction();

            $parcels = ExpressParcel::whereIn('id', $request->parcel_ids)
                ->where('status', 'arrived')
                ->get();

            foreach ($parcels as $parcel) {
                $oldStatus = $parcel->status;
                $parcel->update(['status' => 'ready_for_pickup']);

                \App\Models\Express\ExpressParcelStatusHistory::create([
                    'express_parcel_id' => $parcel->id,
                    'old_status' => $oldStatus,
                    'new_status' => 'ready_for_pickup',
                    'changed_by_user_id' => auth()->id() ?? 1,
                    'changed_at' => now(),
                    'comment' => 'Colis prêt pour récupération',
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($parcels) . ' colis marqués comme prêts pour récupération',
                'data' => [
                    'parcels_ready' => count($parcels),
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du marquage des colis',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
