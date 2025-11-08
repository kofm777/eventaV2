import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { ApiService } from '../../services/api.service';
import { RegisterParticipantRequest } from '../../models/participant.model';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './register.component.html',
  styleUrls: ['./register.component.css']
})
export class RegisterComponent {
  registerForm: FormGroup;
  loading = false;
  submitted = false;
  error: string | null = null;
  success = false;
  qrCode: string | null = null;
  participantName: string | null = null;

  constructor(
    private fb: FormBuilder,
    private apiService: ApiService
  ) {
    this.registerForm = this.fb.group({
      first_name: ['', [Validators.required, Validators.maxLength(255)]],
      last_name: ['', [Validators.required, Validators.maxLength(255)]],
      gender: ['', Validators.required],
      phone: ['', [Validators.maxLength(30), Validators.pattern(/^[\+]?[0-9\s\-\(\)]+$/)]],
      email: ['', [Validators.required, Validators.email, Validators.maxLength(255)]],
      access_type: ['', Validators.required]
    });
  }

  get f() {
    return this.registerForm.controls;
  }

  onSubmit(): void {
    this.submitted = true;
    this.error = null;

    if (this.registerForm.invalid) {
      return;
    }

    this.loading = true;
    const formData: RegisterParticipantRequest = this.registerForm.value;

    this.apiService.register(formData).subscribe({
      next: (response) => {
        this.loading = false;
        if (response.ok) {
          this.success = true;
          this.qrCode = response.qr;
          this.participantName = `${response.participant.first_name} ${response.participant.last_name}`;
          this.registerForm.reset();
          this.submitted = false;
        }
      },
      error: (err) => {
        this.loading = false;
        this.error = err.error?.message || 'Une erreur est survenue lors de l\'inscription.';
        if (err.error?.errors) {
          const errors = err.error.errors;
          Object.keys(errors).forEach(key => {
            const control = this.registerForm.get(key);
            if (control) {
              control.setErrors({ serverError: errors[key][0] });
            }
          });
        }
      }
    });
  }

  downloadQR(): void {
    if (!this.qrCode) return;

    const link = document.createElement('a');
    link.href = `data:image/png;base64,${this.qrCode}`;
    link.download = `qr-code-${Date.now()}.png`;
    link.click();
  }

  printQR(): void {
    if (!this.qrCode) return;

    const printWindow = window.open('', '_blank');
    if (printWindow) {
      printWindow.document.write(`
        <html>
          <head>
            <title>QR Code - Event Access</title>
            <style>
              body { text-align: center; font-family: Arial, sans-serif; padding: 20px; }
              h1 { color: #2563eb; }
              img { max-width: 400px; margin: 20px 0; }
            </style>
          </head>
          <body>
            <h1>Event Access - QR Code</h1>
            <p><strong>${this.participantName}</strong></p>
            <img src="data:image/png;base64,${this.qrCode}" alt="QR Code" />
            <p>Présentez ce QR code à l'entrée de l'événement</p>
          </body>
        </html>
      `);
      printWindow.document.close();
      printWindow.print();
    }
  }

  registerAnother(): void {
    this.success = false;
    this.qrCode = null;
    this.participantName = null;
    this.error = null;
  }
}
