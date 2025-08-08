// src/contexts/AuthContext.tsx
import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import type { ReactNode } from 'react';
import api from '../services/api';

interface User {
  id: number;
  email: string;
  google_id: string;
  name?: string;
  picture?: string; // Google profile picture URL
  birth_date?: number;
  birth_month?: number;
  call_preference?: string;
  aspri_name?: string;
  aspri_persona?: string;
  is_registered: boolean;
  created_at: string;
  updated_at?: string;
}

interface AuthContextType {
  user: User | null;
  token: string | null;
  loginWithGoogleToken: (googleToken: string) => Promise<{ success: boolean; isRegistered: boolean; error?: string }>;
  handleAuthCallback: (token: string) => Promise<void>;
  logout: () => void;
  updateUser: (userData: User) => void;
  isLoading: boolean;
  checkTokenValidity: () => Promise<boolean>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

// Helper function to check if token is expired
const isTokenExpired = (token: string): boolean => {
  try {
    const payload = JSON.parse(atob(token.split('.')[1]));
    const currentTime = Date.now() / 1000;
    return payload.exp < currentTime;
  } catch (error) {
    console.error('Error parsing token:', error);
    return true; // Consider invalid tokens as expired
  }
};

export const AuthProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(() => localStorage.getItem('token'));
  const [isLoading, setIsLoading] = useState(true); // Start with loading true
  const [lastTokenCheck, setLastTokenCheck] = useState<number>(0);

  const logout = useCallback(() => {
    setUser(null);
    setToken(null);
    setLastTokenCheck(0);
    localStorage.removeItem('token');
    // The request interceptor in api.ts will no longer find a token.
    // The 401 interceptor will handle redirecting if a protected route is accessed.
  }, []);

  useEffect(() => {
    const handleAuthError = () => {
      console.log('Auth error event received, logging out.');
      logout();
    };
    window.addEventListener('auth-error', handleAuthError);
    return () => {
      window.removeEventListener('auth-error', handleAuthError);
    };
  }, [logout]);

  const fetchCurrentUser = useCallback(async () => {
    try {
      const response = await api.get('/auth/me');
      setUser(response.data);
      setLastTokenCheck(Date.now());
    } catch (error) {
      console.error('Failed to fetch current user:', error);
      logout(); // The interceptor should have already handled this, but as a fallback.
    }
  }, [logout]);

  useEffect(() => {
    if (token) {
      if (isTokenExpired(token)) {
        console.log('Token is expired, logging out...');
        logout();
        setIsLoading(false);
        return;
      }
      if (!user) {
        fetchCurrentUser().finally(() => setIsLoading(false));
      } else {
        setIsLoading(false);
      }
    } else {
      setIsLoading(false);
    }
  }, [token, user, fetchCurrentUser, logout]);

  const checkTokenValidity = useCallback(async (): Promise<boolean> => {
    if (!token) {
      return false;
    }
    if (isTokenExpired(token)) {
      logout();
      return false;
    }
    const now = Date.now();
    if (lastTokenCheck && (now - lastTokenCheck) < 10 * 60 * 1000) {
      return true;
    }
    try {
      const response = await api.get('/auth/me');
      if (response.data) {
        setUser(response.data);
        setLastTokenCheck(now);
        return true;
      }
      return false;
    } catch (error) {
      console.error('Token validation failed:', error);
      logout();
      return false;
    }
  }, [token, lastTokenCheck, logout]);

  const handleAuthCallback = async (jwtToken: string): Promise<void> => {
    setIsLoading(true);
    try {
      localStorage.setItem('token', jwtToken);
      setToken(jwtToken);
      // The useEffect will trigger fetchCurrentUser
    } catch (error) {
      console.error("Auth callback failed:", error);
      logout();
    } finally {
      setIsLoading(false);
    }
  };

  const loginWithGoogleToken = async (googleToken: string): Promise<{ success: boolean; isRegistered: boolean; error?: string }> => {
    setIsLoading(true);
    try {
      const response = await api.post('/auth/login_with_token', {
        google_token: googleToken
      });
      const { access_token, user: userData, is_registered } = response.data;
      localStorage.setItem('token', access_token);
      setToken(access_token);
      setUser(userData);
      setLastTokenCheck(Date.now());
      return { success: true, isRegistered: is_registered };
    } catch (error: any) {
      console.error('Login failed:', error);
      return { 
        success: false, 
        isRegistered: false, 
        error: error.response?.data?.detail || 'Login failed' 
      };
    } finally {
      setIsLoading(false);
    }
  };

  const updateUser = (userData: User) => {
    setUser(userData);
  };

  const value: AuthContextType = {
    user,
    token,
    loginWithGoogleToken,
    handleAuthCallback,
    logout,
    updateUser,
    isLoading,
    checkTokenValidity
  };

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = (): AuthContextType => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};