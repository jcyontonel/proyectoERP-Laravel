# Imagen base con PHP y extensiones necesarias
FROM php:8.2-cli

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    curl \
    libpq-dev \
    libzip-dev \
    zip \
    && docker-php-ext-install pdo pdo_pgsql zip

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Establecer directorio de trabajo
WORKDIR /app

# Copiar los archivos del proyecto
COPY . .

# Instalar dependencias de Laravel (modo producción)
RUN composer install --no-dev --optimize-autoloader

# Generar la clave de app, correr migraciones y luego iniciar servidor
CMD php artisan key:generate && php artisan migrate --seed --force && php artisan serve --host=0.0.0.0 --port=${PORT}
