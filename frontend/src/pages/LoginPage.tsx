// src/pages/LoginPage.tsx
import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, LogIn } from 'lucide-react';
import { Link } from 'react-router-dom';
import Navbar from '../components/Navbar';
import Footer from '../components/Footer';

export default function LoginPage() {
  const navigate = useNavigate();
  const { login, user, isLoading } = useAuth();
  const { t } = useTranslation();

  useEffect(() => {
    // If user is already logged in, redirect based on registration status
    if (user) {
      if (user.is_registered) {
        navigate('/dashboard');
      } else {
        navigate('/register');
      }
    }
  }, [user, navigate]);

  const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000';

  const handleLoginClick = () => {
    // Redirect to the backend login endpoint
    window.location.href = `${API_BASE_URL}/auth/google/login`;
  };

  return (
    <>
      <Navbar />
      <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 dark:from-zinc-900 dark:via-zinc-800 dark:to-zinc-900 flex items-center justify-center px-4 pt-16">
        <div className="max-w-md w-full">
          {/* Back button */}
          <Link 
            to="/" 
            className="inline-flex items-center gap-2 text-zinc-600 dark:text-zinc-400 hover:text-brand dark:hover:text-brand transition mb-8"
          >
            <ArrowLeft size={20} />
            {t('common.back_to_home')}
          </Link>

          {/* Login Card */}
          <div className="bg-white dark:bg-zinc-800 rounded-2xl shadow-xl p-8">
            <div className="text-center mb-8">
              <h1 className="text-3xl font-bold text-zinc-900 dark:text-white mb-2">
                {t('auth.welcome_back')}
              </h1>
              <p className="text-zinc-600 dark:text-zinc-400">
                {t('auth.sign_in_description')}
              </p>
            </div>

            {/* Login Button */}
            <div className="flex justify-center">
              {isLoading ? (
                <div className="flex items-center justify-center py-3 px-6 w-full bg-zinc-100 dark:bg-zinc-700 rounded-lg">
                  <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-brand"></div>
                  <span className="ml-3 text-zinc-600 dark:text-zinc-400">
                    {t('auth.signing_in')}
                  </span>
                </div>
              ) : (
                <button
                  onClick={handleLoginClick}
                  className="w-full flex items-center justify-center gap-3 bg-brand hover:bg-brand/90 text-white font-semibold py-3 px-6 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-brand"
                >
                  <LogIn size={20} />
                  <span>{t('auth.sign_in_with_google')}</span>
                </button>
              )}
            </div>

            {/* Additional Info */}
            <div className="mt-8 text-center">
              <p className="text-sm text-zinc-500 dark:text-zinc-400">
                {t('auth.privacy_notice')}
              </p>
            </div>
          </div>
        </div>
      </div>
      <Footer />
    </>
  );
}