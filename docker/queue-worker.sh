#!/bin/bash
set -e

echo "[$(date)] Starting Laravel Queue Worker..."

# Wait a bit for application to be fully ready
sleep 2

# Check if we can connect to database before starting worker
MAX_RETRIES=10
RETRY_COUNT=0

while [ $RETRY_COUNT -lt $MAX_RETRIES ]; do
    echo "[$(date)] Attempt $((RETRY_COUNT + 1))/$MAX_RETRIES: Testing database connection..."
    
    if php /var/www/html/artisan db:show 2>&1 | grep -q "Connection:"; then
        echo "[$(date)] Database connection successful!"
        break
    fi
    
    RETRY_COUNT=$((RETRY_COUNT + 1))
    
    if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
        echo "[$(date)] Database not ready, waiting 3 seconds..."
        sleep 3
    else
        echo "[$(date)] ERROR: Could not connect to database after $MAX_RETRIES attempts"
        echo "[$(date)] Attempting to start worker anyway..."
    fi
done

# Start the queue worker
echo "[$(date)] Starting queue worker process..."
exec php /var/www/html/artisan queue:work --verbose --sleep=3 --tries=3 --max-time=3600 --timeout=60
