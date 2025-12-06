<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;

Route::get('/', function () {
    return view('welcome');
});

// Route de test pour Telegram (à supprimer après les tests)
Route::get('/test-telegram', function () {
    try {
        $botToken = env('TELEGRAM_BOT_TOKEN') ?? config('services.telegram.bot_token');
        
        if (!$botToken) {
            return response()->json([
                'error' => 'TELEGRAM_BOT_TOKEN non configuré',
                'check' => 'Vérifiez .env ou config/services.php',
            ], 500);
        }

        // Test 1: Vérifier que le bot existe
        $getMeResponse = Http::timeout(10)->get("https://api.telegram.org/bot{$botToken}/getMe");
        
        $result = [
            'test_1_getMe' => [
                'status' => $getMeResponse->successful() ? 'SUCCESS' : 'FAILED',
                'response' => $getMeResponse->json(),
            ],
        ];

        // Test 2: Envoyer un message de test
        $chatId = env('TELEGRAM_CHAT_ID') ?? config('services.telegram.chat_id');
        if ($chatId) {
            $telegramService = app(\App\Services\TelegramService::class);
            $testMessage = '🧪 Test Telegram depuis Laravel - ' . now()->format('d/m/Y H:i:s');
            
            $sendResult = $telegramService->sendToConfiguredChats($testMessage);
            
            $result['test_2_sendMessage'] = [
                'status' => $sendResult ? 'SUCCESS' : 'FAILED',
                'chat_id' => $chatId,
                'message' => $testMessage,
            ];
        } else {
            $result['test_2_sendMessage'] = [
                'status' => 'SKIPPED',
                'reason' => 'TELEGRAM_CHAT_ID non configuré',
            ];
        }

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
