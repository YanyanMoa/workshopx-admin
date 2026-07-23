FROM php:8.2-apache

# Install curl extension which is required by our Supabase API wrapper class
RUN apt-get update && apt-get install -y libcurl4-openssl-dev && docker-php-ext-install curl

# Copy all application files to the standard Apache public directory
COPY . /var/www/html/

# Enable Apache rewrite module
RUN a2enmod rewrite

# Expose port 80
EXPOSE 80
