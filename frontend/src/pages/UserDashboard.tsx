// src/pages/UserDashboard.tsx
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import { 
  Home, 
  User, 
  Settings, 
  LogOut, 
  Menu, 
  X,
  MessageSquare,
  Calendar,
  BarChart3
} from 'lucide-react';
import ThemeToggle from '../components/ThemeToggle';
import LangToggle from '../components/LangToggle';

const menuItems = [
  { id: 'dashboard', label: 'dashboard.menu.dashboard', icon: Home },
  { id: 'chat', label: 'dashboard.menu.chat', icon: MessageSquare },
  { id: 'calendar', label: 'dashboard.menu.calendar', icon: Calendar },
  { id: 'analytics', label: 'dashboard.menu.analytics', icon: BarChart3 },
  { id: 'profile', label: 'dashboard.menu.profile', icon: User },
  { id: 'settings', label: 'dashboard.menu.settings', icon: Settings },
];

export default function UserDashboard() {
  const { user, logout } = useAuth();
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [activeItem, setActiveItem] = useState('dashboard');

  const handleLogout = () => {
    logout();
    navigate('/');
  };

  const handleMenuClick = (itemId: string) => {
    setActiveItem(itemId);
    setSidebarOpen(false);
    // In a real app, you would navigate to the appropriate page or component
  };

  // Redirect to login if user is not authenticated
  if (!user) {
    navigate('/login');
    return null;
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-zinc-900 flex">
      {/* Sidebar */}
      <div 
        className={`fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-zinc-800 shadow-lg transform transition-transform duration-300 ease-in-out lg:translate-x-0 lg:static lg:shadow-none ${
          sidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex flex-col h-full">
          {/* Sidebar header */}
          <div className="flex items-center justify-between h-16 px-4 border-b border-gray-200 dark:border-zinc-700">
            <div className="flex items-center">
              <div className="bg-brand w-8 h-8 rounded-lg flex items-center justify-center">
                <span className="text-white font-bold">A</span>
              </div>
              <span className="ml-2 text-xl font-bold text-zinc-900 dark:text-white">ASPRI</span>
            </div>
            <button 
              onClick={() => setSidebarOpen(false)}
              className="lg:hidden text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
            >
              <X size={24} />
            </button>
          </div>

          {/* Sidebar menu */}
          <div className="flex-1 overflow-y-auto py-4">
            <nav>
              <ul>
                {menuItems.map((item) => {
                  const Icon = item.icon;
                  return (
                    <li key={item.id}>
                      <button
                        onClick={() => handleMenuClick(item.id)}
                        className={`w-full flex items-center px-4 py-3 text-left transition-colors ${
                          activeItem === item.id
                            ? 'bg-brand/10 text-brand border-l-4 border-brand'
                            : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-700'
                        }`}
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

          {/* Sidebar footer */}
          <div className="p-4 border-t border-gray-200 dark:border-zinc-700">
            <button
              onClick={handleLogout}
              className="w-full flex items-center px-4 py-3 text-left text-red-600 hover:bg-red-50 dark:hover:bg-zinc-700 rounded-lg transition-colors"
            >
              <LogOut size={20} className="mr-3" />
              <span>{t('nav.logout')}</span>
            </button>
          </div>
        </div>
      </div>

      {/* Main content */}
      <div className="flex-1 flex flex-col lg:ml-0">
        {/* Top navbar */}
        <header className="bg-white dark:bg-zinc-800 border-b border-gray-200 dark:border-zinc-700">
          <div className="flex items-center justify-between h-16 px-4">
            <div className="flex items-center">
              <button
                onClick={() => setSidebarOpen(true)}
                className="lg:hidden text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 mr-4"
              >
                <Menu size={24} />
              </button>
              <h1 className="text-xl font-bold text-zinc-900 dark:text-white capitalize">
                {t(`dashboard.menu.${activeItem}`)}
              </h1>
            </div>
            
            <div className="flex items-center space-x-4">
              {/* Theme and Language toggles */}
              <div className="flex items-center space-x-2">
                <ThemeToggle />
                <LangToggle />
              </div>
              
              {/* User profile */}
              <div className="flex items-center">
                <div className="w-8 h-8 rounded-full bg-brand flex items-center justify-center text-white font-bold">
                  {user.name ? user.name.charAt(0).toUpperCase() : user.email.charAt(0).toUpperCase()}
                </div>
                <div className="ml-2 hidden md:block">
                  <p className="text-sm font-medium text-zinc-900 dark:text-white">
                    {user.name || user.email}
                  </p>
                  <p className="text-xs text-zinc-500 dark:text-zinc-400">
                    {user.email}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </header>

        {/* Dashboard content */}
        <main className="flex-1 p-4 md:p-6 overflow-y-auto">
          <div className="max-w-4xl mx-auto">
            <div className="bg-white dark:bg-zinc-800 rounded-xl shadow-sm p-6 mb-6">
              <h2 className="text-2xl font-bold text-zinc-900 dark:text-white mb-2">
                {t('dashboard.welcome_back', { name: user.name || user.email })}
              </h2>
              <p className="text-zinc-600 dark:text-zinc-400 mb-6">
                {t('dashboard.dashboard_description')}
              </p>
              
              {/* User info card */}
              <div className="border border-gray-200 dark:border-zinc-700 rounded-lg p-4 mb-6">
                <h3 className="text-lg font-semibold text-zinc-900 dark:text-white mb-3">
                  {t('dashboard.user_info')}
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm text-zinc-500 dark:text-zinc-400">{t('register.name')}</p>
                    <p className="font-medium text-zinc-900 dark:text-white">{user.name || '-'}</p>
                  </div>
                  <div>
                    <p className="text-sm text-zinc-500 dark:text-zinc-400">{t('register.email')}</p>
                    <p className="font-medium text-zinc-900 dark:text-white">{user.email}</p>
                  </div>
                  <div>
                    <p className="text-sm text-zinc-500 dark:text-zinc-400">{t('register.call_preference')}</p>
                    <p className="font-medium text-zinc-900 dark:text-white">{user.call_preference || '-'}</p>
                  </div>
                  <div>
                    <p className="text-sm text-zinc-500 dark:text-zinc-400">{t('register.aspri_name')}</p>
                    <p className="font-medium text-zinc-900 dark:text-white">{user.aspri_name || '-'}</p>
                  </div>
                </div>
              </div>
              
              {/* Quick actions */}
              <div>
                <h3 className="text-lg font-semibold text-zinc-900 dark:text-white mb-3">
                  {t('dashboard.quick_actions')}
                </h3>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <button 
                    onClick={() => handleMenuClick('chat')}
                    className="bg-brand/10 hover:bg-brand/20 border border-brand/20 rounded-lg p-4 text-center transition-colors"
                  >
                    <MessageSquare size={24} className="mx-auto mb-2 text-brand" />
                    <span className="font-medium text-zinc-900 dark:text-white">{t('dashboard.menu.chat')}</span>
                  </button>
                  <button 
                    onClick={() => handleMenuClick('calendar')}
                    className="bg-brand/10 hover:bg-brand/20 border border-brand/20 rounded-lg p-4 text-center transition-colors"
                  >
                    <Calendar size={24} className="mx-auto mb-2 text-brand" />
                    <span className="font-medium text-zinc-900 dark:text-white">{t('dashboard.menu.calendar')}</span>
                  </button>
                  <button 
                    onClick={() => handleMenuClick('profile')}
                    className="bg-brand/10 hover:bg-brand/20 border border-brand/20 rounded-lg p-4 text-center transition-colors"
                  >
                    <User size={24} className="mx-auto mb-2 text-brand" />
                    <span className="font-medium text-zinc-900 dark:text-white">{t('dashboard.menu.profile')}</span>
                  </button>
                </div>
              </div>
            </div>
            
            {/* Stats section */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div className="bg-white dark:bg-zinc-800 rounded-xl shadow-sm p-6">
                <h3 className="text-lg font-semibold text-zinc-900 dark:text-white mb-2">
                  {t('dashboard.conversations')}
                </h3>
                <p className="text-3xl font-bold text-brand">12</p>
                <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                  {t('dashboard.in_the_last_30_days')}
                </p>
              </div>
              <div className="bg-white dark:bg-zinc-800 rounded-xl shadow-sm p-6">
                <h3 className="text-lg font-semibold text-zinc-900 dark:text-white mb-2">
                  {t('dashboard.tasks_completed')}
                </h3>
                <p className="text-3xl font-bold text-brand">8</p>
                <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                  {t('dashboard.in_the_last_30_days')}
                </p>
              </div>
              <div className="bg-white dark:bg-zinc-800 rounded-xl shadow-sm p-6">
                <h3 className="text-lg font-semibold text-zinc-900 dark:text-white mb-2">
                  {t('dashboard.satisfaction')}
                </h3>
                <p className="text-3xl font-bold text-brand">94%</p>
                <p className="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                  {t('dashboard.user_rating')}
                </p>
              </div>
            </div>
          </div>
        </main>
      </div>

      {/* Overlay for mobile sidebar */}
      {sidebarOpen && (
        <div 
          className="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        ></div>
      )}
    </div>
  );
}