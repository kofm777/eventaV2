import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';
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

  constructor(
    private fb: FormBuilder,
    private apiService: ApiService,
    private router: Router
  ) {
    this.registerForm = this.fb.group({
      first_name: ['', [Validators.required, Validators.maxLength(255)]],
      last_name: ['', [Validators.required, Validators.maxLength(255)]],
      gender: ['', Validators.required],
      phone: ['', [Validators.maxLength(30), Validators.pattern(/^[\+]?[0-9\s\-\(\)]+$/)]],
      email: ['', [Validators.required, Validators.email, Validators.maxLength(255)]],
      access_type: ['foire', Validators.required] // Default to 'foire'
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
          // Navigate to badge display page with data
          this.router.navigate(['/badge'], {
            state: {
              qrCode: response.qr,
              participantName: `${response.participant.first_name} ${response.participant.last_name}`,
              emailSent: response.email_sent ?? true
            }
          });
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


}
