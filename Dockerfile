FROM php:8.1-apache

# Install PHP extensions yang diperlukan (misalnya mysqli, pdo_mysql)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy project ke direktori container
COPY . /var/www/html/

# Ganti permission folder (jika diperlukan)
RUN chown -R www-data:www-data /var/www/html/

# Expose port (Railway akan menggunakan port yang diatur melalui variabel env)
EXPOSE 80

# Jalankan server Apache
CMD ["apache2-foreground"]
