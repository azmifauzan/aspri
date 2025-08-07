// src/pages/AuthCallback.tsx
import { useEffect } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

export default function AuthCallback() {
  const { handleAuthCallback, user } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();

  useEffect(() => {
    const searchParams = new URLSearchParams(location.search);
    const token = searchParams.get('token');

    if (token) {
      handleAuthCallback(token);
    } else {
      // Handle error: no token found
      console.error("No token found in callback URL");
      navigate('/login');
    }
  }, [location, handleAuthCallback, navigate]);

  useEffect(() => {
    if (user) {
      if (user.is_registered) {
        navigate('/dashboard');
      } else {
        navigate('/register');
      }
    }
  }, [user, navigate]);

  return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="text-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-brand mb-4"></div>
        <h1 className="text-2xl font-bold">Authenticating...</h1>
        <p className="text-zinc-600">Please wait while we log you in.</p>
      </div>
    </div>
  );
}
