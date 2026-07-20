FROM php:8.2-cli

# Install dependensi sistem yang dibutuhkan Laravel & ekstensi PHP umum
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    nodejs \
    npm \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy semua file project
COPY . .

# Install dependency PHP (production, tanpa dev dependencies)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install dependency Node & build asset frontend (Vite)
RUN npm install && npm run build

# Set permission storage & bootstrap/cache (wajib biar Laravel bisa nulis log/cache)
RUN chmod -R 775 storage bootstrap/cache

# Cache config Laravel biar lebih cepat (jalan di runtime, karena butuh env vars)
# Tidak di-cache di sini karena APP_KEY & env lain baru ada saat container jalan

EXPOSE 8080

# Jalankan migrasi database lalu start server
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
