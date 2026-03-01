#!/bin/bash

# ARS Apps Drupal Deployment Script
# This script handles post-deployment tasks

set -e

echo "Starting deployment tasks..."

# Change to web directory
cd /var/www/html/web

# Run database updates if flag is set
if [ "${RUN_DRUSH_UPDATES}" = "true" ]; then
    echo "Running database updates..."
    ../vendor/bin/drush updatedb -y
    
    echo "Importing configuration..."
    ../vendor/bin/drush config-import -y
    
    echo "Rebuilding cache..."
    ../vendor/bin/drush cache-rebuild
    
    # Clear the flag
    unset RUN_DRUSH_UPDATES
fi

# Clear cache if flag is set
if [ "${CLEAR_CACHE}" = "true" ]; then
    echo "Clearing Drupal cache..."
    ../vendor/bin/drush cache-rebuild
    
    # Clear the flag
    unset CLEAR_CACHE
fi

# Health check
echo "Performing health check..."
curl -f http://localhost/health || {
    echo "Health check failed!"
    exit 1
}

echo "Deployment tasks completed successfully!"

# Keep container running
exec "$@"