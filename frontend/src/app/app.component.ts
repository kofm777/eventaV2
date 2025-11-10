import { Component, OnInit } from '@angular/core';
import { RouterOutlet, RouterLink, Router, NavigationEnd } from '@angular/router';
import { CommonModule } from '@angular/common';
import { AuthService } from './services/auth.service';
import { filter } from 'rxjs/operators';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule, RouterOutlet, RouterLink],
  template: `
    <div class="app-container">
      <header class="app-header">
        <div class="container">
          <div class="header-content">
            <h1 class="logo">
              <a routerLink="/">Event Access</a>
            </h1>
            <nav class="nav">
              <a *ngIf="currentUrl !== '/admin/login'" routerLink="/register" class="nav-link">Inscription</a>
              <a *ngIf="!isAuthenticated && currentUrl !== '/register'" routerLink="/admin/login" class="nav-link">Admin</a>
              <div *ngIf="isAuthenticated" class="admin-menu">
                <a routerLink="/admin" class="nav-link">Participants</a>
                <a routerLink="/admin/scanner" class="nav-link">Scanner</a>
                <button (click)="logout()" class="btn btn-secondary btn-sm">Déconnexion</button>
              </div>
            </nav>
          </div>
        </div>
      </header>
      
      <main class="app-main">
        <router-outlet></router-outlet>
      </main>
      
      <footer class="app-footer">
        <div class="container">
          <p>&copy; 2025 Event Access. Tous droits réservés.</p>
        </div>
      </footer>
    </div>
  `,
  styles: [`
    .app-container {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .app-header {
      background-color: #2563eb;
      color: white;
      padding: 1rem 0;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .header-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .logo a {
      color: white;
      text-decoration: none;
      font-size: 1.5rem;
      font-weight: bold;
    }

    .nav {
      display: flex;
      gap: 1.5rem;
      align-items: center;
    }

    .nav-link {
      color: white;
      text-decoration: none;
      padding: 0.5rem 1rem;
      border-radius: 4px;
      transition: background-color 0.3s;
    }

    .nav-link:hover {
      background-color: rgba(255, 255, 255, 0.1);
    }

    .admin-menu {
      display: flex;
      gap: 1rem;
      align-items: center;
    }

    .btn-sm {
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
    }

    .app-main {
      flex: 1;
      padding: 2rem 0;
    }

    .app-footer {
      background-color: #1f2937;
      color: #9ca3af;
      padding: 1.5rem 0;
      text-align: center;
      margin-top: auto;
    }

    @media (max-width: 768px) {
      .header-content {
        flex-direction: column;
        gap: 1rem;
      }

      .nav {
        flex-direction: column;
        gap: 0.5rem;
      }
    }
  `]
})
export class AppComponent implements OnInit {
  isAuthenticated = false;
  currentUrl = '';

  constructor(
    private authService: AuthService,
    private router: Router
  ) {
    this.authService.currentAdmin$.subscribe(admin => {
      this.isAuthenticated = !!admin;
    });
  }

  ngOnInit(): void {
    this.router.events
      .pipe(filter((event): event is NavigationEnd => event instanceof NavigationEnd))
      .subscribe((event: NavigationEnd) => {
        this.currentUrl = event.url;
      });
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/admin/login']);
  }
}
