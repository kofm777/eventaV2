.PHONY: up down build seed test clean logs

# Start all services
up:
	docker-compose up --build -d

# Stop all services
down:
	docker-compose down

# Build all services
build:
	docker-compose build

# Seed the database
seed:
	docker-compose exec backend php artisan migrate:fresh --seed

# Run all tests
test:
	@echo "Running backend tests..."
	docker-compose exec backend php artisan test
	@echo "Running frontend tests..."
	docker-compose exec frontend npm test -- --watch=false
	@echo "Running E2E tests..."
	docker-compose exec frontend npm run e2e:headless

# Clean up containers and volumes
clean:
	docker-compose down -v
	docker system prune -f

# Show logs
logs:
	docker-compose logs -f

# Backend specific commands
backend-shell:
	docker-compose exec backend bash

frontend-shell:
	docker-compose exec frontend bash

# Database commands
db-migrate:
	docker-compose exec backend php artisan migrate

db-rollback:
	docker-compose exec backend php artisan migrate:rollback

db-fresh:
	docker-compose exec backend php artisan migrate:fresh

# Laravel commands
artisan:
	docker-compose exec backend php artisan $(cmd)

# Install dependencies
install:
	docker-compose exec backend composer install
	docker-compose exec frontend npm install

# Generate application key
key-generate:
	docker-compose exec backend php artisan key:generate

# Clear caches
cache-clear:
	docker-compose exec backend php artisan cache:clear
	docker-compose exec backend php artisan config:clear
	docker-compose exec backend php artisan route:clear
