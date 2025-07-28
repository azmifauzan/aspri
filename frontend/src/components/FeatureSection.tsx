// src/components/FeatureSection.tsx
import { CalendarCheck, BarChart2, FileText, MessageCircle } from "lucide-react";
import FeatureCard from "@/components/FeatureCard";

const coreFeatures = [
  {
    icon: <CalendarCheck size={30} strokeWidth={1.8} />,
    title: "Penjadwalan Pintar",
    desc: "Tambah, ubah, dan sinkron otomatis dengan Google Calendar.",
  },
  {
    icon: <BarChart2 size={30} strokeWidth={1.8} />,
    title: "Wawasan Keuangan",
    desc: "Lacak pemasukan & pengeluaran, ringkasan AI tiap bulan.",
  },
  {
    icon: <FileText size={30} strokeWidth={1.8} />,
    title: "Dokumen Cerdas",
    desc: "Unggah PDF lalu ajukan pertanyaan—jawaban instan dari LLM.",
  },
  {
    icon: <MessageCircle size={30} strokeWidth={1.8} />,
    title: "Kendali Satu Chat",
    desc: "Semua aksi cukup melalui satu percakapan intuitif.",
  },
];

export default function FeatureSection() {
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
          Fitur&nbsp;Inti
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
