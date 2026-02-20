# ============================================
# Stage 1: Build PHP dependencies
# ============================================
FROM php:8.4-cli AS composer-builder

# Install GD and ZIP dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy composer files
COPY composer*.json composer.lock ./

# Install dependencies (with cache mount)
RUN --mount=type=cache,target=/tmp/cache \
    --mount=from=composer:2,src=/usr/bin/composer,target=/usr/bin/composer \
    composer install --prefer-dist --no-interaction --optimize-autoloader --no-dev --no-scripts

# ============================================
# Stage 2: Build Node.js assets (requires PHP for Wayfinder)
# ============================================
FROM php:8.4-cli AS node-builder

# Install Node.js and SQLite for Wayfinder
RUN apt-get update && apt-get install -y --no-install-recommends \
    nodejs \
    npm \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install npm dependencies
RUN --mount=type=cache,target=/root/.npm \
    npm ci --prefer-offline --no-audit

# Copy vendor for Wayfinder
COPY --from=composer-builder /app/vendor ./vendor

# Copy application files needed for build
COPY artisan composer.json ./
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY routes ./routes
COPY database ./database
COPY resources ./resources
COPY public ./public
COPY vite.config.ts tsconfig.json .env.example ./
COPY storage ./storage

# Create .env for artisan commands and generate Wayfinder types before build
RUN cp .env.example .env \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
    && touch database/database.sqlite \
    && php artisan key:generate \
    && php artisan wayfinder:generate --with-form \
    && npm run build:ssr

# ============================================
# Stage 3: Final production image
# ============================================
FROM php:8.4-apache

# Install only runtime dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    supervisor \
    cron \
    netcat-openbsd \
    tzdata \
    nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install PHP extensions (will install required libraries automatically)
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql mbstring exif pcntl bcmath gd zip redis intl \
    && rm -f /usr/local/bin/install-php-extensions

# Copy composer binary from official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV TZ="Asia/Jakarta"

# Enable Apache modules and set document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN a2enmod rewrite headers \
  && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
  && sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy security/header config for Apache
COPY docker/apache-security.conf /etc/apache2/conf-available/security-custom.conf
RUN a2enconf security-custom

# Configure PHP upload limits
RUN { \
        echo "upload_max_filesize = 20M"; \
        echo "post_max_size = 20M"; \
        echo "max_execution_time = 300"; \
        echo "max_input_time = 300"; \
        echo "memory_limit = 256M"; \
    } > /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# Copy configuration files
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/app-entrypoint.sh /usr/local/bin/app-entrypoint.sh
COPY docker/queue-worker.sh /usr/local/bin/queue-worker.sh
COPY docker/health-check.sh /usr/local/bin/health-check.sh
RUN chmod +x /usr/local/bin/app-entrypoint.sh /usr/local/bin/queue-worker.sh /usr/local/bin/health-check.sh

# Copy application files (leverage .dockerignore)
COPY --chown=www-data:www-data . .

# Copy built assets from node-builder
COPY --from=node-builder --chown=www-data:www-data /app/public/build ./public/build

# Copy SSR bundle from node-builder
COPY --from=node-builder --chown=www-data:www-data /app/bootstrap/ssr ./bootstrap/ssr

# Copy vendor from composer-builder
COPY --from=composer-builder --chown=www-data:www-data /app/vendor ./vendor

# Setup environment and directories
RUN mv .env.example .env \
    && mkdir -p storage/app/public storage/app/public/uploads/user_photos \
                storage/framework/cache storage/framework/sessions storage/framework/views \
                storage/logs bootstrap/cache public database \
    && touch database/database.sqlite \
    && chown -R www-data:www-data storage bootstrap/cache public database \
    && php artisan key:generate --force \
    && php artisan storage:link || true

# Setup cron for Laravel scheduler
RUN echo "* * * * * cd /var/www/html && /usr/local/bin/php artisan schedule:run >> /var/log/cron.log 2>&1" > /etc/cron.d/laravel-scheduler \
    && chmod 0644 /etc/cron.d/laravel-scheduler \
    && crontab /etc/cron.d/laravel-scheduler \
    && touch /var/log/cron.log \
    && chmod 666 /var/log/cron.log

# Create volume for persistent storage
VOLUME ["/var/www/html/storage/app/public"]

EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
  CMD /usr/local/bin/health-check.sh

ENTRYPOINT ["app-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
