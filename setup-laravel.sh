#!/bin/bash

# Create Laravel project using Docker
echo "Setting up Laravel project..."

# Remove existing backend directory contents except Dockerfile and .env.example
find backend -mindepth 1 -not -name 'Dockerfile*' -not -name '.env.example' -delete

# Create Laravel project in a temporary container
docker run --rm -v $(pwd)/backend:/app composer:latest create-project laravel/laravel . "^10.0"

# Install additional packages
docker run --rm -v $(pwd)/backend:/app composer:latest require laravel/sanctum simplesoftwareio/simple-qrcode

echo "Laravel project setup complete!"
