import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { AuthService, UserProfile } from '../../services/auth.service';
import { ThemeService } from '../../services/theme.service';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, RouterModule, TranslateModule],
  templateUrl: './dashboard.component.html',
  styleUrl: './dashboard.component.css'
})
export class DashboardComponent implements OnInit {
  profile: UserProfile | null = null;
  theme: 'light' | 'dark' = 'light';
  currentLang = 'id';

  constructor(
    private authService: AuthService,
    private router: Router,
    private themeService: ThemeService,
    private translate: TranslateService
  ) {}

  ngOnInit() {
    this.theme = this.themeService.currentTheme;
    this.currentLang = this.translate.currentLang || 'id';
    this.loadProfile();
  }

  loadProfile() {
    this.authService.getProfile().subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.profile = response.data;
        }
      },
      error: (error) => {
        console.error('Failed to load profile', error);
      }
    });
  }

  logout() {
    this.authService.logout().subscribe({
      next: () => {
        this.router.navigate(['/']);
      },
      error: (error) => {
        console.error('Logout error', error);
        // Redirect anyway
        this.router.navigate(['/']);
      }
    });
  }

  toggleTheme() {
    this.themeService.toggleTheme();
    this.theme = this.themeService.currentTheme;
  }

  toggleLanguage() {
    this.currentLang = this.currentLang === 'id' ? 'en' : 'id';
    this.translate.use(this.currentLang);
    localStorage.setItem('lang', this.currentLang);
  }
}
