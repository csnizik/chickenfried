#!/bin/bash

# Local build script for testing Docker image

set -e

IMAGE_NAME="acrasegrinprod01.azurecr.io/ars-apps-drupal-rep:latest"

echo "Building Docker image locally..."

# Build the image
docker build -t $IMAGE_NAME .

echo "Image built successfully: $IMAGE_NAME"

# Optional: Run the container locally for testing
if [ "$1" = "--run" ]; then
    echo "Starting container for local testing..."
    docker run -d \
        --name ars-apps-test \
        -p 8080:80 \
        -e DB_HOST=host.docker.internal \
        -e DB_NAME=drupal \
        -e DB_USER=drupal \
        -e DB_PASSWORD=drupal \
        -e DRUPAL_HASH_SALT=test-salt-change-in-production \
        -e SITE_HOSTNAME=localhost \
        $IMAGE_NAME
    
    echo "Container started on http://localhost:8080"
    echo "Stop with: docker stop ars-apps-test && docker rm ars-apps-test"
fi