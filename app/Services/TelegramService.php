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
        // Utiliser config() au lieu de env() pour supporter le cache de config
        $this->botToken = config('services.telegram.bot_token') ?? env('TELEGRAM_BOT_TOKEN');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}";
        
        if (empty($this->botToken)) {
            Log::warning('TELEGRAM_BOT_TOKEN n\'est pas configuré. Vérifiez config/services.php ou .env');
        }
    }

    /**
     * Envoyer un message à un chat Telegram
     * Gère automatiquement la limite de 4096 caractères en découpant le message
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

        // Telegram limite à 4096 caractères - découper si nécessaire
        $maxLength = 4096;
        $messages = $this->splitMessage($message, $maxLength);

        $allSuccess = true;
        foreach ($messages as $chunk) {
            if (!$this->sendSingleMessage($chatId, $chunk, $parseMode)) {
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
    private function sendSingleMessage($chatId, string $message, ?string $parseMode = null): bool
    {
        try {
            $url = "{$this->apiUrl}/sendMessage";
            
            Log::debug('TelegramService: Envoi message', [
                'url' => str_replace($this->botToken, 'TOKEN_MASKED', $url),
                'chat_id' => $chatId,
                'message_length' => mb_strlen($message),
                'has_token' => !empty($this->botToken),
            ]);
            
            $response = Http::timeout(10)->post($url, [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
            ]);

            if ($response->successful() && $response->json('ok')) {
                Log::debug('TelegramService: Message envoyé avec succès', [
                    'chat_id' => $chatId,
                ]);
                return true;
            }

            // Logger les erreurs Telegram avec plus de détails
            $errorResponse = $response->json();
            Log::error('Erreur Telegram API', [
                'response' => $errorResponse,
                'chat_id' => $chatId,
                'message_length' => mb_strlen($message),
                'status_code' => $response->status(),
                'error_code' => $errorResponse['error_code'] ?? null,
                'description' => $errorResponse['description'] ?? null,
                'url' => str_replace($this->botToken, 'TOKEN_MASKED', $url),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception lors de l\'envoi Telegram', [
                'message' => $e->getMessage(),
                'chat_id' => $chatId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
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
        $chatId = config('services.telegram.chat_id') ?? env('TELEGRAM_CHAT_ID');
        return !empty($this->botToken) && !empty($chatId);
    }

    /**
     * Envoyer un message aux chats configurés dans .env
     */
    public function sendToConfiguredChats(string $message, ?string $parseMode = null): bool
    {
        // Utiliser config() au lieu de env() pour supporter le cache de config
        $chatIds = config('services.telegram.chat_id') ?? env('TELEGRAM_CHAT_ID');
        
        if (!$chatIds) {
            Log::warning('TELEGRAM_CHAT_ID n\'est pas configuré. Vérifiez config/services.php ou .env');
            return false;
        }

        // Supporter plusieurs chat_ids séparés par des virgules
        $chatIdArray = array_map('trim', explode(',', $chatIds));

        return $this->sendMessage($chatIdArray, $message, $parseMode);
    }
}
