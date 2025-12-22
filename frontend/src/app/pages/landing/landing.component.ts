import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { TranslateModule, TranslateService } from '@ngx-translate/core';
import { ThemeService } from '../../services/theme.service';

@Component({
  selector: 'app-landing',
  imports: [CommonModule, RouterModule, TranslateModule],
  templateUrl: './landing.component.html',
  styleUrl: './landing.component.css'
})
export class LandingComponent implements OnInit {
  theme: 'light' | 'dark' = 'light';
  currentLang = 'id';

  constructor(
    private router: Router,
    private themeService: ThemeService,
    private translate: TranslateService
  ) {
    // Initialize language
    const savedLang = localStorage.getItem('lang') || 'id';
    this.currentLang = savedLang;
    this.translate.use(savedLang);
  }

  ngOnInit() {
    this.theme = this.themeService.currentTheme;
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
