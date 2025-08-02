// src/components/ThemeToggle.tsx
import { Moon, Sun } from "lucide-react";
import useDarkMode from "../hooks/useDarkMode";

export default function ThemeToggle() {
  const [dark, setDark] = useDarkMode();

  return (
    <button
      aria-label="Toggle theme"
      onClick={() => setDark(!dark)}
      className="p-2 rounded-lg hover:bg-zinc-100 dark:hover:bg-zinc-700 transition"
    >
      {dark ? <Sun size={20} className="text-yellow-400" /> : <Moon size={20} />}
    </button>
  );
}