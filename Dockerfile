# Etapa 1: instalar dependencias
FROM composer:2.7 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

# Etapa 2: PHP + Apache
FROM php:8.3-apache
WORKDIR /var/www/html

# Instalar extensiones necesarias
RUN apt-get update && apt-get install -y \
    libpq-dev zip unzip git && \
    docker-php-ext-install pdo pdo_pgsql && \
    a2enmod rewrite

# Copiar dependencias y proyecto
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Crear .env si no existe (a partir del .env.example)
RUN if [ ! -f ".env" ]; then cp .env.render .env; fi

# Asignar permisos correctos
RUN chown -R www-data:www-data storage bootstrap/cache

# Generar APP_KEY solo si no está definida
RUN php artisan config:clear && \
    if [ -z "$(grep APP_KEY .env | cut -d '=' -f2)" ]; then php artisan key:generate --force; fi

# Exponer el puerto 80
EXPOSE 80

# Migrar y servir la app
CMD php artisan migrate --force && php artisan db:seed --force && apache2-foreground
