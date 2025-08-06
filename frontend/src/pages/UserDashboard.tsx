// src/pages/UserDashboard.tsx
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { useTranslation } from 'react-i18next';
import DocumentsPage from './DocumentsPage';
import ChatPage from './ChatPage';
import FinancePage from './FinancePage';
import ThemeToggle from '../components/ThemeToggle';
import LangToggle from '../components/LangToggle';
import {
  Home,
  Menu,
  X,
  MessageSquare,
  Calendar,
  FileText,
  LogOut,
  User,
  Settings,
  ChevronDown,
  Landmark
} from 'lucide-react';

const menuItems = [
  { id: 'dashboard', label: 'dashboard.menu.dashboard', icon: Home },
  { id: 'chat', label: 'dashboard.menu.chat', icon: MessageSquare },
  { id: 'documents', label: 'dashboard.menu.documents', icon: FileText },
  { id: 'finance', label: 'dashboard.menu.finance', icon: Landmark },
  { id: 'calendar', label: 'dashboard.menu.calendar', icon: Calendar },
];

export default function UserDashboard() {
  const { user, logout } = useAuth();
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [activeItem, setActiveItem] = useState('dashboard');
  const [userDropdownOpen, setUserDropdownOpen] = useState(false);

  const handleLogout = () => {
    logout();
    setUserDropdownOpen(false);
    navigate('/'); // Redirect to landing page instead of /#home
  };

  const handleProfileClick = () => {
    setUserDropdownOpen(false);
    // Handle profile navigation - could navigate to profile page
    setActiveItem('profile');
  };

  const handleSettingsClick = () => {
    setUserDropdownOpen(false);
    // Handle settings navigation - could navigate to settings page
    setActiveItem('settings');
  };

  const handleMenuClick = (itemId: string) => {
    setActiveItem(itemId);
    setSidebarOpen(false);
    // In a real app, you would navigate to the appropriate page or component
  };

  if (!user) {
    // This should technically not be reached if ProtectedRoute is used,
    // but as a fallback, we can show a loading state or redirect.
    // For now, let's assume ProtectedRoute does its job and user is always available.
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
          <div className="flex items-center justify-between h-14 px-4 border-b border-gray-200 dark:border-zinc-700">
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
          <div className="flex items-center justify-between h-14 px-4">
            <div className="flex items-center">
              <button
                onClick={() => setSidebarOpen(true)}
                className="lg:hidden text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200 mr-3"
              >
                <Menu size={20} />
              </button>
              <h1 className="text-lg font-semibold text-zinc-900 dark:text-white capitalize">
                {t(`dashboard.menu.${activeItem}`)}
              </h1>
            </div>
            
            {/* Right side - Toggles and User dropdown */}
            <div className="flex items-center gap-1">
              {/* Language and Theme toggles */}
              <LangToggle />
              <ThemeToggle />
              
              {/* User dropdown */}
              <div className="relative ml-2">
                <button
                  onClick={() => setUserDropdownOpen(!userDropdownOpen)}
                  className="flex items-center gap-2 py-1.5 px-2 text-zinc-700 dark:text-white hover:text-brand dark:hover:text-brand transition text-sm font-medium rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800"
                >
                  <div className="w-7 h-7 rounded-full bg-brand flex items-center justify-center text-white font-bold text-xs overflow-hidden">
                    {user.picture ? (
                      <img 
                        src={user.picture} 
                        alt={user.name || user.email}
                        className="w-full h-full object-cover"
                        onError={(e) => {
                          // Fallback to initials if image fails to load
                          const target = e.target as HTMLImageElement;
                          target.style.display = 'none';
                          const nextElement = target.nextElementSibling as HTMLElement;
                          if (nextElement) nextElement.style.display = 'block';
                        }}
                      />
                    ) : null}
                    <span className={user.picture ? 'hidden' : 'block'}>
                      {user.name ? user.name.charAt(0).toUpperCase() : user.email.charAt(0).toUpperCase()}
                    </span>
                  </div>
                  <span className="hidden md:block text-sm">{user.name || user.email}</span>
                  <ChevronDown size={14} className={`transition-transform ${userDropdownOpen ? 'rotate-180' : ''}`} />
                </button>

                {/* Dropdown menu */}
                {userDropdownOpen && (
                  <div className="absolute right-0 mt-2 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1 z-50">
                    <button
                      onClick={handleProfileClick}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors"
                    >
                      <User size={16} />
                      {t('dashboard.menu.profile')}
                    </button>
                    <button
                      onClick={handleSettingsClick}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition-colors"
                    >
                      <Settings size={16} />
                      {t('dashboard.menu.settings')}
                    </button>
                    <hr className="my-1 border-zinc-200 dark:border-zinc-700" />
                    <button
                      onClick={handleLogout}
                      className="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-zinc-700 transition-colors"
                    >
                      <LogOut size={16} />
                      {t('nav.logout')}
                    </button>
                  </div>
                )}
              </div>
            </div>
          </div>
        </header>
        
        {/* Dashboard content */}
        <main className="flex-1 p-0 md:p-0 overflow-y-auto">
          {activeItem === 'dashboard' && (
            <div className="max-w-4xl mx-auto p-4 md:p-6">
              <div className="bg-white dark:bg-zinc-800 rounded-xl shadow-sm p-6 mb-6">
                <h2 className="text-2xl font-bold text-zinc-900 dark:text-white mb-2">
                  {t('dashboard.welcome_back', { name: user.name || user.email })}
                </h2>
                {/* <p className="text-zinc-600 dark:text-zinc-400 mb-6">
                  {t('dashboard.dashboard_description')}
                </p> */}
                
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
                      <p className="text-sm text-zinc-500 dark:text-zinc-400">{t('register.aspri_name')}</p>
                      <p className="font-medium text-zinc-900 dark:text-white">{user.aspri_name}</p>
                    </div>
                    <div>
                      <p className="text-sm text-zinc-500 dark:text-zinc-400">{t('register.call_preference')}</p>
                      <p className="font-medium text-zinc-900 dark:text-white">{user.call_preference || '-'}</p>
                    </div>
                    <div>
                      <p className="text-sm text-zinc-500 dark:text-zinc-400">{t('register.aspri_persona')}</p>
                      <p className="font-medium text-zinc-900 dark:text-white">{user.aspri_persona || '-'}</p>
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
                      onClick={() => handleMenuClick('documents')}
                      className="bg-brand/10 hover:bg-brand/20 border border-brand/20 rounded-lg p-4 text-center transition-colors"
                    >
                      <FileText size={24} className="mx-auto mb-2 text-brand" />
                      <span className="font-medium text-zinc-900 dark:text-white">{t('dashboard.menu.documents')}</span>
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
            </div>
          )}
          
          {/* Chat Page */}
          {activeItem === 'chat' && (
            <div className="w-full h-full">
              <ChatPage />
            </div>
          )}
          
          {/* Documents Page */}
          {activeItem === 'documents' && (
            <div className="w-full">
              {/* Import and use DocumentsPage component */}
              {/* @ts-ignore */}
              <DocumentsPage />
            </div>
          )}
          
          {activeItem === 'finance' && (
            <div className="w-full">
              <FinancePage />
            </div>
          )}
          {/* Other pages will be added here */}
        </main>
      </div>
      
      {/* Overlay for mobile sidebar */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden"
          onClick={() => setSidebarOpen(false)}
        ></div>
      )}

      {/* Click outside to close user dropdown */}
      {userDropdownOpen && (
        <div
          className="fixed inset-0 z-40"
          onClick={() => setUserDropdownOpen(false)}
        />
      )}
    </div>
  );
}