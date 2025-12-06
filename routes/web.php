<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::get('/', function () {
    return view('welcome');
});

// Route de test pour Telegram (à supprimer après les tests)
Route::get('/test-telegram', function () {
    try {
        // Débogage : vérifier d'où vient le token
        $envToken = env('TELEGRAM_BOT_TOKEN');
        $configToken = config('services.telegram.bot_token');
        $botToken = $configToken ?? $envToken;
        
        $debugInfo = [
            'env_token_exists' => !empty($envToken),
            'env_token_length' => $envToken ? strlen($envToken) : 0,
            'env_token_prefix' => $envToken ? substr($envToken, 0, 20) . '...' : null,
            'config_token_exists' => !empty($configToken),
            'config_token_length' => $configToken ? strlen($configToken) : 0,
            'config_token_prefix' => $configToken ? substr($configToken, 0, 20) . '...' : null,
            'final_token_used' => $botToken ? substr($botToken, 0, 20) . '...' : null,
        ];
        
        if (!$botToken) {
            return response()->json([
                'error' => 'TELEGRAM_BOT_TOKEN non configuré',
                'debug' => $debugInfo,
                'check' => 'Vérifiez .env ou config/services.php',
            ], 500);
        }

        // Test 1: Vérifier que le bot existe avec le token utilisé
        $getMeResponse = Http::timeout(10)->get("https://api.telegram.org/bot{$botToken}/getMe");
        
        $result = [
            'debug_info' => $debugInfo,
            'test_1_getMe' => [
                'status' => $getMeResponse->successful() ? 'SUCCESS' : 'FAILED',
                'status_code' => $getMeResponse->status(),
                'response' => $getMeResponse->json(),
                'token_used' => substr($botToken, 0, 15) . '...' . substr($botToken, -10),
            ],
        ];

        // Test 2: Envoyer un message de test (test direct avec Http)
        $envChatId = env('TELEGRAM_CHAT_ID');
        $configChatId = config('services.telegram.chat_id');
        $chatId = $configChatId ?? $envChatId;
        
        if ($chatId) {
            $testMessage = '🧪 Test Telegram depuis Laravel - ' . now()->format('d/m/Y H:i:s');
            
            // Test direct avec Http pour voir l'erreur exacte
            $directSendResponse = Http::timeout(10)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $testMessage,
            ]);
            
            $result['test_2_sendMessage_direct'] = [
                'status' => $directSendResponse->successful() && $directSendResponse->json('ok') ? 'SUCCESS' : 'FAILED',
                'status_code' => $directSendResponse->status(),
                'response' => $directSendResponse->json(),
                'chat_id' => $chatId,
                'chat_id_source' => $configChatId ? 'config' : ($envChatId ? 'env' : 'none'),
                'message' => $testMessage,
            ];
            
            // Test via TelegramService avec débogage
            $telegramService = app(\App\Services\TelegramService::class);
            
            // Vérifier le token dans le service
            $reflection = new \ReflectionClass($telegramService);
            $botTokenProperty = $reflection->getProperty('botToken');
            $botTokenProperty->setAccessible(true);
            $serviceToken = $botTokenProperty->getValue($telegramService);
            
            // Test 2a: sendMessage direct avec chat_id spécifié
            $directServiceResult = $telegramService->sendMessage($testMessage, $chatId);
            
            // Test 2b: sendMessage sans chat_id (utilise celui de .env)
            $noChatIdResult = $telegramService->sendMessage($testMessage);
            
            // Test 2c: sendToConfiguredChats
            $sendResult = $telegramService->sendToConfiguredChats($testMessage);
            
            $result['test_2_sendMessage_viaService'] = [
                'test_2a_sendMessage_with_chatId' => [
                    'status' => $directServiceResult ? 'SUCCESS' : 'FAILED',
                    'chat_id' => $chatId,
                    'description' => 'sendMessage avec chat_id explicite',
                ],
                'test_2b_sendMessage_default_chatId' => [
                    'status' => $noChatIdResult ? 'SUCCESS' : 'FAILED',
                    'description' => 'sendMessage utilisant chat_id de .env',
                ],
                'test_2c_sendToConfiguredChats' => [
                    'status' => $sendResult ? 'SUCCESS' : 'FAILED',
                    'description' => 'sendToConfiguredChats (méthode principale)',
                ],
                'service_token_prefix' => $serviceToken ? substr($serviceToken, 0, 20) . '...' : 'NULL',
                'service_token_length' => $serviceToken ? strlen($serviceToken) : 0,
                'token_matches' => $serviceToken === $botToken,
            ];
        } else {
            $result['test_2_sendMessage'] = [
                'status' => 'SKIPPED',
                'reason' => 'TELEGRAM_CHAT_ID non configuré',
                'env_chat_id' => $envChatId,
                'config_chat_id' => $configChatId,
            ];
        }
        
        // Test 3: Test direct avec le token depuis l'URL (pour comparer)
        $hardcodedToken = '8441163675:AAEmzeIIjLYwNGvb9ZFmNZ9vT7-8LCXf09A';
        $directTestResponse = Http::timeout(10)->get("https://api.telegram.org/bot{$hardcodedToken}/getMe");
        
        $result['test_3_hardcodedToken'] = [
            'status' => $directTestResponse->successful() ? 'SUCCESS' : 'FAILED',
            'status_code' => $directTestResponse->status(),
            'response' => $directTestResponse->json(),
        ];

        return response()->json($result, 200, [], JSON_PRETTY_PRINT);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], 500);
    }
});

// Route pour vider le cache (à supprimer après les tests)
Route::get('/clear-cache', function () {
    try {
        \Artisan::call('config:clear');
        \Artisan::call('cache:clear');
        return response()->json([
            'success' => true,
            'message' => 'Cache vidé avec succès',
            'output' => \Artisan::output(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});
