# Start with the official PHP Apache image
FROM php:8.3-apache

# 1. NEW STEP: Install system dependencies for PostgreSQL
# First, update the list of available packages, then install the
# 'libpq-dev' package, which contains the required 'libpq-fe.h' file.
RUN apt-get update && apt-get install -y libpq-dev

# 2. NOW, install the PHP extensions
# This command will now succeed because the dependencies are installed.
RUN docker-php-ext-install pdo pdo_pgsql

# 3. Copy your application code into the server's public directory
COPY . /var/www/html/
