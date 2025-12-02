#!/bin/sh
# Ne pas arrêter le script en cas d'erreur pour permettre le diagnostic
set +e

echo "🚀 Démarrage de l'application Laravel..." >&2

# Créer les répertoires nécessaires
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/bootstrap/cache
mkdir -p /var/log/nginx
mkdir -p /var/log/supervisor

# Configurer les permissions
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Afficher les variables d'environnement de base de données (pour debug)
# Forcer l'affichage sur stdout et stderr
echo "==========================================" >&2
echo "🔍 Variables d'environnement de base de données:" >&2
echo "DB_CONNECTION: ${DB_CONNECTION:-non définie}" >&2
echo "DB_HOST: ${DB_HOST:-non définie}" >&2
echo "DB_PORT: ${DB_PORT:-non définie}" >&2
echo "DB_DATABASE: ${DB_DATABASE:-non définie}" >&2
echo "DB_USERNAME: ${DB_USERNAME:-non définie}" >&2
echo "DB_PASSWORD: ${DB_PASSWORD:+définie (masquée)}" >&2
echo "==========================================" >&2

# Attendre que la base de données soit prête (avec timeout)
echo "⏳ Vérification de la connexion à la base de données..." >&2
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    # Tester la connexion avec une commande PHP simple
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
    echo "⚠️  Vérifiez que les variables DB_* sont correctement configurées dans Railway" >&2
fi

# Exécuter les migrations
echo "📦 Exécution des migrations..." >&2
php artisan migrate --force 2>&1 || echo "⚠️  Erreur lors des migrations, mais on continue..." >&2

# Créer le lien symbolique pour le storage
echo "🔗 Création du lien symbolique storage..." >&2
php artisan storage:link 2>&1 || echo "⚠️  Le lien storage existe déjà ou erreur" >&2

# Découvrir les packages Laravel (nécessaire après composer install --no-scripts)
echo "📦 Découverte des packages Laravel..." >&2
php artisan package:discover --ansi 2>&1 || true

# Optimiser Laravel pour la production
echo "⚡ Optimisation de Laravel..." >&2
php artisan config:cache 2>&1 || true
php artisan route:cache 2>&1 || true
php artisan view:cache 2>&1 || true

echo "✅ Application prête! Démarrage des services..." >&2

# Remplacer PORT dans la configuration Nginx (Railway utilise un port dynamique)
if [ -n "$PORT" ]; then
    sed -i "s/listen \${PORT:-80};/listen $PORT;/g" /etc/nginx/conf.d/default.conf
    echo "🌐 Nginx configuré pour écouter sur le port $PORT" >&2
else
    echo "⚠️  Variable PORT non définie, utilisation du port 80 par défaut" >&2
fi

# Vérifier que PHP-FPM peut démarrer
echo "🔍 Vérification de PHP-FPM..." >&2
php-fpm -t 2>&1 || echo "⚠️  Erreur de configuration PHP-FPM" >&2

# Vérifier que Nginx peut démarrer
echo "🔍 Vérification de Nginx..." >&2
nginx -t 2>&1 || echo "⚠️  Erreur de configuration Nginx" >&2

# Démarrer Supervisor
echo "🚀 Démarrage de Supervisor..." >&2
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

