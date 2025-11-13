describe('Registration Flow', () => {
  beforeEach(() => {
    cy.visit('/register');
  });

  it('should display the registration form', () => {
    cy.contains('Inscription à l\'événement').should('be.visible');
    cy.get('input[id="first_name"]').should('be.visible');
    cy.get('input[id="last_name"]').should('be.visible');
    cy.get('select[id="gender"]').should('be.visible');
    cy.get('input[id="email"]').should('be.visible');
    cy.get('select[id="access_type"]').should('be.visible');
  });

  it('should show validation errors for empty form', () => {
    cy.get('button[type="submit"]').click();
    cy.contains('Le prénom est obligatoire').should('be.visible');
    cy.contains('Le nom est obligatoire').should('be.visible');
    cy.contains('Le genre est obligatoire').should('be.visible');
    cy.contains('L\'email est obligatoire').should('be.visible');
    cy.contains('Le type d\'accès est obligatoire').should('be.visible');
  });

  it('should successfully register a participant', () => {
    const timestamp = Date.now();
    const email = `test${timestamp}@example.com`;

    cy.get('input[id="first_name"]').type('Jean');
    cy.get('input[id="last_name"]').type('Dupont');
    cy.get('select[id="gender"]').select('Male');
    cy.get('input[id="phone"]').type('+33 6 12 34 56 78');
    cy.get('input[id="email"]').type(email);
    cy.get('select[id="access_type"]').select('both');

    cy.get('button[type="submit"]').click();

    // Should show success message
    cy.contains('Inscription réussie', { timeout: 10000 }).should('be.visible');
    
    // Should display QR code
    cy.get('.qr-image').should('be.visible');
    
    // Should have download and print buttons
    cy.contains('Télécharger le QR Code').should('be.visible');
    cy.contains('Imprimer').should('be.visible');
  });

  it('should validate email format', () => {
    cy.get('input[id="email"]').type('invalid-email');
    cy.get('button[type="submit"]').click();
    cy.contains('Format d\'email invalide').should('be.visible');
  });

  it('should allow registering another participant after success', () => {
    const timestamp = Date.now();
    const email = `test${timestamp}@example.com`;

    // First registration
    cy.get('input[id="first_name"]').type('Jean');
    cy.get('input[id="last_name"]').type('Dupont');
    cy.get('select[id="gender"]').select('Male');
    cy.get('input[id="email"]').type(email);
    cy.get('select[id="access_type"]').select('foire');
    cy.get('button[type="submit"]').click();

    cy.contains('Inscription réussie', { timeout: 10000 }).should('be.visible');

    // Click "Nouvelle inscription"
    cy.contains('Nouvelle inscription').click();

    // Form should be visible again
    cy.get('input[id="first_name"]').should('be.visible');
    cy.get('input[id="first_name"]').should('have.value', '');
  });
});
