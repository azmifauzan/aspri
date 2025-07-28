import { useTranslation } from "react-i18next";

export default function LangToggle() {
  const { i18n } = useTranslation();

  const next = i18n.language === "id" ? "en" : "id";

  return (
    <button
      onClick={() => i18n.changeLanguage(next)}
      className="px-3 py-1 text-sm font-medium rounded-lg border border-brand text-brand hover:bg-brand/10 dark:hover:bg-brand/20 transition"
    >
      {next.toUpperCase()}
    </button>
  );
}
