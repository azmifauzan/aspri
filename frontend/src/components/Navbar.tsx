// src/components/Navbar.tsx
import { useState } from "react";
import { Menu, X } from "lucide-react";
import ThemeToggle from "@/components/ThemeToggle";
import LangToggle from "@/components/LangToggle";

const links = [
  { id: "home", label: "Beranda" },
  { id: "problem", label: "Fitur" },
  { id: "chat",    label: "Preview" },
  { id: "contact", label: "Kontak" },
];

/* ✅ hanya satu itemClass */
const itemClass =
  "py-2 px-4 text-zinc-700 dark:text-white hover:text-brand dark:hover:text-brand transition text-sm font-medium";

export default function Navbar() {
  const [open, setOpen] = useState(false);

  return (
    <header className="fixed top-0 w-full bg-white/70 dark:bg-zinc-900/80 backdrop-blur z-50 shadow-sm">
      <div className="max-w-6xl mx-auto flex items-center justify-between px-4 h-14">
        {/* Logo */}
        <a href="#home" className="text-brand font-bold text-lg">
          ASPRI
        </a>

        {/* Desktop menu */}
        <nav className="hidden md:flex items-center gap-2">
          {links.map((l) => (
            <a key={l.id} href={`#${l.id}`} className={itemClass}>
              {l.label}
            </a>
          ))}
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
              {l.label}
            </a>
          ))}
        </nav>
      )}
    </header>
  );
}
