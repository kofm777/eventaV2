# Event Access - Assumptions and Design Decisions

## Project Assumptions

### 1. Event Scope
- **Single Event**: The system is designed for a single event with two access types (foire, conference)
- **Event Duration**: No specific start/end dates enforced; system is always active
- **Capacity**: No maximum participant limit enforced
- **Registration Period**: Open registration with no cutoff date

### 2. User Roles
- **Participants**: Public users who register for the event
- **Admins**: Authenticated users who manage registrations and scan QR codes
- **No Self-Service**: Participants cannot modify their registration after submission

### 3. Registration Workflow
- **Approval Required**: All registrations start with "pending" status
- **Admin Decision**: Admins must manually accept or reject each registration
- **Email Notification**: Participants receive emails for all status changes
- **No Payment**: Registration is free; no payment processing required

### 4. QR Code Specifications
- **Format**: PNG image, 300x300 pixels
- **Encoding**: Base64 for transmission
- **Security**: HMAC-SHA256 signature
- **Lifetime**: QR codes never expire
- **Single Use**: QR codes can be scanned multiple times (no single-use restriction)

### 5. Access Control
- **Status Check**: Only "accepted" participants can enter
- **Access Type**: System logs access type but doesn't enforce separate entry points
- **Scan Logging**: All scan attempts are logged regardless of success/failure
- **No Time Restrictions**: QR codes can be scanned at any time

## Technical Assumptions

### 1. Infrastructure
- **Development Environment**: Docker Compose on local machine
- **Production Environment**: Similar Docker-based deployment
- **Database**: MySQL 8.0 with utf8mb4 character set
- **Email**: MailHog for development, SMTP server for production
- **No CDN**: Static assets served directly from application

### 2. Browser Support
- **Modern Browsers**: Chrome, Firefox, Safari, Edge (latest 2 versions)
- **Mobile Support**: Responsive design for tablets and phones
- **Camera Access**: Required for QR scanning feature
- **JavaScript**: Required; no fallback for disabled JavaScript
- **Web Speech API**: Required for TTS; graceful degradation if unavailable

### 3. Security
- **HTTPS**: Required in production (not enforced in development)
- **CORS**: Restricted to frontend URL only
- **Rate Limiting**: Applied to prevent abuse
- **Password Policy**: No complexity requirements (admin passwords)
- **Session Management**: Token-based with Sanctum

### 4. Performance
- **Concurrent Users**: Designed for up to 100 concurrent users
- **Database Size**: Optimized for up to 10,000 participants
- **QR Generation**: Synchronous (no queue)
- **Email Sending**: Synchronous in development, should be queued in production

## Design Decisions

### 1. Technology Choices

#### Why Laravel 10?
- **Mature Framework**: Stable, well-documented, large community
- **Built-in Features**: Authentication, validation, ORM, mail
- **Sanctum**: Simple token-based API authentication
- **Eloquent ORM**: Clean database interactions with migrations

#### Why Angular 17?
- **Standalone Components**: Modern, simplified architecture
- **TypeScript**: Type safety and better IDE support
- **Reactive Forms**: Powerful form validation
- **Dependency Injection**: Clean service architecture

#### Why MySQL?
- **Reliability**: Proven database for web applications
- **Docker Support**: Official Docker images
- **Laravel Integration**: First-class support in Laravel
- **Familiarity**: Widely known and used

### 2. Architecture Decisions

#### Monolithic Backend
- **Simplicity**: Single codebase easier to develop and deploy
- **Performance**: No inter-service communication overhead
- **Sufficient**: Adequate for expected load

#### RESTful API
- **Standard**: Well-understood HTTP methods and status codes
- **Stateless**: Each request contains all necessary information
- **Cacheable**: Responses can be cached if needed

#### Token-Based Auth
- **Stateless**: No server-side session storage
- **Mobile-Friendly**: Easy to use from any client
- **Sanctum**: Laravel's recommended solution for SPAs

### 3. Feature Decisions

#### Manual Approval
- **Quality Control**: Admins can review each registration
- **Spam Prevention**: Prevents automated fake registrations
- **Flexibility**: Admins can reject inappropriate registrations

#### QR Code Security
- **HMAC Signature**: Prevents QR code forgery
- **No Expiration**: Simplifies user experience
- **Payload Included**: Reduces database lookups during scanning

#### Email Notifications
- **User Communication**: Keeps participants informed
- **QR Delivery**: Ensures participants receive their QR code
- **Status Updates**: Notifies of acceptance/rejection

#### TTS Instead of Pre-recorded Audio
- **Flexibility**: Can welcome any name without pre-recording
- **Personalization**: Each participant gets a unique greeting
- **No Storage**: No need to store audio files
- **Free**: Web Speech API is free and built into browsers

### 4. Avatar Video Placeholder

#### Why Placeholder?
- **User Requirement**: User will add video manually later
- **Preparation**: Component structure ready for video integration
- **TTS Integration**: Audio works independently of video

#### Future Video Integration
- **Format**: MP4 video file
- **Location**: `/assets/avatar-video.mp4`
- **Behavior**: Silent video loops while TTS plays
- **Synchronization**: Video plays during speech (visual feedback)

### 5. Validation Rules

#### Email Uniqueness
- **Assumption**: One registration per email address
- **Rationale**: Prevents duplicate registrations
- **Limitation**: Users with multiple emails can register multiple times

#### Phone Optional
- **Assumption**: Not all participants have phones
- **Rationale**: Email is sufficient for communication
- **Validation**: Format validated if provided

#### Gender Field
- **Options**: Male, Female, Other
- **Rationale**: Inclusive options
- **Usage**: For personalization and statistics

### 6. Localization

#### French Only
- **Requirement**: All user-facing text in French
- **Forms**: French labels and placeholders
- **Validation**: French error messages
- **Emails**: French email templates
- **No i18n**: No internationalization framework (not needed)

## Known Limitations

### 1. Scalability
- **Single Server**: Not designed for horizontal scaling
- **Synchronous Operations**: Email sending blocks request
- **No Caching**: No Redis or Memcached integration
- **File Storage**: QR codes generated on-the-fly (not stored)

### 2. Features Not Implemented
- **Participant Self-Service**: Cannot update own information
- **Bulk Operations**: No bulk accept/reject
- **Export**: No CSV/Excel export of participants
- **Analytics**: No dashboard with statistics
- **Notifications**: No real-time notifications (WebSockets)
- **Multi-Event**: Cannot manage multiple events

### 3. Security Considerations
- **No 2FA**: Admin login uses password only
- **No Password Reset**: Admins cannot reset passwords
- **No Account Lockout**: No protection against brute force
- **No Audit Log**: Admin actions not logged
- **QR Reuse**: QR codes can be scanned multiple times

### 4. User Experience
- **No Offline Mode**: Requires internet connection
- **No Mobile App**: Web-only interface
- **No Push Notifications**: Email-only notifications
- **Camera Required**: QR scanning requires camera access
- **No Accessibility**: Limited ARIA labels and screen reader support

## Future Enhancements

### Short Term (Next Sprint)
1. **Queue System**: Async email sending with Laravel Queue
2. **Export Feature**: CSV export of participants
3. **Bulk Actions**: Accept/reject multiple participants
4. **Search Improvements**: Full-text search on participants

### Medium Term (Next Quarter)
1. **Analytics Dashboard**: Registration statistics and charts
2. **Email Templates**: Customizable email templates
3. **Password Reset**: Self-service password reset for admins
4. **Audit Log**: Track all admin actions
5. **Multi-Language**: Support for English and other languages

### Long Term (Future Versions)
1. **Multi-Event**: Support multiple events in one system
2. **Mobile App**: Native iOS/Android apps
3. **Real-Time**: WebSocket-based real-time updates
4. **Advanced Security**: 2FA, account lockout, IP whitelisting
5. **Microservices**: Separate services for QR, email, scanning
6. **AI Integration**: Automated fraud detection

## Testing Strategy

### Unit Tests
- **Backend**: PHPUnit for models, services, controllers
- **Frontend**: Jasmine/Karma for components and services
- **Coverage**: Aim for 80%+ code coverage

### Integration Tests
- **API Tests**: Test complete request/response cycles
- **Database Tests**: Test migrations and seeders
- **Email Tests**: Verify email content and delivery

### E2E Tests
- **Cypress**: Test complete user workflows
- **Scenarios**: Registration, login, approval, scanning
- **Cross-Browser**: Test on Chrome, Firefox, Safari

### Manual Testing
- **QR Scanning**: Test with real devices and cameras
- **TTS**: Test voice quality and pronunciation
- **Responsive**: Test on various screen sizes
- **Accessibility**: Test with screen readers

## Deployment Checklist

### Development
- [x] Docker Compose configuration
- [x] Environment variables documented
- [x] Database migrations
- [x] Seed data for testing
- [x] MailHog for email testing

### Production
- [ ] HTTPS configuration
- [ ] Production database setup
- [ ] SMTP server configuration
- [ ] Environment variables secured
- [ ] Backup strategy
- [ ] Monitoring setup
- [ ] Error tracking (Sentry, Bugsnag)
- [ ] Log rotation
- [ ] Performance optimization
- [ ] Security hardening

## Contact and Support

For questions about these assumptions or design decisions, please contact the development team or refer to the main README.md file.
