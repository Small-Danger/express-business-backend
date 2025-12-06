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

        // Test 2: Envoyer un message de test
        $envChatId = env('TELEGRAM_CHAT_ID');
        $configChatId = config('services.telegram.chat_id');
        $chatId = $configChatId ?? $envChatId;
        
        if ($chatId) {
            $telegramService = app(\App\Services\TelegramService::class);
            $testMessage = '🧪 Test Telegram depuis Laravel - ' . now()->format('d/m/Y H:i:s');
            
            $sendResult = $telegramService->sendToConfiguredChats($testMessage);
            
            $result['test_2_sendMessage'] = [
                'status' => $sendResult ? 'SUCCESS' : 'FAILED',
                'chat_id' => $chatId,
                'chat_id_source' => $configChatId ? 'config' : ($envChatId ? 'env' : 'none'),
                'message' => $testMessage,
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
        $hardcodedToken = '8441163675:AAEmzelljLYwNGvb9ZFmNZ9vT7-8LCXF09A';
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
