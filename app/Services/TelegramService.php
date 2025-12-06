<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $botToken;
    private string $chatId;

    public function __construct()
    {
        // Toujours utiliser env() directement pour Railway (config() peut ne pas être rechargé dans les jobs)
        $this->botToken = env('TELEGRAM_BOT_TOKEN', '');
        $this->chatId = env('TELEGRAM_CHAT_ID', '');

        if (empty($this->botToken) || empty($this->chatId)) {
            Log::warning('TelegramService : token ou chat_id manquant.', [
                'has_token' => !empty($this->botToken),
                'has_chat_id' => !empty($this->chatId),
            ]);
        }
    }

    /**
     * URL de l'API Telegram
     */
    private function apiUrl(string $method): string
    {
        return "https://api.telegram.org/bot{$this->botToken}/{$method}";
    }

    /**
     * Envoyer un message à un chat Telegram
     * Gère automatiquement la limite de 4096 caractères en découpant le message
     *
     * @param string $message Message à envoyer
     * @param string|null $chatId ID du chat (optionnel, utilise celui de .env si non fourni)
     * @param string $parseMode Mode de parsing (HTML par défaut pour éviter les problèmes)
     * @return bool
     */
    public function sendMessage(string $message, ?string $chatId = null, string $parseMode = 'HTML'): bool
    {
        $targetChatId = $chatId ?? $this->chatId;

        if (empty($this->botToken) || empty($targetChatId)) {
            Log::warning('TelegramService : impossible d\'envoyer le message, config manquante.', [
                'has_token' => !empty($this->botToken),
                'has_chat_id' => !empty($targetChatId),
            ]);
            return false;
        }

        // Telegram limite à 4096 caractères - découper si nécessaire
        $maxLength = 4096;
        $messages = $this->splitMessage($message, $maxLength);

        $allSuccess = true;
        foreach ($messages as $chunk) {
            if (!$this->sendSingleMessage($targetChatId, $chunk, $parseMode)) {
                $allSuccess = false;
            }
            // Petite pause entre les messages pour éviter les rate limits
            if (count($messages) > 1) {
                usleep(500000); // 0.5 seconde
            }
        }

        return $allSuccess;
    }

    /**
     * Découper un message long en plusieurs morceaux
     */
    private function splitMessage(string $message, int $maxLength): array
    {
        if (mb_strlen($message) <= $maxLength) {
            return [$message];
        }

        $chunks = [];
        $lines = explode("\n", $message);
        $currentChunk = '';

        foreach ($lines as $line) {
            // Si une seule ligne dépasse la limite, la tronquer
            if (mb_strlen($line) > $maxLength) {
                if (!empty($currentChunk)) {
                    $chunks[] = $currentChunk;
                    $currentChunk = '';
                }
                // Tronquer la ligne trop longue
                $chunks[] = mb_substr($line, 0, $maxLength - 10) . '...';
                continue;
            }

            // Vérifier si on peut ajouter cette ligne au chunk actuel
            if (mb_strlen($currentChunk . $line . "\n") <= $maxLength) {
                $currentChunk .= $line . "\n";
            } else {
                // Le chunk actuel est plein, le sauvegarder et commencer un nouveau
                if (!empty($currentChunk)) {
                    $chunks[] = rtrim($currentChunk);
                    $currentChunk = '';
                }
                $currentChunk = $line . "\n";
            }
        }

        // Ajouter le dernier chunk s'il n'est pas vide
        if (!empty($currentChunk)) {
            $chunks[] = rtrim($currentChunk);
        }

        return $chunks;
    }

    /**
     * Envoyer un seul message (utilisé en interne)
     */
    private function sendSingleMessage(string $chatId, string $message, string $parseMode): bool
    {
        try {
            $response = Http::timeout(10)->post($this->apiUrl('sendMessage'), [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
            ]);

            if ($response->successful() && $response->json('ok')) {
                Log::debug('TelegramService : message envoyé avec succès', [
                    'chat_id' => $chatId,
                    'message_length' => mb_strlen($message),
                ]);
                return true;
            }

            // Logger les erreurs Telegram
            $errorResponse = $response->json();
            Log::error('TelegramService : erreur Telegram', [
                'response' => $errorResponse,
                'status' => $response->status(),
                'chat_id' => $chatId,
                'message_length' => mb_strlen($message),
                'error_code' => $errorResponse['error_code'] ?? null,
                'description' => $errorResponse['description'] ?? null,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('TelegramService Exception', [
                'message' => $e->getMessage(),
                'chat_id' => $chatId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return false;
        }
    }

    /**
     * Envoyer un message aux chats configurés dans .env
     * Supporte plusieurs chat_ids séparés par des virgules
     */
    public function sendToConfiguredChats(string $message, string $parseMode = 'HTML'): bool
    {
        if (empty($this->chatId)) {
            Log::warning('TELEGRAM_CHAT_ID n\'est pas configuré.');
            return false;
        }

        // Supporter plusieurs chat_ids séparés par des virgules
        $chatIds = array_map('trim', explode(',', $this->chatId));

        $allSuccess = true;
        foreach ($chatIds as $chatId) {
            if (!$this->sendMessage($message, $chatId, $parseMode)) {
                $allSuccess = false;
            }
        }

        return $allSuccess;
    }

    /**
     * Vérifier si le bot est configuré
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken) && !empty($this->chatId);
    }

    /**
     * Envoyer un message formaté avec HTML (méthode de compatibilité)
     */
    public function sendHtmlMessage(string $message, ?string $chatId = null): bool
    {
        return $this->sendMessage($message, $chatId, 'HTML');
    }
}
