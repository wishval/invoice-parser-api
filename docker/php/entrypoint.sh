#!/bin/bash
set -e

# Install Composer dependencies if vendor directory does not exist
if [ ! -d "vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Copy .env from .env.example if .env does not exist
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        echo "Creating .env from .env.example..."
        cp .env.example .env
    fi
fi

# Generate application key if APP_KEY is empty
if [ -f ".env" ] && grep -q "^APP_KEY=$" .env; then
    echo "Generating application key..."
    php artisan key:generate --no-interaction
fi

# Create SQLite database if it does not exist
if [ ! -f "database/database.sqlite" ] && [ -d "database" ]; then
    echo "Creating SQLite database..."
    touch database/database.sqlite
fi

# Run database migrations
echo "Running migrations..."
php artisan migrate --force

# Execute the container CMD
exec "$@"
