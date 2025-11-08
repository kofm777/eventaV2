# Changelog

All notable changes to the Event Access project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Planned
- Queue system for asynchronous email sending
- CSV export of participants
- Bulk accept/reject operations
- Analytics dashboard
- Password reset functionality
- Audit logging for admin actions

## [1.0.0] - 2025-01-07

### Added
- Initial release of Event Access application
- Participant registration system with form validation
- QR code generation with HMAC-SHA256 signatures
- Email notifications for registration and status changes
- Admin authentication with Laravel Sanctum
- Admin dashboard for participant management
- Accept/reject workflow for participant approvals
- QR code scanner with camera integration (@zxing/browser)
- Speaker avatar component with TTS (Web Speech API)
- Responsive design for mobile and desktop
- Docker Compose setup for development
- MySQL database with migrations and seeders
- MailHog integration for email testing
- Rate limiting on API endpoints
- CORS configuration for security
- Comprehensive API documentation (OpenAPI 3.0)
- Architecture documentation with Mermaid diagrams
- PHPUnit tests for backend
- Cypress E2E tests for frontend
- GitHub Actions CI/CD pipeline
- Health check endpoint

### Backend Features
- Laravel 10 with PHP 8.2
- RESTful API with JSON responses
- Eloquent ORM for database operations
- Request validation with French error messages
- QR code service with signature verification
- Email templates with Blade
- Database seeders for admin user
- Logging with dedicated scan log channel
- Middleware for authentication and rate limiting

### Frontend Features
- Angular 17 with standalone components
- Reactive forms with validation
- HTTP client service for API calls
- Authentication service with token management
- Route guards for protected pages
- QR code scanning with camera
- Text-to-speech welcome messages
- Responsive CSS with mobile support
- Loading states and error handling

### Infrastructure
- Docker containers for all services
- MySQL 8.0 database
- MailHog SMTP server
- Makefile for common commands
- Environment variable configuration
- Volume mounts for development

### Documentation
- README with project overview
- SETUP guide with troubleshooting
- OpenAPI specification
- Architecture diagrams
- Assumptions and design decisions
- Contributing guidelines
- Progress tracking document

### Security
- HMAC-signed QR codes
- Token-based authentication
- Password hashing with Bcrypt
- CSRF protection
- Rate limiting
- CORS restrictions
- Input validation and sanitization

### Testing
- PHPUnit unit and feature tests
- Cypress E2E tests
- Test database configuration
- CI/CD pipeline with automated tests
- Code coverage reporting

## [0.1.0] - 2025-01-01

### Added
- Project initialization
- Basic project structure
- Technology stack selection
- Requirements documentation

---

## Version History

### Version 1.0.0 (Current)
**Release Date**: January 7, 2025

**Highlights**:
- Complete event registration system
- QR code-based access control
- Admin approval workflow
- Camera-based QR scanning
- TTS welcome messages

**Statistics**:
- Backend: 15+ controllers, models, and services
- Frontend: 5 main components
- Database: 3 tables with relationships
- Tests: 10+ test files
- Documentation: 5 comprehensive documents

**Known Issues**:
- Email sending is synchronous (should be queued)
- No bulk operations for admin
- Limited accessibility features
- No export functionality

**Upgrade Notes**:
- First release, no upgrade path needed
- Default admin credentials: admin@example.com / admin123
- Change admin password after first login

---

## Future Releases

### Version 1.1.0 (Planned)
**Target Date**: Q1 2025

**Planned Features**:
- Queue system for emails
- CSV export
- Bulk operations
- Enhanced search
- Password reset

### Version 2.0.0 (Planned)
**Target Date**: Q2 2025

**Planned Features**:
- Multi-event support
- Analytics dashboard
- Advanced security (2FA)
- Mobile app
- Real-time notifications

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for information on how to contribute to this project.

## License

This project is proprietary software. All rights reserved.

## Support

For issues, questions, or contributions, please refer to the project repository or contact the development team.
