# Event Access - Manual Installation Guide

This guide explains how to install and run Event Access **without Docker**, using native installations of PHP, MySQL, and Node.js.

## 📋 Table of Contents

- [Prerequisites](#prerequisites)
- [Backend Installation](#backend-installation)
- [Frontend Installation](#frontend-installation)
- [Database Setup](#database-setup)
- [Running the Application](#running-the-application)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Production Deployment](#production-deployment)

---

## Prerequisites

### Required Software

#### 1. PHP 8.2 or Higher
**Windows**:
```bash
# Download from https://windows.php.net/download/
# Or use Chocolatey
choco install php --version=8.2

# Verify installation
php -v
```

**Linux (Ubuntu/Debian)**:
```bash
sudo apt update
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-bcmath php8.2-gd

# Verify installation
php -v
```

**macOS**:
```bash
# Using Homebrew
brew install php@8.2

# Verify installation
php -v
```

#### 2. Composer (PHP Package Manager)
```bash
# Download from https://getcomposer.org/download/

# Verify installation
composer --version
```

#### 3. MySQL 8.0 or Higher
**Windows**:
```bash
# Download from https://dev.mysql.com/downloads/installer/
# Or use Chocolatey
choco install mysql

# Verify installation
mysql --version
```

**Linux (Ubuntu/Debian)**:
```bash
sudo apt install -y mysql-server mysql-client

# Secure installation
sudo mysql_secure_installation

# Verify installation
mysql --version
```

**macOS**:
```bash
brew install mysql@8.0

# Start MySQL
brew services start mysql@8.0

# Verify installation
mysql --version
```

#### 4. Node.js 20.x and npm
**Windows**:
```bash
# Download from https://nodejs.org/
# Or use Chocolatey
choco install nodejs --version=20.10.0

# Verify installation
node -v
npm -v
```

**Linux (Ubuntu/Debian)**:
```bash
# Using NodeSource repository
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Verify installation
node -v
npm -v
```

**macOS**:
```bash
brew install node@20

# Verify installation
node -v
npm -v
```

#### 5. Angular CLI
```bash
npm install -g @angular/cli@17

# Verify installation
ng version
```

### Optional (Recommended)

- **Git**: For version control
- **VS Code**: Recommended code editor
- **Postman**: For API testing

---

## Backend Installation

### Step 1: Navigate to Backend Directory

```bash
cd C:/Users/aidou/Downloads/eventaccess/backend
```

### Step 2: Install PHP Dependencies

```bash
# Install Composer dependencies
composer install

# This will install:
# - Laravel 10
# - Laravel Sanctum
# - Simple QR Code library
# - PHPUnit and other dev dependencies
```

### Step 3: Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Edit .env file with your settings
# Use notepad, nano, vim, or your preferred editor
notepad .env
```

**Update these values in `.env`**:

```env
APP_NAME="Event Access"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

# Frontend URL for CORS
FRONTEND_URL=http://localhost:4200

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eventaccess
DB_USERNAME=root
DB_PASSWORD=your_mysql_password

# Mail Configuration (for local development)
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@eventaccess.local"
MAIL_FROM_NAME="${APP_NAME}"

# QR Code Security
QR_HMAC_SECRET=your-random-secret-key-change-this-in-production

# Session & Cache
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
```

### Step 4: Generate Application Key

```bash
php artisan key:generate
```

This will automatically update the `APP_KEY` in your `.env` file.

### Step 5: Create Storage Directories

```bash
# Windows
mkdir storage\logs
mkdir storage\framework\cache
mkdir storage\framework\sessions
mkdir storage\framework\views
mkdir bootstrap\cache

# Linux/macOS
mkdir -p storage/logs
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p bootstrap/cache
```

### Step 6: Set Permissions (Linux/macOS only)

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

---

## Database Setup

### Step 1: Start MySQL Service

**Windows**:
```bash
# Start MySQL service
net start MySQL80

# Or from Services app (services.msc)
```

**Linux**:
```bash
sudo systemctl start mysql
sudo systemctl enable mysql
```

**macOS**:
```bash
brew services start mysql@8.0
```

### Step 2: Create Database

```bash
# Login to MySQL
mysql -u root -p

# Enter your MySQL root password
```

**In MySQL console**:
```sql
-- Create database
CREATE DATABASE eventaccess CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user (optional, for better security)
CREATE USER 'eventaccess_user'@'localhost' IDENTIFIED BY 'your_secure_password';

-- Grant privileges
GRANT ALL PRIVILEGES ON eventaccess.* TO 'eventaccess_user'@'localhost';

-- Flush privileges
FLUSH PRIVILEGES;

-- Exit
EXIT;
```

**Update `.env` if you created a dedicated user**:
```env
DB_USERNAME=eventaccess_user
DB_PASSWORD=your_secure_password
```

### Step 3: Run Migrations

```bash
# Navigate to backend directory
cd C:/Users/aidou/Downloads/eventaccess/backend

# Run migrations
php artisan migrate

# You should see:
# Migration table created successfully.
# Migrating: 2024_01_01_000001_create_participants_table
# Migrated:  2024_01_01_000001_create_participants_table
# Migrating: 2024_01_01_000002_create_scans_table
# Migrated:  2024_01_01_000002_create_scans_table
# Migrating: 2024_01_01_000003_create_admins_table
# Migrated:  2024_01_01_000003_create_admins_table
```

### Step 4: Seed Database

```bash
# Seed admin user
php artisan db:seed

# This creates:
# - Admin user: admin@example.com / admin123
```

---

## Frontend Installation

### Step 1: Navigate to Frontend Directory

```bash
cd C:/Users/aidou/Downloads/eventaccess/frontend
```

### Step 2: Install Node Dependencies

```bash
# Install npm packages
npm install

# This will install:
# - Angular 17
# - @zxing/browser (QR scanner)
# - Cypress (E2E testing)
# - And all other dependencies
```

### Step 3: Configure Environment

The default configuration should work, but verify:

**File**: `frontend/src/environments/environment.ts`
```typescript
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000/api/v1',
  apiBaseUrl: 'http://localhost:8000',
};
```

**File**: `frontend/src/environments/environment.prod.ts`
```typescript
export const environment = {
  production: true,
  apiUrl: 'https://your-domain.com/api/v1',
  apiBaseUrl: 'https://your-domain.com',
};
```

---

## Running the Application

### Step 1: Start Backend Server

**Terminal 1** (Backend):
```bash
cd C:/Users/aidou/Downloads/eventaccess/backend

# Start Laravel development server
php artisan serve

# Server will start on http://localhost:8000
# Keep this terminal open
```

**Verify backend is running**:
```bash
# In another terminal
curl http://localhost:8000/api/health

# Should return: {"status":"ok"}
```

### Step 2: Start Frontend Server

**Terminal 2** (Frontend):
```bash
cd C:/Users/aidou/Downloads/eventaccess/frontend

# Start Angular development server
ng serve

# Or with specific port
ng serve --port 4200

# Server will start on http://localhost:4200
# Keep this terminal open
```

**Verify frontend is running**:
- Open browser: http://localhost:4200
- You should see the Event Access registration page

### Step 3: Access the Application

- **Frontend**: http://localhost:4200
- **Backend API**: http://localhost:8000/api/v1
- **Health Check**: http://localhost:8000/api/health
- **Admin Login**: http://localhost:4200/admin/login
  - Email: `admin@example.com`
  - Password: `admin123`

---

## Email Configuration (Optional)

For local development, you can use one of these options:

### Option 1: Log Emails to File (Simplest)

**Update `.env`**:
```env
MAIL_MAILER=log
```

Emails will be written to `storage/logs/laravel.log`

### Option 2: Use Mailtrap (Free Service)

1. Sign up at https://mailtrap.io/
2. Get SMTP credentials
3. **Update `.env`**:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
```

### Option 3: Install MailHog Locally

**Windows**:
```bash
# Download from https://github.com/mailhog/MailHog/releases
# Run MailHog.exe
```

**Linux/macOS**:
```bash
# Install MailHog
go install github.com/mailhog/MailHog@latest

# Run MailHog
~/go/bin/MailHog
```

**Update `.env`**:
```env
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=1025
```

Access MailHog UI: http://localhost:8025

---

## Testing

### Backend Tests

```bash
cd C:/Users/aidou/Downloads/eventaccess/backend

# Run all tests
php artisan test

# Run specific test
php artisan test --filter=RegistrationTest

# Run with coverage
php artisan test --coverage
```

### Frontend Unit Tests

```bash
cd C:/Users/aidou/Downloads/eventaccess/frontend

# Run unit tests
npm run test

# Run in watch mode
npm run test -- --watch
```

### Frontend E2E Tests

```bash
cd C:/Users/aidou/Downloads/eventaccess/frontend

# Make sure backend and frontend are running first!

# Run Cypress tests (headless)
npx cypress run

# Open Cypress UI
npx cypress open
```

---

## Troubleshooting

### Backend Issues

#### "Class 'PDO' not found"
**Solution**: Enable PDO extension in `php.ini`
```ini
extension=pdo_mysql
```

#### "Permission denied" on storage directory
**Solution** (Linux/macOS):
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

#### "SQLSTATE[HY000] [2002] Connection refused"
**Solution**: 
- Verify MySQL is running
- Check DB credentials in `.env`
- Try `DB_HOST=127.0.0.1` instead of `localhost`

#### "419 Page Expired" on forms
**Solution**: Clear cache
```bash
php artisan config:clear
php artisan cache:clear
```

### Frontend Issues

#### "Port 4200 is already in use"
**Solution**: Use different port
```bash
ng serve --port 4201
```

Then update `FRONTEND_URL` in backend `.env`

#### "Cannot find module '@angular/core'"
**Solution**: Reinstall dependencies
```bash
rm -rf node_modules package-lock.json
npm install
```

#### CORS errors in browser console
**Solution**: 
- Verify `FRONTEND_URL` in backend `.env` matches frontend URL
- Clear Laravel config cache: `php artisan config:clear`

### Database Issues

#### "Access denied for user"
**Solution**: 
- Verify MySQL credentials
- Reset MySQL password if needed
- Check user has proper privileges

#### "Unknown database 'eventaccess'"
**Solution**: Create database
```bash
mysql -u root -p -e "CREATE DATABASE eventaccess;"
```

---

## Production Deployment

### Backend (Apache/Nginx)

#### 1. Install Dependencies
```bash
composer install --optimize-autoloader --no-dev
```

#### 2. Configure Environment
```bash
cp .env.example .env
nano .env
```

Update for production:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
FRONTEND_URL=https://your-domain.com

DB_HOST=your-db-host
DB_DATABASE=your-db-name
DB_USERNAME=your-db-user
DB_PASSWORD=your-secure-password

MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-smtp-user
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
```

#### 3. Optimize Laravel
```bash
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan db:seed --force
```

#### 4. Set Permissions
```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

#### 5. Configure Web Server

**Apache** (`.htaccess` already included):
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot /path/to/eventaccess/backend/public

    <Directory /path/to/eventaccess/backend/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

**Nginx**:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/eventaccess/backend/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Frontend (Production Build)

#### 1. Build for Production
```bash
cd frontend
npm run build

# Or with specific configuration
ng build --configuration production
```

#### 2. Deploy Build Files
```bash
# Build output is in: frontend/dist/eventaccess
# Copy to web server root
cp -r dist/eventaccess/* /var/www/html/
```

#### 3. Configure Web Server

**Nginx**:
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html;

    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }
}
```

---

## Quick Reference

### Common Commands

**Backend**:
```bash
# Start server
php artisan serve

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Clear cache
php artisan cache:clear
php artisan config:clear

# Run tests
php artisan test
```

**Frontend**:
```bash
# Start dev server
ng serve

# Build for production
ng build --configuration production

# Run tests
npm run test

# Run E2E tests
npx cypress run
```

**Database**:
```bash
# Login to MySQL
mysql -u root -p

# Create database
mysql -u root -p -e "CREATE DATABASE eventaccess;"

# Import SQL
mysql -u root -p eventaccess < backup.sql
```

---

## System Requirements

### Minimum
- PHP 8.2+
- MySQL 8.0+
- Node.js 20.x
- 2GB RAM
- 5GB disk space

### Recommended
- PHP 8.2+ with OPcache
- MySQL 8.0+ with InnoDB
- Node.js 20.x LTS
- 4GB RAM
- 10GB disk space
- SSD storage

---

## Next Steps

1. ✅ Follow this guide to install manually
2. ✅ Test all features
3. ✅ Review [SETUP.md](SETUP.md) for additional configuration
4. ✅ Check [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md) before production
5. ✅ Read [CONTRIBUTING.md](CONTRIBUTING.md) if you want to contribute

---

## Support

For issues with manual installation:
- Check [SETUP.md](SETUP.md) troubleshooting section
- Review this guide's troubleshooting section
- Check Laravel documentation: https://laravel.com/docs/10.x
- Check Angular documentation: https://angular.io/docs

---

**Last Updated**: January 7, 2025  
**Version**: 1.0.0
