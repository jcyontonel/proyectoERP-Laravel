FROM php:8.2-fpm

# Instalar extensiones requeridas
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip mbstring exif pcntl bcmath

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Establecer el directorio de trabajo
WORKDIR /app

# Copiar todo el proyecto
COPY . .

# Instalar dependencias sin las de desarrollo
RUN composer install --no-dev --optimize-autoloader

# Generar key y correr migraciones
RUN php artisan key:generate \
    && php artisan config:clear \
    && php artisan migrate:fresh --seed --force

CMD ["php-fpm"]
