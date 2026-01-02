<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Base query : afficher TOUS les clients par défaut
            $query = Client::query();

            // Filtre par type de client (optionnel)
            if ($request->has('client_type')) {
                $clientType = $request->client_type;
                if ($clientType === 'business') {
                    // Business uniquement (pas Express)
                    $query->where('is_business_client', true)
                        ->where('is_express_client', false);
                } elseif ($clientType === 'express') {
                    // Clients Express uniquement (pas Business)
                    $query->where('is_express_client', true)
                        ->where('is_business_client', false);
                } elseif ($clientType === 'both') {
                    // Business ET Express
                    $query->where('is_business_client', true)
                        ->where('is_express_client', true);
                }
            } else {
                // Par défaut, afficher tous les clients (Business, Express, ou les deux)
                // Pas de filtre supplémentaire
            }

            // Recherche par nom, téléphone, email
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Filtre par pays
            if ($request->has('country')) {
                $query->where('country', $request->country);
            }

            // Filtre par ville
            if ($request->has('city')) {
                $query->where('city', $request->city);
            }

            $clients = $query->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $clients,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des clients',
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
            'code' => 'nullable|string|max:255|unique:clients,code',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:255|unique:clients,phone',
            'whatsapp_phone' => 'nullable|string|max:255|unique:clients,whatsapp_phone',
            'email' => 'nullable|email|max:255|unique:clients,email',
            'country' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'nullable|string',
            'is_business_client' => 'boolean',
            'is_express_client' => 'boolean',
            'notes' => 'nullable|string',
        ], [
            'code.unique' => 'Ce code existe déjà dans la base de données. Un client avec ce code a déjà été enregistré. Veuillez utiliser un code différent pour éviter les doublons.',
            'first_name.required' => 'Le prénom est obligatoire.',
            'first_name.string' => 'Le prénom doit être une chaîne de caractères.',
            'first_name.max' => 'Le prénom ne peut pas dépasser 255 caractères.',
            'last_name.required' => 'Le nom est obligatoire.',
            'last_name.string' => 'Le nom doit être une chaîne de caractères.',
            'last_name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'phone.required' => 'Le numéro de téléphone est obligatoire. Veuillez saisir un numéro de téléphone.',
            'phone.string' => 'Le numéro de téléphone doit être une chaîne de caractères.',
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser 255 caractères.',
            'phone.unique' => 'Ce numéro de téléphone existe déjà dans la base de données. Un client avec ce numéro a déjà été enregistré. Veuillez vérifier votre liste de clients ou utiliser un numéro différent pour éviter les doublons.',
            'whatsapp_phone.string' => 'Le numéro WhatsApp doit être une chaîne de caractères.',
            'whatsapp_phone.max' => 'Le numéro WhatsApp ne peut pas dépasser 255 caractères.',
            'whatsapp_phone.unique' => 'Ce numéro WhatsApp existe déjà dans la base de données. Un client avec ce numéro WhatsApp a déjà été enregistré. Veuillez vérifier votre liste de clients ou utiliser un numéro différent pour éviter les doublons.',
            'email.email' => 'L\'adresse email n\'est pas valide. Veuillez saisir une adresse email correcte (exemple : nom@domaine.com).',
            'email.max' => 'L\'adresse email ne peut pas dépasser 255 caractères.',
            'email.unique' => 'Cette adresse email existe déjà dans la base de données. Un client avec cette adresse email a déjà été enregistré. Veuillez vérifier votre liste de clients ou utiliser une adresse email différente pour éviter les doublons.',
            'country.required' => 'Le pays est obligatoire.',
            'country.string' => 'Le pays doit être une chaîne de caractères.',
            'country.max' => 'Le pays ne peut pas dépasser 255 caractères.',
            'city.required' => 'La ville est obligatoire.',
            'city.string' => 'La ville doit être une chaîne de caractères.',
            'city.max' => 'La ville ne peut pas dépasser 255 caractères.',
            'address.string' => 'L\'adresse doit être une chaîne de caractères.',
            'is_business_client.boolean' => 'Le champ client Business doit être vrai ou faux.',
            'is_express_client.boolean' => 'Le champ client Express doit être vrai ou faux.',
            'notes.string' => 'Les notes doivent être une chaîne de caractères.',
        ]);

        // Validation personnalisée : au moins un type doit être sélectionné
        $validator->after(function ($validator) use ($request) {
            if (!$request->get('is_business_client', false) && !$request->get('is_express_client', false)) {
                $validator->errors()->add('is_business_client', 'Le client doit être au moins Business ou Express.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Générer le code automatiquement si non fourni
            $code = $request->code;
            if (empty($code)) {
                // Utiliser une transaction avec verrou pour éviter les conflits de concurrence
                \DB::transaction(function () use ($request, &$code) {
                    $prefix = $request->get('is_business_client', false) && $request->get('is_express_client', false) 
                        ? 'CLI-BOTH' 
                        : ($request->get('is_business_client', false) ? 'CLI-BUS' : 'CLI-EXP');
                    
                    // Détecter le driver de la base de données pour utiliser la bonne syntaxe
                    $dbDriver = \DB::connection()->getDriverName();
                    $startPos = strlen($prefix) + 2;
                    
                    // Verrouiller pour lecture et obtenir le dernier numéro
                    if ($dbDriver === 'pgsql') {
                        // PostgreSQL : utiliser SUBSTRING avec FROM et CAST en INTEGER
                        $lastClient = Client::where('code', 'like', $prefix . '-%')
                            ->lockForUpdate()
                            ->orderByRaw("CAST(SUBSTRING(code FROM {$startPos}) AS INTEGER) DESC")
                            ->first();
                    } else {
                        // MySQL : utiliser SUBSTRING avec position et CAST en UNSIGNED
                        $lastClient = Client::where('code', 'like', $prefix . '-%')
                            ->lockForUpdate()
                            ->orderByRaw("CAST(SUBSTRING(code, {$startPos}) AS UNSIGNED) DESC")
                            ->first();
                    }
                    
                    $lastNumber = 0;
                    if ($lastClient && preg_match('/-(\d+)$/', $lastClient->code, $matches)) {
                        $lastNumber = (int) $matches[1];
                    }
                    
                    $code = $prefix . '-' . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
                    
                    // Vérifier l'unicité du code généré (avec verrou)
                    $attempts = 0;
                    while (Client::where('code', $code)->lockForUpdate()->exists() && $attempts < 100) {
                        $lastNumber++;
                        $code = $prefix . '-' . str_pad($lastNumber, 3, '0', STR_PAD_LEFT);
                        $attempts++;
                    }
                });
            }

            // Dans le module Business, par défaut is_business_client doit être true
            // Si la valeur n'est pas fournie explicitement, on la met à true
            $isBusinessClient = $request->has('is_business_client') 
                ? $request->get('is_business_client', false) 
                : true;

            $client = Client::create([
                'code' => $code,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'whatsapp_phone' => $request->whatsapp_phone,
                'email' => $request->email,
                'country' => $request->country,
                'city' => $request->city,
                'address' => $request->address,
                'is_business_client' => $isBusinessClient,
                'is_express_client' => $request->get('is_express_client', false),
                'notes' => $request->notes,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Client créé avec succès',
                'data' => $client,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du client',
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
            // Récupérer le client sans filtre de type (peut être Business, Express, ou les deux)
            $client = Client::where('id', $id)
                ->with(['businessOrders' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(10);
                }])
                ->with(['expressParcels' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(10);
                }])
                ->withCount(['businessOrders', 'expressParcels'])
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $client,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Client non trouvé',
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
            'code' => 'nullable|string|max:255|unique:clients,code,' . $id,
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:255|unique:clients,phone,' . $id,
            'whatsapp_phone' => 'nullable|string|max:255|unique:clients,whatsapp_phone,' . $id,
            'email' => 'nullable|email|max:255|unique:clients,email,' . $id,
            'country' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'is_business_client' => 'boolean',
            'is_express_client' => 'boolean',
            'notes' => 'nullable|string',
        ], [
            'code.unique' => 'Ce code existe déjà dans la base de données. Un client avec ce code a déjà été enregistré. Veuillez utiliser un code différent pour éviter les doublons.',
            'first_name.required' => 'Le prénom est obligatoire.',
            'first_name.string' => 'Le prénom doit être une chaîne de caractères.',
            'first_name.max' => 'Le prénom ne peut pas dépasser 255 caractères.',
            'last_name.required' => 'Le nom est obligatoire.',
            'last_name.string' => 'Le nom doit être une chaîne de caractères.',
            'last_name.max' => 'Le nom ne peut pas dépasser 255 caractères.',
            'phone.required' => 'Le numéro de téléphone est obligatoire. Veuillez saisir un numéro de téléphone.',
            'phone.string' => 'Le numéro de téléphone doit être une chaîne de caractères.',
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser 255 caractères.',
            'phone.unique' => 'Ce numéro de téléphone existe déjà dans la base de données. Un client avec ce numéro a déjà été enregistré. Veuillez vérifier votre liste de clients ou utiliser un numéro différent pour éviter les doublons.',
            'whatsapp_phone.string' => 'Le numéro WhatsApp doit être une chaîne de caractères.',
            'whatsapp_phone.max' => 'Le numéro WhatsApp ne peut pas dépasser 255 caractères.',
            'whatsapp_phone.unique' => 'Ce numéro WhatsApp existe déjà dans la base de données. Un client avec ce numéro WhatsApp a déjà été enregistré. Veuillez vérifier votre liste de clients ou utiliser un numéro différent pour éviter les doublons.',
            'email.email' => 'L\'adresse email n\'est pas valide. Veuillez saisir une adresse email correcte (exemple : nom@domaine.com).',
            'email.max' => 'L\'adresse email ne peut pas dépasser 255 caractères.',
            'email.unique' => 'Cette adresse email existe déjà dans la base de données. Un client avec cette adresse email a déjà été enregistré. Veuillez vérifier votre liste de clients ou utiliser une adresse email différente pour éviter les doublons.',
            'country.required' => 'Le pays est obligatoire.',
            'country.string' => 'Le pays doit être une chaîne de caractères.',
            'country.max' => 'Le pays ne peut pas dépasser 255 caractères.',
            'city.required' => 'La ville est obligatoire.',
            'city.string' => 'La ville doit être une chaîne de caractères.',
            'city.max' => 'La ville ne peut pas dépasser 255 caractères.',
            'address.string' => 'L\'adresse doit être une chaîne de caractères.',
            'is_business_client.boolean' => 'Le champ client Business doit être vrai ou faux.',
            'is_express_client.boolean' => 'Le champ client Express doit être vrai ou faux.',
            'notes.string' => 'Les notes doivent être une chaîne de caractères.',
        ]);

        // Validation personnalisée : au moins un type doit être sélectionné
        $validator->after(function ($validator) use ($request) {
            $isBusiness = $request->has('is_business_client') ? $request->get('is_business_client', false) : null;
            $isExpress = $request->has('is_express_client') ? $request->get('is_express_client', false) : null;
            
            // Si les deux sont définis et sont tous les deux false
            if ($isBusiness !== null && $isExpress !== null && !$isBusiness && !$isExpress) {
                $validator->errors()->add('is_business_client', 'Le client doit être au moins Business ou Express.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Récupérer le client sans filtre de type (peut être Business, Express, ou les deux)
            $client = Client::where('id', $id)
                ->firstOrFail();

            $updateData = $request->only([
                'code',
                'first_name',
                'last_name',
                'phone',
                'whatsapp_phone',
                'email',
                'country',
                'city',
                'address',
                'is_business_client',
                'is_express_client',
                'notes',
            ]);

            // Ne mettre à jour que les champs fournis
            $updateData = array_filter($updateData, function ($value) {
                return $value !== null;
            });

            $client->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Client mis à jour avec succès',
                'data' => $client->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du client',
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
            // Récupérer le client sans filtre de type (peut être Business, Express, ou les deux)
            $client = Client::where('id', $id)
                ->firstOrFail();

            // Vérifier si le client a des commandes
            if ($client->businessOrders()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer ce client car il a des commandes associées',
                ], 422);
            }

            $client->delete();

            return response()->json([
                'success' => true,
                'message' => 'Client supprimé avec succès',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du client',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
