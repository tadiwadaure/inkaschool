# Use official PHP 8.2 Apache image
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libapache2-mod-php \
    php-mysql \
    php-curl \
    php-gd \
    php-mbstring \
    php-xml \
    php-zip \
    php-intl \
    php-bcmath \
    php-json \
    php-tokenizer \
    php-xmlwriter \
    php-dom \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    default-mysql-client \
    wget \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo_mysql mysqli \
    && docker-php-ext-install mbstring xml intl bcmath \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure PHP
RUN echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "display_errors = On" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/custom.ini

# Configure Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && sed -i 's/DocumentRoot \/var\/www\/html/DocumentRoot \/var\/www\/html\/public/g' /etc/apache2/sites-available/000-default.conf

# Copy application files
COPY . /var/www/html/

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/uploads /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/uploads /var/www/html/logs

# Create .htaccess if it doesn't exist (for URL rewriting)
RUN if [ ! -f /var/www/html/.htaccess ]; then \
        echo "RewriteEngine On" > /var/www/html/.htaccess; \
    fi

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Start Apache
CMD ["apache2-foreground"]
