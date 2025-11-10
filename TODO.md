# Event Access System Updates

## Pending Tasks

### 1. Update Registration Form
- [ ] Change access_type from select dropdown to checkbox-based selection
- [ ] Default to 'foire' (fair only)
- [ ] Add checkbox for conference access (if checked, access_type = 'both')
- [ ] Update register.component.html and register.component.ts

### 2. Modify Registration Controller
- [ ] Change default status from 'pending' to 'accepted' in RegistrationController.php
- [ ] Update registration logic to always accept participants

### 3. Database Schema Updates
- [ ] Create migration to add qr_image column to participants table
- [ ] Update Participant model to include qr_image field
- [ ] Update migration file to include qr_image column

### 4. Admin Interface Updates
- [ ] Add QR token column to admin participants table
- [ ] Add QR image column to admin participants table
- [ ] Update admin-list.component.html to display QR token and image
- [ ] Add delete participant functionality with email notifications
- [ ] Update AdminController.php to handle delete with notifications

### 5. Scanner Component Enhancements
- [ ] Add file upload functionality for QR code images
- [ ] Implement image processing (crop/resize, size limits <=5MB)
- [ ] Add manual QR token input field
- [ ] Update scanner.component.html and scanner.component.ts

### 6. Speaker Avatar Updates
- [ ] Update speaker avatar to announce access details
- [ ] Modify speaker-avatar.component.ts to include access type in announcements

### 7. Badge Component Modifications
- [ ] Update badge page to modern printable design
- [ ] Add print button functionality
- [ ] Modify badge.component.html and badge.component.ts for printing

### 8. Model and Service Updates
- [ ] Update participant.model.ts to handle new fields
- [ ] Update api.service.ts to handle new endpoints and fields
- [ ] Ensure all services handle qr_image and qr_token fields

### 9. Testing and Validation
- [ ] Test registration with new access type logic
- [ ] Test admin delete functionality
- [ ] Test scanner with multiple input methods
- [ ] Test badge printing functionality
- [ ] Validate email notifications for deletions
