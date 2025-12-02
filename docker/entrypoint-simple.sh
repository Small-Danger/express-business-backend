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
    php artisan key:generate --force 2>&1 || {
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
rm -rf /var/www/html/bootstrap/cache/*.php || true
rm -rf /var/www/html/storage/framework/cache/data/* || true
rm -rf /var/www/html/storage/framework/views/*.php || true
rm -rf /var/www/html/storage/framework/sessions/* || true

# Maintenant on peut utiliser artisan (les caches sont supprimés)
echo "🧹 Nettoyage des caches Laravel..." >&2
php artisan config:clear 2>&1 | grep -v "Class.*env.*does not exist" || true
php artisan route:clear 2>&1 | grep -v "Class.*env.*does not exist" || true
php artisan view:clear 2>&1 | grep -v "Class.*env.*does not exist" || true
php artisan cache:clear 2>&1 | grep -v "Class.*env.*does not exist" || true

# Découvrir les packages Laravel (sans cache de config)
echo "📦 Découverte des packages Laravel..." >&2
php artisan package:discover --ansi 2>&1 | grep -v "Class.*env.*does not exist" || true

# Exécuter les migrations
echo "📦 Exécution des migrations..." >&2
php artisan migrate --force 2>&1 | grep -v "Class.*env.*does not exist" || echo "⚠️  Erreur lors des migrations, mais on continue..." >&2

# Créer le lien symbolique pour le storage
echo "🔗 Création du lien symbolique storage..." >&2
php artisan storage:link 2>&1 | grep -v "Class.*env.*does not exist" || echo "⚠️  Le lien storage existe déjà ou erreur" >&2

# Optimiser Laravel pour la production (sans config:cache pour éviter l'erreur env)
echo "⚡ Optimisation de Laravel..." >&2
php artisan route:cache 2>&1 | grep -v "Class.*env.*does not exist" || true
php artisan view:cache 2>&1 | grep -v "Class.*env.*does not exist" || true
# Ne pas mettre en cache la config pour éviter l'erreur "Class env does not exist"
# php artisan config:cache || true

# Vérifier que le port est défini
if [ -z "$PORT" ]; then
    echo "⚠️  Variable PORT non définie, utilisation du port 8000 par défaut" >&2
    PORT=8000
fi

# Démarrer le serveur PHP intégré
echo "✅ Application prête! Démarrage du serveur..." >&2
echo "🌐 Serveur accessible sur le port $PORT" >&2

# Utiliser exec pour que le processus serveur devienne PID 1
# Cela permet à Railway de détecter correctement si le conteneur crash
exec php artisan serve --host=0.0.0.0 --port=$PORT

