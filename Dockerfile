FROM php:8.2-fpm-alpine

# Installer les dépendances système
RUN apk add --no-cache \
    nginx \
    supervisor \
    postgresql-dev \
    mysql-client \
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

# Note: L'autoloader est déjà optimisé avec --optimize-autoloader lors de composer install
# Les scripts Laravel seront exécutés dans l'entrypoint quand .env sera disponible

# Créer le répertoire docker s'il n'existe pas
RUN mkdir -p /etc/nginx/conf.d /etc/supervisor/conf.d /var/log/supervisor /var/log/nginx

# Copier la configuration Nginx principale
COPY docker/nginx-main.conf /etc/nginx/nginx.conf

# Copier la configuration Nginx du serveur
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

# Copier la configuration Supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copier la configuration PHP-FPM
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Copier le script d'entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Configurer les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Exposer le port
EXPOSE 80

# Utiliser le script d'entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

