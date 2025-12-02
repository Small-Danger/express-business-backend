#!/bin/sh
set +e

echo "🚀 Démarrage de l'application Laravel (mode simple)..." >&2

# Créer les répertoires nécessaires
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/bootstrap/cache

# Configurer les permissions
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Vérifier que APP_KEY est défini (critique pour Laravel)
if [ -z "$APP_KEY" ]; then
    echo "❌ ERREUR: APP_KEY n'est pas défini!" >&2
    echo "⚠️  Génération d'une clé d'application..." >&2
    # Supprimer les caches avant de générer la clé
    rm -rf /var/www/html/bootstrap/cache/*.php 2>/dev/null || true
    php artisan key:generate --force 2>&1 | grep -vE "(Class.*env.*does not exist|Target class)" || {
        echo "❌ Impossible de générer APP_KEY. Veuillez définir APP_KEY dans Railway." >&2
        exit 1
    }
else
    echo "✅ APP_KEY est défini" >&2
fi

# Afficher les variables d'environnement de base de données
echo "==========================================" >&2
echo "🔍 Variables d'environnement de base de données:" >&2
echo "DB_CONNECTION: ${DB_CONNECTION:-non définie}" >&2
echo "DB_HOST: ${DB_HOST:-non définie}" >&2
echo "DB_PORT: ${DB_PORT:-non définie}" >&2
echo "DB_DATABASE: ${DB_DATABASE:-non définie}" >&2
echo "DB_USERNAME: ${DB_USERNAME:-non définie}" >&2
echo "DB_PASSWORD: ${DB_PASSWORD:+définie (masquée)}" >&2
echo "PORT: ${PORT:-8000}" >&2
echo "==========================================" >&2

# Attendre que la base de données soit prête
echo "⏳ Vérification de la connexion à la base de données..." >&2
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    if php -r "
    try {
        \$pdo = new PDO(
            'pgsql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
        echo 'OK';
    } catch (Exception \$e) {
        exit(1);
    }
    " > /dev/null 2>&1; then
        echo "✅ Base de données connectée!" >&2
        break
    fi
    attempt=$((attempt + 1))
    echo "Tentative $attempt/$max_attempts..." >&2
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "⚠️  Impossible de se connecter à la base de données, mais on continue..." >&2
fi

# Vider TOUS les caches existants MANUELLEMENT (avant d'utiliser artisan)
echo "🧹 Nettoyage complet des caches..." >&2
# Supprimer complètement le répertoire bootstrap/cache et le recréer
rm -rf /var/www/html/bootstrap/cache 2>/dev/null || true
mkdir -p /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/bootstrap/cache

# Supprimer tous les autres caches
rm -rf /var/www/html/storage/framework/cache/data/* 2>/dev/null || true
rm -rf /var/www/html/storage/framework/views/*.php 2>/dev/null || true
rm -rf /var/www/html/storage/framework/sessions/* 2>/dev/null || true
rm -rf /var/www/html/storage/framework/cache/*.php 2>/dev/null || true

# Régénérer l'autoloader pour s'assurer qu'il est à jour
# Désactiver les scripts pour éviter que package:discover ne crée des fichiers corrompus
echo "🔄 Régénération de l'autoloader..." >&2
COMPOSER_DISABLE_XDEBUG_WARN=1 composer dump-autoload --no-interaction --optimize --classmap-authoritative --no-scripts 2>&1 | grep -vE "(Class.*env.*does not exist|Target class)" || true

# Activer temporairement le debug pour capturer les vraies erreurs
echo "🔍 Activation du mode debug pour diagnostic..." >&2
export APP_DEBUG=true
export LOG_LEVEL=debug

# Maintenant on peut utiliser artisan (les caches sont supprimés)
echo "🧹 Nettoyage des caches Laravel..." >&2
# Capturer les vraies erreurs au lieu de les filtrer
echo "=== CONFIG:CLEAR ===" >&2
php artisan config:clear --no-interaction 2>&1 || {
    echo "❌ ERREUR lors de config:clear - Voir les détails ci-dessus" >&2
    echo "📋 Affichage des logs Laravel:" >&2
    tail -n 50 /var/www/html/storage/logs/laravel.log 2>/dev/null || echo "Pas de logs disponibles" >&2
}

echo "=== ROUTE:CLEAR ===" >&2
php artisan route:clear --no-interaction 2>&1 || {
    echo "❌ ERREUR lors de route:clear" >&2
    tail -n 30 /var/www/html/storage/logs/laravel.log 2>/dev/null || true
}

echo "=== VIEW:CLEAR ===" >&2
php artisan view:clear --no-interaction 2>&1 || {
    echo "❌ ERREUR lors de view:clear" >&2
    tail -n 30 /var/www/html/storage/logs/laravel.log 2>/dev/null || true
}

echo "=== CACHE:CLEAR ===" >&2
php artisan cache:clear --no-interaction 2>&1 || {
    echo "❌ ERREUR lors de cache:clear" >&2
    tail -n 30 /var/www/html/storage/logs/laravel.log 2>/dev/null || true
}

# Découvrir les packages Laravel (sans cache de config)
echo "=== PACKAGE:DISCOVER ===" >&2
php artisan package:discover --ansi --no-interaction 2>&1 || {
    echo "❌ ERREUR lors de package:discover - C'est probablement la source du problème!" >&2
    echo "📋 Dernières lignes des logs:" >&2
    tail -n 50 /var/www/html/storage/logs/laravel.log 2>/dev/null || true
}

# Exécuter les migrations
echo "=== MIGRATE ===" >&2
php artisan migrate --force --no-interaction 2>&1 || {
    echo "⚠️  Erreur lors des migrations, mais on continue..." >&2
    tail -n 30 /var/www/html/storage/logs/laravel.log 2>/dev/null || true
}

# Vérifier si la table cache existe, sinon exécuter la migration spécifique
echo "=== VÉRIFICATION TABLE CACHE ===" >&2
php artisan migrate --path=database/migrations/0001_01_01_000001_create_cache_table.php --force --no-interaction 2>&1 || {
    echo "⚠️  La migration de la table cache a peut-être déjà été exécutée ou a échoué" >&2
}

# Créer le lien symbolique pour le storage
echo "=== STORAGE:LINK ===" >&2
php artisan storage:link --no-interaction 2>&1 || echo "⚠️  Le lien storage existe déjà ou erreur" >&2

# Optimiser Laravel pour la production (sans config:cache pour éviter l'erreur env)
echo "=== OPTIMIZE ===" >&2
php artisan route:cache --no-interaction 2>&1 || {
    echo "⚠️  Erreur lors de route:cache" >&2
    tail -n 30 /var/www/html/storage/logs/laravel.log 2>/dev/null || true
}
php artisan view:cache --no-interaction 2>&1 || {
    echo "⚠️  Erreur lors de view:cache" >&2
    tail -n 30 /var/www/html/storage/logs/laravel.log 2>/dev/null || true
}
# Ne pas mettre en cache la config pour éviter l'erreur "Class env does not exist"
# php artisan config:cache || true

# Exécuter le script de diagnostic pour identifier les problèmes
echo "==========================================" >&2
echo "🔍 EXÉCUTION DU DIAGNOSTIC..." >&2
echo "==========================================" >&2
php /var/www/html/docker/diagnose.php 2>&1 || echo "⚠️  Le diagnostic a échoué, mais on continue..." >&2

# Afficher les dernières erreurs des logs avant de démarrer
echo "==========================================" >&2
echo "📋 DERNIÈRES ERREURS DANS LES LOGS:" >&2
echo "==========================================" >&2
if [ -f /var/www/html/storage/logs/laravel.log ]; then
    tail -n 100 /var/www/html/storage/logs/laravel.log | grep -i "error\|exception\|fatal\|class.*not found\|target class" | tail -n 30 || echo "Aucune erreur récente dans les logs" >&2
else
    echo "Le fichier de log n'existe pas encore" >&2
fi
echo "==========================================" >&2

# Vérifier que le port est défini
if [ -z "$PORT" ]; then
    echo "⚠️  Variable PORT non définie, utilisation du port 8000 par défaut" >&2
    PORT=8000
fi

# Démarrer le serveur PHP intégré
echo "✅ Application prête! Démarrage du serveur..." >&2
echo "🌐 Serveur accessible sur le port $PORT" >&2

# Utiliser le serveur PHP intégré directement avec un router personnalisé
# Cela évite les problèmes de cache Laravel avec artisan serve
# -t spécifie le répertoire racine du serveur (public)
echo "🚀 Démarrage du serveur PHP sur 0.0.0.0:$PORT..." >&2

# Utiliser exec pour que le processus serveur devienne PID 1 (important pour Railway)
exec php -S 0.0.0.0:$PORT -t /var/www/html/public /var/www/html/docker/router.php

