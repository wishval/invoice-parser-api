FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libmagickwand-dev \
    libsqlite3-dev \
    ghostscript \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite \
        zip \
        bcmath \
        gd \
        pcntl

# Install Imagick via PECL
RUN pecl install imagick-3.8.1 \
    && docker-php-ext-enable imagick

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Allow ImageMagick to process PDF files
RUN for dir in /etc/ImageMagick-6 /etc/ImageMagick-7; do \
        if [ -d "$dir" ]; then \
            sed -i 's/<policy domain="coder" rights="none" pattern="PDF" \/>/<policy domain="coder" rights="read|write" pattern="PDF" \/>/g' "$dir/policy.xml"; \
        fi; \
    done

# Copy Composer from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP upload limits (buffer above 10MB Laravel validation)
RUN echo 'upload_max_filesize = 20M\npost_max_size = 20M' > /usr/local/etc/php/conf.d/uploads.ini

# Ensure Ghostscript is accessible from PHP-FPM worker processes
RUN echo 'env[PATH] = /usr/local/bin:/usr/bin:/bin' >> /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# Ensure www-data owns the working directory
RUN chown -R www-data:www-data /var/www/html

# Copy entrypoint script
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Run as www-data for security; entrypoint handles any root-needed operations
USER www-data

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
