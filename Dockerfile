# Etapa 1: Dependencias PHP y Composer
FROM composer:2.7 AS vendor
WORKDIR /app

# Copiar solo los archivos necesarios para instalar dependencias
COPY composer.json composer.lock ./

# Instalar dependencias sin ejecutar scripts (evita el error de artisan)
RUN composer install --no-dev --no-interaction --no-scripts --no-progress --optimize-autoloader

# Copiar todo el proyecto Laravel
COPY . .

# Ejecutar composer nuevamente con scripts ahora que artisan existe
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

# Etapa 2: PHP con Apache
FROM php:8.3-apache
WORKDIR /var/www/html

# Instalar dependencias del sistema y extensiones necesarias
RUN apt-get update && apt-get install -y \
    git zip unzip libpq-dev && \
    docker-php-ext-install pdo pdo_pgsql && \
    a2enmod rewrite && \
    rm -rf /var/lib/apt/lists/*

# Copiar el proyecto desde la etapa anterior
COPY --from=vendor /app ./

# Crear .env si no existe
RUN if [ ! -f ".env" ]; then cp .env.example .env; fi

# Asignar permisos correctos
RUN chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache

# Configurar variables de entorno (Render las inyecta, pero dejamos fallback)
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV APP_URL=http://localhost

# Generar APP_KEY si no existe
RUN php artisan config:clear && \
    if ! grep -q "APP_KEY=base64" .env; then php artisan key:generate --force; fi

# Exponer puerto
EXPOSE 80

# Ejecutar migraciones y arrancar el servidor
CMD php artisan migrate --force && php artisan db:seed --force && apache2-foreground
