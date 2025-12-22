import { useTranslation } from "react-i18next";
import { Calendar, Wallet, FileText } from "lucide-react";

export default function Hero() {
  const { t } = useTranslation();
  return (
    <section
      id="home"
      className="
        min-h-screen
        flex flex-col justify-center
        bg-gradient-to-b from-brand/5 via-transparent to-zinc-50
        dark:bg-gradient-to-b dark:from-zinc-800 dark:via-zinc-900 dark:to-zinc-950
        px-4
      "
    >
      <div className="max-w-6xl mx-auto grid md:grid-cols-2 gap-10 items-center">
        {/* ---------- Teks ---------- */}
        <div className="text-center md:text-left">
          <h1 className="text-4xl md:text-5xl font-extrabold text-brand dark:text-white mb-4">
            {t("hero.heading")}
          </h1>

          <p className="text-lg text-zinc-600 dark:text-zinc-300 mb-8">
            {t("hero.tagline")}
          </p>

          <ul className="space-y-3 mb-10">
            {[
              { icon: Calendar, text: "hero.bullet_1" },
              { icon: Wallet,   text: "hero.bullet_2" },
              { icon: FileText, text: "hero.bullet_3" },
            ].map(({ icon: Icon, text }) => (
              <li key={text} className="flex items-center gap-3">
                <Icon className="text-brand" size={20} />
                <span className="text-zinc-700 dark:text-zinc-200">{t(text)}</span>
              </li>
            ))}
          </ul>

          <div className="flex flex-col sm:flex-row gap-4 sm:justify-start justify-center">
            <a
              href="https://wa.me/6287716326524"
              className="px-8 py-3 rounded-lg bg-brand text-white font-semibold shadow hover:opacity-90 transition text-center"
            >
              {t("hero.cta_demo")}
            </a>

            {/* arahkan ke daftar fitur */}
            <a
              href="#problem"
              className="px-8 py-3 rounded-lg border border-brand text-brand font-semibold hover:bg-brand/10 dark:hover:bg-brand/20 transition text-center"
            >
              {t("hero.cta_feature")}
            </a>
          </div>
        </div>

        {/* ---------- Mockup Chat ---------- */}
        <div className="hidden md:flex justify-center">
          <div className="w-72 h-[480px] rounded-3xl shadow-xl bg-white flex flex-col overflow-hidden">
            <div className="h-10 bg-brand flex items-center justify-center text-white text-sm font-medium">
              ASPRI Chat
            </div>

            <div className="p-4 space-y-3 flex-1 overflow-y-auto">
              <div className="text-xs text-center text-zinc-400 dark:text-zinc-500">
                {t("hero.chat.date")}
              </div>

              {/* user ➜ kanan */}
              <div className="flex justify-end">
                <div className="bg-zinc-100 p-2 rounded-lg text-sm">
                  {t("hero.chat.user1")}
                </div>
              </div>
              {/* aspri ➜ kiri */}
              <div className="flex justify-start">
                <div className="bg-brand text-white p-2 rounded-lg text-sm">
                  {t("hero.chat.aspri1")}
                </div>
              </div>
              {/* user */}
              <div className="flex justify-end">
                <div className="bg-zinc-100 p-2 rounded-lg text-sm">
                  {t("hero.chat.user2")}
                </div>
              </div>
              {/* aspri */}
              <div className="flex justify-start">
                <div className="bg-brand text-white p-2 rounded-lg text-sm">
                  {t("hero.chat.aspri2")}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}