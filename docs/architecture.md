# Event Access - Architecture Documentation

## System Overview

Event Access is a full-stack web application for managing event registrations with QR code-based access control. The system consists of a Laravel backend API, an Angular frontend, and a MySQL database, all containerized with Docker.

## Architecture Diagram

```mermaid
graph TB
    subgraph "Frontend - Angular 17"
        A[User Browser] --> B[Angular App]
        B --> C[Register Component]
        B --> D[Admin Dashboard]
        B --> E[QR Scanner]
        B --> F[Speaker Avatar]
    end
    
    subgraph "Backend - Laravel 10"
        G[API Gateway] --> H[Registration Controller]
        G --> I[Admin Controller]
        G --> J[Scan Controller]
        G --> K[Auth Controller]
        H --> L[QR Code Service]
        H --> M[Mail Service]
    end
    
    subgraph "Data Layer"
        N[(MySQL Database)]
        O[MailHog SMTP]
    end
    
    B -->|HTTP/JSON| G
    H --> N
    I --> N
    J --> N
    K --> N
    M --> O
    
    style A fill:#e1f5ff
    style B fill:#bbdefb
    style G fill:#fff9c4
    style N fill:#c8e6c9
    style O fill:#ffccbc
```

## Component Architecture

### Frontend Components

```mermaid
graph LR
    A[App Component] --> B[Register Component]
    A --> C[Login Component]
    A --> D[Admin List Component]
    A --> E[Scanner Component]
    E --> F[Speaker Avatar Component]
    
    B --> G[API Service]
    C --> H[Auth Service]
    D --> G
    E --> G
    
    H --> G
    
    style A fill:#2196f3,color:#fff
    style G fill:#4caf50,color:#fff
    style H fill:#ff9800,color:#fff
```

### Backend Architecture

```mermaid
graph TB
    A[HTTP Request] --> B[Middleware Stack]
    B --> C{Route}
    
    C -->|POST /register| D[RegistrationController]
    C -->|POST /scan| E[ScanController]
    C -->|POST /auth/admin/login| F[AuthController]
    C -->|GET /admin/participants| G[AdminController]
    
    D --> H[QrCodeService]
    D --> I[ParticipantAccessMail]
    D --> J[Participant Model]
    
    E --> H
    E --> K[Scan Model]
    E --> J
    
    F --> L[Admin Model]
    F --> M[Sanctum Auth]
    
    G --> J
    G --> M
    
    J --> N[(participants table)]
    K --> O[(scans table)]
    L --> P[(admins table)]
    
    style D fill:#4caf50,color:#fff
    style E fill:#2196f3,color:#fff
    style F fill:#ff9800,color:#fff
    style G fill:#9c27b0,color:#fff
```

## Data Flow

### Registration Flow

```mermaid
sequenceDiagram
    participant U as User
    participant F as Frontend
    participant B as Backend API
    participant Q as QR Service
    participant D as Database
    participant M as Mail Service
    
    U->>F: Fill registration form
    F->>B: POST /api/v1/register
    B->>B: Validate data
    B->>D: Create participant (status: pending)
    D-->>B: Participant created
    B->>Q: Generate QR code with HMAC
    Q-->>B: QR code (base64)
    B->>M: Send email with QR
    M-->>B: Email queued
    B-->>F: {ok, participant, qr, message}
    F-->>U: Show QR code + success message
```

### Admin Approval Flow

```mermaid
sequenceDiagram
    participant A as Admin
    participant F as Frontend
    participant B as Backend API
    participant D as Database
    participant M as Mail Service
    
    A->>F: Login
    F->>B: POST /api/v1/auth/admin/login
    B->>D: Verify credentials
    D-->>B: Admin data
    B-->>F: {ok, admin, token}
    F->>F: Store token
    
    A->>F: View participants
    F->>B: GET /api/v1/admin/participants
    B->>D: Query participants
    D-->>B: Participant list
    B-->>F: {ok, participants}
    
    A->>F: Accept participant
    F->>B: POST /api/v1/admin/participants/{id}/accept
    B->>D: Update status to 'accepted'
    D-->>B: Updated
    B->>M: Send acceptance email
    M-->>B: Email sent
    B-->>F: {ok, message}
    F-->>A: Show success
```

### QR Scanning Flow

```mermaid
sequenceDiagram
    participant S as Scanner
    participant F as Frontend
    participant B as Backend API
    participant Q as QR Service
    participant D as Database
    participant A as Avatar/TTS
    
    S->>F: Scan QR code
    F->>B: POST /api/v1/scan {payload}
    B->>Q: Verify HMAC signature
    Q-->>B: Signature valid
    B->>D: Find participant by token
    D-->>B: Participant data
    B->>B: Check status = 'accepted'
    B->>D: Create scan record
    D-->>B: Scan created
    B-->>F: {ok, participant, scan_id, message}
    F->>A: Play welcome message
    A-->>S: "Bienvenue [Name]"
```

## Database Schema

```mermaid
erDiagram
    PARTICIPANTS ||--o{ SCANS : has
    PARTICIPANTS {
        bigint id PK
        string first_name
        string last_name
        enum gender
        string phone
        string email UK
        enum access_type
        string qr_token UK
        json qr_payload
        enum status
        timestamp created_at
        timestamp updated_at
    }
    
    SCANS {
        bigint id PK
        bigint participant_id FK
        string scanner_user
        timestamp scanned_at
        timestamp created_at
        timestamp updated_at
    }
    
    ADMINS {
        bigint id PK
        string name
        string email UK
        string password
        timestamp created_at
        timestamp updated_at
    }
```

## Security Architecture

### QR Code Security

1. **HMAC-SHA256 Signature**: Each QR code contains a signed payload
   - Format: `base64url(payload).base64url(signature)`
   - Secret key stored in environment variable
   - Constant-time comparison prevents timing attacks

2. **Payload Structure**:
   ```json
   {
     "participant_id": 123,
     "email": "user@example.com",
     "access_type": "both",
     "issued_at": "2025-01-01T12:00:00Z"
   }
   ```

### Authentication

1. **Laravel Sanctum**: Token-based authentication for admin routes
2. **CORS**: Restricted to frontend URL only
3. **Rate Limiting**:
   - Registration: 5 requests/minute
   - Login: 5 requests/minute
   - Scan: 30 requests/minute

### Data Protection

1. **Password Hashing**: Bcrypt for admin passwords
2. **HTTPS**: Required in production
3. **Input Validation**: Server-side validation for all inputs
4. **SQL Injection Protection**: Eloquent ORM with parameter binding

## Technology Stack

### Frontend
- **Framework**: Angular 17 (Standalone Components)
- **HTTP Client**: Angular HttpClient
- **QR Scanning**: @zxing/browser
- **TTS**: Web Speech API
- **Styling**: Custom CSS with responsive design

### Backend
- **Framework**: Laravel 10
- **PHP Version**: 8.2
- **Authentication**: Laravel Sanctum
- **QR Generation**: simplesoftwareio/simple-qrcode
- **Email**: Laravel Mail with MailHog (dev)

### Database
- **DBMS**: MySQL 8.0
- **ORM**: Eloquent
- **Migrations**: Laravel Migrations

### Infrastructure
- **Containerization**: Docker & Docker Compose
- **Web Server**: PHP Built-in (dev), Nginx (prod)
- **Process Manager**: None (dev), Supervisor (prod)

## Deployment Architecture

```mermaid
graph TB
    subgraph "Docker Compose"
        A[Frontend Container<br/>Node 20 + Angular]
        B[Backend Container<br/>PHP 8.2 + Laravel]
        C[MySQL Container<br/>MySQL 8.0]
        D[MailHog Container<br/>SMTP Server]
    end
    
    A -->|Port 4200| E[Host Machine]
    B -->|Port 8000| E
    C -->|Port 3306| B
    D -->|Port 8025| E
    D -->|Port 1025| B
    
    style A fill:#42a5f5
    style B fill:#66bb6a
    style C fill:#ffa726
    style D fill:#ef5350
```

## Scalability Considerations

### Current Architecture (MVP)
- Single server deployment
- Synchronous email sending
- In-memory session storage

### Future Enhancements
1. **Queue System**: Redis + Laravel Queue for async email
2. **Load Balancing**: Multiple backend instances behind Nginx
3. **Database Replication**: Master-slave MySQL setup
4. **CDN**: Static assets served via CDN
5. **Caching**: Redis for session and data caching
6. **Microservices**: Separate QR generation service

## Monitoring & Logging

### Logging Strategy
- **Application Logs**: Laravel log files (daily rotation)
- **Scan Logs**: Dedicated `scans.log` channel
- **Error Tracking**: Laravel exception handler
- **Access Logs**: Web server access logs

### Health Checks
- **Endpoint**: `/api/health`
- **Checks**: Database connectivity, disk space
- **Response**: JSON with status and timestamp

## Assumptions & Design Decisions

See [assumptions.md](./assumptions.md) for detailed assumptions and design decisions.
