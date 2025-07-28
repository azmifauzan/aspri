// src/components/ProblemSolution.tsx
import { useTranslation } from "react-i18next";
import { CalendarX, FileWarning, Wallet } from "lucide-react";
import FeatureCard from "@/components/FeatureCard";

/* ── data ──────────────────────────────────────────────── */
const features = [
  {
    icon: <CalendarX size={32} strokeWidth={1.8} />,
    title: "problem.item_1.title",
    desc: "problem.item_1.desc",
  },
  {
    icon: <Wallet size={32} strokeWidth={1.8} />,
    title: "problem.item_2.title",
    desc: "problem.item_2.desc",
  },
  {
    icon: <FileWarning size={32} strokeWidth={1.8} />,
    title: "problem.item_3.title",
    desc: "problem.item_3.desc",
  },
];

/* ── komponen ─────────────────────────────────────────── */
export default function ProblemSolution() {
  const { t } = useTranslation();
  return (
    <section
      id="problem"
      className="
        py-20 px-4
        bg-zinc-100 dark:bg-zinc-900
        bg-gradient-to-b from-zinc-100/70 to-zinc-100
        dark:bg-gradient-to-b dark:from-zinc-800 dark:to-zinc-900
      "
    >
      <div className="max-w-5xl mx-auto">
        <h2 className="text-3xl md:text-4xl font-bold text-center mb-12 text-zinc-800 dark:text-zinc-100">
          {t("problem.title")}
        </h2>

        <div className="grid gap-8 md:grid-cols-3">
          {features.map(({ icon, title, desc }) => (
            <FeatureCard
              key={title}
              icon={
                <div className="w-10 h-10 flex items-center justify-center rounded-full bg-brand/10 dark:bg-brand/20 text-brand">
                  {icon}
                </div>
              }
              title={t(title)}
              desc={t(desc)}
            />
          ))}
        </div>
      </div>
    </section>
  );
}
