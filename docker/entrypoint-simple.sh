#!/bin/sh
set +e

echo "🚀 Démarrage de l'application Laravel (mode simple)..."

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

# Afficher les variables d'environnement de base de données
echo "=========================================="
echo "🔍 Variables d'environnement de base de données:"
echo "DB_CONNECTION: ${DB_CONNECTION:-non définie}"
echo "DB_HOST: ${DB_HOST:-non définie}"
echo "DB_PORT: ${DB_PORT:-non définie}"
echo "DB_DATABASE: ${DB_DATABASE:-non définie}"
echo "DB_USERNAME: ${DB_USERNAME:-non définie}"
echo "DB_PASSWORD: ${DB_PASSWORD:+définie (masquée)}"
echo "=========================================="

# Attendre que la base de données soit prête
echo "⏳ Vérification de la connexion à la base de données..."
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

# Vider TOUS les caches existants MANUELLEMENT (avant d'utiliser artisan)
echo "🧹 Nettoyage complet des caches..."
rm -rf /var/www/html/bootstrap/cache/*.php || true
rm -rf /var/www/html/storage/framework/cache/data/* || true
rm -rf /var/www/html/storage/framework/views/*.php || true
rm -rf /var/www/html/storage/framework/sessions/* || true

# Maintenant on peut utiliser artisan (les caches sont supprimés)
php artisan config:clear 2>&1 | grep -v "Class.*env.*does not exist" || true
php artisan route:clear 2>&1 | grep -v "Class.*env.*does not exist" || true
php artisan view:clear 2>&1 | grep -v "Class.*env.*does not exist" || true
php artisan cache:clear 2>&1 | grep -v "Class.*env.*does not exist" || true

# Découvrir les packages Laravel (sans cache de config)
echo "📦 Découverte des packages Laravel..."
php artisan package:discover --ansi 2>&1 | grep -v "Class.*env.*does not exist" || true

# Exécuter les migrations
echo "📦 Exécution des migrations..."
php artisan migrate --force 2>&1 | grep -v "Class.*env.*does not exist" || echo "⚠️  Erreur lors des migrations, mais on continue..."

# Créer le lien symbolique pour le storage
echo "🔗 Création du lien symbolique storage..."
php artisan storage:link 2>&1 | grep -v "Class.*env.*does not exist" || echo "⚠️  Le lien storage existe déjà ou erreur"

# Optimiser Laravel pour la production (sans config:cache pour éviter l'erreur env)
echo "⚡ Optimisation de Laravel..."
php artisan route:cache 2>&1 | grep -v "Class.*env.*does not exist" || true
php artisan view:cache 2>&1 | grep -v "Class.*env.*does not exist" || true
# Ne pas mettre en cache la config pour éviter l'erreur "Class env does not exist"
# php artisan config:cache || true

# Démarrer le serveur PHP intégré
echo "✅ Application prête! Démarrage du serveur..."
echo "🌐 Serveur accessible sur le port ${PORT:-8000}"

# Utiliser le port fourni par Railway ou 8000 par défaut
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}

