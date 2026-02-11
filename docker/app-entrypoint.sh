#!/bin/bash
set -e

echo "Starting Laravel application setup..."

# Wait for database connection if needed
if [ -n "$DB_HOST" ]; then
    echo "Checking database connection to ${DB_HOST}:${DB_PORT:-5432}..."
    timeout 60 bash -c 'until nc -z ${DB_HOST} ${DB_PORT:-5432}; do echo "Waiting for database..."; sleep 2; done' && echo "Database is ready!" || echo "Database connection timeout, continuing anyway..."
fi

# Clear and optimize caches for better performance
echo "Optimizing Laravel caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan route:cache
php artisan view:cache
php artisan config:cache

# Create storage link for file uploads
echo "Creating storage link..."
php artisan storage:link || true

# Set proper permissions for Laravel directories
echo "Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache public/storage || true
chmod -R 775 storage bootstrap/cache public/storage || true

echo "Laravel setup completed successfully!"

# Execute the main container command
exec "$@"
