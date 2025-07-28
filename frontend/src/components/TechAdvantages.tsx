import {
  ShieldCheck,
  Lock,
  Database,
  Cpu,
  Cloud,
} from "lucide-react";
import Badge from "@/components/Badge";

const items = [
  { icon: <ShieldCheck size={18} />, text: "Google OAuth 100 %" },
  { icon: <Lock size={18} />, text: "Enkripsi End-to-End" },
  { icon: <Database size={18} />, text: "Vector Search Cepat" },
  { icon: <Cpu size={18} />, text: "LLM Open-Source" },
  { icon: <Cloud size={18} />, text: "Hosting Lokal & Aman" },
];

export default function TechAdvantages() {
  return (
    <section className="py-20 px-4 bg-white">
      <div className="max-w-5xl mx-auto">
        <h2 className="text-3xl md:text-4xl font-bold text-center mb-12">
          Keunggulan&nbsp;Teknis&nbsp;ASPRI
        </h2>

        <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3">
          {items.map(({ icon, text }) => (
            <Badge key={text} icon={icon} text={text} />
          ))}
        </div>
      </div>
    </section>
  );
}
