# Contributing to Event Access

Thank you for your interest in contributing to Event Access! This document provides guidelines and instructions for contributing to the project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing Guidelines](#testing-guidelines)
- [Commit Messages](#commit-messages)
- [Pull Request Process](#pull-request-process)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Features](#suggesting-features)

## Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inclusive environment for all contributors, regardless of experience level, gender, gender identity and expression, sexual orientation, disability, personal appearance, body size, race, ethnicity, age, religion, or nationality.

### Our Standards

- Be respectful and considerate
- Welcome newcomers and help them learn
- Focus on what is best for the community
- Show empathy towards other community members
- Accept constructive criticism gracefully

## Getting Started

### Prerequisites

Before you begin, ensure you have:

- Docker and Docker Compose installed
- Git installed
- A code editor (VS Code recommended)
- Basic knowledge of Laravel, Angular, and Docker

### Setting Up Development Environment

1. **Fork the repository**
   ```bash
   # Fork on GitHub, then clone your fork
   git clone https://github.com/YOUR_USERNAME/eventaccess.git
   cd eventaccess
   ```

2. **Set up the project**
   ```bash
   # Copy environment files
   cp backend/.env.example backend/.env
   
   # Start Docker containers
   make up
   
   # Install dependencies
   make install
   
   # Run migrations and seed database
   make migrate
   make seed
   ```

3. **Verify setup**
   ```bash
   # Check backend
   curl http://localhost:8000/api/health
   
   # Check frontend
   curl http://localhost:4200
   ```

## Development Workflow

### Branch Strategy

We use Git Flow for branch management:

- `main` - Production-ready code
- `develop` - Integration branch for features
- `feature/*` - New features
- `bugfix/*` - Bug fixes
- `hotfix/*` - Urgent production fixes

### Creating a Feature Branch

```bash
# Update develop branch
git checkout develop
git pull origin develop

# Create feature branch
git checkout -b feature/your-feature-name

# Make your changes
# ...

# Commit your changes
git add .
git commit -m "feat: add your feature description"

# Push to your fork
git push origin feature/your-feature-name
```

## Coding Standards

### Backend (Laravel/PHP)

- Follow PSR-12 coding standard
- Use type hints for parameters and return types
- Write PHPDoc comments for classes and methods
- Use Eloquent ORM for database queries
- Keep controllers thin, use services for business logic

**Example:**
```php
<?php

namespace App\Services;

class ExampleService
{
    /**
     * Process the example data.
     *
     * @param array $data
     * @return array
     */
    public function process(array $data): array
    {
        // Implementation
        return $result;
    }
}
```

### Frontend (Angular/TypeScript)

- Follow Angular style guide
- Use TypeScript strict mode
- Use reactive forms for form handling
- Keep components focused and small
- Use services for business logic and API calls

**Example:**
```typescript
import { Component } from '@angular/core';

@Component({
  selector: 'app-example',
  standalone: true,
  templateUrl: './example.component.html',
  styleUrls: ['./example.component.css']
})
export class ExampleComponent {
  // Implementation
}
```

### General Guidelines

- Write self-documenting code
- Keep functions small and focused
- Avoid deep nesting (max 3 levels)
- Use meaningful variable and function names
- Remove commented-out code before committing
- No console.log() in production code

## Testing Guidelines

### Backend Tests

All new features must include tests:

```bash
# Run all tests
cd backend
php artisan test

# Run specific test
php artisan test --filter=RegistrationTest

# Run with coverage
php artisan test --coverage
```

**Test Structure:**
```php
public function test_feature_works_correctly()
{
    // Arrange
    $data = ['key' => 'value'];
    
    // Act
    $response = $this->postJson('/api/endpoint', $data);
    
    // Assert
    $response->assertStatus(200);
    $this->assertDatabaseHas('table', $data);
}
```

### Frontend Tests

Write unit tests for components and services:

```bash
# Run unit tests
cd frontend
npm run test

# Run E2E tests
npm run e2e
```

**Test Structure:**
```typescript
describe('ExampleComponent', () => {
  it('should create', () => {
    // Arrange
    const component = new ExampleComponent();
    
    // Act & Assert
    expect(component).toBeTruthy();
  });
});
```

### Test Coverage

- Aim for 80%+ code coverage
- All new features must have tests
- Bug fixes should include regression tests
- Critical paths must have E2E tests

## Commit Messages

We follow [Conventional Commits](https://www.conventionalcommits.org/):

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

### Examples

```bash
feat(registration): add phone number validation

Add regex validation for phone numbers in the registration form.
Supports international formats with + prefix.

Closes #123

---

fix(scanner): resolve camera permission issue

Fix camera access on iOS Safari by requesting permissions
before initializing the scanner.

Fixes #456

---

docs(api): update OpenAPI specification

Add missing endpoints and update response schemas.
```

## Pull Request Process

### Before Submitting

1. **Update your branch**
   ```bash
   git checkout develop
   git pull origin develop
   git checkout your-branch
   git rebase develop
   ```

2. **Run tests**
   ```bash
   make test
   ```

3. **Check code style**
   ```bash
   # Backend
   cd backend
   vendor/bin/phpcs --standard=PSR12 app
   
   # Frontend
   cd frontend
   npm run lint
   ```

### Submitting a Pull Request

1. Push your branch to your fork
2. Go to the original repository on GitHub
3. Click "New Pull Request"
4. Select your branch
5. Fill out the PR template:

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Unit tests pass
- [ ] E2E tests pass
- [ ] Manual testing completed

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Comments added for complex code
- [ ] Documentation updated
- [ ] No new warnings generated
```

### Review Process

- At least one approval required
- All tests must pass
- No merge conflicts
- Code style checks must pass
- Documentation must be updated

## Reporting Bugs

### Before Reporting

1. Check existing issues
2. Verify it's reproducible
3. Test on latest version

### Bug Report Template

```markdown
**Describe the bug**
A clear description of the bug.

**To Reproduce**
Steps to reproduce:
1. Go to '...'
2. Click on '...'
3. See error

**Expected behavior**
What should happen.

**Screenshots**
If applicable, add screenshots.

**Environment:**
- OS: [e.g., Windows 10]
- Browser: [e.g., Chrome 120]
- Version: [e.g., 1.0.0]

**Additional context**
Any other relevant information.
```

## Suggesting Features

### Feature Request Template

```markdown
**Is your feature request related to a problem?**
A clear description of the problem.

**Describe the solution you'd like**
A clear description of what you want to happen.

**Describe alternatives you've considered**
Alternative solutions or features.

**Additional context**
Any other context, mockups, or examples.
```

## Development Tips

### Useful Commands

```bash
# Start development environment
make up

# View logs
make logs

# Access backend shell
make backend-shell

# Access frontend shell
make frontend-shell

# Run tests
make test

# Stop environment
make down
```

### Debugging

**Backend:**
```php
// Use Laravel's dd() helper
dd($variable);

// Use Log facade
Log::info('Debug message', ['data' => $data]);
```

**Frontend:**
```typescript
// Use console for debugging (remove before commit)
console.log('Debug:', data);

// Use Angular DevTools in browser
```

### Common Issues

**Port already in use:**
```bash
# Change ports in docker-compose.yml
# Or stop conflicting services
```

**Database connection failed:**
```bash
# Restart MySQL container
docker-compose restart mysql
```

**Frontend build errors:**
```bash
# Clear node_modules and reinstall
cd frontend
rm -rf node_modules package-lock.json
npm install
```

## Questions?

If you have questions:

1. Check the documentation in `/docs`
2. Search existing issues
3. Ask in discussions
4. Contact the maintainers

## License

By contributing, you agree that your contributions will be licensed under the same license as the project.

---

Thank you for contributing to Event Access! 🎉
