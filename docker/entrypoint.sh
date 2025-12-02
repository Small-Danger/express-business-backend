#!/bin/sh
set -e

echo "🚀 Démarrage de l'application Laravel..."

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

# Attendre que la base de données soit prête (avec timeout)
echo "⏳ Vérification de la connexion à la base de données..."
max_attempts=30
attempt=0

while [ $attempt -lt $max_attempts ]; do
    if php artisan db:show > /dev/null 2>&1; then
        echo "✅ Base de données connectée!"
        break
    fi
    attempt=$((attempt + 1))
    echo "Tentative $attempt/$max_attempts..."
    sleep 2
done

if [ $attempt -eq $max_attempts ]; then
    echo "⚠️  Impossible de se connecter à la base de données, mais on continue..."
fi

# Exécuter les migrations
echo "📦 Exécution des migrations..."
php artisan migrate --force || echo "⚠️  Erreur lors des migrations, mais on continue..."

# Créer le lien symbolique pour le storage
echo "🔗 Création du lien symbolique storage..."
php artisan storage:link || echo "⚠️  Le lien storage existe déjà ou erreur"

# Optimiser Laravel pour la production
echo "⚡ Optimisation de Laravel..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "✅ Application prête! Démarrage des services..."

# Démarrer Supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

