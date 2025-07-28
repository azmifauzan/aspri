import { Mail, Phone, Github } from "lucide-react";

export default function Footer() {
  return (
    <footer className="bg-zinc-900 text-zinc-300 pt-12 pb-8" id="contact">
      <div className="max-w-6xl mx-auto px-4">
        {/* Grid 1 → Logo & Deskripsi • Grid 2 → Kontak */}
        <div className="grid md:grid-cols-2 gap-10 mb-10">
          {/* — Logo / Brand — */}
          <div>
            <h3 className="text-2xl font-bold text-white mb-3">ASPRI</h3>
            <p className="text-sm leading-relaxed">
              Asisten pribadi berbasis AI untuk membantu Anda merapikan jadwal,
              keuangan, dan dokumen melalui sebuah percakapan.
            </p>
          </div>

          {/* — Kontak — */}
          <div className="space-y-4">
            <h4 className="text-lg font-semibold text-white">Hubungi Kami</h4>

            <div className="flex items-center gap-3">
              <Phone size={18} /> 
              <a href="https://wa.me/6287716326524" className="hover:text-white">
                +62 877-1632-6524 (WhatsApp)
              </a>
            </div>

            <div className="flex items-center gap-3">
              <Mail size={18} />
              <a href="mailto:support@aspri.io" className="hover:text-white">
                support@aspri.io
              </a>
            </div>

            <div className="flex items-center gap-3">
              <Github size={18} />
              <a
                href="https://github.com/your-org/aspri"
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
