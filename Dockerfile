# Etapa 1: construir dependencias con Composer
FROM composer:2.7 AS build
WORKDIR /app

# Copiar archivos necesarios para instalar dependencias
COPY composer.json composer.lock ./
# Instalar dependencias de producción sin ejecutar scripts (evita el error de artisan)
RUN composer install --no-dev --no-interaction --no-scripts --no-progress --optimize-autoloader

# Copiar el resto del proyecto Laravel
COPY . .

# Ejecutar scripts ahora que artisan existe
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

# Etapa 2: Servidor PHP + Apache
FROM php:8.3-apache
WORKDIR /var/www/html

# Instalar dependencias del sistema y extensiones necesarias para PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev zip unzip git && \
    docker-php-ext-install pdo pdo_pgsql && \
    a2enmod rewrite

# Copiar el proyecto y dependencias desde la etapa anterior
COPY --from=build /app ./

# Crear el .env si no existe
RUN if [ ! -f ".env" ]; then cp .env.example .env; fi

# Asignar permisos
RUN chown -R www-data:www-data storage bootstrap/cache

# Generar APP_KEY si no está definida
RUN php artisan config:clear && \
    if [ -z "$(grep APP_KEY .env | cut -d '=' -f2)" ]; then php artisan key:generate --force; fi

# Exponer el puerto 80
EXPOSE 80

# Ejecutar migraciones y arrancar Apache
CMD php artisan migrate --force && php artisan db:seed --force && apache2-foreground
