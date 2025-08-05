// src/components/ProtectedRoute.tsx
import React, { useEffect, useState } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';

interface ProtectedRouteProps {
  children: React.ReactNode;
}

const ProtectedRoute: React.FC<ProtectedRouteProps> = ({ children }) => {
  const { user, token, checkTokenValidity } = useAuth();
  const [isChecking, setIsChecking] = useState(true);
  const [isValid, setIsValid] = useState(false);
  const location = useLocation();

  useEffect(() => {
    const verifyToken = async () => {
      const valid = await checkTokenValidity();
      setIsValid(valid);
      setIsChecking(false);
    };

    if (token) {
      verifyToken();
    } else {
      setIsChecking(false);
      setIsValid(false);
    }
  }, [token, checkTokenValidity]);

  if (isChecking) {
    return <div>Loading...</div>; // Or a spinner component
  }

  if (!isValid) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  // If user is authenticated but not registered, and trying to access dashboard, redirect to register
  if (isValid && user && !user.is_registered && location.pathname !== '/register') {
    return <Navigate to="/register" replace />;
  }

  // If user is registered and tries to access register page, redirect to dashboard
  if (isValid && user && user.is_registered && location.pathname === '/register') {
    return <Navigate to="/dashboard" replace />;
  }

  return <>{children}</>;
};

export default ProtectedRoute;
