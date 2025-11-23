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

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterModule],
  templateUrl: './register.component.html',
  styleUrls: ['./register.component.scss'],
})
export class RegisterComponent {
  registerForm: FormGroup;
  conferenceAccess: FormControl;
  loading = false;
  submitted = false;
  error: string | null = null;
  success: string | null = null;

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
      company_name: ['', [Validators.required, Validators.maxLength(255)]],
      gender: ['', Validators.required],
      phone: [
        '',
        [Validators.maxLength(30), Validators.pattern(/^[+]?\d[0-9\s\-()]+$/)],
      ],
      email: [
        '',
        [Validators.required, Validators.email, Validators.maxLength(255)],
      ],
      access_type: ['fair', Validators.required], // default to 'fair'
    });

    // Ensure access_type updates correctly based on checkbox
    this.conferenceAccess.valueChanges.subscribe((checked: boolean) => {
      this.registerForm.patchValue({ access_type: checked ? 'fair + conference' : 'fair' }, { emitEvent: false });
    });

// Optional: Ensure initial value is always valid
    if (!this.registerForm.get('access_type')?.value) {
      this.registerForm.patchValue({ access_type: 'fair' }, { emitEvent: false });
    }
  }

  // Getter
  get f() {
    return this.registerForm.controls;
  }

// Human-readable
  getSelectedAccessType(): string {
    const accessType = this.registerForm.get('access_type')?.value;
    return accessType === 'fair + conference' ? 'Fair and Conference' : 'Fair only';
  }

  onSubmit(): void {
    this.submitted = true;
    if (this.registerForm.invalid) return;

    const accessType = this.conferenceAccess.value ? 'fair + conference' : 'fair';
    const payload = { ...this.registerForm.value, access_type: accessType };

    this.loading = true;
    this.apiService.register(payload).subscribe({
      next: (response) => {
        this.loading = false;
        if (response.ok) {
          this.success = response.message || 'Registration successful!';
          this.autoDismissToast('success');
          this.ngZone.run(async () => {
            await this.router.navigate(['/badge'], {
              state: {
                qrCode: response.qr, // ← This is now base64!
                participantGender: response.participant.gender,
                participantName: `${response.participant.first_name} ${response.participant.last_name}`,
                accessType: accessType,
                emailSent: response.email_sent ?? true,
              },
            });
          });
        }
      },
      error: (err) => {
        this.loading = false;
        this.error = err.error?.message || 'An error occurred.';
        this.autoDismissToast('error');
      },
    });
  }


// Auto-dismiss toast after 4 seconds
  autoDismissToast(type: 'error' | 'success') {
    setTimeout(() => {
      if (type === 'error') this.error = null;
      if (type === 'success') this.success = null;
    }, 8000);
  }

}