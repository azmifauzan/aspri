import { Mail, Github } from "lucide-react";
import { useTranslation } from "react-i18next";

export default function Footer() {
  const { t } = useTranslation();
  return (
    <footer className="bg-zinc-900 text-zinc-300 pt-12 pb-8" id="contact">
      <div className="max-w-6xl mx-auto px-4">
        {/* Grid 1 → Logo & Deskripsi • Grid 2 → Kontak */}
        <div className="grid md:grid-cols-2 gap-10 mb-10">
          {/* — Logo / Brand — */}
          <div>
            <h3 className="text-2xl font-bold text-white mb-3">ASPRI</h3>
            <p className="text-sm leading-relaxed">
              {t("footer.description")}
            </p>
          </div>

          {/* — Kontak — */}
          <div className="space-y-4">
            <h4 className="text-lg font-semibold text-white">{t("footer.contact")}</h4>

            <div className="flex items-center gap-3">
              <Mail size={18} />
              <a href="mailto:support@aspri.io" className="hover:text-white">
                support@aspri.io
              </a>
            </div>

            <div className="flex items-center gap-3">
              <Github size={18} />
              <a
                href="https://github.com/azmifauzan/aspri"
                target="_blank"
                rel="noopener noreferrer"
                className="hover:text-white"
              >
                Github Project
              </a>
            </div>
          </div>
        </div>

        {/* Garis & copyright */}
        <div className="border-t border-zinc-700 pt-6 text-center text-xs text-zinc-500">
          © {new Date().getFullYear()} ASPRI. All rights reserved.
        </div>
      </div>
    </footer>
  );
}
