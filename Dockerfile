# Gunakan PHP resmi dengan ekstensi yang dibutuhkan
FROM php:8.2-apache

# Install dependencies dan ekstensi Laravel
RUN apt-get update && apt-get install -y \
    git unzip libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libzip-dev zip && \
    docker-php-ext-install pdo pdo_mysql gd mbstring zip exif pcntl

# Copy semua file project ke dalam container
COPY . /var/www/html

# Set working directory
WORKDIR /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install dependency Laravel
RUN composer install --no-dev --optimize-autoloader

# Set permission
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Ubah Apache DocumentRoot ke folder public Laravel
RUN sed -i 's|/var/www/html|/var/www/html/public|' /etc/apache2/sites-available/000-default.conf

# Aktifkan mod_rewrite untuk Laravel route
RUN a2enmod rewrite
RUN echo "<Directory /var/www/html/public>\n\
    AllowOverride All\n\
</Directory>" >> /etc/apache2/apache2.conf

# Note: APP_KEY akan di-set via environment variable di Railway
# Tidak perlu generate key di Dockerfile karena .env tidak ada saat build
# Pastikan set APP_KEY di Railway dashboard: php artisan key:generate --show

# Jalankan Apache di port 8080 (Railway otomatis detect)
EXPOSE 8080

CMD ["apache2-foreground"]
