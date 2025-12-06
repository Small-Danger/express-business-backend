<?php

namespace App\Console\Commands;

use App\Models\Business\BusinessConvoy;
use App\Models\Business\BusinessOrder;
use App\Models\Express\ExpressTrip;
use App\Models\Express\ExpressParcel;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendDailyAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'alerts:daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoyer les alertes quotidiennes via Telegram';

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
        if (!$this->telegramService->isConfigured()) {
            $this->warn('Telegram n\'est pas configuré. Vérifiez TELEGRAM_BOT_TOKEN et TELEGRAM_CHAT_ID dans .env');
            return Command::FAILURE;
        }

        $this->info('Génération des alertes quotidiennes...');
        $messages = [];

        // 1. Vérifier les trajets qui partent bientôt (Business)
        $departureReminders = $this->checkUpcomingDepartures();
        if (!empty($departureReminders)) {
            $messages = array_merge($messages, $departureReminders);
        }

        // 2. Vérifier les trajets Express qui partent bientôt
        $expressDepartureReminders = $this->checkUpcomingExpressTrips();
        if (!empty($expressDepartureReminders)) {
            $messages = array_merge($messages, $expressDepartureReminders);
        }

        // 3. Vérifier les dettes impayées
        $debtAlerts = $this->checkUnpaidDebts();
        if (!empty($debtAlerts)) {
            $messages[] = $debtAlerts;
        }

        // 4. Vérifier les colis en attente de récupération
        $pendingParcels = $this->checkPendingParcels();
        if (!empty($pendingParcels)) {
            $messages[] = $pendingParcels;
        }

        // Envoyer tous les messages
        $sentCount = 0;
        foreach ($messages as $message) {
            if ($this->telegramService->sendToConfiguredChats($message)) {
                $sentCount++;
            }
        }

        // Si aucune alerte, envoyer un message de confirmation
        if ($sentCount === 0) {
            $today = Carbon::today('Africa/Casablanca');
            $noAlertsMessage = "✅ Aucune alerte pour aujourd'hui ({$today->format('d/m/Y')})\n\n";
            $noAlertsMessage .= "📊 Statut :\n";
            $noAlertsMessage .= "   • Aucun trajet qui part dans 1, 3 ou 7 jours\n";
            $noAlertsMessage .= "   • Aucune dette impayée\n";
            $noAlertsMessage .= "   • Aucun colis en attente de récupération\n\n";
            $noAlertsMessage .= "👋 Tout est sous contrôle !";
            
            if ($this->telegramService->sendToConfiguredChats($noAlertsMessage)) {
                $this->info('✅ Message de confirmation envoyé (aucune alerte)');
                $sentCount = 1;
            } else {
                $this->warn('⚠️ Aucune alerte à envoyer, mais erreur lors de l\'envoi du message de confirmation');
            }
        } else {
            $this->info("✅ {$sentCount} alerte(s) envoyée(s) avec succès");
        }

        return Command::SUCCESS;
    }

    /**
     * Vérifier les trajets Business qui partent bientôt
     */
    private function checkUpcomingDepartures(): array
    {
        $messages = [];
        $today = Carbon::today('Africa/Casablanca');
        
        // Vérifier les trajets qui partent dans 1, 3, ou 7 jours
        $reminderDays = [1, 3, 7];
        
        foreach ($reminderDays as $days) {
            $targetDate = $today->copy()->addDays($days);
            
            $convoys = BusinessConvoy::where('status', 'planned')
                ->whereDate('planned_departure_date', $targetDate)
                ->get();

            foreach ($convoys as $convoy) {
                $priority = $days === 1 ? '🚨 URGENT' : ($days === 3 ? '⚠️' : '🚚');
                $emoji = $days === 1 ? '🚨' : ($days === 3 ? '⚠️' : '🚚');
                
                $message = "{$priority} Rappel : Le trajet \"{$convoy->name}\" part dans {$days} jour(s)\n";
                $message .= "📅 Date de départ : {$convoy->planned_departure_date->format('d/m/Y')}\n";
                $message .= "📍 {$convoy->from_city}, {$convoy->from_country} → {$convoy->to_city}, {$convoy->to_country}\n";
                $message .= "👤 Voyageur : {$convoy->traveler_name}\n";
                
                if ($days === 1) {
                    $message .= "\n⚠️ ACTION REQUISE : Vérifier que toutes les commandes sont prêtes !";
                }
                
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Vérifier les trajets Express qui partent bientôt
     */
    private function checkUpcomingExpressTrips(): array
    {
        $messages = [];
        $today = Carbon::today('Africa/Casablanca');
        $reminderDays = [1, 3, 7];
        
        foreach ($reminderDays as $days) {
            $targetDate = $today->copy()->addDays($days);
            
            $trips = ExpressTrip::where('status', 'planned')
                ->whereDate('planned_date', $targetDate)
                ->get();

            foreach ($trips as $trip) {
                $priority = $days === 1 ? '🚨 URGENT' : ($days === 3 ? '⚠️' : '🚚');
                
                $message = "{$priority} Rappel : Le trajet Express \"{$trip->name}\" part dans {$days} jour(s)\n";
                $message .= "📅 Date prévue : {$trip->planned_date->format('d/m/Y')}\n";
                $message .= "📍 {$trip->from_city}, {$trip->from_country} → {$trip->to_city}, {$trip->to_country}\n";
                
                if ($trip->traveler_name) {
                    $message .= "👤 Voyageur : {$trip->traveler_name}\n";
                }
                
                if ($days === 1) {
                    $message .= "\n⚠️ ACTION REQUISE : Vérifier que tous les colis sont prêts !";
                }
                
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Vérifier les dettes impayées
     */
    private function checkUnpaidDebts(): ?string
    {
        $ordersWithDebt = BusinessOrder::where('has_debt', true)
            ->where('status', '!=', 'cancelled')
            ->with('client')
            ->get();

        if ($ordersWithDebt->isEmpty()) {
            return null;
        }

        $totalDebt = 0;
        $ordersList = [];
        
        foreach ($ordersWithDebt as $order) {
            $debt = $order->total_amount - $order->total_paid;
            $totalDebt += $debt;
            
            $clientName = $order->client ? $order->client->name : 'Client inconnu';
            $ordersList[] = "   • {$order->reference} - {$clientName} : " . number_format($debt, 0, ',', ' ') . " {$order->currency}";
        }

        $message = "💰 Alerte : " . $ordersWithDebt->count() . " commande(s) avec dette(s) impayée(s)\n\n";
        $message .= "📊 Total dû : " . number_format($totalDebt, 0, ',', ' ') . " MAD\n\n";
        $message .= "📋 Détails :\n" . implode("\n", array_slice($ordersList, 0, 5));
        
        if ($ordersWithDebt->count() > 5) {
            $message .= "\n   ... et " . ($ordersWithDebt->count() - 5) . " autre(s)";
        }

        return $message;
    }

    /**
     * Vérifier les colis en attente de récupération
     */
    private function checkPendingParcels(): ?string
    {
        $threeDaysAgo = Carbon::now('Africa/Casablanca')->subDays(3);
        
        $pendingParcels = ExpressParcel::where('status', 'ready_for_pickup')
            ->where('updated_at', '<=', $threeDaysAgo)
            ->with('client')
            ->get();

        if ($pendingParcels->isEmpty()) {
            return null;
        }

        $message = "📬 Rappel : " . $pendingParcels->count() . " colis prêt(s) pour récupération depuis plus de 3 jours\n\n";
        
        $parcelsList = [];
        foreach ($pendingParcels->take(5) as $parcel) {
            $clientName = $parcel->client ? $parcel->client->name : 'Client inconnu';
            $daysPending = $parcel->updated_at->diffInDays(Carbon::now('Africa/Casablanca'));
            $parcelsList[] = "   • {$parcel->reference} - {$clientName} (depuis {$daysPending} jour(s))";
        }
        
        $message .= "📋 Détails :\n" . implode("\n", $parcelsList);
        
        if ($pendingParcels->count() > 5) {
            $message .= "\n   ... et " . ($pendingParcels->count() - 5) . " autre(s)";
        }
        
        $message .= "\n\n👥 Action : Contacter les clients pour qu'ils viennent récupérer leurs colis";

        return $message;
    }
}
