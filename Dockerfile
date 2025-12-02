FROM php:8.2-cli-alpine

# Installer les dépendances système
RUN apk add --no-cache \
    postgresql-dev \
    postgresql-client \
    nodejs \
    npm \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev

# Installer les extensions PHP (pdo_pgsql en premier pour s'assurer qu'il est installé)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    pdo_mysql \
    gd \
    mbstring \
    xml \
    zip \
    opcache

# Vérifier que l'extension PostgreSQL est bien installée et activée
RUN php -m | grep -i pdo_pgsql || (echo "❌ Extension pdo_pgsql non trouvée!" && echo "Extensions disponibles:" && php -m && exit 1) \
    && echo "✅ Extension pdo_pgsql installée et activée" \
    && php -r "if (!extension_loaded('pdo_pgsql')) { echo '❌ Extension non chargée au runtime!'; exit(1); } else { echo '✅ Extension chargée au runtime'; }"

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le répertoire de travail
WORKDIR /var/www/html

# Copier les fichiers composer
COPY composer.json composer.lock ./

# Installer les dépendances PHP (sans dev pour production)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copier les fichiers package.json
COPY package.json package-lock.json ./

# Installer les dépendances Node.js
RUN npm ci

# Copier le reste des fichiers
COPY . .

# Builder les assets frontend (Vite)
RUN npm run build

# Supprimer tous les caches qui pourraient avoir été créés pendant le build
# Supprimer complètement le répertoire bootstrap/cache et le recréer vide
RUN rm -rf bootstrap/cache storage/framework/cache/* storage/framework/views/* || true \
    && mkdir -p bootstrap/cache \
    && touch bootstrap/cache/.gitkeep

# Note: L'autoloader est déjà optimisé avec --optimize-autoloader lors de composer install
# Les scripts Laravel seront exécutés dans l'entrypoint quand .env sera disponible

# Copier le script d'entrypoint simplifié, le router PHP et les scripts de diagnostic
COPY docker/entrypoint-simple.sh /usr/local/bin/entrypoint.sh
COPY docker/router.php /var/www/html/docker/router.php
COPY docker/diagnose.php /var/www/html/docker/diagnose.php
COPY docker/test-db-connection.php /var/www/html/docker/test-db-connection.php
RUN chmod +x /usr/local/bin/entrypoint.sh

# Configurer les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Exposer le port
EXPOSE 8000

# Utiliser le script d'entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

