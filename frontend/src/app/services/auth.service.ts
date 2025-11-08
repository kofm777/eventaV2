import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';
import { tap } from 'rxjs/operators';
import { ApiService } from './api.service';
import { Admin, LoginRequest } from '../models/admin.model';

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private currentAdminSubject = new BehaviorSubject<Admin | null>(null);
  public currentAdmin$ = this.currentAdminSubject.asObservable();

  constructor(private apiService: ApiService) {
    this.loadAdminFromStorage();
  }

  private loadAdminFromStorage(): void {
    const adminData = localStorage.getItem('admin_data');
    if (adminData) {
      try {
        const admin = JSON.parse(adminData);
        this.currentAdminSubject.next(admin);
      } catch (e) {
        console.error('Failed to parse admin data', e);
        this.clearAuth();
      }
    }
  }

  login(credentials: LoginRequest): Observable<any> {
    return this.apiService.login(credentials).pipe(
      tap(response => {
        if (response.ok && response.token) {
          localStorage.setItem('admin_token', response.token);
          localStorage.setItem('admin_data', JSON.stringify(response.admin));
          this.currentAdminSubject.next(response.admin);
        }
      })
    );
  }

  logout(): void {
    this.apiService.logout().subscribe({
      next: () => {
        this.clearAuth();
      },
      error: () => {
        this.clearAuth();
      }
    });
  }

  private clearAuth(): void {
    localStorage.removeItem('admin_token');
    localStorage.removeItem('admin_data');
    this.currentAdminSubject.next(null);
  }

  isAuthenticated(): boolean {
    return !!localStorage.getItem('admin_token');
  }

  getCurrentAdmin(): Admin | null {
    return this.currentAdminSubject.value;
  }
}
