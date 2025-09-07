# Use the official PHP image that includes an Apache web server
FROM php:8.3-apache

# Install the necessary PHP extension for connecting to PostgreSQL.
# This is a critical step for your application to communicate with the Supabase database.
RUN docker-php-ext-install pdo pdo_pgsql

# Copy the *contents* of your "allinone" folder into the web server's public directory.
COPY . /var/www/html/
