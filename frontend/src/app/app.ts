import { Component, OnInit } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { TranslateService } from '@ngx-translate/core';
import { ThemeService } from './services/theme.service';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet],
  templateUrl: './app.html',
  styleUrl: './app.css',
})
export class App implements OnInit {
  constructor(
    private translate: TranslateService,
    private themeService: ThemeService
  ) {
    // Set default language
    translate.setDefaultLang('id');
    
    // Use saved language or default to 'id'
    const savedLang = localStorage.getItem('lang') || 'id';
    translate.use(savedLang);
  }

  ngOnInit() {
    // Initialize theme
    // Theme service is already initialized in its constructor
  }
}
