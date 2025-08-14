// src/pages/LoginPage.tsx
import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { ArrowLeft } from 'lucide-react';
import GoogleLogo from '../assets/google.svg';
import { Link } from 'react-router-dom';
import Navbar from '../components/Navbar';
import Footer from '../components/Footer';

export default function LoginPage() {
  const navigate = useNavigate();
  const { user, isLoading } = useAuth();
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
                  className="w-full flex items-center justify-center gap-3 bg-white border border-zinc-200 dark:border-zinc-700 shadow-sm hover:shadow-md text-zinc-900 font-semibold py-3 px-6 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#4285F4]"
                  style={{ boxShadow: '0 1px 2px rgba(60,64,67,.08)' }}
                  type="button"
                  aria-label={t('auth.sign_in_with_google')}
                >
                  <img src={GoogleLogo} alt="Google" className="w-5 h-5" style={{ marginRight: 8 }} />
                  <span className="text-sm font-medium text-zinc-900" style={{ color: '#5F6368' }}>{t('auth.sign_in_with_google')}</span>
                </button>
              )}
            </div>

            {/* Additional Info */}
            <div className="mt-8 text-center">
              <p className="text-sm text-zinc-500 dark:text-zinc-400">
                By signing in, you agree to our{' '}
                <Link to="/terms" className="underline hover:text-brand">
                  {t('legal.terms_of_service.title')}
                </Link>{' '}
                and{' '}
                <Link to="/privacy" className="underline hover:text-brand">
                  {t('legal.privacy_policy.title')}
                </Link>
                .
              </p>
            </div>
          </div>
        </div>
      </div>
      <Footer />
    </>
  );
}