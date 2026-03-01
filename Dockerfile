# Build ARS Apps Drupal site
FROM php:8.3-apache AS base

# Set timezone to America/New_York by default
RUN ln -sf /usr/share/zoneinfo/America/New_York /etc/localtime

# Get the most recent commit if passed as a bnuild-arg
ARG GIT_SHA=unknown
ENV GIT_SHA=${GIT_SHA}

# Copy the DigiCert G2 root certificate into the image
COPY /docker/certs/DigiCertGlobalG2.crt.pem /usr/local/share/ca-certificates/DigiCertGlobalG2.crt

# Add it to the system's trusted CAs
RUN update-ca-certificates

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    mariadb-client \
    libzip-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js and npm for theme building
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Configure Apache
RUN a2enmod rewrite headers expires
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./
COPY scripts/ ./scripts/

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy application code
COPY . .

# Build theme assets
WORKDIR /var/www/html/web/themes/custom/ui_suite_arsapps
RUN npm ci --legacy-peer-deps \
    && npm run build \
    && rm -rf node_modules

# Set permissions
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configure PHP for production
COPY docker/php/php.ini /usr/local/etc/php/conf.d/drupal.ini

# Add our Drupal entrypoint
COPY docker/drupal-entrypoint.sh /usr/local/bin/drupal-entrypoint
RUN chmod +x /usr/local/bin/drupal-entrypoint

# SSH for Azure App Service remote access
RUN apt-get update && apt-get install -y --no-install-recommends openssh-server \
    && echo "root:Docker!" | chpasswd \
    && mkdir -p /run/sshd \
    && rm -rf /var/lib/apt/lists/*
COPY docker/sshd_config /etc/ssh/sshd_config
EXPOSE 2222

EXPOSE 80

# Add an entrypoint to log a shell command
ENTRYPOINT ["/usr/local/bin/drupal-entrypoint"]

# Our entrypoint should handle the handoff to apache. Leaving this here JIC.
RUN echo "Returned to Dockerfile before apache handoff."
CMD ["apache2-foreground"]
