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

# Mapper automatiquement les variables Railway PostgreSQL vers Laravel DB_*
# Railway fournit PGHOST, PGPORT, etc. mais Laravel attend DB_HOST, DB_PORT, etc.
if [ -z "$DB_HOST" ] && [ -n "$PGHOST" ]; then
    export DB_HOST="$PGHOST"
    echo "✅ DB_HOST mappé depuis PGHOST: $DB_HOST" >&2
fi

if [ -z "$DB_PORT" ] && [ -n "$PGPORT" ]; then
    export DB_PORT="$PGPORT"
    echo "✅ DB_PORT mappé depuis PGPORT: $DB_PORT" >&2
fi

if [ -z "$DB_DATABASE" ] && [ -n "$PGDATABASE" ]; then
    export DB_DATABASE="$PGDATABASE"
    echo "✅ DB_DATABASE mappé depuis PGDATABASE: $DB_DATABASE" >&2
fi

if [ -z "$DB_USERNAME" ] && [ -n "$PGUSER" ]; then
    export DB_USERNAME="$PGUSER"
    echo "✅ DB_USERNAME mappé depuis PGUSER: $DB_USERNAME" >&2
fi

if [ -z "$DB_PASSWORD" ] && [ -n "$PGPASSWORD" ]; then
    export DB_PASSWORD="$PGPASSWORD"
    echo "✅ DB_PASSWORD mappé depuis PGPASSWORD (masquée)" >&2
fi

# Si DATABASE_URL est défini, l'utiliser
if [ -z "$DB_HOST" ] && [ -n "$DATABASE_URL" ]; then
    echo "✅ Utilisation de DATABASE_URL pour la connexion" >&2
    export DB_URL="$DATABASE_URL"
fi

# S'assurer que DB_CONNECTION est défini
if [ -z "$DB_CONNECTION" ]; then
    export DB_CONNECTION="pgsql"
    echo "✅ DB_CONNECTION défini à: pgsql" >&2
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
echo "DATABASE_URL: ${DATABASE_URL:+définie (masquée)}" >&2
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
    echo "❌ Impossible de se connecter à la base de données après $max_attempts tentatives!" >&2
    echo "⚠️  Vérifiez que:" >&2
    echo "   1. Le service Postgres est démarré dans Railway" >&2
    echo "   2. Les variables d'environnement sont correctement configurées:" >&2
    echo "      - DB_CONNECTION=pgsql" >&2
    echo "      - DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD" >&2
    echo "   3. Le service Postgres est lié au service backend dans Railway" >&2
    exit 1
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
php artisan migrate --force --no-interaction 2>&1
MIGRATE_EXIT_CODE=$?

if [ $MIGRATE_EXIT_CODE -ne 0 ]; then
    echo "❌ Les migrations ont échoué avec le code $MIGRATE_EXIT_CODE" >&2
    echo "📋 Détails de l'erreur:" >&2
    tail -n 50 /var/www/html/storage/logs/laravel.log 2>/dev/null || true
    echo "" >&2
    echo "⚠️  VÉRIFICATIONS À FAIRE DANS RAILWAY:" >&2
    echo "   1. Allez dans votre projet Railway" >&2
    echo "   2. Cliquez sur le service Postgres" >&2
    echo "   3. Allez dans l'onglet 'Variables'" >&2
    echo "   4. Vérifiez que les variables suivantes sont définies dans le service backend:" >&2
    echo "      - DB_CONNECTION=pgsql" >&2
    echo "      - DB_HOST (copié depuis Postgres -> Variables -> PGHOST)" >&2
    echo "      - DB_PORT (copié depuis Postgres -> Variables -> PGPORT)" >&2
    echo "      - DB_DATABASE (copié depuis Postgres -> Variables -> PGDATABASE)" >&2
    echo "      - DB_USERNAME (copié depuis Postgres -> Variables -> PGUSER)" >&2
    echo "      - DB_PASSWORD (copié depuis Postgres -> Variables -> PGPASSWORD)" >&2
    echo "" >&2
    echo "   OU utilisez la fonction 'Connect' de Railway qui génère automatiquement ces variables" >&2
else
    echo "✅ Migrations exécutées avec succès" >&2
fi

# Créer le lien symbolique pour le storage
echo "=== STORAGE:LINK ===" >&2
php artisan storage:link --no-interaction 2>&1 || echo "⚠️  Le lien storage existe déjà ou erreur" >&2

# Optimiser Laravel pour la production (sans config:cache pour éviter l'erreur env)
echo "=== OPTIMIZE ===" >&2
# Ne pas mettre en cache les routes si ça échoue (conflits de noms)
php artisan route:cache --no-interaction 2>&1 || {
    echo "⚠️  Erreur lors de route:cache - Les routes ne seront pas mises en cache" >&2
    echo "ℹ️  L'application fonctionnera sans cache de routes (légèrement plus lent mais fonctionnel)" >&2
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

