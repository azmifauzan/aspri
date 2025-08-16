import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import ThemeToggle from './ThemeToggle';
import LangToggle from './LangToggle';
import { Home, Menu, X, LogOut, User, Settings, ChevronDown, MessageSquare, FileText, Calendar, Landmark, Users } from 'lucide-react';
import { useLocation } from 'react-router-dom';

interface Props {
  title?: string;
  children: React.ReactNode;
}

const DashboardLayout: React.FC<Props> = ({ title, children }) => {
  let user = null as any;
  let logout = () => {};
  try {
    const auth = useAuth();
    user = auth.user;
    logout = auth.logout;
  } catch (err) {
    // If AuthProvider is not present, fallback to safe defaults to avoid runtime crash.
    user = null;
    logout = () => { window.location.href = '/'; };
  }
  const { t } = useTranslation();
  const navigate = useNavigate();
  const location = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [userDropdownOpen, setUserDropdownOpen] = useState(false);

  const handleLogout = () => {
    logout();
    setUserDropdownOpen(false);
    navigate('/');
  };

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-zinc-900 flex">
      {/* Sidebar - minimal copy of existing styles */}
  <div className={`fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-zinc-800 shadow-lg transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:shadow-none ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'}`}>
        <div className="flex flex-col h-full">
          <div className="flex items-center justify-between h-14 px-4 border-b border-gray-200 dark:border-zinc-700">
            <div className="flex items-center">
              <div className="bg-brand w-8 h-8 rounded-lg flex items-center justify-center">
                <span className="text-white font-bold">A</span>
              </div>
              <span className="ml-2 text-xl font-bold text-zinc-900 dark:text-white">ASPRI</span>
            </div>
            <button onClick={() => setSidebarOpen(false)} className="lg:hidden text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200">
              <X size={24} />
            </button>
          </div>

          <div className="flex-1 overflow-y-auto py-4">
            <nav>
              <ul>
                {[
                  { id: 'dashboard', label: 'dashboard.menu.dashboard', icon: Home, path: '/dashboard' },
                  { id: 'chat', label: 'dashboard.menu.chat', icon: MessageSquare, path: '/chat' },
                  { id: 'documents', label: 'dashboard.menu.documents', icon: FileText, path: '/documents' },
                  { id: 'finance', label: 'dashboard.menu.finance', icon: Landmark, path: '/finance' },
                  { id: 'contacts', label: 'dashboard.menu.contacts', icon: Users, path: '/contacts' },
                  { id: 'calendar', label: 'dashboard.menu.calendar', icon: Calendar, path: '/calendar' },
                ].map((item) => {
                  const Icon = item.icon as any;
                  const isActive = location.pathname.startsWith(item.path);
                  return (
                    <li key={item.id}>
                      <button
                        onClick={() => navigate(item.path)}
                        className={`w-full flex items-center px-4 py-3 text-left transition-colors ${isActive ? 'bg-brand/10 text-brand border-l-4 border-brand' : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700'}`}
                      >
                        <Icon size={20} className="mr-3" />
                        <span>{t(item.label)}</span>
                      </button>
                    </li>
                  );
                })}
              </ul>
            </nav>
          </div>

          <div className="p-4 border-t border-gray-200 dark:border-zinc-700">
            <button onClick={handleLogout} className="w-full flex items-center px-4 py-3 text-left text-red-600 hover:bg-red-50 dark:hover:bg-zinc-700 rounded-lg transition-colors">
              <LogOut size={20} className="mr-3" />
              <span>{t('nav.logout')}</span>
            </button>
          </div>
        </div>
      </div>

      {/* Main */}
      <div className="flex-1 flex flex-col lg:ml-0">
        <header className="bg-white dark:bg-zinc-800 border-b border-gray-200 dark:border-zinc-700">
          <div className="flex items-center justify-between h-14 px-4">
            <div className="flex items-center">
              <button onClick={() => setSidebarOpen(true)} className="lg:hidden text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 mr-3">
                <Menu size={20} />
              </button>
              <h1 className="text-lg font-semibold text-zinc-900 dark:text-white capitalize">{title}</h1>
            </div>

            <div className="flex items-center gap-1">
              <LangToggle />
              <ThemeToggle />

              <div className="relative ml-2">
                <button onClick={() => setUserDropdownOpen(!userDropdownOpen)} className="flex items-center gap-2 py-1.5 px-2 text-zinc-700 dark:text-white hover:text-brand dark:hover:text-brand transition text-sm font-medium rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800">
                  <div className="w-7 h-7 rounded-full bg-brand flex items-center justify-center text-white font-bold text-xs overflow-hidden">
                    {user?.picture ? (
                      <img src={user.picture} alt={user.name || user.email} className="w-full h-full object-cover" />
                    ) : (
                      <span>{user?.name ? user.name.charAt(0).toUpperCase() : user?.email?.charAt(0).toUpperCase()}</span>
                    )}
                  </div>
                  <span className="hidden md:block text-sm">{user?.name || user?.email}</span>
                  <ChevronDown size={14} className={`transition-transform ${userDropdownOpen ? 'rotate-180' : ''}`} />
                </button>

                {userDropdownOpen && (
                  <div className="absolute right-0 mt-2 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 z-50">
                    <button onClick={() => navigate('/profile')} className="w-full flex items-center gap-2 px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                      <User size={16} />
                      {t('dashboard.menu.profile')}
                    </button>
                    <button onClick={() => navigate('/settings')} className="w-full flex items-center gap-2 px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors">
                      <Settings size={16} />
                      {t('dashboard.menu.settings')}
                    </button>
                    <hr className="my-1 border-zinc-200 dark:border-zinc-700" />
                    <button onClick={handleLogout} className="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-zinc-700 transition-colors">
                      <LogOut size={16} />
                      {t('nav.logout')}
                    </button>
                  </div>
                )}
              </div>
            </div>
          </div>
        </header>

        <main className="flex-1 p-4 md:p-6 overflow-y-auto">
          {children}
        </main>
      </div>

      {/* Overlay */}
      {sidebarOpen && (
        <div className="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden" onClick={() => setSidebarOpen(false)} />
      )}
    </div>
  );
};

export default DashboardLayout;
