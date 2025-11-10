# Event Access - Deployment Checklist

Use this checklist to verify your Event Access installation and prepare for deployment.

## ✅ Pre-Deployment Verification

### 1. File Structure
- [ ] All backend files present in `backend/` directory
- [ ] All frontend files present in `frontend/` directory
- [ ] Documentation files in `docs/` directory
- [ ] Docker configuration files present
- [ ] CI/CD workflow in `.github/workflows/`

### 2. Configuration Files
- [ ] `backend/.env.example` exists
- [ ] `backend/.env` created (copy from .env.example)
- [ ] `docker-compose.yml` configured
- [ ] `Makefile` present with all commands
- [ ] Frontend environment files configured

### 3. Dependencies
- [ ] `backend/composer.json` with all Laravel dependencies
- [ ] `frontend/package.json` with all Angular dependencies
- [ ] Docker images specified in Dockerfiles

## 🚀 Local Development Setup

### Step 1: Prerequisites
- [ ] Docker Desktop installed and running
- [ ] Git installed
- [ ] Ports available: 3306, 4200, 8000, 8025
- [ ] At least 4GB RAM available for Docker

### Step 2: Initial Setup
```bash
# Navigate to project
cd C:/Users/aidou/Downloads/eventaccess

# Create directory structure (Windows)
powershell -ExecutionPolicy Bypass -File setup-backend-dirs.ps1
powershell -ExecutionPolicy Bypass -File setup-frontend-dirs.ps1

# Copy environment file
cp backend/.env.example backend/.env
```

- [ ] Directories created successfully
- [ ] `.env` file created

### Step 3: Start Services
```bash
# Start Docker containers
make up

# Wait for services to start (30-60 seconds)
```

- [ ] MySQL container running
- [ ] Backend container running
- [ ] Frontend container running
- [ ] MailHog container running

### Step 4: Install Dependencies
```bash
# Install backend dependencies
make install

# This may take 2-5 minutes
```

- [ ] Composer dependencies installed
- [ ] No error messages

### Step 5: Database Setup
```bash
# Run migrations
make migrate

# Seed database
make seed
```

- [ ] Migrations ran successfully
- [ ] Admin user created
- [ ] Database tables created

### Step 6: Verify Services
- [ ] Frontend: http://localhost:4200 loads
- [ ] Backend: http://localhost:8000/api/health returns OK
- [ ] MailHog: http://localhost:8025 accessible
- [ ] No console errors in browser

## 🧪 Testing Checklist

### Manual Testing

#### Registration Flow
- [ ] Navigate to http://localhost:4200
- [ ] Fill registration form with valid data
- [ ] Submit form
- [ ] QR code displays
- [ ] Email appears in MailHog (http://localhost:8025)
- [ ] QR code is in email
- [ ] Can download QR code
- [ ] Can print QR code

#### Admin Login
- [ ] Navigate to http://localhost:4200/admin/login
- [ ] Login with admin@example.com / admin123
- [ ] Redirects to admin dashboard
- [ ] Participant list displays
- [ ] Can see registered participant

#### Admin Approval
- [ ] Click accept (✓) button on participant
- [ ] Status changes to "Accepté"
- [ ] Acceptance email appears in MailHog
- [ ] QR code is in acceptance email

#### QR Scanning
- [ ] Navigate to Scanner page
- [ ] Select camera from dropdown
- [ ] Start scanner
- [ ] Camera feed displays
- [ ] Scan QR code (or enter manually)
- [ ] Success message displays
- [ ] Avatar speaks welcome message
- [ ] Scan logged in database

### Automated Testing

#### Backend Tests
```bash
# Run PHPUnit tests
docker-compose exec backend php artisan test
```

- [ ] All tests pass
- [ ] No errors or warnings

#### Frontend E2E Tests
```bash
# Run Cypress tests
cd frontend
npx cypress run
```

- [ ] Registration tests pass
- [ ] Admin tests pass
- [ ] No failures

## 🔒 Security Checklist

### Development
- [ ] QR codes have HMAC signatures
- [ ] Admin routes protected by authentication
- [ ] CORS configured for frontend URL
- [ ] Rate limiting enabled
- [ ] Input validation on all forms
- [ ] SQL injection protection (Eloquent ORM)

### Production (Before Deployment)
- [ ] Change admin password from default
- [ ] Update `APP_KEY` in .env
- [ ] Update `QR_HMAC_SECRET` in .env
- [ ] Configure HTTPS
- [ ] Update `APP_URL` and `FRONTEND_URL`
- [ ] Disable debug mode (`APP_DEBUG=false`)
- [ ] Configure production SMTP
- [ ] Set up database backups
- [ ] Configure error logging
- [ ] Set up monitoring

## 📧 Email Configuration

### Development (MailHog)
- [ ] `MAIL_MAILER=smtp`
- [ ] `MAIL_HOST=mailhog`
- [ ] `MAIL_PORT=1025`
- [ ] Emails appear in http://localhost:8025

### Production (SMTP)
- [ ] Update `MAIL_MAILER` (smtp, sendmail, etc.)
- [ ] Update `MAIL_HOST` to production SMTP server
- [ ] Update `MAIL_PORT` (usually 587 or 465)
- [ ] Set `MAIL_USERNAME` and `MAIL_PASSWORD`
- [ ] Set `MAIL_ENCRYPTION` (tls or ssl)
- [ ] Set `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`
- [ ] Test email sending

## 🎥 Avatar Video Setup

### Preparation
- [ ] Video file prepared (MP4 format recommended)
- [ ] Video shows silent woman avatar
- [ ] Video loops seamlessly
- [ ] File size reasonable (<10MB)

### Installation
```bash
# Copy video to assets
cp /path/to/avatar-video.mp4 frontend/src/assets/avatar-video.mp4
```

- [ ] Video file in `frontend/src/assets/`
- [ ] File named `avatar-video.mp4`

### Configuration
Edit `frontend/src/app/components/speaker-avatar/speaker-avatar.component.html`:

```html
<!-- Uncomment this section -->
<video #videoElement 
       [src]="videoSrc" 
       [muted]="true" 
       [loop]="true"
       class="avatar-video">
</video>
```

- [ ] Video element uncommented
- [ ] Video plays when speaking
- [ ] Video loops correctly
- [ ] Audio (TTS) plays over video

## 🌐 Production Deployment

### Server Requirements
- [ ] Docker and Docker Compose installed
- [ ] Minimum 2GB RAM
- [ ] Minimum 10GB disk space
- [ ] Ports 80, 443 available
- [ ] Domain name configured (optional)
- [ ] SSL certificate (Let's Encrypt recommended)

### Environment Configuration
- [ ] Production `.env` file created
- [ ] All secrets updated
- [ ] Database credentials set
- [ ] SMTP configured
- [ ] URLs updated for production domain

### Database
- [ ] Production database created
- [ ] Database user created with proper permissions
- [ ] Migrations run
- [ ] Admin user seeded
- [ ] Backup strategy configured

### Deployment Steps
```bash
# On production server
git clone <repository>
cd eventaccess

# Configure environment
cp backend/.env.example backend/.env
nano backend/.env  # Update all values

# Start services
docker-compose -f docker-compose.prod.yml up -d

# Install dependencies
docker-compose exec backend composer install --optimize-autoloader --no-dev

# Set up database
docker-compose exec backend php artisan migrate --force
docker-compose exec backend php artisan db:seed --force

# Optimize
docker-compose exec backend php artisan config:cache
docker-compose exec backend php artisan route:cache
docker-compose exec backend php artisan view:cache
```

- [ ] All services running
- [ ] Application accessible
- [ ] HTTPS working
- [ ] Emails sending
- [ ] QR codes generating
- [ ] Scanner working

### Post-Deployment
- [ ] Change admin password
- [ ] Test all features
- [ ] Monitor logs
- [ ] Set up backups
- [ ] Configure monitoring
- [ ] Document any issues

## 📊 Monitoring Checklist

### Logs
- [ ] Backend logs: `docker-compose logs backend`
- [ ] Frontend logs: `docker-compose logs frontend`
- [ ] MySQL logs: `docker-compose logs mysql`
- [ ] Scan logs: `backend/storage/logs/scans.log`

### Health Checks
- [ ] Backend health: http://your-domain/api/health
- [ ] Database connection working
- [ ] Email sending working
- [ ] QR generation working
- [ ] Scanner working

### Performance
- [ ] Page load times acceptable (<3s)
- [ ] API response times acceptable (<500ms)
- [ ] Database queries optimized
- [ ] No memory leaks
- [ ] No excessive CPU usage

## 🐛 Troubleshooting

### Common Issues

**Port Already in Use**
```bash
# Check what's using the port
netstat -ano | findstr :8000

# Stop the process or change port in docker-compose.yml
```

**Database Connection Failed**
```bash
# Restart MySQL
docker-compose restart mysql

# Check logs
docker-compose logs mysql
```

**Frontend Not Loading**
```bash
# Check container status
docker ps

# Restart frontend
docker-compose restart frontend

# Check logs
docker-compose logs frontend
```

**Backend 500 Error**
```bash
# Check logs
docker-compose logs backend

# Regenerate app key
docker-compose exec backend php artisan key:generate

# Clear cache
docker-compose exec backend php artisan cache:clear
```

**Emails Not Sending**
- [ ] Check SMTP configuration in `.env`
- [ ] Check MailHog is running (dev)
- [ ] Check firewall rules (prod)
- [ ] Check SMTP credentials (prod)
- [ ] Check logs for errors

## ✅ Final Verification

### Functionality
- [ ] Users can register
- [ ] QR codes generate correctly
- [ ] Emails send successfully
- [ ] Admins can login
- [ ] Admins can accept/reject
- [ ] Scanner works with camera
- [ ] Avatar speaks welcome messages
- [ ] All features working as expected

### Performance
- [ ] Application responsive
- [ ] No errors in console
- [ ] No errors in logs
- [ ] Database queries efficient
- [ ] Memory usage acceptable

### Security
- [ ] HTTPS enabled (production)
- [ ] Admin password changed
- [ ] Secrets updated
- [ ] Debug mode disabled (production)
- [ ] Error reporting configured
- [ ] Backups configured

### Documentation
- [ ] README.md reviewed
- [ ] SETUP.md followed
- [ ] API documentation accessible
- [ ] Team trained on system
- [ ] Support process defined

## 🎉 Launch Checklist

- [ ] All tests passing
- [ ] All features verified
- [ ] Security hardened
- [ ] Performance optimized
- [ ] Monitoring configured
- [ ] Backups configured
- [ ] Documentation complete
- [ ] Team trained
- [ ] Support ready
- [ ] **READY TO LAUNCH!**

---

## 📞 Support

If you encounter issues:

1. Check this checklist
2. Review [SETUP.md](SETUP.md) troubleshooting
3. Check Docker logs: `make logs`
4. Review [docs/](docs/) for technical details
5. Check [CONTRIBUTING.md](CONTRIBUTING.md) for development help

---

**Good luck with your deployment!** 🚀
