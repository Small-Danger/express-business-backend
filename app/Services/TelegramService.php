<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected $botToken;
    protected $apiUrl;

    public function __construct()
    {
        $this->botToken = env('TELEGRAM_BOT_TOKEN');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
    }

    /**
     * Envoyer un message à un chat Telegram
     *
     * @param string|array $chatId ID du chat ou tableau d'IDs pour envoyer à plusieurs personnes
     * @param string $message Message à envoyer
     * @param string|null $parseMode Mode de parsing (HTML ou Markdown)
     * @return bool
     */
    public function sendMessage($chatId, string $message, ?string $parseMode = null): bool
    {
        if (!$this->botToken) {
            Log::warning('TELEGRAM_BOT_TOKEN n\'est pas configuré dans .env');
            return false;
        }

        // Si plusieurs chat_ids (tableau), envoyer à chacun
        if (is_array($chatId)) {
            $success = true;
            foreach ($chatId as $id) {
                if (!$this->sendMessage($id, $message, $parseMode)) {
                    $success = false;
                }
            }
            return $success;
        }

        try {
            $response = Http::timeout(10)->post("{$this->apiUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
            ]);

            if ($response->successful() && $response->json('ok')) {
                return true;
            }

            Log::error('Erreur Telegram API', [
                'response' => $response->json(),
                'chat_id' => $chatId,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception lors de l\'envoi Telegram', [
                'message' => $e->getMessage(),
                'chat_id' => $chatId,
            ]);

            return false;
        }
    }

    /**
     * Envoyer un message formaté avec HTML
     */
    public function sendHtmlMessage($chatId, string $message): bool
    {
        return $this->sendMessage($chatId, $message, 'HTML');
    }

    /**
     * Vérifier si le bot est configuré
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken) && !empty(env('TELEGRAM_CHAT_ID'));
    }

    /**
     * Envoyer un message aux chats configurés dans .env
     */
    public function sendToConfiguredChats(string $message, ?string $parseMode = null): bool
    {
        $chatIds = env('TELEGRAM_CHAT_ID');
        
        if (!$chatIds) {
            Log::warning('TELEGRAM_CHAT_ID n\'est pas configuré dans .env');
            return false;
        }

        // Supporter plusieurs chat_ids séparés par des virgules
        $chatIdArray = array_map('trim', explode(',', $chatIds));

        return $this->sendMessage($chatIdArray, $message, $parseMode);
    }
}
