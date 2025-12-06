<?php

namespace App\Console\Commands;

use App\Models\Business\BusinessConvoy;
use App\Models\Business\BusinessOrder;
use App\Models\Express\ExpressParcel;
use App\Models\Express\ExpressTrip;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailySummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:daily-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoyer le résumé quotidien de l\'activité via Telegram';

    protected $telegramService;

    public function __construct(TelegramService $telegramService)
    {
        parent::__construct();
        $this->telegramService = $telegramService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            Log::info('SendDailySummary: Démarrage de la commande');
            
            // Écrire aussi dans un fichier pour déboguer
            file_put_contents(storage_path('logs/daily-summary-debug.log'), date('Y-m-d H:i:s') . " - Démarrage\n", FILE_APPEND);
            
            // Vérifier la connexion à la base de données avec retry
            // Si on est en phase de build, la DB n'est pas disponible - on skip silencieusement
            $dbConnected = false;
            for ($i = 0; $i < 3; $i++) {
                try {
                    \DB::connection()->getPdo();
                    $dbConnected = true;
                    Log::info('SendDailySummary: Connexion DB OK');
                    break;
                } catch (\Exception $e) {
                    // Vérifier si c'est une erreur de résolution DNS (typique pendant le build)
                    $errorMessage = $e->getMessage();
                    if (str_contains($errorMessage, 'could not translate host name') || 
                        str_contains($errorMessage, 'Name or service not known')) {
                        
                        // Si on est probablement en phase de build, on skip silencieusement
                        // pour ne pas faire échouer le build
                        if ($i === 0) {
                            $this->info('⏭️  Base de données non disponible (probablement pendant le build), skip de la commande');
                            Log::info('SendDailySummary: DB non disponible, skip (probable build phase)');
                            return Command::SUCCESS;
                        }
                    }
                    
                    if ($i < 2) {
                        sleep(2);
                        try {
                            \DB::reconnect();
                        } catch (\Exception $reconnectException) {
                            // Ignorer les erreurs de reconnexion
                        }
                        continue;
                    }
                    // Si après 3 tentatives, la DB n'est toujours pas accessible
                    $errorMsg = '❌ Impossible de se connecter à la base de données après 3 tentatives : ' . $e->getMessage();
                    $this->error($errorMsg);
                    Log::error('SendDailySummary: Erreur connexion DB', [
                        'message' => $e->getMessage(),
                        'host' => config('database.connections.pgsql.host'),
                    ]);
                    file_put_contents(storage_path('logs/daily-summary-debug.log'), 
                        date('Y-m-d H:i:s') . " - ERREUR DB: " . $e->getMessage() . "\n", 
                        FILE_APPEND);
                    return Command::FAILURE;
                }
            }
            
            if (!$this->telegramService->isConfigured()) {
                $this->warn('Telegram n\'est pas configuré. Vérifiez TELEGRAM_BOT_TOKEN et TELEGRAM_CHAT_ID dans .env');
                Log::warning('SendDailySummary: Telegram non configuré');
                return Command::FAILURE;
            }

            Log::info('SendDailySummary: Telegram configuré correctement');
            file_put_contents(storage_path('logs/daily-summary-debug.log'), date('Y-m-d H:i:s') . " - Telegram configuré\n", FILE_APPEND);
            
            $today = Carbon::today('Africa/Casablanca');
            $this->info("Génération du résumé pour {$today->format('d/m/Y')}...");
            Log::info("SendDailySummary: Date du jour = {$today->format('Y-m-d')}");
            file_put_contents(storage_path('logs/daily-summary-debug.log'), date('Y-m-d H:i:s') . " - Date: {$today->format('Y-m-d')}\n", FILE_APPEND);

            // Compter l'activité du jour avec retry en cas d'erreur de connexion
            Log::info('SendDailySummary: Comptage des commandes');
            $newOrders = $this->retryDbQuery(function() use ($today) {
                return BusinessOrder::whereDate('created_at', $today->toDateString())->count();
            });
            Log::info("SendDailySummary: {$newOrders} nouvelles commandes");
            file_put_contents(storage_path('logs/daily-summary-debug.log'), date('Y-m-d H:i:s') . " - Commandes: {$newOrders}\n", FILE_APPEND);
            
            Log::info('SendDailySummary: Comptage des colis');
            $newParcels = $this->retryDbQuery(function() use ($today) {
                return ExpressParcel::whereDate('created_at', $today->toDateString())->count();
            });
            Log::info("SendDailySummary: {$newParcels} nouveaux colis");
            file_put_contents(storage_path('logs/daily-summary-debug.log'), date('Y-m-d H:i:s') . " - Colis: {$newParcels}\n", FILE_APPEND);
            
            Log::info('SendDailySummary: Calcul des revenus');
            $totalRevenue = $this->calculateTodayRevenue($today);
            Log::info("SendDailySummary: Revenus du jour = {$totalRevenue}");
            file_put_contents(storage_path('logs/daily-summary-debug.log'), date('Y-m-d H:i:s') . " - Revenus: {$totalRevenue}\n", FILE_APPEND);

            // Compter les dettes
            Log::info('SendDailySummary: Comptage des dettes');
            $ordersWithDebt = $this->retryDbQuery(function() {
                return BusinessOrder::where('has_debt', true)->count();
            });
            Log::info("SendDailySummary: {$ordersWithDebt} commandes avec dette");
            
            $totalDebt = $this->retryDbQuery(function() {
                return BusinessOrder::where('has_debt', true)
                    ->get()
                    ->sum(function ($order) {
                        return ($order->total_amount ?? 0) - ($order->total_paid ?? 0);
                    });
            });
            Log::info("SendDailySummary: Dette totale = {$totalDebt}");

            // Compter les trajets qui partent bientôt
            Log::info('SendDailySummary: Comptage des trajets');
            $convoysDeparting = $this->retryDbQuery(function() use ($today) {
                return BusinessConvoy::where('status', 'planned')
                    ->whereBetween('planned_departure_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
                    ->count();
            });
            Log::info("SendDailySummary: {$convoysDeparting} convois Business qui partent bientôt");
            
            $tripsDeparting = $this->retryDbQuery(function() use ($today) {
                return ExpressTrip::where('status', 'planned')
                    ->whereBetween('planned_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
                    ->count();
            });
            Log::info("SendDailySummary: {$tripsDeparting} trajets Express qui partent bientôt");

            // Générer le message
            if ($newOrders === 0 && $newParcels === 0 && $totalRevenue === 0) {
                // Absence d'activité
                $message = "👋 Bonjour ! Aucune activité enregistrée aujourd'hui ({$today->format('d/m/Y')})\n\n";
                
                if ($ordersWithDebt > 0 || $convoysDeparting > 0 || $tripsDeparting > 0) {
                    $message .= "💡 Rappels :\n";
                    
                    if ($convoysDeparting > 0 || $tripsDeparting > 0) {
                        $totalDeparting = $convoysDeparting + $tripsDeparting;
                        $message .= "   • {$totalDeparting} trajet(s) part(ent) bientôt\n";
                    }
                    
                    if ($ordersWithDebt > 0) {
                        $message .= "   • {$ordersWithDebt} commande(s) avec dette(s) impayée(s) (" . number_format($totalDebt, 0, ',', ' ') . " MAD)\n";
                    }
                }
                
                $message .= "\n📊 Aujourd'hui : 0 commande, 0 colis, 0 revenu";
            } else {
                // Résumé avec activité
                $message = "📅 Résumé de la journée ({$today->format('d/m/Y')})\n\n";
                $message .= "✅ Nouveau aujourd'hui :\n";
                $message .= "   • {$newOrders} nouvelle(s) commande(s)\n";
                $message .= "   • {$newParcels} nouveau(x) colis\n";
                $message .= "   • " . number_format($totalRevenue, 0, ',', ' ') . " MAD de revenus\n\n";
                
                $message .= "📊 Statistiques :\n";
                $message .= "   • Commandes en cours : " . $this->retryDbQuery(function() {
                    return BusinessOrder::where('status', '!=', 'cancelled')->count();
                }) . "\n";
                $message .= "   • Colis en transit : " . $this->retryDbQuery(function() {
                    return ExpressParcel::where('status', 'in_transit')->count();
                }) . "\n";
                
                if ($ordersWithDebt > 0) {
                    $message .= "   • Dettes totales : " . number_format($totalDebt, 0, ',', ' ') . " MAD ({$ordersWithDebt} commande(s))\n";
                }
                
                if ($convoysDeparting > 0 || $tripsDeparting > 0) {
                    $totalDeparting = $convoysDeparting + $tripsDeparting;
                    $message .= "   • Trajets qui partent bientôt : {$totalDeparting}\n";
                }
            }

            // Vérifier la longueur du message avant envoi
            $messageLength = mb_strlen($message);
            Log::info("SendDailySummary: Longueur du message = {$messageLength} caractères");
            file_put_contents(storage_path('logs/daily-summary-debug.log'), 
                date('Y-m-d H:i:s') . " - Longueur message: {$messageLength} caractères\n", 
                FILE_APPEND);

            // Envoyer le message
            Log::info('SendDailySummary: Envoi du message Telegram');
            Log::debug('SendDailySummary: Message', ['message' => substr($message, 0, 200)]);
            file_put_contents(storage_path('logs/daily-summary-debug.log'), date('Y-m-d H:i:s') . " - Envoi du message\n", FILE_APPEND);
            
            $sendResult = $this->telegramService->sendToConfiguredChats($message);
            
            if ($sendResult) {
                $this->info('✅ Résumé quotidien envoyé avec succès');
                Log::info('SendDailySummary: Message envoyé avec succès');
                file_put_contents(storage_path('logs/daily-summary-debug.log'), date('Y-m-d H:i:s') . " - SUCCÈS\n", FILE_APPEND);
                return Command::SUCCESS;
            }

            $this->error('❌ Erreur lors de l\'envoi du résumé. Vérifiez les logs Laravel pour plus de détails.');
            Log::error('SendDailySummary: Erreur lors de l\'envoi du message', [
                'message_length' => $messageLength,
                'check_logs' => 'Vérifiez storage/logs/laravel.log pour les détails Telegram',
            ]);
            file_put_contents(storage_path('logs/daily-summary-debug.log'), 
                date('Y-m-d H:i:s') . " - ERREUR: Échec envoi Telegram (longueur: {$messageLength})\n", 
                FILE_APPEND);
            return Command::FAILURE;
        } catch (\Exception $e) {
            $errorMsg = '❌ Erreur lors de la génération du résumé : ' . $e->getMessage();
            $this->error($errorMsg);
            Log::error('SendDailySummary: Exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            file_put_contents(storage_path('logs/daily-summary-debug.log'), 
                date('Y-m-d H:i:s') . " - EXCEPTION: " . $e->getMessage() . "\nFichier: " . $e->getFile() . " Ligne: " . $e->getLine() . "\n", 
                FILE_APPEND);
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $errorMsg = '❌ Erreur fatale lors de la génération du résumé : ' . $e->getMessage();
            $this->error($errorMsg);
            Log::error('SendDailySummary: Throwable', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Écrire l'erreur dans un fichier
            file_put_contents(storage_path('logs/daily-summary-debug.log'), 
                date('Y-m-d H:i:s') . " - ERREUR: " . $e->getMessage() . "\nFichier: " . $e->getFile() . " Ligne: " . $e->getLine() . "\n", 
                FILE_APPEND);
            return Command::FAILURE;
        }
    }

    /**
     * Calculer les revenus du jour
     */
    private function calculateTodayRevenue($today): float
    {
        try {
            // Revenus des commandes Business créées aujourd'hui
            $ordersRevenue = $this->retryDbQuery(function() use ($today) {
                return BusinessOrder::whereDate('created_at', $today->toDateString())
                    ->get()
                    ->sum(function ($order) {
                        return $order->total_amount ?? 0;
                    });
            });

            // Revenus des colis Express créés aujourd'hui
            $parcelsRevenue = $this->retryDbQuery(function() use ($today) {
                return ExpressParcel::whereDate('created_at', $today->toDateString())
                    ->get()
                    ->sum(function ($parcel) {
                        if (($parcel->price_mad ?? 0) > 0) {
                            return $parcel->price_mad;
                        }
                        if (($parcel->price_cfa ?? 0) > 0) {
                            return $parcel->price_cfa / 63; // Conversion simple
                        }
                        return 0;
                    });
            });

            return $ordersRevenue + $parcelsRevenue;
        } catch (\Exception $e) {
            Log::error('Erreur calculateTodayRevenue', [
                'message' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Réessayer une requête DB en cas d'erreur de connexion
     * Utile pour gérer les problèmes de résolution DNS temporaires
     */
    private function retryDbQuery(callable $callback, int $maxRetries = 3, int $delaySeconds = 2)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxRetries) {
            try {
                return $callback();
            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;
                
                // Vérifier si c'est une erreur de connexion
                $errorMessage = $e->getMessage();
                if (
                    str_contains($errorMessage, 'could not translate host name') ||
                    str_contains($errorMessage, 'Connection refused') ||
                    str_contains($errorMessage, 'Name or service not known')
                ) {
                    if ($attempt < $maxRetries) {
                        Log::warning("SendDailySummary: Erreur de connexion DB (tentative {$attempt}/{$maxRetries}), retry dans {$delaySeconds}s", [
                            'error' => $errorMessage,
                        ]);
                        sleep($delaySeconds);
                        // Réessayer la connexion
                        try {
                            \DB::reconnect();
                        } catch (\Exception $reconnectException) {
                            Log::warning("SendDailySummary: Échec reconnexion DB", [
                                'error' => $reconnectException->getMessage(),
                            ]);
                        }
                        continue;
                    }
                }
                
                // Si ce n'est pas une erreur de connexion ou qu'on a épuisé les tentatives, throw
                throw $e;
            }
        }

        // Si on arrive ici, toutes les tentatives ont échoué
        throw $lastException ?? new \Exception('Erreur inconnue lors de la requête DB');
    }
}
