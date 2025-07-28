import {
  Send,
  Brain,
  Wrench,
  CheckCircle2,
  ArrowRight,
} from "lucide-react";

const steps = [
  { icon: <Send size={24} />, title: "Kirim Chat" },
  { icon: <Brain size={24} />, title: "LLM Memahami" },
  { icon: <Wrench size={24} />, title: "Tool Eksekusi" },
  { icon: <CheckCircle2 size={24} />, title: "Jawaban" },
];

export default function Workflow() {
  return (
    <section className="bg-zinc-50 py-20 px-4">
      <div className="max-w-5xl mx-auto">
        <h2 className="text-3xl md:text-4xl font-bold text-center mb-12">
          Cara&nbsp;Kerja&nbsp;ASPRI
        </h2>

        <div className="flex flex-col items-center gap-8 md:flex-row md:justify-between">
          {steps.map((s, idx) => (
            <div key={s.title} className="flex items-center gap-4">
              <div className="flex flex-col items-center">
                <div className="w-14 h-14 flex items-center justify-center rounded-full bg-brand/10 text-brand">
                  {s.icon}
                </div>
                <span className="mt-2 text-sm font-medium text-center">
                  {s.title}
                </span>
              </div>

              {/* panah kecuali langkah terakhir */}
              {idx < steps.length - 1 && (
                <ArrowRight
                  className="hidden md:block text-zinc-400"
                  size={28}
                  strokeWidth={1.5}
                />
              )}
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
