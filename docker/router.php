<?php

/**
 * Router PHP simple pour le serveur de développement
 * Redirige toutes les requêtes vers index.php (comme un serveur web normal)
 */

// Gérer les erreurs pour éviter que le serveur crash
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// S'assurer que les fichiers bootstrap/cache sont vides avant de charger Laravel
$bootstrapCacheDir = '/var/www/html/bootstrap/cache';
if (is_dir($bootstrapCacheDir)) {
    // Supprimer tous les fichiers PHP dans bootstrap/cache
    $files = glob($bootstrapCacheDir . '/*.php');
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Le serveur PHP sert déjà les fichiers statiques depuis /var/www/html/public
// grâce à l'option -t, donc on n'a qu'à rediriger vers index.php pour les routes Laravel
// Si on arrive ici, c'est que le fichier n'existe pas, donc on redirige vers index.php

try {
    require_once '/var/www/html/public/index.php';
} catch (Throwable $e) {
    // Logger l'erreur complète dans les logs Laravel
    $logFile = '/var/www/html/storage/logs/laravel.log';
    $errorMessage = sprintf(
        "[%s] Router error: %s\nFile: %s:%d\nStack trace:\n%s\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    file_put_contents($logFile, $errorMessage, FILE_APPEND);
    
    // Afficher l'erreur complète dans la réponse si en mode debug
    http_response_code(500);
    header('Content-Type: application/json');
    
    $response = [
        'error' => 'Internal Server Error',
        'message' => 'An error occurred while processing your request.'
    ];
    
    // En mode debug, inclure les détails de l'erreur
    if (getenv('APP_DEBUG') === 'true') {
        $response['debug'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
}

