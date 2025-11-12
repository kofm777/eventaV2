import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';

export const routes: Routes = [
  { path: '', loadComponent: () => import('./components/home/home.component').then(m => m.HomeComponent), pathMatch: 'full' },
  { path: 'home', loadComponent: () => import('./components/home/home.component').then(m => m.HomeComponent) },
  { path: 'register', loadComponent: () => import('./components/register/register.component').then(m => m.RegisterComponent) },
  { path: 'badge', loadComponent: () => import('./components/badge/badge.component').then(m => m.BadgeComponent) },

  // Admin
  { path: 'admin/login', loadComponent: () => import('./components/login/login.component').then(m => m.LoginComponent) },
  { path: 'admin/participants', canActivate: [authGuard], loadComponent: () => import('./components/admin-list/admin-list.component').then(m => m.AdminListComponent) },
  { path: 'admin/scanner', canActivate: [authGuard], loadComponent: () => import('./components/scanner/scanner.component').then(m => m.ScannerComponent) },

  { path: '**', redirectTo: '' }
];
