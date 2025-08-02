// src/components/FeatureSection.tsx
import { CalendarCheck, BarChart2, FileText, MessageCircle } from "lucide-react";
import FeatureCard from "./FeatureCard";
import { useTranslation } from "react-i18next";

const coreFeatures = [
  {
    icon: <CalendarCheck size={30} strokeWidth={1.8} />,
    title: "features.item_1.title",
    desc: "features.item_1.desc",
  },
  {
    icon: <BarChart2 size={30} strokeWidth={1.8} />,
    title: "features.item_2.title",
    desc: "features.item_2.desc",
  },
  {
    icon: <FileText size={30} strokeWidth={1.8} />,
    title: "features.item_3.title",
    desc: "features.item_3.desc",
  },
  {
    icon: <MessageCircle size={30} strokeWidth={1.8} />,
    title: "features.item_4.title",
    desc: "features.item_4.desc",
  },
];

export default function FeatureSection() {
  const { t } = useTranslation();
  return (
    <section 
      className="
        py-20 px-4
        bg-zinc-100 dark:bg-zinc-900
        bg-gradient-to-b from-zinc-100/70 to-zinc-100
        dark:bg-gradient-to-b dark:from-zinc-800 dark:to-zinc-900
      " id="features">
      <div className="max-w-6xl mx-auto">
        <h2 className="text-3xl md:text-4xl font-bold text-center mb-12 dark:text-zinc-100">
          {t("features.title")}
        </h2>

        <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
          {coreFeatures.map((f) => (
            <FeatureCard key={f.title} {...f} />
          ))}
        </div>
      </div>
    </section>
  );
}
