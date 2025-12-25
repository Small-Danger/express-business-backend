<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Connexion d'un utilisateur
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'L\'adresse email est obligatoire. Veuillez saisir votre adresse email.',
            'email.email' => 'L\'adresse email n\'est pas valide. Veuillez saisir une adresse email correcte (exemple : nom@domaine.com).',
            'password.required' => 'Le mot de passe est obligatoire. Veuillez saisir votre mot de passe.',
            'password.string' => 'Le mot de passe doit être une chaîne de caractères.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun compte trouvé avec cet email. Vérifiez votre adresse email.',
                ], 401);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe incorrect. Vérifiez votre mot de passe.',
                ], 401);
            }

            // Créer un token Sanctum
            $token = $user->createToken('auth-token')->plainTextToken;

            // Logger la connexion
            $this->activityLogService->logLogin($user->id, $request);

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Erreur de connexion', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Une erreur est survenue lors de la connexion. Veuillez réessayer.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Déconnexion de l'utilisateur
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Logger la déconnexion avant de supprimer le token
            $this->activityLogService->logLogout($user->id, $request);
            
            // Supprimer le token actuel
            $user->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtenir les informations de l'utilisateur connecté
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'data' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des informations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
