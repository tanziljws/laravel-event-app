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

# Create storage link (jika belum ada)
RUN php artisan storage:link || true

# Ubah Apache DocumentRoot ke folder public Laravel
RUN sed -i 's|/var/www/html|/var/www/html/public|' /etc/apache2/sites-available/000-default.conf

# Aktifkan mod_rewrite dan mod_headers untuk Laravel route dan CORS
RUN a2enmod rewrite headers
RUN echo "<Directory /var/www/html/public>\n\
    AllowOverride All\n\
</Directory>" >> /etc/apache2/apache2.conf

# Fix ServerName warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Note: APP_KEY akan di-set via environment variable di Railway
# Tidak perlu generate key di Dockerfile karena .env tidak ada saat build
# Pastikan set APP_KEY di Railway dashboard: php artisan key:generate --show

# Jalankan Apache di port 8080 (Railway otomatis detect)
EXPOSE 8080

# Create startup script untuk handle PORT environment variable
RUN echo '#!/bin/bash\n\
set -e\n\
\n\
# Set port dari environment variable (default 8080)\n\
PORT=${PORT:-8080}\n\
\n\
# Create storage link jika belum ada\n\
php artisan storage:link || true\n\
\n\
# Clear config cache\n\
php artisan config:clear || true\n\
\n\
# Update Apache ports.conf\n\
sed -i "s/^Listen .*/Listen $PORT/" /etc/apache2/ports.conf\n\
\n\
# Update VirtualHost di 000-default.conf (handle both *:80 and *:8080)\n\
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:$PORT>/" /etc/apache2/sites-available/000-default.conf\n\
\n\
# Start Apache\n\
exec apache2-foreground' > /usr/local/bin/start-apache.sh && \
    chmod +x /usr/local/bin/start-apache.sh

CMD ["/usr/local/bin/start-apache.sh"]
