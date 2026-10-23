# Riset notifikasi saat aplikasi tidak dibuka (PWA + Web Push, WhatsApp)

Issue: #28 (revisi FT poin 17). Tanggal riset: 2026-10-23. Tidak ada kode aplikasi yang diubah.

## 1. Ringkasan rekomendasi

1. Bangun Web Push lewat PWA terpasang sebagai kanal utama untuk notifikasi saat aplikasi tertutup. Mahasiswa Android bisa menerima tanpa memasang PWA, pengguna iPhone wajib memasang ke Home Screen.
2. Pakai paket `laravel-notification-channels/webpush` (aktif, mendukung Laravel 12). Perlu persetujuan dependency di issue ini.
3. Email tetap jadi cadangan, tanpa pekerjaan besar: cukup menambah channel `mail` pada event yang paling penting.
4. WhatsApp Cloud API resmi ditunda. Biayanya per pesan, perlu akun Meta Business, template disetujui Meta, dan opt-in tertulis. Gateway tidak resmi tidak direkomendasikan.
5. Perkiraan MVP Web Push: 6-8 agent-hari (bagian 8).

## 2. Kondisi SITA sekarang

Notifikasi dikirim lewat satu kelas, `App\Notifications\RealtimeNotification`
(`app/Notifications/RealtimeNotification.php`). Method `via()` mengembalikan `['database', 'broadcast']`: baris disimpan di tabel `notifications` dan disiarkan lewat Reverb ke channel privat `App.Models.User.{id}` (`routes/channels.php`).

Semua pengirim lewat `App\Services\RealtimeNotificationService::notifyUser()`. Service ini mengecek `users.notification_preferences`, lalu `$user->notify()`. Kalau sedang di dalam transaksi, pengiriman ditunda sampai commit (`DB::afterCommit`).

Kunci preferensi (`User::NOTIFICATION_PREFERENCE_KEYS`, `app/Models/User.php`): `pesanBaru`, `statusTugasAkhir`, `jadwalBimbingan`, `feedbackDokumen`, `reminderDeadline`, `pengumumanSistem`, `konfirmasiBimbingan`. Semua default aktif.

Pemicu yang ada di kode:

| Pemicu                                                          | Kunci                                          | Sumber                                                                                                                                |
| --------------------------------------------------------------- | ---------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| Pesan chat pribadi/thread bimbingan/sempro/sidang               | `pesanBaru`                                    | `Mahasiswa/PesanController`, `Dosen/PesanBimbinganController`                                                                         |
| Dokumen diunggah mahasiswa, status dokumen diubah dosen         | `statusTugasAkhir` (12 pemanggilan, terbanyak) | `Mahasiswa/UploadDokumenController`, `Dosen/DokumenRevisiController`                                                                  |
| Mahasiswa meminta jadwal bimbingan                              | `jadwalBimbingan`                              | `Mahasiswa/JadwalBimbinganController`                                                                                                 |
| Dosen membuat/mengubah status jadwal                            | `konfirmasiBimbingan`                          | `Dosen/JadwalBimbinganController`                                                                                                     |
| Revisi/saran dosen di chat                                      | `feedbackDokumen`                              | `Dosen/PesanBimbinganController`                                                                                                      |
| Pengumuman sistem                                               | `pengumumanSistem`                             | `SystemAnnouncementService`                                                                                                           |
| Reminder H-1 hari dan H-1 jam: bimbingan, sempro/sidang, revisi | `reminderDeadline`                             | `ReminderDeadlineNotificationService`, perintah `notifications:send-deadline-reminders`, dijadwalkan tiap jam di `routes/console.php` |

Sisi klien:

- `resources/js/echo.ts` membuat koneksi Echo ke Reverb. Nginx meneruskan `/app/` ke container `reverb` (`docker/nginx/`).
- `resources/js/components/app-sidebar-header.tsx` (sekitar baris 243-337) mendengarkan `channel.notification(...)`, menampilkan toast, dan kalau `Notification.permission === 'granted'` memanggil `new window.Notification(...)`. Notifikasi OS hanya muncul selama tab terbuka.
- Izin diminta di `resources/js/components/settings/notification-settings-panel.tsx`. Pilihan disimpan di `users.browser_notifications_enabled` dan `notification_preferences`.
- Tidak ada web app manifest dan service worker. `resources/views/app.blade.php` hanya punya favicon dan `apple-touch-icon`.

Hal lain yang relevan:

- `RealtimeNotification` memakai trait `Queueable` tetapi tidak mengimplementasikan `ShouldQueue`, jadi `database` dan `broadcast` berjalan sinkron di request. Container `queue` sudah ada di Docker Compose dan `QUEUE_CONNECTION=database`. Pengiriman push ke layanan eksternal tidak boleh sinkron.
- Email hanya dipakai untuk reset password (`app/Notifications/Auth/*`, mailer Resend).
- `users.phone_number` dan `phone_number_confirmed_at` sudah ada (migrasi Oktober 2026, terkait #26). Itu modal awal untuk WhatsApp.
- Notifikasi Filament (`Filament\Notifications\Notification`) di panel admin hanya toast UI, bukan bagian dari alur ini.

Konsekuensi: celah yang dibahas issue ini sempit. Notifikasi sudah tersimpan dan terstruktur, yang belum ada hanya jalur ke perangkat saat tab tertutup.

## 3. PWA dan Web Push

### 3.1 Syarat teknis

- HTTPS. SITA sudah di belakang Cloudflare Tunnel yang mengakhiri HTTPS (`docker/nginx/` mengirim header HSTS).
- Service worker yang dilayani dari root origin (`/sw.js`, scope `/`). Taruh di `public/`, jangan di `public/build/`, supaya scope mencakup seluruh aplikasi.
- Kunci VAPID (pasangan kunci publik/privat). Paket di bawah membuatnya lewat `php artisan webpush:vapid`.
- Subscription dari `PushManager.subscribe({ userVisibleOnly: true, applicationServerKey })`, dikirim ke server dan disimpan per perangkat.
- Web app manifest dengan `display: standalone`, `name`, `icons` (192 dan 512 px), `start_url`, `id`. Untuk Android Chrome manifest wajib hanya kalau ingin tombol install. Untuk iOS manifest wajib karena tanpanya situs hanya menjadi bookmark ([WebKit](https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/)).
- `userVisibleOnly: true` berarti setiap push harus menampilkan notifikasi. Tidak ada push senyap, jadi tidak bisa dipakai untuk sinkronisasi latar belakang.

### 3.2 Dukungan per platform

| Platform                                          | Status                                                       | Catatan                                                                                                                                                                                                                                                                                                                 |
| ------------------------------------------------- | ------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Android Chrome/Edge/Firefox                       | Didukung (Chrome 42+, Edge 17+, Firefox 44+)                 | Push masuk walau PWA tidak dipasang, karena browser berjalan sebagai layanan. Sumber: [web.dev](https://web.dev/blog/push-notifications-in-all-modern-browsers), [MDN Push API](https://developer.mozilla.org/en-US/docs/Web/API/Push_API)                                                                              |
| iPhone/iPad (iOS/iPadOS 16.4+)                    | Didukung hanya untuk web app yang ditambahkan ke Home Screen | Izin harus diminta dari aksi pengguna (tombol), bukan otomatis. Safari biasa dan browser pihak ketiga di tab biasa tidak bisa. Pakai APNs, tidak perlu akun Apple Developer, endpoint `*.push.apple.com` tidak boleh diblokir. Sumber: [WebKit](https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/) |
| Desktop (Chrome, Edge, Firefox, Safari 16+ macOS) | Didukung                                                     | Cocok untuk dosen di laptop. Safari desktop sejak 16.1                                                                                                                                                                                                                                                                  |

MDN menandai Push API "Baseline: widely available" sejak Maret 2023 ([MDN](https://developer.mozilla.org/en-US/docs/Web/API/Push_API)).

Untuk Safari/iOS, `VAPID_SUBJECT` wajib diisi URL atau `mailto:` yang valid. Tanpanya Apple menolak dengan `BadJwtToken` ([README paket](https://github.com/laravel-notification-channels/webpush)).

Hal yang belum saya verifikasi dan perlu dicoba di perangkat sungguhan [INFERENCE]: penghematan baterai agresif pada beberapa merek Android (Xiaomi, Oppo, Vivo, Realme) bisa menunda atau menahan push saat browser dimatikan paksa. Ini keluhan umum di komunitas, tetapi saya tidak menemukan dokumen resmi vendor. Masukkan ke uji prototipe.

### 3.3 Paket Laravel

Opsi utama: `laravel-notification-channels/webpush` ([Packagist](https://packagist.org/packages/laravel-notification-channels/webpush), [rilis](https://github.com/laravel-notification-channels/webpush/releases)).

- Versi terakhir 13.0.1 (8 September 2026). Rilis aktif: 12.1.0 (Agustus 2026), 11.0.0 (Mei 2026), 10.5.0 (Februari 2026, dukungan Laravel 13).
- Requirement: PHP ^8.2, `illuminate/*` ^12.0 atau ^13.13, `minishlink/web-push` ^11. Cocok dengan Laravel 12 di `composer.json` SITA. Di Laravel 12 paket memakai Guzzle, bukan HTTP client Laravel.
- Memakai kembali sistem Notification Laravel: cukup tambah `WebPushChannel::class` di `via()` dan method `toWebPush()`.
- Trait `HasPushSubscriptions` di `User`, migrasi sendiri (tabel `push_subscriptions`, kolom endpoint 1024 karakter sejak 12.1.0, relasi polimorfik).
- Langganan kedaluwarsa (410/404 dari layanan push) dihapus otomatis.
- Mendukung TTL, ikon, `tag`, `data`, dan Declarative Web Push (masih berkembang, jangan dipakai di MVP).
- Rilis 13.0.0 mengubah return `WebPushChannel::send()`. Jika kita tidak meng-override, tidak berdampak.
- Lisensi MIT, maintainer organisasi `laravel-notification-channels`. Risiko: mayoritas pekerjaan oleh sedikit kontributor. Mitigasi: pustaka dasarnya `minishlink/web-push` yang juga bisa dipakai langsung.

Alternatif `minishlink/web-push` langsung berarti menulis sendiri channel, tabel, dan pembersihan langganan, jadi tidak sepadan. FCM/OneSignal menambah vendor dan membawa data pengguna keluar, padahal Web Push standar cukup.

### 3.4 Penyimpanan subscription

Satu pengguna bisa punya beberapa perangkat (HP, laptop, PWA dan browser). Tabel `push_subscriptions` dari paket menyimpan endpoint, kunci `p256dh`/`auth`, dan encoding per baris, terikat ke `users`. Tambahan yang kita butuhkan: `user_agent` ringkas atau label perangkat agar pengguna bisa melihat dan mencabut langganan di pengaturan.

Endpoint dan kunci adalah kredensial pengiriman. Perlakukan sebagai data sensitif: tidak masuk log, tidak dikirim ke frontend selain milik pengguna sendiri. Saat logout, hapus langganan perangkat itu supaya notifikasi akun lain tidak muncul di perangkat bersama.

### 3.5 UX izin

- Jangan minta izin saat halaman pertama dibuka. Safari mewajibkannya dari aksi pengguna, dan [MDN](https://developer.mozilla.org/en-US/docs/Web/API/Push_API/Best_Practices) mencatat sebagian besar permintaan otomatis diblokir. Minta lewat tombol "Aktifkan notifikasi di perangkat ini" di pengaturan notifikasi yang sudah ada (`notification-settings-panel.tsx`), dan sekali sebagai banner halus setelah login pertama.
- Tampilkan state: tidak didukung, perlu dipasang ke Home Screen (khusus iOS), belum diminta, ditolak (jelaskan cara membuka lagi lewat pengaturan browser), aktif.
- Untuk iPhone, tampilkan petunjuk singkat bergambar "Bagikan > Tambah ke Layar Utama" sebelum tombol aktif. Deteksi dengan fitur (`'PushManager' in window`), bukan dengan user agent.
- Preferensi per jenis (`notification_preferences`) tetap berlaku. Kolom `browser_notifications_enabled` menjadi saklar kanal push, dipakai ulang bukan diganti.
- Jangan kirim push kalau pengguna sedang aktif di tab (Echo sudah menampilkan toast). Service worker bisa memeriksa `clients.matchAll({ type: 'window' })` dan hanya menampilkan notifikasi bila tidak ada jendela fokus, untuk menghindari notifikasi ganda.

### 3.6 Batas pengiriman

- Payload Web Push dibatasi sekitar 4 KB setelah enkripsi (standar RFC 8291/8030). Judul dan deskripsi SITA pendek, aman. Kirim `url` dan `id`, bukan isi panjang.
- TTL: tentukan per jenis. Pesan chat dan reminder jam sebaiknya TTL pendek (misalnya 1-6 jam), reminder harian lebih panjang. Tanpa TTL layanan push membuang pesan saat perangkat mati.
- Layanan push (FCM untuk Chrome, Mozilla autopush, APNs untuk Safari) tidak menjamin pengiriman dan tidak memberi laporan baca. Anggap push sebagai pengingat, bukan kanal resmi. Status sebenarnya tetap ada di tabel `notifications` dan halaman aplikasi.
- Paket mengembalikan laporan pengiriman per endpoint (13.0.0), cukup untuk log dan penghapusan langganan mati.
- Tidak ada biaya per pesan.

### 3.7 Perubahan di deploy Docker

- Tidak ada container baru. Pengiriman memakai `queue` yang sudah ada. Pastikan notifikasi yang memakai channel push mengimplementasikan `ShouldQueue` agar request tidak menunggu layanan push. Ini mengubah perilaku `RealtimeNotification` yang kini sinkron, jadi perlu diputuskan: ubah kelas itu menjadi antrean (sisi baik: `database` dan `broadcast` ikut antre, ada jeda kecil) atau buat kelas terpisah.
- Variabel baru di `.env`: `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`. Kunci tidak boleh berganti, kalau berganti semua langganan mati. Simpan di secret store deploy dan catat di runbook. Kunci publik dikirim ke frontend lewat prop Inertia, bukan `VITE_*`, supaya image GHCR (`docker-compose.deploy.yml`) tidak perlu dibangun ulang per lingkungan.
- Nginx: `location /` sudah `try_files` ke file statis, jadi `public/sw.js` dan `public/manifest.webmanifest` tersaji. Tambahkan `Cache-Control: no-cache` untuk `sw.js` agar pembaruan service worker cepat diterima. Cek juga bahwa Cloudflare tidak men-cache `sw.js` terlalu lama.
- Egress: server harus bisa menjangkau layanan push (`fcm.googleapis.com`, `updates.push.services.mozilla.com`, `*.push.apple.com`, `*.notify.windows.com`) lewat HTTPS. Jika ada pembatasan keluar di host, buka daftar ini.
- Vite: manifest dan service worker bisa ditulis tangan di `public/` (rekomendasi MVP, tanpa dependency baru) karena kita hanya butuh handler `push` dan `notificationclick`, tanpa cache offline. `vite-plugin-pwa` 2.0.0 mendukung Vite 7, tetapi menambah Workbox dan tidak diperlukan untuk tujuan ini.
- Dev lokal: `localhost` dianggap konteks aman, jadi service worker dan push bisa diuji tanpa HTTPS. Uji di perangkat nyata butuh tunnel HTTPS.

## 4. WhatsApp

### 4.1 Cloud API resmi (WhatsApp Business Platform)

Persyaratan ([Business Messaging Policy](https://whatsappbusiness.com/policy/), [dokumen template](https://developers.facebook.com/documentation/business-messaging/whatsapp/templates/overview)):

- Akun Meta Business dan akun WhatsApp Business (WABA) atas nama institusi, nomor telepon khusus yang tidak terpasang di aplikasi WhatsApp biasa.
- Verifikasi bisnis menaikkan batas: portofolio belum terverifikasi dibatasi 250 template, yang terverifikasi sampai 6.000. Batas pengiriman harian juga mengikuti ([messaging limits](https://developers.facebook.com/documentation/business-messaging/whatsapp/messaging-limits)). Siapa pemilik legal akun (Universitas Bumigora, bukan pribadi dosen/pengembang) perlu diputuskan.
- Pesan pertama ke pengguna di luar jendela layanan 24 jam hanya boleh memakai template yang disetujui Meta. Review otomatis, biasanya sampai 24 jam, template bisa ditolak, dijeda, atau dinonaktifkan bila kualitasnya buruk.
- Opt-in wajib: pengguna harus memberikan nomor dan menyetujui menerima pesan dari kita. Kebijakan Meta mewajibkan menghormati permintaan berhenti.
- Template reminder jadwal/ujian termasuk kategori utility (transaksional). Pengumuman umum cenderung marketing dan lebih mahal. Penentuan kategori ada di Meta, bukan kita.

Biaya. Sejak 1 Juli 2025 Meta menagih per pesan terkirim, bukan per percakapan ([Meta pricing](https://developers.facebook.com/documentation/business-messaging/whatsapp/pricing)). Sumber di penugasan menyebut harga per percakapan, itu sudah tidak berlaku. Pesan dari pengguna ke bisnis gratis. Pesan layanan (balasan dalam 24 jam) punya 1.000 gratis per nomor per bulan.

Tarif Indonesia (salinan rate card Meta efektif 1 Januari 2026 dari [Gupshup](https://www.gupshup.ai/resources/wp-content/uploads/2025/12/IDR_Jan2026.pdf)): utility Rp356,65 per pesan, marketing Rp586,33 per pesan, autentikasi Rp356,65. Meta mengubah tarif paling cepat per kuartal dan halaman resminya menyebut pembaruan efektif 1 Oktober 2026, jadi angka ini harus dicek ulang pada rate card terbaru (CSV IDR) sebelum keputusan anggaran. Biaya belum termasuk markup jika lewat penyedia resmi (BSP) lokal.

Contoh hitung kasar (asumsi saya, bukan data SITA): 500 mahasiswa dan 100 dosen menerima rata-rata 15 pesan utility per bulan = 9.000 pesan x Rp356,65, sekitar Rp3,2 juta per bulan sebelum markup BSP. Jumlah pengguna aktif sebenarnya perlu dari produk.

Integrasi di Laravel: tidak ada paket resmi Meta. Ada channel komunitas, tetapi lebih aman memanggil Graph API langsung lewat `Http::` (satu channel kustom kecil), ditambah webhook status pengiriman. Pekerjaan lebih besar dari Web Push karena ada penyimpanan nomor yang terverifikasi, opt-in, template per bahasa, dan penanganan gagal kirim.

### 4.2 Gateway tidak resmi

Gateway yang menyambungkan nomor biasa lewat WhatsApp Web (Baileys, whatsapp-web.js, atau layanan "WA gateway" murah) melanggar ketentuan. WhatsApp menyatakan penggunaan otomatis atau massal tanpa izin melanggar Terms of Service dan siap menempuh langkah hukum ([WhatsApp Help Center](https://faq.whatsapp.com/5957850900902049)).

Risiko untuk SITA:

- Nomor bisa diblokir permanen. Kalau itu nomor kontak resmi prodi atau dosen, dampaknya bukan hanya notifikasi.
- Sesi sering putus dan perlu scan QR ulang, jadi tidak andal untuk reminder ujian.
- Tidak ada jaminan: bisa berhenti bekerja kapan saja saat WhatsApp mengubah protokol.
- Kita menyerahkan nomor HP mahasiswa ke pihak ketiga tanpa kontrak data yang jelas. Itu berisiko terhadap UU PDP (2022) [INFERENCE: perlu ditinjau bagian hukum kampus].
- Opt-in dan opt-out tidak ditegakkan oleh platform, tanggung jawab sepenuhnya di kita.

Kesimpulan: tidak dipakai. Kalau WhatsApp diinginkan, hanya lewat Cloud API resmi.

Satu catatan produk: `PRODUCT.md` menyatakan koordinasi informal tetap di WhatsApp di luar aplikasi. Notifikasi WhatsApp otomatis dari sistem mengubah posisi itu sedikit, jadi ini juga keputusan produk, bukan hanya teknis.

### 4.3 Email sebagai cadangan

- Sudah ada infrastruktur: Resend, antrean, `User::notify()`. Cukup tambahkan `mail` di `via()` untuk jenis tertentu dan buat `toMail()` dengan teks Indonesia dan label dari `AcademicTerminology`.
- Biaya mengikuti paket Resend yang dipakai kampus (cek kuota). Tidak ada persetujuan template, tidak ada opt-in khusus selain preferensi.
- Kekurangan: mahasiswa jarang membuka email kampus, dan balasan lambat. Cocok untuk reminder ujian (H-1) dan perubahan jadwal, tidak cocok untuk chat.
- Aturan anti-spam: kirim email hanya untuk jenis yang memang penting, dan hanya bila push gagal/tidak ada langganan aktif [keputusan produk, lihat bagian 7].

## 5. Notifikasi mana yang layak dikirim sebagai push

Prinsip dari [MDN](https://developer.mozilla.org/en-US/docs/Web/API/Push_API/Best_Practices): kirim hanya yang berguna dan peka waktu, beri jalan keluar mudah.

| Kunci                 | Push?                             | Alasan                                                                                                                                               |
| --------------------- | --------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- |
| `reminderDeadline`    | Ya (MVP)                          | Peka waktu, paling bernilai saat aplikasi tertutup                                                                                                   |
| `konfirmasiBimbingan` | Ya (MVP)                          | Dosen menyetujui/menjadwal ulang, mahasiswa perlu tahu segera                                                                                        |
| `jadwalBimbingan`     | Ya (MVP)                          | Permintaan ke dosen, perlu tindakan                                                                                                                  |
| `statusTugasAkhir`    | Ya, tetapi terpilih               | Dokumen baru atau status diubah. Hindari event kecil yang berulang                                                                                   |
| `feedbackDokumen`     | Ya                                | Mahasiswa menunggu revisi dosen                                                                                                                      |
| `pesanBaru`           | Tahap 2, dengan penggabungan      | Paling berpotensi spam. Gabungkan per thread memakai `tag` (satu notifikasi per thread, diganti saat ada pesan baru) dan jeda minimal beberapa menit |
| `pengumumanSistem`    | Opsional, default mati untuk push | Siaran ke semua pengguna                                                                                                                             |

Anti-spam tambahan: `tag` per objek (jadwal, thread) supaya notifikasi menimpa, bukan menumpuk. Satu kali kirim per `(pengguna, kunci, id objek, jendela)`: `reminderDeadline` sudah punya `reminderKey` sehingga idempoten, ikuti pola itu. Hormati jam tenang bila produk menginginkan.

## 6. Rencana bertahap

### Tahap 0: prototipe (di luar produksi)

Manifest + `sw.js` + tombol berlangganan + satu perintah artisan yang mengirim push uji. Buktikan di: Android Chrome (aplikasi tertutup, layar mati, 2 merek berbeda), iPhone iOS 16.4+ terpasang di Home Screen, laptop Chrome. Keluaran: tabel hasil uji dan keputusan lanjut. Perkiraan: 1-1,5 hari.

### Tahap 1: MVP Web Push (target pertama)

Lingkup:

- Dependency `laravel-notification-channels/webpush`, migrasi, trait di `User`, kunci VAPID di deploy.
- Endpoint simpan/hapus langganan (Form Request, route Wayfinder, hanya milik pengguna sendiri), hapus langganan saat logout.
- Manifest, ikon, `sw.js` (`push`, `notificationclick` membuka `url`, cek jendela fokus), registrasi service worker di `app.tsx`.
- Perluas `RealtimeNotification` (atau kelas baru) dengan `WebPushChannel`, diantre lewat `queue`. Hormati `notification_preferences` dan `browser_notifications_enabled`.
- Push untuk `reminderDeadline`, `konfirmasiBimbingan`, `jadwalBimbingan`, `statusTugasAkhir`, `feedbackDokumen`.
- Pengaturan: tombol aktif/nonaktif per perangkat, daftar perangkat, petunjuk iOS.
- Header nginx untuk `sw.js`, dokumentasi deploy (variabel `.env`, daftar egress).

Opt-in: pengguna aktif memilih lewat tombol. Tidak ada langganan otomatis. Semua label dari `AcademicTerminology`, bukan teks tetap.

Tes:

- Pest: simpan/hapus langganan, otorisasi milik sendiri, notifikasi memilih channel `WebPushChannel` sesuai preferensi, `Http::fake`/channel fake untuk memastikan tidak ada panggilan nyata, penghapusan langganan kedaluwarsa, idempotensi reminder.
- Playwright: alur pengaturan notifikasi (state izin, tombol, izin yang ditolak). Browser headless tidak menerima push nyata, jadi pengiriman diuji di Pest dan di perangkat (daftar periksa manual di PR).
- `npm run types`, `npm run lint`, `vendor/bin/pint --dirty`.

### Tahap 2

- Push `pesanBaru` dengan penggabungan per thread.
- Cadangan email untuk reminder ujian dan perubahan jadwal.
- Badge ikon aplikasi (Badging API, jumlah belum dibaca) untuk iOS dan Android terpasang.

### Tahap 3 (hanya jika disetujui)

WhatsApp Cloud API resmi untuk 2-3 template utility (reminder jadwal bimbingan, jadwal sempro/sidang). Prasyarat di luar kode: akun WABA kampus, nomor khusus, verifikasi bisnis, opt-in tertulis, anggaran bulanan.

## 7. Estimasi (agent-hari)

| Pekerjaan                                                                         | Hari                                                |
| --------------------------------------------------------------------------------- | --------------------------------------------------- |
| Tahap 0 prototipe + tabel uji perangkat                                           | 1-1,5                                               |
| Tahap 1 backend (paket, migrasi, channel, antrean, endpoint, tes Pest)            | 2-3                                                 |
| Tahap 1 frontend (manifest, service worker, UI pengaturan, state iOS, Playwright) | 2-3                                                 |
| Deploy dan dokumentasi (VAPID, nginx, egress, runbook)                            | 0,5-1                                               |
| Total MVP (0 + 1)                                                                 | 6-8                                                 |
| Tahap 2 (pesan digabung, email, badge)                                            | 2-3                                                 |
| Tahap 3 WhatsApp resmi (kode: channel, webhook, opt-in, template)                 | 4-6, belum termasuk proses akun Meta di sisi kampus |

Angka ini perkiraan saya dari struktur kode saat ini, belum diuji dengan perangkat nyata. Waktu terbesar yang tidak pasti adalah uji perangkat Android dan iOS.

## 8. Risiko

- iOS wajib dipasang ke Home Screen. Jika banyak mahasiswa memakai iPhone dan tidak memasang, push tidak sampai. Mitigasi: panduan di aplikasi, email sebagai cadangan.
- Penghemat baterai vendor Android dapat menunda push [INFERENCE]. Mitigasi: uji prototipe, dokumentasikan langkah di panduan mahasiswa.
- Notifikasi ganda (toast Echo plus push). Mitigasi: cek jendela fokus di service worker.
- Kunci VAPID hilang atau berganti memutus semua langganan. Mitigasi: simpan di secret store.
- Push mengubah `RealtimeNotification` dari sinkron ke antre, memengaruhi kecepatan munculnya notifikasi di header. Uji `ScheduleUpdated`/chat yang bergantung pada notifikasi sinkron.
- Paket `webpush` dikelola komunitas. Jika berhenti dirawat, pindah ke `minishlink/web-push` langsung dengan perubahan kecil.
- Spam dan kelelahan notifikasi. Mitigasi: daftar jenis di bagian 5, `tag`, preferensi per jenis.
- WhatsApp: biaya berulang, akun kampus dapat dibatasi bila kualitas template rendah, ketergantungan pada kebijakan Meta yang berubah tiap kuartal.
- Privasi: judul notifikasi tampil di layar kunci. Jangan masukkan isi pesan chat atau catatan dosen, cukup "Pesan baru dari Dosen X". Ikuti aturan privasi di `PRODUCT.md`.

## 9. Keputusan yang harus diambil pemilik produk

1. Setuju menambah dependency `laravel-notification-channels/webpush` (dan `minishlink/web-push` sebagai turunannya)?
2. Mahasiswa iPhone: cukup petunjuk "tambah ke Layar Utama", atau perlu dukungan lain? Berapa perkiraan proporsi iPhone di kampus?
3. Jenis notifikasi yang boleh jadi push pada MVP (usulan di bagian 5) dan apakah `pesanBaru` ditunda ke tahap 2.
4. Default saat ini: opt-in per perangkat lewat tombol. Setuju tidak ada langganan otomatis?
5. Email sebagai cadangan: untuk jenis apa, dan hanya bila push tidak tersedia atau selalu?
6. WhatsApp: lanjut atau tidak? Jika ya, siapa pemilik akun WABA (institusi), siapa yang mengurus verifikasi bisnis, dan berapa anggaran bulanan? Dan setujukah WhatsApp otomatis mengubah posisi di `PRODUCT.md` bahwa WhatsApp hanya untuk koordinasi informal?
7. Apakah nomor HP yang sudah dikonfirmasi (#26) boleh dipakai untuk notifikasi otomatis, dan apa teks persetujuannya (PDP)?
8. Jam tenang dan batas jumlah push per hari per pengguna?
9. `RealtimeNotification` boleh dijadikan antrean (`ShouldQueue`), atau dibuat kelas terpisah untuk push?

## 10. Usulan issue implementasi (belum dikerjakan)

1. Prototipe Web Push di Android/iOS/desktop (Tahap 0).
2. Backend langganan dan channel Web Push, antre, preferensi.
3. PWA: manifest, service worker, UI pengaturan perangkat dan petunjuk iOS.
4. Deploy: VAPID, nginx `sw.js`, dokumentasi egress.
5. Push `pesanBaru` digabung per thread + badge (Tahap 2).
6. Email cadangan untuk reminder (Tahap 2).
7. WhatsApp Cloud API (Tahap 3, menunggu keputusan #6).
