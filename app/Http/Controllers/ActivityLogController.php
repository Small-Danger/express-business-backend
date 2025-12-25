<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Liste des logs d'activité avec filtres
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = ActivityLog::with(['user'])
                ->orderBy('created_at', 'desc');

            // Filtres
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('action_type')) {
                $query->where('action_type', $request->action_type);
            }

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            if ($request->has('related_type')) {
                $query->where('related_type', $request->related_type);
                if ($request->has('related_id')) {
                    $query->where('related_id', $request->related_id);
                }
            }

            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->end_date);
            }

            // Recherche dans la description
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                      ->orWhereHas('user', function($userQuery) use ($search) {
                          $userQuery->where('name', 'like', "%{$search}%")
                                   ->orWhere('email', 'like', "%{$search}%");
                      });
                });
            }

            $logs = $query->paginate($request->get('per_page', 50));

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des logs
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $query = ActivityLog::query();

            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->end_date);
            }

            $stats = [
                'total' => $query->count(),
                'by_action_type' => $query->selectRaw('action_type, count(*) as count')
                    ->groupBy('action_type')
                    ->pluck('count', 'action_type')
                    ->toArray(),
                'by_category' => $query->selectRaw('category, count(*) as count')
                    ->groupBy('category')
                    ->pluck('count', 'category')
                    ->toArray(),
                'by_user' => ActivityLog::selectRaw('user_id, count(*) as count')
                    ->whereNotNull('user_id')
                    ->groupBy('user_id')
                    ->get()
                    ->map(function($item) {
                        $user = \App\Models\User::find($item->user_id);
                        return [
                            'user_id' => $item->user_id,
                            'user_name' => $user->name ?? 'Inconnu',
                            'user_email' => $user->email ?? '',
                            'count' => $item->count,
                        ];
                    })
                    ->toArray(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir un log spécifique
     */
    public function show(string $id): JsonResponse
    {
        try {
            $log = ActivityLog::with(['user'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $log,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Log non trouvé',
                'error' => $e->getMessage(),
            ], 404);
        }
    }
}
