<?php
/**
 * Script de test de connexion à la base de données
 * Utilise DATABASE_URL en priorité, puis les variables individuelles
 */

// Vérifier que l'extension PostgreSQL est chargée
if (!extension_loaded('pdo_pgsql')) {
    echo "ERROR: Extension pdo_pgsql non chargée. Extensions disponibles: " . implode(', ', get_loaded_extensions());
    exit(1);
}

$databaseUrl = getenv('DATABASE_URL') ?: getenv('DATABASE_PUBLIC_URL');

if ($databaseUrl) {
    try {
        $pdo = new PDO($databaseUrl);
        echo "OK (via DATABASE_URL)";
        exit(0);
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage();
        exit(1);
    }
} else {
    // Utiliser les variables individuelles
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: '5432';
    $database = getenv('DB_DATABASE') ?: 'railway';
    $username = getenv('DB_USERNAME') ?: 'postgres';
    $password = getenv('DB_PASSWORD') ?: '';
    
    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database";
        $pdo = new PDO($dsn, $username, $password);
        echo "OK (via variables individuelles)";
        exit(0);
    } catch (Exception $e) {
        echo "ERROR: " . $e->getMessage();
        exit(1);
    }
}

