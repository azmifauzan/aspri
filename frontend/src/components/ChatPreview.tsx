import ChatBubble from "@/components/ChatBubble";

export default function ChatPreview() {
  return (
    <section className="
        py-20 px-4
        bg-zinc-100 dark:bg-zinc-900
        bg-gradient-to-b from-zinc-100/70 to-zinc-100
        dark:bg-gradient-to-b dark:from-zinc-800 dark:to-zinc-900
      " id="chat">
      <div className="max-w-3xl mx-auto">
        <h2 className="text-3xl md:text-4xl font-bold text-center mb-12 dark:text-zinc-100">
          Contoh&nbsp;Percakapan&nbsp;dengan&nbsp;ASPRI
        </h2>

        <div className="rounded-xl shadow-lg bg-zinc-50 p-6 space-y-4">
          <ChatBubble
            side="right"
            text="Catat pengeluaran Rp150.000 untuk makan siang hari ini."
          />
          <ChatBubble
            side="left"
            text="✅ Tercatat! Saldo makan bulan ini tersisa Rp1.350.000."
          />
          <ChatBubble
            side="right"
            text="Tambahkan meeting dengan Pak Budi besok jam 10.00."
          />
          <ChatBubble
            side="left"
            text="📅 Jadwal sudah ditambahkan dan disinkron ke Google Calendar."
          />
          <ChatBubble
            side="right"
            text="Ringkas isi kontrak ‘Kerja Sama ABC.pdf’."
          />
          <ChatBubble
            side="left"
            text="Berikut ringkasannya: 1) Durasi 2 tahun, 2) Nilai proyek Rp2 M, 3) Kewajiban…"
          />
        </div>
      </div>
    </section>
  );
}
