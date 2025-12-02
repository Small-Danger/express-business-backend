#!/bin/sh
# Ne pas arrêter le script en cas d'erreur pour permettre le diagnostic
set +e

echo "🚀 Démarrage de l'application Laravel..."

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
echo "=========================================="
echo "🔍 Variables d'environnement de base de données:"
echo "DB_CONNECTION: ${DB_CONNECTION:-non définie}"
echo "DB_HOST: ${DB_HOST:-non définie}"
echo "DB_PORT: ${DB_PORT:-non définie}"
echo "DB_DATABASE: ${DB_DATABASE:-non définie}"
echo "DB_USERNAME: ${DB_USERNAME:-non définie}"
echo "DB_PASSWORD: ${DB_PASSWORD:+définie (masquée)}"
echo "=========================================="

# Attendre que la base de données soit prête (avec timeout)
echo "⏳ Vérification de la connexion à la base de données..."
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
        echo "✅ Base de données connectée!"
        break
    fi
    attempt=$((attempt + 1))
    echo "Tentative $attempt/$max_attempts..."
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "⚠️  Impossible de se connecter à la base de données, mais on continue..."
    echo "⚠️  Vérifiez que les variables DB_* sont correctement configurées dans Railway"
fi

# Exécuter les migrations
echo "📦 Exécution des migrations..."
php artisan migrate --force || echo "⚠️  Erreur lors des migrations, mais on continue..."

# Créer le lien symbolique pour le storage
echo "🔗 Création du lien symbolique storage..."
php artisan storage:link || echo "⚠️  Le lien storage existe déjà ou erreur"

# Découvrir les packages Laravel (nécessaire après composer install --no-scripts)
echo "📦 Découverte des packages Laravel..."
php artisan package:discover --ansi || true

# Optimiser Laravel pour la production
echo "⚡ Optimisation de Laravel..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "✅ Application prête! Démarrage des services..."

# Remplacer PORT dans la configuration Nginx (Railway utilise un port dynamique)
if [ -n "$PORT" ]; then
    sed -i "s/listen \${PORT:-80};/listen $PORT;/g" /etc/nginx/conf.d/default.conf
    echo "🌐 Nginx configuré pour écouter sur le port $PORT"
else
    echo "⚠️  Variable PORT non définie, utilisation du port 80 par défaut"
fi

# Vérifier que PHP-FPM peut démarrer
echo "🔍 Vérification de PHP-FPM..."
php-fpm -t || echo "⚠️  Erreur de configuration PHP-FPM"

# Vérifier que Nginx peut démarrer
echo "🔍 Vérification de Nginx..."
nginx -t || echo "⚠️  Erreur de configuration Nginx"

# Démarrer Supervisor
echo "🚀 Démarrage de Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

