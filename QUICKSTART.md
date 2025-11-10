# Event Access - Quick Start Guide

Get Event Access up and running in 5 minutes!

## Prerequisites

- ✅ Docker Desktop installed and running
- ✅ Git installed
- ✅ Ports 3306, 4200, 8000, 8025 available

## Installation Steps

### 1. Clone or Navigate to Project

```bash
cd C:/Users/aidou/Downloads/eventaccess
```

### 2. Create Directory Structure (Windows)

```powershell
# Run the setup scripts
powershell -ExecutionPolicy Bypass -File setup-backend-dirs.ps1
powershell -ExecutionPolicy Bypass -File setup-frontend-dirs.ps1
```

### 3. Configure Environment

```bash
# Copy environment file
cp backend/.env.example backend/.env

# The default configuration works for local development
# No changes needed for quick start!
```

### 4. Start the Application

```bash
# Start all Docker containers
make up

# This will start:
# - MySQL database (port 3306)
# - Laravel backend (port 8000)
# - Angular frontend (port 4200)
# - MailHog email server (port 8025)
```

### 5. Install Dependencies

```bash
# Install backend dependencies
make install

# This runs composer install inside the Docker container
```

### 6. Set Up Database

```bash
# Run database migrations
make migrate

# Seed the database with admin user
make seed
```

## Access the Application

### Frontend (User Interface)
**URL**: http://localhost:4200

**Features**:
- Register for the event
- View QR code
- Download/print QR code

### Backend API
**URL**: http://localhost:8000

**Health Check**: http://localhost:8000/api/health

### Admin Dashboard
**URL**: http://localhost:4200/admin/login

**Default Credentials**:
- Email: `admin@example.com`
- Password: `admin123`

**Features**:
- View all participants
- Accept/reject registrations
- Scan QR codes
- View scan history

### Email Testing (MailHog)
**URL**: http://localhost:8025

**Purpose**: View all emails sent by the application

## Quick Test

### Test Registration Flow

1. **Open Frontend**: http://localhost:4200
2. **Fill Registration Form**:
   - First Name: Jean
   - Last Name: Dupont
   - Gender: Homme
   - Email: jean.dupont@test.com
   - Access Type: Foire et Conférence
3. **Submit**: Click "S'inscrire"
4. **View QR Code**: Your QR code will be displayed
5. **Check Email**: Open http://localhost:8025 to see the email

### Test Admin Flow

1. **Login**: http://localhost:4200/admin/login
   - Email: admin@example.com
   - Password: admin123
2. **View Participants**: You'll see Jean Dupont with status "En attente"
3. **Accept Registration**: Click the ✓ button
4. **Check Email**: Jean will receive an acceptance email

### Test QR Scanner

1. **Navigate to Scanner**: Click "Scanner" in the navigation
2. **Select Camera**: Choose your camera from the dropdown
3. **Start Scanner**: Click "Démarrer le scanner"
4. **Scan QR Code**: Point camera at the QR code
5. **Hear Welcome**: The avatar will speak a welcome message

## Common Commands

```bash
# Start application
make up

# Stop application
make down

# View logs
make logs

# Run tests
make test

# Access backend shell
make backend-shell

# Access frontend shell
make frontend-shell

# Restart services
make down && make up
```

## Troubleshooting

### Port Already in Use

```bash
# Check what's using the port
netstat -ano | findstr :8000

# Stop the process or change port in docker-compose.yml
```

### Database Connection Error

```bash
# Restart MySQL container
docker-compose restart mysql

# Wait 10 seconds, then try again
```

### Frontend Not Loading

```bash
# Check if container is running
docker ps

# Restart frontend container
docker-compose restart frontend

# Check logs
docker-compose logs frontend
```

### Backend 500 Error

```bash
# Check backend logs
docker-compose logs backend

# Ensure .env file exists
ls backend/.env

# Regenerate app key
docker-compose exec backend php artisan key:generate
```

## Next Steps

### For Development

1. **Read Documentation**:
   - [SETUP.md](SETUP.md) - Detailed setup guide
   - [docs/architecture.md](docs/architecture.md) - System architecture
   - [CONTRIBUTING.md](CONTRIBUTING.md) - How to contribute

2. **Explore the Code**:
   - Backend: `backend/app/`
   - Frontend: `frontend/src/app/`
   - Tests: `backend/tests/`, `frontend/cypress/`

3. **Run Tests**:
   ```bash
   # Backend tests
   docker-compose exec backend php artisan test
   
   # Frontend E2E tests
   cd frontend && npx cypress open
   ```

### For Production

1. **Change Admin Password**:
   - Login as admin
   - Update password in database

2. **Configure SMTP**:
   - Update `MAIL_*` variables in `backend/.env`
   - Test email sending

3. **Add Avatar Video**:
   - Place video file at `frontend/src/assets/avatar-video.mp4`
   - Uncomment video element in `speaker-avatar.component.html`

4. **Enable HTTPS**:
   - Configure SSL certificates
   - Update `FRONTEND_URL` in `.env`

5. **Set Up Queue**:
   - Configure Redis
   - Update `QUEUE_CONNECTION=redis`
   - Run queue worker

## Features Overview

### ✅ Registration
- Multi-field form with validation
- QR code generation
- Email notification
- French language

### ✅ Admin Dashboard
- Secure login
- Participant list with filters
- Accept/reject workflow
- Email notifications

### ✅ QR Scanner
- Camera-based scanning
- Manual code entry
- Signature verification
- Scan logging

### ✅ Speaker Avatar
- Text-to-speech welcome
- French voice
- Personalized messages
- Visual feedback

## Support

### Documentation
- [README.md](README.md) - Project overview
- [SETUP.md](SETUP.md) - Detailed setup
- [docs/openapi.yaml](docs/openapi.yaml) - API documentation
- [docs/architecture.md](docs/architecture.md) - Architecture
- [docs/assumptions.md](docs/assumptions.md) - Design decisions

### Getting Help
- Check existing documentation
- Review troubleshooting section
- Check Docker logs
- Verify environment configuration

## Success Checklist

- [ ] Docker containers running (`docker ps`)
- [ ] Frontend accessible (http://localhost:4200)
- [ ] Backend accessible (http://localhost:8000/api/health)
- [ ] MailHog accessible (http://localhost:8025)
- [ ] Can register a participant
- [ ] Can login as admin
- [ ] Can accept/reject participants
- [ ] Can scan QR codes
- [ ] Emails appear in MailHog

## What's Next?

Once you have the application running:

1. **Explore Features**: Try all the workflows
2. **Read Documentation**: Understand the architecture
3. **Run Tests**: Ensure everything works
4. **Customize**: Adapt to your needs
5. **Deploy**: Follow production checklist

---

**Congratulations!** 🎉 You now have Event Access running locally!

For detailed information, see [SETUP.md](SETUP.md) and other documentation files.
