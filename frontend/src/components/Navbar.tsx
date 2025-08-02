// src/components/Navbar.tsx
import { useState } from "react";
import { Menu, X, User, LogOut } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
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

/* ✅ hanya satu itemClass */
const itemClass =
  "py-2 px-4 text-zinc-700 dark:text-white hover:text-brand dark:hover:text-brand transition text-sm font-medium";

export default function Navbar() {
  const [open, setOpen] = useState(false);
  const { t } = useTranslation();
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  const handleLogout = () => {
    logout();
    navigate('/');
  };

  return (
    <header className="fixed top-0 w-full bg-white/70 dark:bg-zinc-900/80 backdrop-blur z-50 shadow-sm">
      <div className="max-w-6xl mx-auto flex items-center justify-between px-4 h-14">
        {/* Logo */}
        <Link to="/" className="text-brand font-bold text-lg">
          ASPRI
        </Link>

        {/* Desktop menu */}
        <nav className="hidden md:flex items-center gap-2">
          {links.map((l) => (
            <a key={l.id} href={`#${l.id}`} className={itemClass}>
              {t(l.label)}
            </a>
          ))}
          
          {/* Authentication buttons */}
          {user ? (
            <div className="flex items-center gap-2 ml-4">
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
          ) : (
            <Link
              to="/login"
              className="ml-4 py-2 px-4 bg-brand text-white rounded-lg hover:bg-brand/90 transition text-sm font-medium"
            >
              {t('nav.login')}
            </Link>
          )}
          
          <LangToggle />
          <ThemeToggle />
        </nav>

        {/* Mobile: theme + burger */}
        <div className="md:hidden flex items-center gap-2">
          <LangToggle />
          <ThemeToggle />
          <button
            className="p-2"
            onClick={() => setOpen((p) => !p)}
            aria-label="Toggle menu"
          >
            {open ? <X size={24} /> : <Menu size={24} />}
          </button>
        </div>
      </div>

      {/* Mobile dropdown */}
      {open && (
        <nav className="md:hidden bg-white dark:bg-zinc-900 border-t dark:border-zinc-700 px-4 pb-4 pt-2 shadow">
          {links.map((l) => (
            <a
              key={l.id}
              href={`#${l.id}`}
              className={`${itemClass} block`}
              onClick={() => setOpen(false)}
            >
              {t(l.label)}
            </a>
          ))}
          
          {/* Mobile authentication */}
          {user ? (
            <div className="border-t dark:border-zinc-700 pt-2 mt-2">
              <Link
                to="/dashboard"
                className={`${itemClass} block flex items-center gap-2`}
                onClick={() => setOpen(false)}
              >
                <User size={16} />
                {user.name || user.email}
              </Link>
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
            <div className="border-t dark:border-zinc-700 pt-2 mt-2">
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
    </header>
  );
}