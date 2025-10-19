# --------------------------------------------
# 🐘 Etapa 1: Construcción de dependencias con Composer
# --------------------------------------------
FROM composer:2.7 AS build

WORKDIR /app

# Copiamos archivos de composer
COPY composer.json composer.lock ./

# Instalamos dependencias sin dev
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader

# Copiamos todo el código del proyecto
COPY . .

# --------------------------------------------
# 🚀 Etapa 2: Imagen final PHP + Apache
# --------------------------------------------
FROM php:8.2-apache

# Instalar extensiones necesarias para Laravel + PostgreSQL
RUN apt-get update && apt-get install -y \
    git unzip zip libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_pgsql gd zip opcache \
    && a2enmod rewrite

# Copiamos el código de la etapa anterior
COPY --from=build /app /var/www/html

# Configuramos el directorio de trabajo
WORKDIR /var/www/html

# Permisos correctos para Laravel
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Exponemos el puerto estándar de Render
EXPOSE 10000

# Variables de entorno predeterminadas
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV PORT=10000

# Comando al iniciar el contenedor
# (genera clave si falta, limpia caché, ejecuta migraciones y seeders)
CMD php artisan key:generate --force && \
    php artisan config:cache && \
    php artisan migrate --force --seed && \
    php artisan serve --host=0.0.0.0 --port=$PORT
