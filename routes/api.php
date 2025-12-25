<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Business\AnalyticsController;
use App\Http\Controllers\Business\BusinessConvoyController;
use App\Http\Controllers\Business\BusinessOrderController;
use App\Http\Controllers\Business\BusinessWaveController;
use App\Http\Controllers\Business\ClientController;
use App\Http\Controllers\Business\InvoiceController;
use App\Http\Controllers\Business\ReceiptController as BusinessReceiptController;
use App\Http\Controllers\Business\PaymentController;
use App\Http\Controllers\Express\ParcelPaymentController;
use App\Http\Controllers\Business\ProductController;
use App\Http\Controllers\Express\DeliveryController;
use App\Http\Controllers\Express\ExpressParcelController;
use App\Http\Controllers\Express\ExpressTripController;
use App\Http\Controllers\Express\ExpressWaveController;
use App\Http\Controllers\Express\ExpressWaveCostController;
use App\Http\Controllers\Express\ExpressTripCostController;
use App\Http\Controllers\Express\ReceiptController as ExpressReceiptController;
use App\Http\Controllers\Express\TaskController;
use App\Http\Controllers\Business\BusinessOrderItemController;
use App\Http\Controllers\Business\BusinessWaveCostController;
use App\Http\Controllers\Business\BusinessConvoyCostController;
use App\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

// Routes publiques (authentification)
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées (nécessitent authentification)
Route::middleware('auth:sanctum')->group(function () {
    // Authentification
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Dashboard (toutes les données en une seule requête)
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index']);

    // Gestion des utilisateurs (Admin uniquement)
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
    });

    // Journal d'activité (Admin uniquement)
    Route::middleware('role:admin')->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/activity-logs/stats', [ActivityLogController::class, 'stats']);
        Route::get('/activity-logs/{id}', [ActivityLogController::class, 'show']);
    });

    // Taux de change et devises (accessibles à tous les utilisateurs authentifiés)
    Route::get('/exchange-rate', [SystemSettingController::class, 'getExchangeRate']);
    Route::get('/secondary-currencies', [SystemSettingController::class, 'getSecondaryCurrencies']);

    // Liste des comptes (accessible à tous pour les sélections dans les formulaires)
    Route::get('/accounts', [\App\Http\Controllers\AccountController::class, 'index']);

    // Trésorerie (Admin et Boss uniquement)
    Route::middleware('role:admin,boss')->group(function () {
        // Routes de modification des comptes
        Route::post('/accounts', [\App\Http\Controllers\AccountController::class, 'store']);
        Route::put('/accounts/{id}', [\App\Http\Controllers\AccountController::class, 'update']);
        Route::delete('/accounts/{id}', [\App\Http\Controllers\AccountController::class, 'destroy']);
        Route::get('/accounts/{id}', [\App\Http\Controllers\AccountController::class, 'show']);
        Route::get('/accounts/{id}/balance', [\App\Http\Controllers\AccountController::class, 'getBalance']);
        Route::get('/accounts/{id}/transactions', [\App\Http\Controllers\AccountController::class, 'getTransactions']);
        
        // Routes spécifiques avant les routes avec paramètres
        Route::get('/financial-transactions/summary', [\App\Http\Controllers\FinancialTransactionController::class, 'summary']);
        Route::post('/financial-transactions/transfer', [\App\Http\Controllers\FinancialTransactionController::class, 'transfer']);
        Route::get('/financial-transactions', [\App\Http\Controllers\FinancialTransactionController::class, 'index']);
        Route::get('/financial-transactions/{id}', [\App\Http\Controllers\FinancialTransactionController::class, 'show']);
    });

    // Paramètres système (Admin uniquement)
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('system-settings', SystemSettingController::class);
        Route::get('/system-settings/key/{key}', [SystemSettingController::class, 'getByKey']);
        Route::put('/system-settings/key/{key}', [SystemSettingController::class, 'updateByKey']);
    });

    // Module Business
    Route::prefix('business')->group(function () {
        // Clients (tous les utilisateurs authentifiés)
        Route::apiResource('clients', ClientController::class);

        // Produits (tous les utilisateurs authentifiés)
        Route::apiResource('products', ProductController::class);

        // Vagues Business (tous les utilisateurs authentifiés)
        Route::apiResource('waves', BusinessWaveController::class)->names([
            'index' => 'business.waves.index',
            'show' => 'business.waves.show',
            'store' => 'business.waves.store',
            'update' => 'business.waves.update',
            'destroy' => 'business.waves.destroy',
        ]);

        // Convois Business (tous les utilisateurs authentifiés)
        Route::apiResource('convoys', BusinessConvoyController::class);
        Route::post('/convoys/{id}/close', [BusinessConvoyController::class, 'close']);

        // Commandes Business (tous les utilisateurs authentifiés)
        Route::apiResource('orders', BusinessOrderController::class);

        // Factures Business (tous les utilisateurs authentifiés)
        Route::get('/invoices/{id}/generate', [InvoiceController::class, 'generate']);
        Route::get('/invoices/{id}/preview', [InvoiceController::class, 'preview']);

        // Reçus Business (tous les utilisateurs authentifiés)
        Route::get('/receipts/{id}/generate', [BusinessReceiptController::class, 'generate']);
        Route::get('/receipts/{id}/preview', [BusinessReceiptController::class, 'preview']);

        // Paiements Business (tous les utilisateurs authentifiés)
        Route::get('/orders/{id}/payments', [PaymentController::class, 'index']);
        Route::post('/orders/{id}/payments', [PaymentController::class, 'store']);

        // Lignes de commande Business (tous les utilisateurs authentifiés)
        Route::apiResource('order-items', BusinessOrderItemController::class);

        // Frais des convois Business (Boss et Admin uniquement)
        Route::middleware('role:boss,admin')->group(function () {
            Route::apiResource('convoy-costs', BusinessConvoyCostController::class)->names([
                'index' => 'business.convoy-costs.index',
                'show' => 'business.convoy-costs.show',
                'store' => 'business.convoy-costs.store',
                'update' => 'business.convoy-costs.update',
                'destroy' => 'business.convoy-costs.destroy',
            ]);
            Route::apiResource('wave-costs', BusinessWaveCostController::class)->names([
                'index' => 'business.wave-costs.index',
                'show' => 'business.wave-costs.show',
                'store' => 'business.wave-costs.store',
                'update' => 'business.wave-costs.update',
                'destroy' => 'business.wave-costs.destroy',
            ]);
        });

        // Analytics (Boss et Admin uniquement - pour voir les marges/bénéfices)
        Route::middleware('role:boss,admin')->group(function () {
            Route::get('/analytics/dashboard', [AnalyticsController::class, 'dashboard']);
            Route::get('/analytics/wave/{waveId}', [AnalyticsController::class, 'waveStats']);
            Route::get('/analytics/client/{clientId}', [AnalyticsController::class, 'clientStats']);
        });
    });

    // Module Express
    Route::prefix('express')->group(function () {
        // Vagues Express (tous les utilisateurs authentifiés)
        Route::apiResource('waves', ExpressWaveController::class)->names([
            'index' => 'express.waves.index',
            'show' => 'express.waves.show',
            'store' => 'express.waves.store',
            'update' => 'express.waves.update',
            'destroy' => 'express.waves.destroy',
        ]);

        // Trajets Express (tous les utilisateurs authentifiés)
        Route::apiResource('trips', ExpressTripController::class);
        Route::post('/trips/{id}/close', [ExpressTripController::class, 'close']);

        // Colis Express (tous les utilisateurs authentifiés)
        Route::apiResource('parcels', ExpressParcelController::class);
        Route::post('/parcels/{id}/pickup', [ExpressParcelController::class, 'pickup']);

        // Reçus Express (tous les utilisateurs authentifiés)
        Route::get('/receipts/{id}/generate', [ExpressReceiptController::class, 'generate']);
        Route::get('/receipts/{id}/preview', [ExpressReceiptController::class, 'preview']);

        // Paiements Express (tous les utilisateurs authentifiés)
        Route::get('/parcels/{id}/payments', [ParcelPaymentController::class, 'index']);
        Route::post('/parcels/{id}/payments', [ParcelPaymentController::class, 'store']);

        // Frais Express (Boss et Admin uniquement)
        Route::middleware('role:boss,admin')->group(function () {
            Route::apiResource('trip-costs', ExpressTripCostController::class)->names([
                'index' => 'express.trip-costs.index',
                'show' => 'express.trip-costs.show',
                'store' => 'express.trip-costs.store',
                'update' => 'express.trip-costs.update',
                'destroy' => 'express.trip-costs.destroy',
            ]);
            Route::apiResource('wave-costs', ExpressWaveCostController::class)->names([
                'index' => 'express.wave-costs.index',
                'show' => 'express.wave-costs.show',
                'store' => 'express.wave-costs.store',
                'update' => 'express.wave-costs.update',
                'destroy' => 'express.wave-costs.destroy',
            ]);
        });

        // Tâches automatisées
        Route::post('/tasks/confirm-loading/{tripId}', [TaskController::class, 'confirmLoading']);
        Route::post('/tasks/confirm-arrival', [TaskController::class, 'confirmArrival']);
        Route::post('/tasks/mark-ready-for-pickup', [TaskController::class, 'markReadyForPickup']);

        // Gestion des livraisons
        Route::get('/deliveries/ready-for-pickup', [DeliveryController::class, 'readyForPickup']);
        Route::get('/deliveries/client/{clientId}/parcels', [DeliveryController::class, 'clientParcels']);
        Route::get('/deliveries/client/{clientId}/check-payment', [DeliveryController::class, 'checkClientPayment']);
        Route::get('/deliveries/wave/{waveId}/profit', [DeliveryController::class, 'calculateWaveProfit']);
    });
});

