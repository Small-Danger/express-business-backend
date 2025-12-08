<?php

namespace App\Console\Commands;

use App\Models\Business\BusinessConvoy;
use App\Models\Business\BusinessOrder;
use App\Models\Express\ExpressTrip;
use App\Models\Express\ExpressParcel;
use App\Models\Express\ExpressParcelStatusHistory;
use App\Models\Client;
use App\Models\Account;
use App\Models\FinancialTransaction;
use App\Models\User;
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

        // 5. Vérifier l'absence d'activité par rôle
        $noActivityAlerts = $this->checkNoActivityByRole();
        if (!empty($noActivityAlerts)) {
            foreach ($noActivityAlerts as $role => $alert) {
                if (!empty($alert['message'])) {
                    // Envoyer l'alerte au chat_id spécifique du rôle
                    $chatId = $alert['chat_id'];
                    if ($chatId && $this->telegramService->sendMessage($alert['message'], $chatId)) {
                        $this->info("✅ Alerte absence d'activité envoyée pour le rôle: {$role}");
                    }
                }
            }
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
            $noAlertsMessage .= "   • Aucun colis en attente de récupération\n";
            $noAlertsMessage .= "   • Activité normale détectée sur le site\n\n";
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

    /**
     * Vérifier l'absence d'activité par rôle
     * Vérifie l'activité de chaque rôle et envoie des alertes personnalisées
     * 
     * @return array Array avec les alertes par rôle ['role' => ['message' => ..., 'chat_id' => ...]]
     */
    private function checkNoActivityByRole(): array
    {
        $now = Carbon::now('Africa/Casablanca');
        $threshold = $now->copy()->subHours(24); // Alerte si aucune activité depuis 24h
        $roles = ['admin', 'boss', 'secretary', 'traveler'];
        $alerts = [];
        
        // Récupérer les chat_ids configurés par rôle
        $chatIdsByRole = config('services.telegram.chat_ids', []);
        $defaultChatId = config('services.telegram.chat_id') ?? env('TELEGRAM_CHAT_ID');
        
        foreach ($roles as $role) {
            // Récupérer le chat_id pour ce rôle (ou utiliser le default)
            $chatId = $chatIdsByRole[$role] ?? $defaultChatId;
            
            if (empty($chatId)) {
                continue; // Pas de chat_id configuré pour ce rôle, on skip
            }
            
            // Vérifier l'activité pour ce rôle spécifique
            $lastActivity = $this->getLastActivityByRole($role);
            
            if ($lastActivity === null || $lastActivity->lt($threshold)) {
                $hoursSinceActivity = $lastActivity 
                    ? $lastActivity->diffInHours($now)
                    : 'N/A';
                
                $daysSinceActivity = $lastActivity 
                    ? $lastActivity->diffInDays($now)
                    : 'N/A';
                
                $roleLabel = $this->getRoleLabel($role);
                
                $message = "⚠️ ALERTE : Aucune activité détectée pour le rôle **{$roleLabel}**\n\n";
                
                if ($lastActivity) {
                    $message .= "🕐 Dernière activité : " . $lastActivity->format('d/m/Y à H:i') . "\n";
                    $message .= "⏱️ Il y a {$hoursSinceActivity} heure(s) ({$daysSinceActivity} jour(s))\n\n";
                } else {
                    $message .= "❌ Aucune activité enregistrée pour ce rôle\n\n";
                }
                
                // Message personnalisé selon le rôle
                switch ($role) {
                    case 'admin':
                        $message .= "📋 Dernières actions vérifiées :\n";
                        $message .= "   • Gestion des utilisateurs\n";
                        $message .= "   • Modifications système\n";
                        $message .= "   • Toutes les activités du site\n\n";
                        break;
                    case 'boss':
                        $message .= "📋 Dernières actions vérifiées :\n";
                        $message .= "   • Commandes Business\n";
                        $message .= "   • Colis Express\n";
                        $message .= "   • Transactions financières\n";
                        $message .= "   • Gestion des comptes\n\n";
                        break;
                    case 'secretary':
                        $message .= "📋 Dernières actions vérifiées :\n";
                        $message .= "   • Création de commandes\n";
                        $message .= "   • Création de colis\n";
                        $message .= "   • Gestion des clients\n";
                        $message .= "   • Saisie de paiements\n\n";
                        break;
                    case 'traveler':
                        $message .= "📋 Dernières actions vérifiées :\n";
                        $message .= "   • Mise à jour des statuts de colis\n";
                        $message .= "   • Confirmation de réception\n";
                        $message .= "   • Mise à jour des trajets\n\n";
                        break;
                }
                
                $message .= "💡 Action suggérée : Vérifier que les utilisateurs de ce rôle peuvent accéder au site et effectuer leurs tâches.";
                
                $alerts[$role] = [
                    'message' => $message,
                    'chat_id' => $chatId,
                ];
            }
        }
        
        return $alerts;
    }
    
    /**
     * Obtenir la dernière activité pour un rôle spécifique
     */
    private function getLastActivityByRole(string $role): ?Carbon
    {
        $lastActivity = null;
        
        // Vérifier selon le rôle
        switch ($role) {
            case 'admin':
                // Admin peut faire toutes les actions, vérifier toutes les tables
                $activities = [];
                
                // Vérifier les utilisateurs créés/modifiés par admin
                $lastUserActivity = \App\Models\User::orderBy('updated_at', 'desc')->first();
                if ($lastUserActivity) {
                    $activities[] = Carbon::parse($lastUserActivity->updated_at)->setTimezone('Africa/Casablanca');
                }
                
                // Vérifier les commandes (peut être créées par admin)
                $lastOrder = \App\Models\Business\BusinessOrder::orderBy('updated_at', 'desc')->first();
                if ($lastOrder) {
                    $activities[] = Carbon::parse($lastOrder->updated_at)->setTimezone('Africa/Casablanca');
                }
                
                // Prendre la plus récente
                foreach ($activities as $activity) {
                    if ($lastActivity === null || $activity->gt($lastActivity)) {
                        $lastActivity = $activity;
                    }
                }
                break;
                
            case 'boss':
                // Boss gère les finances et la trésorerie
                $activities = [];
                
                // Transactions financières
                $lastTransaction = \App\Models\FinancialTransaction::orderBy('created_at', 'desc')->first();
                if ($lastTransaction) {
                    $activities[] = Carbon::parse($lastTransaction->created_at)->setTimezone('Africa/Casablanca');
                }
                
                // Comptes
                $lastAccount = \App\Models\Account::orderBy('updated_at', 'desc')->first();
                if ($lastAccount) {
                    $activities[] = Carbon::parse($lastAccount->updated_at)->setTimezone('Africa/Casablanca');
                }
                
                // Commandes Business (boss peut les gérer)
                $lastOrder = \App\Models\Business\BusinessOrder::orderBy('updated_at', 'desc')->first();
                if ($lastOrder) {
                    $activities[] = Carbon::parse($lastOrder->updated_at)->setTimezone('Africa/Casablanca');
                }
                
                foreach ($activities as $activity) {
                    if ($lastActivity === null || $activity->gt($lastActivity)) {
                        $lastActivity = $activity;
                    }
                }
                break;
                
            case 'secretary':
                // Secrétaire crée commandes, colis, clients
                $activities = [];
                
                // Commandes Business créées par secrétaire
                $lastOrder = \App\Models\Business\BusinessOrder::orderBy('created_at', 'desc')->first();
                if ($lastOrder) {
                    $activities[] = Carbon::parse($lastOrder->created_at)->setTimezone('Africa/Casablanca');
                }
                
                // Colis Express créés par secrétaire
                $lastParcel = \App\Models\Express\ExpressParcel::orderBy('created_at', 'desc')->first();
                if ($lastParcel) {
                    $activities[] = Carbon::parse($lastParcel->created_at)->setTimezone('Africa/Casablanca');
                }
                
                // Clients créés
                $lastClient = \App\Models\Client::orderBy('created_at', 'desc')->first();
                if ($lastClient) {
                    $activities[] = Carbon::parse($lastClient->created_at)->setTimezone('Africa/Casablanca');
                }
                
                foreach ($activities as $activity) {
                    if ($lastActivity === null || $activity->gt($lastActivity)) {
                        $lastActivity = $activity;
                    }
                }
                break;
                
            case 'traveler':
                // Voyageur met à jour les statuts des colis et trajets
                $activities = [];
                
                // Statuts de colis mis à jour
                $lastParcel = \App\Models\Express\ExpressParcel::orderBy('updated_at', 'desc')->first();
                if ($lastParcel) {
                    $activities[] = Carbon::parse($lastParcel->updated_at)->setTimezone('Africa/Casablanca');
                }
                
                // Historique des statuts
                $lastStatusHistory = \App\Models\Express\ExpressParcelStatusHistory::orderBy('created_at', 'desc')->first();
                if ($lastStatusHistory) {
                    $activities[] = Carbon::parse($lastStatusHistory->created_at)->setTimezone('Africa/Casablanca');
                }
                
                // Trajets mis à jour
                $lastTrip = \App\Models\Express\ExpressTrip::orderBy('updated_at', 'desc')->first();
                if ($lastTrip) {
                    $activities[] = Carbon::parse($lastTrip->updated_at)->setTimezone('Africa/Casablanca');
                }
                
                foreach ($activities as $activity) {
                    if ($lastActivity === null || $activity->gt($lastActivity)) {
                        $lastActivity = $activity;
                    }
                }
                break;
        }
        
        return $lastActivity;
    }
    
    /**
     * Obtenir le label d'un rôle
     */
    private function getRoleLabel(string $role): string
    {
        $labels = [
            'admin' => 'Administrateur',
            'boss' => 'Directeur',
            'secretary' => 'Secrétaire',
            'traveler' => 'Voyageur',
        ];
        
        return $labels[$role] ?? ucfirst($role);
    }
}
