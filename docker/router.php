<?php

/**
 * Router PHP simple pour le serveur de développement
 * Redirige toutes les requêtes vers index.php (comme un serveur web normal)
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Le serveur PHP sert déjà les fichiers statiques depuis /var/www/html/public
// grâce à l'option -t, donc on n'a qu'à rediriger vers index.php pour les routes Laravel
// Si on arrive ici, c'est que le fichier n'existe pas, donc on redirige vers index.php
require_once '/var/www/html/public/index.php';

