export default {
    privacyPolicy: 'Kebijakan Privasi',
    termsOfService: 'Syarat dan Ketentuan',
    lastUpdated: 'Terakhir Diperbarui: {date}',
    privacyContent: `
        <h2 class="text-2xl font-bold mt-6 mb-4">1. Pendahuluan</h2>
        <p class="mb-4">Selamat datang di ASPRI (Asisten Pribadi Berbasis AI). ASPRI adalah asisten pribadi berbasis percakapan yang membantu Anda mengelola jadwal, keuangan, dan catatan melalui bahasa natural di Web maupun Telegram. Kami menghargai privasi Anda dan berkomitmen untuk melindungi data pribadi Anda. Kebijakan ini menjelaskan data apa yang kami kumpulkan, bagaimana kami menggunakannya, dan pilihan yang Anda miliki.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">2. Data yang Kami Kumpulkan</h2>
        <p class="mb-4">Kami mengumpulkan kategori data berikut:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li><strong>Data akun</strong> — nama, alamat email, dan kata sandi (disimpan dalam bentuk hash). Jika Anda masuk dengan Google, kami menerima ID akun Google, alamat email, dan foto profil Anda.</li>
            <li><strong>Preferensi profil</strong> — panggilan yang Anda inginkan, nama dan persona asisten Anda, serta preferensi bahasa.</li>
            <li><strong>Data percakapan</strong> — pesan yang Anda kirim ke asisten melalui chat web dan bot Telegram, yang tersusun dalam thread percakapan.</li>
            <li><strong>Memori percakapan</strong> — fakta dan preferensi penting yang diekstrak otomatis oleh AI dari percakapan Anda untuk menjaga kesinambungan antar sesi (mis. kebiasaan dan preferensi berulang).</li>
            <li><strong>Data keuangan</strong> — transaksi, akun (tunai, bank, e-wallet), kategori, anggaran, dan gambar bukti pembayaran yang Anda unggah.</li>
            <li><strong>Data jadwal</strong> — acara kalender, jadwal berulang, dan pengingat.</li>
            <li><strong>Catatan</strong> — isi catatan yang Anda buat, termasuk tag dan format.</li>
            <li><strong>Data plugin</strong> — data yang Anda catat melalui plugin opsional yang Anda aktifkan (mis. pelacak kebiasaan, kesehatan, jurnal suasana hati, pelacak buku).</li>
            <li><strong>Data integrasi</strong> — ID chat Telegram Anda saat Anda menautkan akun Telegram.</li>
            <li><strong>Data penggunaan</strong> — pengukuran penggunaan chat (jumlah token), log aktivitas, dan status langganan, yang digunakan untuk operasional layanan dan audit keamanan.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">3. Bagaimana Kami Menggunakan Data Anda</h2>
        <p class="mb-4">Kami menggunakan data Anda untuk:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>Menyediakan dan mempersonalisasi pengalaman asisten, termasuk persona, panggilan, dan bahasa pilihan Anda.</li>
            <li>Memproses permintaan Anda (membuat transaksi, jadwal, catatan, dan pengingat) melalui pengenalan intent oleh AI.</li>
            <li>Menjaga kesinambungan percakapan melalui sistem memori, sehingga asisten mengingat konteks yang relevan antar sesi.</li>
            <li>Mengirim pengingat jadwal melalui notifikasi aplikasi dan Telegram.</li>
            <li>Mengelola langganan Anda, termasuk masa trial, verifikasi pembayaran, dan penukaran kode promo.</li>
            <li>Memantau kesehatan sistem, mencegah penyalahgunaan, dan menjaga jejak audit keamanan.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">4. Pemrosesan AI dan Layanan Pihak Ketiga</h2>
        <p class="mb-4">Untuk menghasilkan respons, isi percakapan Anda (termasuk konteks memori yang relevan) diproses oleh penyedia AI pihak ketiga yang dikonfigurasi untuk layanan ini, yang dapat mencakup <strong>Google Gemini</strong>, <strong>OpenAI</strong>, dan <strong>Anthropic Claude</strong>. Penyedia tersebut memproses pesan Anda untuk menghasilkan respons asisten dan analisis intent.</p>
        <p class="mb-4">Kami juga terintegrasi dengan:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li><strong>Google</strong> — untuk masuk opsional (OAuth). Kami hanya menerima informasi profil dasar Anda; kami tidak pernah melihat kata sandi Google Anda.</li>
            <li><strong>Telegram</strong> — untuk akses bot opsional. Pesan yang dikirim via Telegram diproses dengan cara yang sama seperti pesan chat web.</li>
        </ul>
        <p class="mb-4">Kami tidak menjual data pribadi Anda kepada pihak ketiga.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">5. Sistem Memori Percakapan</h2>
        <p class="mb-4">ASPRI secara otomatis mengekstrak fakta penting dari percakapan Anda (seperti preferensi dan topik berulang) dan menyimpannya sebagai memori untuk meningkatkan interaksi berikutnya. Memori dipadatkan secara berkala dengan tetap mempertahankan informasi terpenting. Memori hanya digunakan untuk membangun konteks percakapan Anda sendiri dan tidak pernah dibagikan ke pengguna lain.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">6. Penyimpanan dan Keamanan Data</h2>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>Kata sandi disimpan menggunakan hashing satu arah dan tidak pernah dapat kami baca.</li>
            <li>Kredensial sensitif (seperti API key penyedia AI) disimpan terenkripsi.</li>
            <li>Autentikasi dua faktor (TOTP) tersedia untuk melindungi akun Anda.</li>
            <li>Tindakan administratif tercatat dalam log audit.</li>
            <li>Akun yang dibuat via Google tidak dapat melakukan reset kata sandi sebelum kata sandi disetel, untuk mencegah pengambilalihan akun.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">7. Retensi dan Penghapusan Data</h2>
        <p class="mb-4">Data Anda disimpan selama akun Anda aktif. Anda dapat menghapus item individual (transaksi, jadwal, catatan, thread chat) kapan saja. Saat akun Anda dihapus, data pribadi Anda — termasuk percakapan, memori, catatan keuangan, dan file yang diunggah — akan dihapus dari sistem kami.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">8. Hak Anda</h2>
        <p class="mb-4">Anda berhak untuk:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>Mengakses dan memperbarui data akun dan profil Anda kapan saja melalui halaman pengaturan.</li>
            <li>Menghapus percakapan, catatan, dan akun Anda.</li>
            <li>Menonaktifkan plugin dan menghentikan pengumpulan datanya.</li>
            <li>Memutus tautan akun Telegram Anda.</li>
            <li>Meminta salinan data Anda dengan menghubungi kami.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">9. Perubahan Kebijakan Ini</h2>
        <p class="mb-4">Kami dapat memperbarui Kebijakan Privasi ini dari waktu ke waktu. Perubahan signifikan akan diumumkan melalui aplikasi. Tanggal "Terakhir Diperbarui" di bagian atas halaman ini menunjukkan revisi terbaru.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">10. Hubungi Kami</h2>
        <p class="mb-4">Jika Anda memiliki pertanyaan tentang Kebijakan Privasi ini atau bagaimana data Anda diproses, silakan hubungi kami melalui aplikasi atau detail kontak di situs web kami.</p>
    `,
    termsContent: `
        <h2 class="text-2xl font-bold mt-6 mb-4">1. Penerimaan Syarat</h2>
        <p class="mb-4">Dengan mengakses atau menggunakan ASPRI, Anda setuju untuk terikat oleh Syarat dan Ketentuan ini serta Kebijakan Privasi kami. Jika Anda tidak setuju, mohon tidak menggunakan layanan ini.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">2. Deskripsi Layanan</h2>
        <p class="mb-4">ASPRI adalah asisten pribadi berbasis AI dengan pengalaman utama melalui percakapan. Layanan ini mencakup:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>Asisten AI percakapan yang dapat diakses melalui chat web dan bot Telegram.</li>
            <li>Pengelolaan keuangan — transaksi, multi-akun, kategori, dan pelacakan anggaran.</li>
            <li>Pengelolaan jadwal — acara kalender, jadwal berulang, dan pengingat.</li>
            <li>Catatan dengan editor berbasis blok.</li>
            <li>Sistem plugin opsional (mis. pelacak kebiasaan, jadwal sholat, cuaca, konversi mata uang).</li>
            <li>Memori percakapan lintas sesi untuk pengalaman yang dipersonalisasi.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">3. Akun dan Pendaftaran</h2>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>Anda wajib memberikan informasi yang akurat saat mendaftar, baik melalui email maupun masuk dengan Google.</li>
            <li>Anda bertanggung jawab menjaga kerahasiaan kredensial akun Anda dan atas semua aktivitas di bawah akun Anda.</li>
            <li>Kami menyarankan mengaktifkan autentikasi dua faktor untuk keamanan tambahan.</li>
            <li>Satu orang tidak boleh memiliki banyak akun untuk menyalahgunakan masa trial atau promosi.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">4. Langganan dan Pembayaran</h2>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>Akun baru mendapatkan masa trial gratis dengan akses ke fitur inti.</li>
            <li>Langganan premium diaktifkan setelah bukti pembayaran diunggah dan diverifikasi oleh tim kami. Verifikasi dilakukan manual dan dapat memerlukan waktu.</li>
            <li>Kode promo dapat ditukarkan sesuai ketentuan dan masa berlakunya masing-masing.</li>
            <li>Penggunaan chat dapat diukur (berdasarkan penggunaan token AI) sesuai paket langganan Anda.</li>
            <li>Kami berhak menyesuaikan harga dan fitur paket dengan pemberitahuan sebelumnya melalui aplikasi.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">5. Disclaimer Konten yang Dihasilkan AI</h2>
        <p class="mb-4">ASPRI menggunakan kecerdasan buatan untuk memahami permintaan Anda dan menghasilkan respons. Anda memahami bahwa:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>Respons yang dihasilkan AI sesekali dapat tidak akurat, tidak lengkap, atau salah menafsirkan maksud Anda.</li>
            <li>Ringkasan keuangan, insight, dan saran asisten bersifat informatif saja dan <strong>bukan</strong> merupakan nasihat keuangan, hukum, medis, atau profesional.</li>
            <li>Tindakan yang mengubah data (membuat, mengubah, atau menghapus catatan) memerlukan konfirmasi eksplisit dari Anda sebelum dijalankan. Anda bertanggung jawab meninjau konfirmasi sebelum menyetujuinya.</li>
            <li>Anda sebaiknya memverifikasi informasi penting secara mandiri sebelum bertindak berdasarkan informasi tersebut.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">6. Penggunaan yang Dapat Diterima</h2>
        <p class="mb-4">Anda setuju untuk tidak:</p>
        <ul class="list-disc pl-6 mb-4 space-y-2">
            <li>Menggunakan layanan untuk tujuan melanggar hukum atau menyimpan konten ilegal.</li>
            <li>Mencoba melewati batas penggunaan, pembatasan langganan, atau mekanisme keamanan.</li>
            <li>Menyelidiki, mengganggu, atau membebani infrastruktur layanan.</li>
            <li>Menggunakan asisten AI untuk menghasilkan konten berbahaya, kasar, atau menipu.</li>
            <li>Mengunggah bukti pembayaran palsu.</li>
        </ul>

        <h2 class="text-2xl font-bold mt-6 mb-4">7. Plugin dan Data Pihak Ketiga</h2>
        <p class="mb-4">Plugin adalah fitur opsional yang dapat Anda aktifkan per akun. Beberapa plugin menampilkan data dari sumber pihak ketiga (mis. cuaca, berita, jadwal sholat, kurs mata uang). Data tersebut disediakan "sebagaimana adanya" dan kami tidak menjamin keakuratan atau ketersediaannya.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">8. Integrasi Telegram</h2>
        <p class="mb-4">Anda dapat menautkan akun Telegram menggunakan kode sekali pakai untuk berinteraksi dengan ASPRI via Telegram. Anda bertanggung jawab atas keamanan akun Telegram Anda; siapa pun yang memiliki akses ke akun Telegram yang tertaut dapat berinteraksi dengan data ASPRI Anda. Anda dapat memutus tautan Telegram kapan saja.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">9. Hak Kekayaan Intelektual</h2>
        <p class="mb-4">Aplikasi ASPRI, termasuk desain, merek, dan perangkat lunaknya, adalah kekayaan intelektual kami. Konten yang Anda buat (catatan, data, percakapan) tetap milik Anda; Anda memberikan kami lisensi terbatas untuk menyimpan dan memprosesnya semata-mata untuk mengoperasikan layanan.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">10. Batasan Tanggung Jawab</h2>
        <p class="mb-4">Layanan disediakan "sebagaimana adanya" dan "sebagaimana tersedia". Sejauh diizinkan oleh hukum, kami tidak bertanggung jawab atas kerugian tidak langsung, insidental, atau konsekuensial yang timbul dari penggunaan layanan, termasuk kerugian akibat mengandalkan konten yang dihasilkan AI, pengingat yang terlewat, atau gangguan layanan.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">11. Penangguhan dan Penghentian</h2>
        <p class="mb-4">Kami dapat menangguhkan atau menonaktifkan akun yang melanggar ketentuan ini, menyalahgunakan layanan, atau mengirimkan pembayaran palsu. Anda dapat berhenti menggunakan layanan dan menghapus akun Anda kapan saja.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">12. Perubahan Ketentuan</h2>
        <p class="mb-4">Kami berhak mengubah ketentuan ini kapan saja. Perubahan signifikan akan diumumkan melalui aplikasi. Terus menggunakan layanan setelah perubahan berlaku merupakan penerimaan terhadap ketentuan yang direvisi.</p>

        <h2 class="text-2xl font-bold mt-6 mb-4">13. Hukum yang Berlaku</h2>
        <p class="mb-4">Ketentuan ini diatur oleh hukum Republik Indonesia. Setiap sengketa akan diselesaikan terlebih dahulu melalui musyawarah, dan melalui pengadilan yang berwenang jika diperlukan.</p>
    `,
    backToHome: 'Kembali ke Beranda',
};
