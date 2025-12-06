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
            if (!$this->telegramService->isConfigured()) {
                $this->warn('Telegram n\'est pas configuré. Vérifiez TELEGRAM_BOT_TOKEN et TELEGRAM_CHAT_ID dans .env');
                return Command::FAILURE;
            }

            $today = Carbon::today('Africa/Casablanca');
            $this->info("Génération du résumé pour {$today->format('d/m/Y')}...");

            // Compter l'activité du jour
            $newOrders = BusinessOrder::whereDate('created_at', $today->toDateString())->count();
            $newParcels = ExpressParcel::whereDate('created_at', $today->toDateString())->count();
            $totalRevenue = $this->calculateTodayRevenue($today);

            // Compter les dettes
            $ordersWithDebt = BusinessOrder::where('has_debt', true)->count();
            $totalDebt = BusinessOrder::where('has_debt', true)
                ->get()
                ->sum(function ($order) {
                    return ($order->total_amount ?? 0) - ($order->total_paid ?? 0);
                });

            // Compter les trajets qui partent bientôt
            $convoysDeparting = BusinessConvoy::where('status', 'planned')
                ->whereBetween('planned_departure_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
                ->count();
            
            $tripsDeparting = ExpressTrip::where('status', 'planned')
                ->whereBetween('planned_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()])
                ->count();

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
            if ($this->telegramService->sendToConfiguredChats($message)) {
                $this->info('✅ Résumé quotidien envoyé avec succès');
                return Command::SUCCESS;
            }

            $this->error('❌ Erreur lors de l\'envoi du résumé');
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error('❌ Erreur lors de la génération du résumé : ' . $e->getMessage());
            \Log::error('Erreur SendDailySummary', [
                'message' => $e->getMessage(),
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
