import { Component, OnInit, NgZone } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, Validators, ReactiveFormsModule } from '@angular/forms';
import { Router, ActivatedRoute, RouterModule } from '@angular/router';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterModule],
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.css']
})
export class LoginComponent implements OnInit {
  loginForm: FormGroup;
  loading = false;
  submitted = false;
  error: string | null = null;
  returnUrl: string = '/admin/participants';

  constructor(
      private fb: FormBuilder,
      private authService: AuthService,
      private router: Router,
      private route: ActivatedRoute,
      private ngZone: NgZone
  ) {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', Validators.required]
    });
  }

  ngOnInit(): void {
    // Get return URL from query params or default
    this.returnUrl = this.route.snapshot.queryParams['returnUrl'] || '/admin/participants';

    // Redirect **only if authenticated and not already on /admin/login**
    if (this.authService.isAuthenticated() && this.router.url !== '/admin/login') {
      this.ngZone.run(() => this.router.navigate([this.returnUrl]));
    }
  }

  get f() {
    return this.loginForm.controls;
  }

  onSubmit(): void {
    this.submitted = true;
    this.error = null;

    if (this.loginForm.invalid) {
      return;
    }

    this.loading = true;

    this.authService.login(this.loginForm.value).subscribe({
      next: (response) => {
        this.loading = false;
        if (response.ok) {
          this.ngZone.run(() => this.router.navigate([this.returnUrl]));
        }
      },
      error: (err) => {
        this.loading = false;
        this.error = err.error?.message || 'Incorrect credentials. Please try again.';
      }
    });
  }
}
