<?php

/**
 * Script de diagnostic pour identifier les erreurs Laravel
 * Exécute les commandes artisan une par une et affiche les vraies erreurs
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

echo "=== DIAGNOSTIC LARAVEL ===\n";
echo "APP_ENV: " . env('APP_ENV', 'not set') . "\n";
echo "APP_DEBUG: " . (env('APP_DEBUG', false) ? 'true' : 'false') . "\n";
echo "\n";

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Test 1: Package Discover ===\n";
try {
    Artisan::call('package:discover', ['--ansi' => true]);
    echo "✅ Package discover OK\n";
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test 2: Config Clear ===\n";
try {
    Artisan::call('config:clear');
    echo "✅ Config clear OK\n";
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Test 3: Route Clear ===\n";
try {
    Artisan::call('route:clear');
    echo "✅ Route clear OK\n";
} catch (Exception $e) {
    echo "❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Test 4: Vérification des Controllers ===\n";
$controllers = [
    'App\Http\Controllers\AuthController',
    'App\Http\Controllers\SystemSettingController',
    'App\Http\Controllers\UserController',
    'App\Http\Controllers\Business\AnalyticsController',
    'App\Http\Controllers\Business\BusinessConvoyController',
    'App\Http\Controllers\Business\BusinessOrderController',
    'App\Http\Controllers\Business\BusinessWaveController',
    'App\Http\Controllers\Business\ClientController',
    'App\Http\Controllers\Business\InvoiceController',
    'App\Http\Controllers\Business\ProductController',
    'App\Http\Controllers\Express\DeliveryController',
    'App\Http\Controllers\Express\ExpressParcelController',
    'App\Http\Controllers\Express\ExpressTripController',
    'App\Http\Controllers\Express\ExpressWaveController',
    'App\Http\Controllers\Express\ExpressWaveCostController',
    'App\Http\Controllers\Express\ExpressTripCostController',
    'App\Http\Controllers\Express\ReceiptController',
    'App\Http\Controllers\Express\TaskController',
    'App\Http\Controllers\Business\BusinessOrderItemController',
    'App\Http\Controllers\Business\BusinessWaveCostController',
    'App\Http\Controllers\Business\BusinessConvoyCostController',
];

foreach ($controllers as $controller) {
    if (class_exists($controller)) {
        echo "✅ $controller\n";
    } else {
        echo "❌ $controller - CLASSE MANQUANTE!\n";
    }
}

echo "\n=== Test 5: Vérification des Models ===\n";
$models = [
    'App\Models\User',
    'App\Models\Client',
    'App\Models\Product',
];

foreach ($models as $model) {
    if (class_exists($model)) {
        echo "✅ $model\n";
    } else {
        echo "❌ $model - CLASSE MANQUANTE!\n";
    }
}

echo "\n=== FIN DU DIAGNOSTIC ===\n";

