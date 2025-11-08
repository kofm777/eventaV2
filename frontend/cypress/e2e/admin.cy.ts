describe('Admin Flow', () => {
  const adminEmail = 'admin@example.com';
  const adminPassword = 'admin123';

  beforeEach(() => {
    // Clear any existing auth
    cy.clearLocalStorage();
  });

  it('should display login form', () => {
    cy.visit('/login');
    cy.contains('Connexion Administrateur').should('be.visible');
    cy.get('input[type="email"]').should('be.visible');
    cy.get('input[type="password"]').should('be.visible');
  });

  it('should show validation errors for empty login', () => {
    cy.visit('/login');
    cy.get('button[type="submit"]').click();
    cy.contains('L\'email est obligatoire').should('be.visible');
    cy.contains('Le mot de passe est obligatoire').should('be.visible');
  });

  it('should successfully login with valid credentials', () => {
    cy.visit('/login');
    cy.get('input[type="email"]').type(adminEmail);
    cy.get('input[type="password"]').type(adminPassword);
    cy.get('button[type="submit"]').click();

    // Should redirect to admin page
    cy.url({ timeout: 10000 }).should('include', '/admin');
    cy.contains('Gestion des Participants').should('be.visible');
  });

  it('should show error for invalid credentials', () => {
    cy.visit('/login');
    cy.get('input[type="email"]').type('wrong@example.com');
    cy.get('input[type="password"]').type('wrongpassword');
    cy.get('button[type="submit"]').click();

    cy.contains('Identifiants incorrects', { timeout: 10000 }).should('be.visible');
  });

  it('should protect admin routes', () => {
    cy.visit('/admin');
    // Should redirect to login
    cy.url({ timeout: 5000 }).should('include', '/login');
  });

  it('should allow admin to view participants', () => {
    cy.login(adminEmail, adminPassword);
    cy.url({ timeout: 10000 }).should('include', '/admin');
    
    // Should show participants table
    cy.contains('Participants').should('be.visible');
    cy.get('table').should('be.visible');
  });

  it('should allow admin to filter participants', () => {
    cy.login(adminEmail, adminPassword);
    cy.url({ timeout: 10000 }).should('include', '/admin');

    // Apply status filter
    cy.get('select').first().select('pending');
    cy.contains('Appliquer').click();

    // Table should update
    cy.get('table', { timeout: 5000 }).should('be.visible');
  });

  it('should allow admin to logout', () => {
    cy.login(adminEmail, adminPassword);
    cy.url({ timeout: 10000 }).should('include', '/admin');

    // Click logout button
    cy.contains('Déconnexion').click();

    // Should redirect to login
    cy.url({ timeout: 5000 }).should('include', '/login');
  });

  it('should navigate to scanner page', () => {
    cy.login(adminEmail, adminPassword);
    cy.url({ timeout: 10000 }).should('include', '/admin');

    // Click scanner link
    cy.contains('Scanner').click();

    // Should navigate to scanner
    cy.url({ timeout: 5000 }).should('include', '/admin/scanner');
    cy.contains('Scanner QR Code').should('be.visible');
  });
});
