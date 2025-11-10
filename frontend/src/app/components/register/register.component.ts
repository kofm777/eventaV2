import { Component, NgZone } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  FormControl,
  Validators,
  ReactiveFormsModule,
} from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { ApiService } from '../../services/api.service';
import { RegisterParticipantRequest } from '../../models/participant.model';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterModule],
  templateUrl: './register.component.html',
  styleUrls: ['./register.component.css'],
})
export class RegisterComponent {
  registerForm: FormGroup;
  conferenceAccess: FormControl;
  loading = false;
  submitted = false;
  error: string | null = null;

  constructor(
    private fb: FormBuilder,
    private apiService: ApiService,
    private router: Router,
    private ngZone: NgZone
  ) {
    this.conferenceAccess = this.fb.control(false);
    this.registerForm = this.fb.group({
      first_name: ['', [Validators.required, Validators.maxLength(255)]],
      last_name: ['', [Validators.required, Validators.maxLength(255)]],
      gender: ['', Validators.required],
      phone: [
        '',
        [Validators.maxLength(30), Validators.pattern(/^[\+]?[0-9\s\-\(\)]+$/)],
      ],
      email: [
        '',
        [Validators.required, Validators.email, Validators.maxLength(255)],
      ],
      access_type: ['foire', Validators.required],
    });

    this.conferenceAccess.valueChanges.subscribe((checked: boolean) => {
      const accessType = checked ? 'both' : 'foire';
      this.registerForm.patchValue({ access_type: accessType });
    });
  }

  get f() {
    return this.registerForm.controls;
  }

  getSelectedAccessType(): string {
    const accessType = this.registerForm.get('access_type')?.value;
    return accessType === 'both' ? 'Foire et Conférence' : 'Foire uniquement';
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
          alert(response.message);

          console.log('✅ About to navigate to /badge with state:', {
            qrCode: response.qr,
            participantName: `${response.participant.first_name} ${response.participant.last_name}`,
            emailSent: response.email_sent ?? true,
          });

          // ✅ Run navigation in Angular zone + await promise
          this.ngZone.run(async () => {
            try {
              const navResult = await this.router.navigate(['/badge'], {
                state: {
                  qrCode: response.qr,
                  participantName: `${response.participant.first_name} ${response.participant.last_name}`,
                  emailSent: response.email_sent ?? true,
                },
              });

              console.log('🧭 Navigation result:', navResult ? 'SUCCESS' : 'FAILED');
              if (!navResult) {
                console.error('❌ Navigation was cancelled (e.g., by a guard or invalid route).');
              }
            } catch (err) {
              console.error('💥 Navigation error:', err);
            }
          });
        }
      },
      error: (err) => {
        this.loading = false;
        this.error =
          err.error?.message || 'Une erreur est survenue lors de l\'inscription.';
        if (err.error?.errors) {
          const errors = err.error.errors;
          Object.keys(errors).forEach((key) => {
            const control = this.registerForm.get(key);
            if (control) {
              control.setErrors({ serverError: errors[key][0] });
            }
          });
        }
      },
    });
  }
}