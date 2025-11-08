# Event Access - Complete Setup Guide

## 🚀 Quick Setup

This guide will help you set up the Event Access application from scratch.

### Prerequisites

- Docker Desktop installed and running
- Git
- Make (optional, for convenience commands)

### Step 1: Initial Setup

The Laravel backend structure has been created manually. To complete the setup:

1. **Install Laravel dependencies using Docker:**

```bash
# Create a temporary container to install Composer dependencies
docker run --rm -v ${PWD}/backend:/app composer:latest install
```

2. **Copy environment file:**

```bash
cd backend
cp .env.example .env
```

3. **Generate application key:**

```bash
docker run --rm -v ${PWD}/backend:/app php:8.2-cli php artisan key:generate
```

### Step 2: Start the Application

```bash
# From the project root
docker-compose up --build -d
```

### Step 3: Run Migrations and Seed Database

```bash
# Run migrations
docker-compose exec backend php artisan migrate

# Seed the database with admin user
docker-compose exec backend php artisan db:seed
```

### Step 4: Access the Application

- **Frontend:** http://localhost:4200
- **Backend API:** http://localhost:8000
- **MailHog (Email testing):** http://localhost:8025

### Default Admin Credentials

- **Email:** admin@example.com
- **Password:** admin123

## 📁 Project Structure

```
eventaccess/
├── backend/              # Laravel 10 API
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   └── Requests/
│   │   ├── Models/
│   │   ├── Mail/
│   │   └── Services/
│   ├── config/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   └── tests/
├── frontend/             # Angular 17 application
├── docs/                 # Documentation
├── docker-compose.yml
└── Makefile
```

## 🔧 Development Commands

### Using Make

```bash
make up          # Start all services
make down        # Stop all services
make seed        # Seed database
make test        # Run all tests
make logs        # View logs
```

### Using Docker Compose Directly

```bash
# Start services
docker-compose up -d

# Stop services
docker-compose down

# View logs
docker-compose logs -f backend

# Run artisan commands
docker-compose exec backend php artisan [command]

# Access backend shell
docker-compose exec backend bash
```

## 🧪 Testing

### Backend Tests

```bash
docker-compose exec backend php artisan test
```

### Frontend Tests

```bash
docker-compose exec frontend npm test
```

## 🐛 Troubleshooting

### Issue: Composer dependencies not installed

**Solution:**
```bash
docker run --rm -v ${PWD}/backend:/app composer:latest install
```

### Issue: Database connection failed

**Solution:**
1. Ensure MySQL container is running: `docker-compose ps`
2. Check database credentials in `.env`
3. Wait a few seconds for MySQL to fully start

### Issue: Permission errors

**Solution:**
```bash
# Fix storage permissions
docker-compose exec backend chmod -R 775 storage bootstrap/cache
```

### Issue: Application key not set

**Solution:**
```bash
docker-compose exec backend php artisan key:generate
```

## 📝 Environment Variables

### Backend (.env)

Key variables to configure:

```env
APP_NAME=EventAccess
APP_URL=http://localhost:8000
DB_HOST=mysql
DB_DATABASE=event_access
DB_USERNAME=root
DB_PASSWORD=secret
MAIL_HOST=mailhog
FRONTEND_URL=http://localhost:4200
QR_HMAC_SECRET=your-very-long-random-secret-key
```

## 🔐 Security Notes

- Change `QR_HMAC_SECRET` to a strong random value in production
- Update admin password after first login
- Configure proper CORS settings for production
- Use HTTPS in production

## 📚 Next Steps

1. Complete the Angular frontend setup
2. Run tests to verify everything works
3. Review API documentation in `docs/openapi.yaml`
4. Customize email templates as needed

## 🆘 Support

For issues or questions, please check:
- README.md for general information
- docs/architecture.md for system architecture
- docs/assumptions.md for design decisions
