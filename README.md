# Event Access

A complete web application for event registration, QR code generation, admin approval, and entry scanning with avatar TTS integration.

## 🚀 Quick Start

### With Docker (Recommended)

```bash
# Clone and setup
git clone <repository-url>
cd eventaccess

# Start the application
make up

# Seed database with admin user
make seed

# Access the application
# Frontend: http://localhost:4200
# Backend API: http://localhost:8000
# MailHog: http://localhost:8025
```

**See [QUICKSTART.md](QUICKSTART.md) for detailed Docker setup.**

### Without Docker (Manual Installation)

If you prefer to install manually or Docker is not available:

```bash
# Install dependencies
cd backend && composer install
cd ../frontend && npm install

# Configure and run
# See MANUAL_INSTALLATION.md for complete guide
```

**See [MANUAL_INSTALLATION.md](MANUAL_INSTALLATION.md) for detailed manual setup.**

## 🏗️ Architecture

- **Backend**: Laravel 10 (PHP 8.2) with MySQL 8
- **Frontend**: Angular 17 with TypeScript
- **QR Codes**: simplesoftwareio/simple-qrcode with HMAC signatures
- **Scanning**: @zxing/browser for camera-based QR scanning
- **TTS**: Browser Web Speech API (French)
- **Email**: Laravel Mail with MailHog for development
- **Auth**: Laravel Sanctum API tokens
- **Containerization**: Docker & Docker Compose

## 📁 Project Structure

```
eventaccess/
├── backend/           # Laravel API
├── frontend/          # Angular application
├── docs/             # Documentation
├── docker-compose.yml
├── Makefile
└── README.md
```

## 🔧 Environment Setup

### Prerequisites
- Docker & Docker Compose
- Make (optional, for convenience commands)

### Environment Variables

Copy `.env.example` files and configure:

**Backend (.env)**:
```env
APP_NAME=EventAccess
APP_URL=http://localhost:8000
DB_HOST=mysql
DB_DATABASE=event_access
MAIL_HOST=mailhog
FRONTEND_URL=http://localhost:4200
QR_HMAC_SECRET=your-secret-key-here
```

## 🎯 Features

### User Registration
- Multi-step registration form
- QR code generation with HMAC signatures
- Email delivery with embedded QR codes
- Print/download QR functionality

### Admin Dashboard
- Secure login with Sanctum tokens
- Participant management (accept/reject)
- Status filtering and search
- Bulk operations

### QR Scanner
- Camera-based QR code scanning
- Real-time validation and logging
- Entry tracking and history
- Invalid scan detection

### Avatar Integration
- Silent video avatar display
- Personalized TTS welcome messages
- French language support
- Graceful fallbacks

## 🧪 Testing

```bash
# Run all tests
make test

# Backend tests only
cd backend && php artisan test

# Frontend tests only
cd frontend && npm test

# E2E tests
cd frontend && npm run e2e
```

## 📊 API Documentation

OpenAPI specification available at: `docs/openapi.yaml`

### Key Endpoints

- `POST /api/v1/register` - User registration
- `GET /api/v1/participants` - List participants (admin)
- `POST /api/v1/scan` - QR code scanning
- `POST /api/v1/auth/login` - Admin authentication

## 🔐 Security

- HMAC-SHA256 signed QR codes
- Rate limiting on critical endpoints
- CORS protection
- Sanctum API authentication
- Input validation and sanitization

## 🚀 Deployment

### Development
```bash
make up    # Start all services
make down  # Stop all services
make seed  # Seed database
```

### Production
See `docs/deployment.md` for production deployment guidelines.

## 📝 Default Credentials

**Admin User**:
- Email: admin@example.com
- Password: admin123

## 📚 Documentation

- **[QUICKSTART.md](QUICKSTART.md)** - 5-minute Docker setup
- **[MANUAL_INSTALLATION.md](MANUAL_INSTALLATION.md)** - Manual installation without Docker
- **[SETUP.md](SETUP.md)** - Detailed Docker setup guide
- **[PROJECT_SUMMARY.md](PROJECT_SUMMARY.md)** - Complete project overview
- **[DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md)** - Pre-deployment verification
- **[CONTRIBUTING.md](CONTRIBUTING.md)** - Development guidelines
- **[docs/openapi.yaml](docs/openapi.yaml)** - API specification
- **[docs/architecture.md](docs/architecture.md)** - System architecture
- **[DOCUMENTATION_INDEX.md](DOCUMENTATION_INDEX.md)** - Complete documentation guide

## 🤝 Contributing

See `CONTRIBUTING.md` for development guidelines.

## 📄 License

This project is licensed under the MIT License.
