import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';

export const routes: Routes = [
  {
    path: '',
    redirectTo: '/register',
    pathMatch: 'full'
  },
  {
    path: 'register',
    loadComponent: () => import('./components/register/register.component').then(m => m.RegisterComponent)
  },
  {
    path: 'badge',
    loadComponent: () => import('./components/badge/badge.component').then(m => m.BadgeComponent)
  },
  {
    path: 'admin/login',
    loadComponent: () => import('./components/login/login.component').then(m => m.LoginComponent)
  },
  {
    path: 'admin',
    canActivate: [authGuard],
    loadComponent: () => import('./components/admin-list/admin-list.component').then(m => m.AdminListComponent)
  },
  {
    path: 'admin/scanner',
    canActivate: [authGuard],
    loadComponent: () => import('./components/scanner/scanner.component').then(m => m.ScannerComponent)
  },
  {
    path: '**',
    redirectTo: '/register'
  }
];
