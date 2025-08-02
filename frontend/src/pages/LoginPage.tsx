// src/pages/LoginPage.tsx
import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { GoogleLogin } from '@react-oauth/google';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { ArrowLeft } from 'lucide-react';
import { Link } from 'react-router-dom';

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

  const handleGoogleSuccess = async (credentialResponse: any) => {
    if (credentialResponse.credential) {
      const result = await login(credentialResponse.credential);
      
      if (result.success) {
        if (result.isRegistered) {
          navigate('/dashboard');
        } else {
          navigate('/register');
        }
      } else {
        alert(result.error || 'Login failed. Please try again.');
      }
    }
  };

  const handleGoogleError = () => {
    console.error('Google login failed');
    alert('Google login failed. Please try again.');
  };

  return (
    <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 dark:from-zinc-900 dark:via-zinc-800 dark:to-zinc-900 flex items-center justify-center px-4">
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

          {/* Google Login Button */}
          <div className="flex justify-center">
            {isLoading ? (
              <div className="flex items-center justify-center py-3 px-6">
                <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-brand"></div>
                <span className="ml-2 text-zinc-600 dark:text-zinc-400">
                  {t('auth.signing_in')}
                </span>
              </div>
            ) : (
              <GoogleLogin
                onSuccess={handleGoogleSuccess}
                onError={handleGoogleError}
                useOneTap={false}
                theme="outline"
                size="large"
                text="signin_with"
                shape="rectangular"
                logo_alignment="left"
              />
            )}
          </div>

          {/* Additional Info */}
          <div className="mt-8 text-center">
            <p className="text-sm text-zinc-500 dark:text-zinc-400">
              {t('auth.privacy_notice')}
            </p>
          </div>
        </div>

        {/* Features Preview */}
        {/* <div className="mt-8 text-center">
          <h3 className="text-lg font-semibold text-zinc-900 dark:text-white mb-4">
            {t('auth.why_sign_in')}
          </h3>
          <div className="grid grid-cols-1 gap-3">
            <div className="flex items-center gap-3 text-zinc-600 dark:text-zinc-400">
              <div className="w-2 h-2 bg-brand rounded-full"></div>
              {t('auth.feature_1')}
            </div>
            <div className="flex items-center gap-3 text-zinc-600 dark:text-zinc-400">
              <div className="w-2 h-2 bg-brand rounded-full"></div>
              {t('auth.feature_2')}
            </div>
            <div className="flex items-center gap-3 text-zinc-600 dark:text-zinc-400">
              <div className="w-2 h-2 bg-brand rounded-full"></div>
              {t('auth.feature_3')}
            </div>
          </div>
        </div> */}
      </div>
    </div>
  );
}