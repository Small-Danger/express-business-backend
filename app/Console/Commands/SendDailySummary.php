<?php

namespace App\Console\Commands;

use App\Models\Business\BusinessConvoy;
use App\Models\Business\BusinessOrder;
use App\Models\Express\ExpressParcel;
use App\Models\Express\ExpressTrip;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
            \Log::info('SendDailySummary: Démarrage de la commande');
            
            if (!$this->telegramService->isConfigured()) {
                $this->warn('Telegram n\'est pas configuré. Vérifiez TELEGRAM_BOT_TOKEN et TELEGRAM_CHAT_ID dans .env');
                \Log::warning('SendDailySummary: Telegram non configuré');
                return Command::FAILURE;
            }

            \Log::info('SendDailySummary: Telegram configuré correctement');
            
            $today = Carbon::today('Africa/Casablanca');
            $this->info("Génération du résumé pour {$today->format('d/m/Y')}...");
            \Log::info("SendDailySummary: Date du jour = {$today->format('Y-m-d')}");

            // Compter l'activité du jour
            \Log::info('SendDailySummary: Comptage des commandes');
            $newOrders = BusinessOrder::whereDate('created_at', $today->toDateString())->count();
            \Log::info("SendDailySummary: {$newOrders} nouvelles commandes");
            
            \Log::info('SendDailySummary: Comptage des colis');
            $newParcels = ExpressParcel::whereDate('created_at', $today->toDateString())->count();
            \Log::info("SendDailySummary: {$newParcels} nouveaux colis");
            
            \Log::info('SendDailySummary: Calcul des revenus');
            $totalRevenue = $this->calculateTodayRevenue($today);
            \Log::info("SendDailySummary: Revenus du jour = {$totalRevenue}");

            // Compter les dettes
            \Log::info('SendDailySummary: Comptage des dettes');
            $ordersWithDebt = BusinessOrder::where('has_debt', true)->count();
            \Log::info("SendDailySummary: {$ordersWithDebt} commandes avec dette");
            
            $totalDebt = BusinessOrder::where('has_debt', true)
                ->get()
                ->sum(function ($order) {
                    return ($order->total_amount ?? 0) - ($order->total_paid ?? 0);
                });
            \Log::info("SendDailySummary: Dette totale = {$totalDebt}");

            // Compter les trajets qui partent bientôt
            \Log::info('SendDailySummary: Comptage des trajets');
            $convoysDeparting = BusinessConvoy::where('status', 'planned')
                ->whereBetween('planned_departure_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
                ->count();
            \Log::info("SendDailySummary: {$convoysDeparting} convois Business qui partent bientôt");
            
            $tripsDeparting = ExpressTrip::where('status', 'planned')
                ->whereBetween('planned_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
                ->count();
            \Log::info("SendDailySummary: {$tripsDeparting} trajets Express qui partent bientôt");

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
                $message .= "   • Commandes en cours : " . BusinessOrder::where('status', '!=', 'cancelled')->count() . "\n";
                $message .= "   • Colis en transit : " . ExpressParcel::where('status', 'in_transit')->count() . "\n";
                
                if ($ordersWithDebt > 0) {
                    $message .= "   • Dettes totales : " . number_format($totalDebt, 0, ',', ' ') . " MAD ({$ordersWithDebt} commande(s))\n";
                }
                
                if ($convoysDeparting > 0 || $tripsDeparting > 0) {
                    $totalDeparting = $convoysDeparting + $tripsDeparting;
                    $message .= "   • Trajets qui partent bientôt : {$totalDeparting}\n";
                }
            }

            // Envoyer le message
            \Log::info('SendDailySummary: Envoi du message Telegram');
            \Log::debug('SendDailySummary: Message', ['message' => substr($message, 0, 200)]);
            
            if ($this->telegramService->sendToConfiguredChats($message)) {
                $this->info('✅ Résumé quotidien envoyé avec succès');
                \Log::info('SendDailySummary: Message envoyé avec succès');
                return Command::SUCCESS;
            }

            $this->error('❌ Erreur lors de l\'envoi du résumé');
            \Log::error('SendDailySummary: Erreur lors de l\'envoi du message');
            return Command::FAILURE;
        } catch (\Exception $e) {
            $errorMsg = '❌ Erreur lors de la génération du résumé : ' . $e->getMessage();
            $this->error($errorMsg);
            \Log::error('SendDailySummary: Exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $errorMsg = '❌ Erreur fatale lors de la génération du résumé : ' . $e->getMessage();
            $this->error($errorMsg);
            \Log::error('SendDailySummary: Throwable', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
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
            $ordersRevenue = BusinessOrder::whereDate('created_at', $today->toDateString())
                ->get()
                ->sum(function ($order) {
                    return $order->total_amount ?? 0;
                });

            // Revenus des colis Express créés aujourd'hui
            $parcelsRevenue = ExpressParcel::whereDate('created_at', $today->toDateString())
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

            return $ordersRevenue + $parcelsRevenue;
        } catch (\Exception $e) {
            \Log::error('Erreur calculateTodayRevenue', [
                'message' => $e->getMessage(),
            ]);
            return 0;
        }
    }
}
