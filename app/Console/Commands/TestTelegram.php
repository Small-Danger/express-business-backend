<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TestTelegram extends Command
{
    protected $signature = 'telegram:test';
    protected $description = 'Tester l\'envoi d\'un message Telegram';

    public function handle(TelegramService $telegramService)
    {
        $message = '✅ Test ! Les alertes Telegram fonctionnent correctement. Vous recevrez désormais des notifications automatiques tous les jours à 8h00 et 18h00.';
        
        if ($telegramService->sendToConfiguredChats($message)) {
            $this->info('✅ Message envoyé avec succès ! Vérifiez votre Telegram.');
            return Command::SUCCESS;
        }
        
        $this->error('❌ Erreur lors de l\'envoi. Vérifiez vos variables TELEGRAM_BOT_TOKEN et TELEGRAM_CHAT_ID dans .env');
        return Command::FAILURE;
    }
}
