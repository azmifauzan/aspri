// src/components/ProtectedRoute.tsx
import { Navigate, useLocation } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import React, { useEffect, useState } from 'react';

interface ProtectedRouteProps {
  children: React.ReactElement;
}

const ProtectedRoute: React.FC<ProtectedRouteProps> = ({ children }) => {
  const { user, token, checkTokenValidity } = useAuth();
  const [isValidating, setIsValidating] = useState(true);
  const location = useLocation();

  useEffect(() => {
    const validate = async () => {
      if (!token) {
        setIsValidating(false);
        return;
      }
      // We check the token validity, which also fetches the user
      await checkTokenValidity();
      setIsValidating(false);
    };

    validate();
  }, [token, checkTokenValidity]);

  if (isValidating) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-zinc-900 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-brand mx-auto mb-4"></div>
          <p className="text-zinc-600 dark:text-zinc-400">Validating session...</p>
        </div>
      </div>
    );
  }

  if (!user || !token) {
    // Redirect them to the /login page, but save the current location they were
    // trying to go to. This allows us to send them along to that page after a
    // successful login.
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  return children;
};

export default ProtectedRoute;
