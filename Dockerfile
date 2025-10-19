# --------------------------------------------
# 🐘 Etapa 1: Construcción de dependencias con Composer
# --------------------------------------------
FROM composer:2.7 AS build

WORKDIR /app

# Copiamos los archivos de configuración de Composer
COPY composer.json composer.lock ./

# Instalamos dependencias sin autoloader de desarrollo
RUN composer install --no-dev --no-scripts --prefer-dist --optimize-autoloader

# Copiamos el resto del proyecto Laravel
COPY . .

# Ejecutamos scripts de post-instalación (si los hay)
RUN composer dump-autoload --optimize

# --------------------------------------------
# 🚀 Etapa 2: Imagen final con PHP y Apache
# --------------------------------------------
FROM php:8.2-apache

# Instalamos extensiones necesarias para Laravel y base de datos
RUN apt-get update && apt-get install -y \
    git unzip zip libpng-dev libjpeg-dev libfreetype6-dev libzip-dev libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql gd zip opcache \
    && a2enmod rewrite

# Copiamos archivos desde la etapa build
COPY --from=build /app /var/www/html

# Definimos el directorio de trabajo
WORKDIR /var/www/html

# Permisos correctos para Laravel
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Exponemos el puerto que Render usa
EXPOSE 10000

# Variables de entorno por defecto
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV APP_URL=https://ventas-laravel.onrender.com
ENV PORT=10000

# Comando de inicio del servidor Laravel
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=$PORT
