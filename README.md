# Event Access System

A comprehensive event management system for handling participant registration, QR code scanning, and access control for events with multiple access types (trade show, conference, or both).

## Features

### 🎫 Participant Management
- Online registration with email verification
- Support for different access types (trade show only, conference only, or both)
- PDF badge generation with QR codes
- Admin approval workflow

### 📱 QR Code Scanning
- Real-time QR code scanning using device camera
- Manual code entry fallback
- Access validation and logging
- Audio-visual feedback with virtual avatar

### 🎭 Virtual Avatar System
- Text-to-speech welcome messages
- Personalized greetings based on participant details
- Visual access status indicators
- Support for different access types

### 👨‍💼 Admin Dashboard
- Participant approval/rejection
- Real-time participant list with filtering
- Badge PDF download
- Scan history monitoring

### 🔒 Security Features
- QR code signature verification
- Rate limiting on API endpoints
- Secure authentication for admin access
- Input validation and sanitization

## Tech Stack

### Backend
- **Laravel 11** - PHP framework
- **MySQL** - Database
- **DomPDF** - PDF generation
- **Laravel Sanctum** - API authentication

### Frontend
- **Angular 17** - TypeScript framework
- **ZXing** - QR code scanning library
- **Web Speech API** - Text-to-speech functionality

### Infrastructure
- **Docker** - Containerization
- **Docker Compose** - Multi-service orchestration
- **Nginx** - Web server (production)

## Quick Start

### Prerequisites
- Docker and Docker Compose
- Git

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd eventaccess
   ```

2. **Environment Setup**
   ```bash
   # Copy environment files
   cp backend/.env.example backend/.env
   cp frontend/src/environments/environment.ts.example frontend/src/environments/environment.ts

   # Generate Laravel app key
   docker-compose run --rm backend php artisan key:generate
   ```

3. **Start the Application**
   ```bash
   # Build and start all services
   docker-compose up --build

   # Or run in background
   docker-compose up -d --build
   ```

4. **Database Setup**
   ```bash
   # Run migrations and seeders
   docker-compose exec backend php artisan migrate
   docker-compose exec backend php artisan db:seed
   ```

5. **Access the Application**
   - Frontend: http://localhost:4200
   - Backend API: http://localhost:8000
   - Admin Login: Use credentials from AdminSeeder

### Manual Installation (Alternative)

If you prefer not to use Docker, follow the manual installation guide in `MANUAL_INSTALLATION.md`.

## Usage

### For Participants
1. Visit the registration page
2. Fill out the registration form
3. Receive confirmation email with QR code
4. Download and print the badge
5. Present QR code at event entrance

### For Administrators
1. Login to admin dashboard
2. Review and approve/reject registrations
3. Monitor scan activity
4. Download participant badges

### For Event Staff
1. Access scanner interface
2. Select camera and start scanning
3. Present QR codes to camera
4. System provides audio-visual feedback

## API Documentation

The API follows RESTful conventions and is documented using OpenAPI 3.0 specification. See `docs/openapi.yaml` for detailed endpoint documentation.

### Key Endpoints

- `POST /api/v1/register` - Participant registration
- `POST /api/v1/scan` - QR code scanning
- `POST /api/v1/auth/admin/login` - Admin authentication
- `GET /api/v1/admin/participants` - List participants
- `POST /api/v1/admin/participants/{id}/accept` - Accept participant

## Configuration

### Environment Variables

#### Backend (.env)
```env
APP_NAME="Event Access"
APP_ENV=production
APP_KEY=base64:your-app-key
APP_DEBUG=false

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=eventaccess
DB_USERNAME=user
DB_PASSWORD=password

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@eventaccess.com"
MAIL_FROM_NAME="${APP_NAME}"

SANCTUM_STATEFUL_DOMAINS=localhost:4200
```

#### Frontend (environment.ts)
```typescript
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000/api/v1',
  apiBaseUrl: 'http://localhost:8000',
};
```

## Development

### Running Tests
```bash
# Backend tests
docker-compose exec backend php artisan test

# Frontend tests
docker-compose exec frontend npm test
```

### Code Quality
```bash
# Backend linting
docker-compose exec backend ./vendor/bin/phpcs

# Frontend linting
docker-compose exec frontend npm run lint
```

### Building for Production
```bash
# Build frontend
docker-compose exec frontend npm run build

# Build backend assets
docker-compose exec backend npm run build
```

## Deployment

See `DEPLOYMENT_CHECKLIST.md` for detailed deployment instructions.

### Production Checklist
- [ ] Environment variables configured
- [ ] SSL certificates installed
- [ ] Database backups configured
- [ ] Monitoring and logging set up
- [ ] Security headers configured
- [ ] Rate limiting enabled

## Security Considerations

- QR codes include cryptographic signatures
- API endpoints are rate-limited
- Admin authentication required for sensitive operations
- Input validation on all forms
- CSRF protection enabled
- Secure headers configured

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions:
- Check the documentation in the `docs/` directory
- Review existing issues on GitHub
- Create a new issue for bugs or feature requests

## Changelog

See `CHANGELOG.md` for version history and updates.
