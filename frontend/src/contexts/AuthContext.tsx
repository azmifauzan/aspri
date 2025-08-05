// src/contexts/AuthContext.tsx
import React, { createContext, useContext, useState, useEffect } from 'react';
import type { ReactNode } from 'react';
import axios from 'axios';

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
  login: (googleToken: string) => Promise<{ success: boolean; isRegistered: boolean; error?: string }>;
  logout: () => void;
  updateUser: (userData: User) => void;
  isLoading: boolean;
  checkTokenValidity: () => Promise<boolean>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';

// Helper function to check if token is expired
const isTokenExpired = (token: string): boolean => {
  try {
    const payload = JSON.parse(atob(token.split('.')[1]));
    const currentTime = Date.now() / 1000;
    // Add 5 minute buffer before actual expiry
    return payload.exp < (currentTime + 300);
  } catch (error) {
    console.error('Error parsing token:', error);
    return true; // Consider invalid tokens as expired
  }
};

export const AuthProvider: React.FC<{ children: ReactNode }> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(localStorage.getItem('token'));
  const [isLoading, setIsLoading] = useState(false);
  const [lastTokenCheck, setLastTokenCheck] = useState<number>(0);

  useEffect(() => {
    if (token) {
      // Check if token is expired before making requests
      if (isTokenExpired(token)) {
        console.log('Token is expired, logging out...');
        logout();
        return;
      }

      // Set default axios header
      axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
      
      // Only fetch user if we don't have user data yet
      if (!user) {
        fetchCurrentUser();
      }
    }
  }, [token]);

  // Setup axios interceptor to handle 401 responses
  useEffect(() => {
    const interceptor = axios.interceptors.response.use(
      (response) => response,
      (error) => {
        if (error.response?.status === 401) {
          console.log('Received 401, token might be expired. Logging out...');
          logout();
        }
        return Promise.reject(error);
      }
    );

    return () => {
      axios.interceptors.response.eject(interceptor);
    };
  }, []);

  const fetchCurrentUser = async () => {
    try {
      const response = await axios.get(`${API_BASE_URL}/auth/me`);
      setUser(response.data);
      setLastTokenCheck(Date.now());
    } catch (error) {
      console.error('Failed to fetch current user:', error);
      logout();
    }
  };

  const checkTokenValidity = async (): Promise<boolean> => {
    if (!token) {
      return false;
    }

    // First check if token is expired locally (no backend call)
    if (isTokenExpired(token)) {
      logout();
      return false;
    }

    // If we checked recently (within last 10 minutes), assume it's still valid
    const now = Date.now();
    if (lastTokenCheck && (now - lastTokenCheck) < 10 * 60 * 1000) {
      return true;
    }

    try {
      // Only verify with backend if we haven't checked recently
      const response = await axios.get(`${API_BASE_URL}/auth/me`);
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
  };

  const login = async (googleToken: string): Promise<{ success: boolean; isRegistered: boolean; error?: string }> => {
    setIsLoading(true);
    try {
      const response = await axios.post(`${API_BASE_URL}/auth/login`, {
        google_token: googleToken
      });

      const { access_token, user: userData, is_registered } = response.data;
      
      setToken(access_token);
      setUser(userData);
      setLastTokenCheck(Date.now());
      localStorage.setItem('token', access_token);
      axios.defaults.headers.common['Authorization'] = `Bearer ${access_token}`;

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

  const logout = () => {
    setUser(null);
    setToken(null);
    setLastTokenCheck(0);
    localStorage.removeItem('token');
    delete axios.defaults.headers.common['Authorization'];
  };

  const value: AuthContextType = {
    user,
    token,
    login,
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