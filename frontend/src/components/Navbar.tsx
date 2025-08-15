// src/components/Navbar.tsx
import { useState } from "react";
import { Menu, X, User, LogOut, Settings, ChevronDown } from "lucide-react";
import { Link, useLocation } from "react-router-dom";
import ThemeToggle from "./ThemeToggle";
import LangToggle from "./LangToggle";
import { useTranslation } from "react-i18next";
import { useAuth } from "../contexts/AuthContext";

const links = [
  { id: "home", label: "nav.home" },
  { id: "problem", label: "nav.features" },
  { id: "chat", label: "nav.preview" },
  { id: "contact", label: "nav.contact" },
];

// Links for legal pages
const legalLinks = [
  { to: "/terms", label: "nav.terms" },
  { to: "/privacy", label: "nav.privacy" },
];

/* ✅ hanya satu itemClass */
const itemClass =
  "py-2 px-4 text-zinc-700 dark:text-white hover:text-brand dark:hover:text-brand transition text-sm font-medium";

export default function Navbar() {
  const [open, setOpen] = useState(false);
  const [userDropdownOpen, setUserDropdownOpen] = useState(false);
  const { t } = useTranslation();
  const { user, logout } = useAuth();
  const location = useLocation();

  // Check if we're on the landing page, login, terms, or privacy to show navigation links
  const isLandingPage = location.pathname === '/';
  const isLoginPage = location.pathname === '/login';
  const isTermsPage = location.pathname === '/terms';
  const isPrivacyPage = location.pathname === '/privacy';
  const isDashboard = location.pathname === '/dashboard';

  // Show navigation on landing, login, terms, and privacy pages
  const showNavigation = isLandingPage || isLoginPage || isTermsPage || isPrivacyPage;

  const handleLogout = () => {
    setUserDropdownOpen(false);
    logout(); // logout from useAuth will now handle the redirect
  };

  const handleProfileClick = () => {
    setUserDropdownOpen(false);
    // Handle profile navigation
  };

  const handleSettingsClick = () => {
    setUserDropdownOpen(false);
    // Handle settings navigation
  };

  return (
    <header className="fixed top-0 w-full bg-white/70 dark:bg-zinc-900/80 backdrop-blur z-50 shadow-sm">
      <div className="max-w-6xl mx-auto flex items-center justify-between px-4 h-14">
        {/* Left side - Logo and Navigation */}
        <div className="flex items-center gap-6">
          {/* Logo with icon */}
          <Link to="/" className="flex items-center gap-2 text-brand font-bold text-lg">
            <div className="bg-brand w-8 h-8 rounded-lg flex items-center justify-center">
              <span className="text-white font-bold text-sm">A</span>
            </div>
            ASPRI
          </Link>

          {/* Navigation links - only on landing page and login page and desktop */}
          {showNavigation && (
            <nav className="hidden md:flex items-center gap-2">
              {links.map((l) => (
                <a key={l.id} href={`/#${l.id}`} className={itemClass}>
                  {t(l.label)}
                </a>
              ))}
              {/* Legal links */}
              {/* {legalLinks.map((l) => (
                <Link key={l.to} to={l.to} className={itemClass}>
                  {t(l.label)}
                </Link>
              ))} */}
            </nav>
          )}
        </div>

        {/* Right side - Auth, Language, Theme */}
        <div className="flex items-center gap-2">
          {/* Authentication */}
          {user ? (
            <div className="relative">
              {isDashboard ? (
                // Dashboard: User dropdown
                <div className="relative">
                  <button
                    onClick={() => setUserDropdownOpen(!userDropdownOpen)}
                    className="flex items-center gap-2 py-2 px-3 text-zinc-700 dark:text-white hover:text-brand dark:hover:text-brand transition text-sm font-medium rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-800"
                  >
                    <div className="w-6 h-6 rounded-full bg-brand flex items-center justify-center text-white font-bold text-xs overflow-hidden">
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
                    <span className="hidden md:block">{user.name || user.email}</span>
                    <ChevronDown size={16} />
                  </button>

                  {/* Dropdown menu */}
                  {userDropdownOpen && (
                    <div className="absolute right-0 mt-2 w-48 bg-white dark:bg-zinc-800 rounded-lg shadow-lg border border-zinc-200 dark:border-zinc-700 py-1">
                      <button
                        onClick={handleProfileClick}
                        className="w-full flex items-center gap-2 px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                      >
                        <User size={16} />
                        {t('dashboard.menu.profile')}
                      </button>
                      <button
                        onClick={handleSettingsClick}
                        className="w-full flex items-center gap-2 px-4 py-2 text-sm text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700"
                      >
                        <Settings size={16} />
                        {t('dashboard.menu.settings')}
                      </button>
                      <hr className="my-1 border-zinc-200 dark:border-zinc-700" />
                      <button
                        onClick={handleLogout}
                        className="w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-zinc-700"
                      >
                        <LogOut size={16} />
                        {t('nav.logout')}
                      </button>
                    </div>
                  )}
                </div>
              ) : (
                // Other pages: Simple user link and logout
                <div className="hidden md:flex items-center gap-2">
                  <Link 
                    to="/dashboard" 
                    className="flex items-center gap-2 py-2 px-4 text-zinc-700 dark:text-white hover:text-brand dark:hover:text-brand transition text-sm font-medium"
                  >
                    <User size={16} />
                    {user.name || user.email}
                  </Link>
                  <button
                    onClick={handleLogout}
                    className="flex items-center gap-2 py-2 px-4 text-zinc-700 dark:text-white hover:text-red-500 dark:hover:text-red-400 transition text-sm font-medium"
                  >
                    <LogOut size={16} />
                    {t('nav.logout')}
                  </button>
                </div>
              )}
            </div>
          ) : (
            <Link
              to="/login"
              className="py-2 px-4 bg-brand text-white rounded-lg hover:bg-brand/90 transition text-sm font-medium"
            >
              {t('nav.login')}
            </Link>
          )}
          
          {/* Language and Theme toggles */}
          <LangToggle />
          <ThemeToggle />

          {/* Mobile menu button */}
          <button
            className="md:hidden p-2"
            onClick={() => setOpen(!open)}
            aria-label="Toggle menu"
          >
            {open ? <X size={24} /> : <Menu size={24} />}
          </button>
        </div>
      </div>

      {/* Mobile dropdown */}
      {open && (
        <nav className="md:hidden bg-white dark:bg-zinc-900 border-t dark:border-zinc-700 px-4 pb-4 pt-2 shadow">
          {/* Navigation links - only on landing page and login page */}
          {showNavigation && (
            <>
              {links.map((l) => (
                <a
                  key={l.id}
                  href={`/#${l.id}`}
                  className={`${itemClass} block`}
                  onClick={() => setOpen(false)}
                >
                  {t(l.label)}
                </a>
              ))}
              {/* Legal links for mobile */}
              {legalLinks.map((l) => (
                <Link
                  key={l.to}
                  to={l.to}
                  className={`${itemClass} block`}
                  onClick={() => setOpen(false)}
                >
                  {t(l.label)}
                </Link>
              ))}
            </>
          )}
          
          {/* Mobile authentication */}
          {user ? (
            <div className={`${showNavigation ? 'border-t dark:border-zinc-700 pt-2 mt-2' : ''}`}>
              <Link
                to="/dashboard"
                className={`${itemClass} block flex items-center gap-2`}
                onClick={() => setOpen(false)}
              >
                <User size={16} />
                {user.name || user.email}
              </Link>
              {isDashboard && (
                <>
                  <button
                    onClick={() => {
                      handleProfileClick();
                      setOpen(false);
                    }}
                    className={`${itemClass} block w-full text-left flex items-center gap-2`}
                  >
                    <User size={16} />
                    {t('dashboard.menu.profile')}
                  </button>
                  <button
                    onClick={() => {
                      handleSettingsClick();
                      setOpen(false);
                    }}
                    className={`${itemClass} block w-full text-left flex items-center gap-2`}
                  >
                    <Settings size={16} />
                    {t('dashboard.menu.settings')}
                  </button>
                </>
              )}
              <button
                onClick={() => {
                  handleLogout();
                  setOpen(false);
                }}
                className={`${itemClass} block w-full text-left flex items-center gap-2 text-red-500 dark:text-red-400`}
              >
                <LogOut size={16} />
                {t('nav.logout')}
              </button>
            </div>
          ) : (
            <div className={`${showNavigation ? 'border-t dark:border-zinc-700 pt-2 mt-2' : ''}`}>
              <Link
                to="/login"
                className="block w-full py-2 px-4 bg-brand text-white rounded-lg hover:bg-brand/90 transition text-sm font-medium text-center"
                onClick={() => setOpen(false)}
              >
                {t('nav.login')}
              </Link>
            </div>
          )}
        </nav>
      )}

      {/* Click outside to close dropdown */}
      {userDropdownOpen && (
        <div
          className="fixed inset-0 z-40"
          onClick={() => setUserDropdownOpen(false)}
        />
      )}
    </header>
  );
}