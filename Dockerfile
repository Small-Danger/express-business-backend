FROM php:8.2-cli-alpine

# Installer les dépendances système
RUN apk add --no-cache \
    postgresql-dev \
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

# Installer les extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    gd \
    mbstring \
    xml \
    zip \
    opcache

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

# Copier le script d'entrypoint simplifié et le router PHP
COPY docker/entrypoint-simple.sh /usr/local/bin/entrypoint.sh
COPY docker/router.php /var/www/html/docker/router.php
RUN chmod +x /usr/local/bin/entrypoint.sh

# Configurer les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Exposer le port
EXPOSE 8000

# Utiliser le script d'entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

