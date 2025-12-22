import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { BehaviorSubject, Observable, tap } from 'rxjs';
import { environment } from '../../environments/environment';

export interface UserProfile {
  userId: string;
  email: string;
  fullName?: string;
  aspriName?: string;
  aspriPersona?: string;
  callPreference?: string;
  preferredLanguage?: string;
  themePreference?: string;
  createdAt?: string;
  updatedAt?: string;
}

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data?: T;
}

export interface AuthResponse {
  accessToken: string;
  refreshToken: string;
  tokenType: string;
  expiresIn: number;
  user: {
    id: string;
    email: string;
    role: string;
  };
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private apiUrl = environment.apiUrl;
  private tokenKey = 'aspri_access_token';
  private refreshTokenKey = 'aspri_refresh_token';
  
  private currentUserSubject = new BehaviorSubject<any>(null);
  public currentUser$ = this.currentUserSubject.asObservable();

  constructor(private http: HttpClient) {
    this.checkAuthStatus();
  }

  private checkAuthStatus() {
    const token = this.getToken();
    if (token) {
      // Verify token masih valid dengan get profile
      this.getCurrentUser().subscribe({
        next: (response) => {
          if (response.success && response.data) {
            this.currentUserSubject.next(response.data);
          }
        },
        error: () => {
          this.clearTokens();
        }
      });
    }
  }

  private getToken(): string | null {
    return localStorage.getItem(this.tokenKey);
  }

  private getRefreshToken(): string | null {
    return localStorage.getItem(this.refreshTokenKey);
  }

  private setTokens(accessToken: string, refreshToken: string) {
    localStorage.setItem(this.tokenKey, accessToken);
    localStorage.setItem(this.refreshTokenKey, refreshToken);
  }

  private clearTokens() {
    localStorage.removeItem(this.tokenKey);
    localStorage.removeItem(this.refreshTokenKey);
    this.currentUserSubject.next(null);
  }

  private getHeaders(): HttpHeaders {
    const token = this.getToken();
    return new HttpHeaders({
      'Content-Type': 'application/json',
      'Authorization': token ? `Bearer ${token}` : ''
    });
  }

  /**
   * Register user baru
   */
  register(email: string, password: string): Observable<ApiResponse<AuthResponse>> {
    return this.http.post<ApiResponse<AuthResponse>>(
      `${this.apiUrl}/auth/register`,
      { email, password }
    ).pipe(
      tap(response => {
        if (response.success && response.data) {
          this.setTokens(response.data.accessToken, response.data.refreshToken);
          this.currentUserSubject.next(response.data.user);
        }
      })
    );
  }

  /**
   * Login user
   */
  login(email: string, password: string): Observable<ApiResponse<AuthResponse>> {
    return this.http.post<ApiResponse<AuthResponse>>(
      `${this.apiUrl}/auth/login`,
      { email, password }
    ).pipe(
      tap(response => {
        if (response.success && response.data) {
          this.setTokens(response.data.accessToken, response.data.refreshToken);
          this.currentUserSubject.next(response.data.user);
        }
      })
    );
  }

  /**
   * Logout user
   */
  logout(): Observable<ApiResponse<void>> {
    return this.http.post<ApiResponse<void>>(
      `${this.apiUrl}/auth/logout`,
      {},
      { headers: this.getHeaders() }
    ).pipe(
      tap(() => {
        this.clearTokens();
      })
    );
  }

  /**
   * Get user profile
   */
  getProfile(): Observable<ApiResponse<UserProfile>> {
    return this.http.get<ApiResponse<UserProfile>>(
      `${this.apiUrl}/auth/profile`,
      { headers: this.getHeaders() }
    );
  }

  /**
   * Update user profile
   */
  updateProfile(updates: Partial<UserProfile>): Observable<ApiResponse<UserProfile>> {
    return this.http.put<ApiResponse<UserProfile>>(
      `${this.apiUrl}/auth/profile`,
      updates,
      { headers: this.getHeaders() }
    );
  }

  /**
   * Get current authenticated user
   */
  getCurrentUser(): Observable<ApiResponse<UserProfile>> {
    return this.http.get<ApiResponse<UserProfile>>(
      `${this.apiUrl}/auth/me`,
      { headers: this.getHeaders() }
    );
  }

  /**
   * Check if user is authenticated
   */
  isAuthenticated(): boolean {
    return !!this.getToken();
  }
}
