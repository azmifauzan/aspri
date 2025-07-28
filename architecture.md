# Asisten Pribadi (Aspri)

## Konsep Aplikasi

**Tujuan Utama**: ASPRI adalah asisten pribadi berbasis AI yang membantu pengguna mengelola kehidupan sehari-hari melalui antarmuka chat intuitif. Aplikasi ini menggunakan LLM (seperti GPT-series atau model open-source seperti Llama) untuk memproses permintaan alami, dengan akses ke data pribadi pengguna untuk respons yang kontekstual dan akurat. Konsep inti adalah "chat-first" di mana semua fitur diakses melalui percakapan, mirip dengan LLM frontier seperti ChatGPT, tetapi ditingkatkan dengan tools khusus untuk data internal (jadwal, keuangan, dokumen).

**Fitur Utama**:
1. **Pencatatan Jadwal**: Pengguna dapat menambahkan, mengedit, atau menghapus jadwal melalui chat (e.g., "Tambahkan meeting besok jam 10"). Sinkronisasi dua arah dengan Google Calendar menggunakan API untuk update real-time.
2. **Pencatatan Keuangan**: Lacak pemasukan dan pengeluaran (e.g., "Catat pengeluaran Rp100.000 untuk makan"). Fitur ringkasan bulanan, prediksi, atau alert melalui LLM.
3. **Pengelolaan Dokumen Pribadi**: Upload dan embed dokumen (PDF, teks) ke VectorDB untuk pencarian semantik. LLM dapat merangkum atau menjawab pertanyaan berdasarkan dokumen (e.g., "Apa isi kontrak saya?").
4. **Chat Utama**: Antarmuka pusat seperti chatbot LLM, dengan tools tambahan:
   - Tool Jadwal: Akses/mutasi data jadwal.
   - Tool Keuangan: Query/transaksi keuangan.
   - Tool Dokumen: Retrieval dan analisis dokumen dari VectorDB.
   Semua fitur didukung LLM untuk pemrosesan bahasa alami, dengan fallback ke UI manual jika diperlukan.

**Alur Pengguna**:
- Login aman (OAuth atau email).
- Menu utama: Chat window dengan prompt awal.
- Pengguna bertanya/memerintah via teks (e.g., "Jadwalkan libur minggu depan dan catat pengeluaran hari ini").
- LLM parse permintaan, panggil tools relevan, dan respons dengan hasil.
- Dashboard samping untuk view manual (jadwal kalender, grafik keuangan, daftar dokumen).

**Prinsip Desain**: User-centric, privasi-first (data disimpan lokal/encrypted), dan extensible (mudah tambah tools baru).

## Arsitektur Aplikasi

Arsitektur mengadopsi pola client-server dengan microservices untuk modulasi. Frontend berfokus pada UI interaktif, backend menangani logika bisnis, integrasi, dan LLM. Data flow: Pengguna → Frontend → Backend API → LLM/Tools → Respons.

**Komponen Utama**:
1. **Frontend**:
   - Framework: React.js (atau Vue.js) dengan state management (Redux) untuk chat real-time.
   - UI: Chat interface (mirip WhatsApp), dashboard untuk fitur manual, upload dokumen.
   - Integrasi: WebSocket untuk chat live, API calls ke backend.

2. **Backend**:
   - Framework: Node.js dengan Express.
   - API: RESTful endpoints untuk autentikasi, fitur (e.g., /schedule, /finance, /documents).
   - LLM Integration: LangChain atau LlamaIndex untuk chaining LLM dengan tools custom (e.g., tool untuk query VectorDB atau Google API).
   - Tools LLM: 
     - Jadwal: Integrasi Google Calendar API (OAuth2 untuk auth).
     - Keuangan: CRUD operations ke database.
     - Dokumen: Embedding menggunakan model seperti Sentence Transformers, simpan ke VectorDB.

3. **Database**:
   - Relational/NoSQL: MongoDB atau PostgreSQL untuk data struktural (jadwal, keuangan).
   - VectorDB: ChromaDB (open-source) atau Pinecone untuk embedding dokumen (vektorisasi teks untuk RAG - Retrieval-Augmented Generation).

4. **Integrasi Eksternal**:
   - Google Calendar API: Untuk sync jadwal (gunakan Google SDK).
   - LLM Provider: OpenAI API atau Hugging Face untuk model lokal (untuk privasi).
   - Authentication: JWT atau Firebase Auth untuk keamanan.
- **Messaging Apps**: Integrasi dengan Telegram Bot API dan WhatsApp Business API untuk memungkinkan interaksi chat melalui platform eksternal. Backend akan menangani webhook incoming dari kedua API ini, merutekan pesan ke LLM engine untuk pemrosesan, dan mengirim respons kembali.

**Diagram Arsitektur Tingkat Tinggi** (Deskripsi Teks):
- **User** ↔ **Frontend (React)** ↔ **Backend (Node.js/Express)** 
  - Backend → **LLM Engine (LangChain)** → **Tools**:
    - **Schedule Tool** → Google Calendar API
    - **Finance Tool** → MongoDB
    - **Document Tool** → VectorDB (Chroma)
- Data Flow: Permintaan chat dapat berasal dari frontend app, atau melalui integrasi messaging seperti Telegram/WhatsApp via webhook, diproses oleh LLM, yang memanggil tools jika diperlukan, lalu hasil dikembalikan ke sumber asal."

**Tech Stack Rekomendasi**:
- Frontend: React, Tailwind CSS untuk UI modern.
- Backend: Node.js, LangChain.
- Database: MongoDB + ChromaDB.
- Deployment: Docker untuk containerization, hosting di Vercel/Heroku untuk awal.
- Keamanan: HTTPS, encryption data sensitif, rate limiting API.

**Pertimbangan Tambahan**:
- **Skalabilitas**: Gunakan cloud services jika pengguna bertambah.
- **Keamanan**: Semua data pribadi dienkripsi; akses Google via token sementara.
- **Pengembangan**: Mulai dengan MVP (chat + satu fitur), lalu iterasi.