import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8888',
});

// Request interceptor to add the auth token to every request
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor to handle 401 errors globally
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // If a 401 is received, remove the token and dispatch an event
      // that the AuthProvider will listen for to update its state.
      localStorage.removeItem('token');
      window.dispatchEvent(new Event('auth-error'));
    }
    return Promise.reject(error);
  }
);

export default api;
