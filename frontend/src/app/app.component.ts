import { Component, OnInit } from "@angular/core";
import {
  RouterOutlet,
  RouterLink,
  Router,
  NavigationEnd,
} from "@angular/router";
import { CommonModule } from "@angular/common";
import { AuthService } from "./services/auth.service";
import { filter } from "rxjs/operators";

@Component({
  selector: "app-root",
  standalone: true,
  imports: [CommonModule, RouterOutlet, RouterLink],
  template: `
    <div class="app-container">
      <!-- 🌌 Navigation Bar -->
      <header class="app-header" [class.scrolled]="isScrolled">
        <div class="container header-content">
          <h1 class="logo">
            <a routerLink="/home">🎟️ Event<span>A</span></a>
          </h1>

          <nav class="nav" [class.open]="menuOpen">
            <button class="burger" (click)="toggleMenu()" aria-label="Toggle navigation">
              <span></span><span></span><span></span>
            </button>

            <div class="nav-links" [class.show]="menuOpen">
              <!-- 🌐 Public navigation: shown only on public routes -->
              <ng-container *ngIf="!isAuthenticated || isPublicRoute">
                <a routerLink="/home" class="nav-link" (click)="menuOpen=false">Home</a>
                <a routerLink="/about" class="nav-link" (click)="menuOpen=false">About</a>
                <a routerLink="/contact" class="nav-link" (click)="menuOpen=false">Contact</a>
                <a routerLink="/register" class="nav-link register" (click)="menuOpen=false">Register</a>
              </ng-container>

              <!-- Admin Login link: only for guests, NOT on public routes, and NOT on /admin/login -->
              <a *ngIf="!isAuthenticated && !isPublicRoute && currentUrl !== '/admin/login'"
                 routerLink="/admin/login"
                 class="nav-link"
                 (click)="menuOpen=false">
                Admin
              </a>

              <!-- 🔒 Authenticated Admin Navigation -->
              <ng-container *ngIf="isAuthenticated">
                <a routerLink="/admin/dashboard" class="nav-link" (click)="menuOpen=false">Dashboard</a>
                <a routerLink="/admin/participants" class="nav-link" (click)="menuOpen=false">Participants</a>
                <a routerLink="/admin/scanner" class="nav-link" (click)="menuOpen=false">Scanner (Fair)</a>
                <a routerLink="/admin/scanner/conference" class="nav-link" (click)="menuOpen=false">Scanner (Conference)</a>
                <a routerLink="/admin/avatar" class="nav-link" (click)="menuOpen=false">Avatar (Fair)</a>
                <a routerLink="/admin/avatar/conference" class="nav-link" (click)="menuOpen=false">Avatar (Conference)</a>
                <button (click)="logout(); menuOpen=false" class="btn-logout">Logout</button>
              </ng-container>
            </div>
          </nav>
        </div>
      </header>

      <!-- 🌠 Page Content -->
      <main class="app-main">
        <router-outlet></router-outlet>
      </main>

      <!-- 🌍 Footer -->
   <!--   <footer class="app-footer">
        <div class="footer-content">
          <p>© 2025 <strong>EventAccess</strong> — Innovating Event Experiences.</p>
          <div class="socials">
            <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
            <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
            <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
          </div>
        </div>
      </footer>-->
    </div>
  `,
  styles: [`
    /* Layout */
    .app-container {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
      color: #111827;
      font-family: 'Inter', sans-serif;
    }

    /* Header */
    .app-header {
      position: sticky;
      top: 0;
      width: 100%;
      z-index: 1000;
      backdrop-filter: blur(16px);
      background: rgba(255, 255, 255, 0.65);
      box-shadow: 0 4px 20px rgba(0,0,0,0.05);
      transition: all 0.4s ease;
    }
    .app-header.scrolled {
      background: rgba(255,255,255,0.9);
      box-shadow: 0 6px 24px rgba(0,0,0,0.1);
    }

    .header-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 2rem;
      max-width: 1200px;
      margin: auto;
    }

    .logo a {
      font-size: 1.7rem;
      font-weight: 700;
      text-decoration: none;
      color: #2563eb;
      letter-spacing: -0.5px;
      transition: all 0.3s ease;
    }
    .logo span {
      color: #1e40af;
    }
    .logo a:hover {
      opacity: 0.85;
    }

    /* Navbar */
    .nav {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .nav-link {
      position: relative;
      text-decoration: none;
      color: #1f2937;
      font-weight: 500;
      padding: 0.6rem 1rem;
      border-radius: 8px;
      transition: all 0.3s ease;
    }

    .nav-link:hover {
      background: rgba(37, 99, 235, 0.1);
      color: #2563eb;
    }

    .register {
      background: #2563eb;
      color: #fff !important;
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(37,99,235,0.3);
    }
    .register:hover {
      background: #1d4ed8;
      transform: translateY(-1px);
    }

    .btn-logout {
      background: #ef4444;
      border: none;
      color: #fff;
      border-radius: 8px;
      padding: 0.5rem 1rem;
      cursor: pointer;
      transition: all 0.3s;
    }
    .btn-logout:hover {
      background: #dc2626;
    }

    /* Mobile menu */
    .burger {
      display: none;
      flex-direction: column;
      border: none;
      background: none;
      cursor: pointer;
    }
    .burger span {
      height: 2px;
      width: 24px;
      background: #1f2937;
      margin: 4px 0;
      transition: 0.3s;
    }

    @media (max-width: 768px) {
      .burger {
        display: flex;
      }

      .nav-links {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        background: rgba(255,255,255,0.95);
        backdrop-filter: blur(12px);
        border-radius: 0 0 12px 12px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        flex-direction: column;
        width: 100%;
        text-align: center;
        padding: 1rem 0;
      }

      .nav-links.show {
        display: flex;
      }

      .nav-link {
        padding: 1rem;
        font-size: 1.1rem;
      }
    }

    /* Footer */
    .app-footer {
      background: linear-gradient(90deg, #1e3a8a, #2563eb);
      color: #fff;
      text-align: center;
      padding: 2rem 1rem;
      margin-top: auto;
    }
    .footer-content {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
    }
    .socials a {
      color: #fff;
      font-size: 1.2rem;
      margin: 0 0.5rem;
      transition: opacity 0.3s;
    }
    .socials a:hover {
      opacity: 0.7;
    }

    .app-main {
      flex: 1;
      animation: fadeIn 0.6s ease-in-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  `]
})
export class AppComponent implements OnInit {
  isAuthenticated = false;
  currentUrl = '';
  menuOpen = false;
  isScrolled = false;

  get isPublicRoute(): boolean {
    const publicPaths = ['/', '/home', '/about', '/contact', '/register','/badge'];
    return publicPaths.some(path =>
        this.currentUrl === path || this.currentUrl.startsWith(path + '/')
    );
  }

  constructor(private authService: AuthService, private router: Router) {
    this.authService.currentAdmin$.subscribe(admin => {
      this.isAuthenticated = !!admin;
    });
  }

  ngOnInit(): void {
    this.router.events
        .pipe(filter((event): event is NavigationEnd => event instanceof NavigationEnd))
        .subscribe((event: NavigationEnd) => {
          this.currentUrl = event.url.split('?')[0];

          // 🔒 Auto-redirect authenticated users away from public pages
          if (this.isAuthenticated && this.isPublicRoute) {
            this.router.navigate(['/admin/scanner']);
          }
        });

    window.addEventListener('scroll', () => {
      this.isScrolled = window.scrollY > 30;
    });
  }

  toggleMenu() {
    this.menuOpen = !this.menuOpen;
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/admin/login']);
  }
}
