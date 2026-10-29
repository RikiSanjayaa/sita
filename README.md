# SiTA

SiTA (Sistem Informasi Tugas Akhir) Universitas Bumigora mencatat perjalanan tugas akhir mahasiswa dari pengajuan judul sampai lulus: bimbingan, dokumen, seminar proposal, sidang, dan revisi. Mahasiswa, dosen, kaprodi, dan admin bekerja di satu aplikasi dengan chat dan notifikasi real-time.

| Portal publik                                                                                                                                                      | Dashboard mahasiswa                                                                                                                                                  |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ![Portal publik](https://raw.githubusercontent.com/RikiSanjayaa/sita/ab1e444ab898439f912ce3d4d1544765f924fc81/catalog/public/beranda-1280-light.png)               | ![Dashboard mahasiswa](https://raw.githubusercontent.com/RikiSanjayaa/sita/ab1e444ab898439f912ce3d4d1544765f924fc81/catalog/mahasiswa/dashboard-1280-light.png)      |
| **Chat bimbingan, sempro, dan sidang**                                                                                                                             | **Dashboard dosen**                                                                                                                                                  |
| ![Chat](https://raw.githubusercontent.com/RikiSanjayaa/sita/ab1e444ab898439f912ce3d4d1544765f924fc81/catalog/mahasiswa/pesan-1280-light.png)                       | ![Dashboard dosen](https://raw.githubusercontent.com/RikiSanjayaa/sita/ab1e444ab898439f912ce3d4d1544765f924fc81/catalog/dosen/dashboard-1280-light.png)              |
| **Pemantauan mahasiswa oleh kaprodi**                                                                                                                              | **Panel admin**                                                                                                                                                      |
| ![Daftar mahasiswa kaprodi](https://raw.githubusercontent.com/RikiSanjayaa/sita/ab1e444ab898439f912ce3d4d1544765f924fc81/catalog/kaprodi/mahasiswa-1280-light.png) | ![Panel admin](https://raw.githubusercontent.com/RikiSanjayaa/sita/ab1e444ab898439f912ce3d4d1544765f924fc81/catalog/admin/dashboard-1280-light.png)                  |
| **Review dokumen bimbingan**                                                                                                                                       | **Pengaturan privasi nomor HP**                                                                                                                                      |
| ![Review dokumen dosen](https://raw.githubusercontent.com/RikiSanjayaa/sita/ab1e444ab898439f912ce3d4d1544765f924fc81/catalog/dosen/dokumen-detail-1280-light.png)  | ![Privasi nomor HP](https://raw.githubusercontent.com/RikiSanjayaa/sita/ab1e444ab898439f912ce3d4d1544765f924fc81/catalog/mahasiswa/pengaturan-profil-1280-light.png) |

Tangkapan layar memakai data contoh dari seeder. Galeri di atas berasal dari katalog CI untuk revisi aplikasi [`3d28b67`](https://github.com/RikiSanjayaa/sita/commit/3d28b676f03ba8836afa24a0bf7fbc70167d0ddd), yang memuat workspace chat terpadu, review dokumen dan pengaturan privasi nomor HP; tautan gambar dipatok ke commit aset agar tidak berubah diam-diam. [Katalog lengkap](https://github.com/RikiSanjayaa/sita/blob/ab1e444ab898439f912ce3d4d1544765f924fc81/catalog/README.md) memuat halaman dan state lain pada 1280px dan 390px, terang dan gelap.

## Fitur

| Peran     | Yang bisa dilakukan                                                                                                                                                                    |
| --------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Mahasiswa | Mengajukan judul dan usulan pembimbing, mengatur bimbingan, mengunggah dokumen, chat, menjawab butir revisi per putaran, serta memantau progres dan riwayat ujian                      |
| Dosen     | Menjadwalkan bimbingan, me-review dokumen, menilai ujian, mencatat butir revisi dan naskah beranotasi, memverifikasi perbaikan, serta memberi catatan mahasiswa/internal               |
| Kaprodi   | Memantau progres dan aktivitas mahasiswa prodi, menetapkan pembimbing, menjadwalkan Sempro/Sinopsis dan Sidang Akhir, serta mengatur kuota dosen                                       |
| Admin     | Mengelola pengguna dan import Excel, mengimpor tugas akhir berjalan, mengoreksi data akademik, memperpanjang masa aktif bimbingan, serta mengelola pengumuman dan audit lewat Filament |

Halaman publik (`/jadwal`, `/topik`, `/pembimbing`, `/mahasiswa-aktif`) bisa dibuka tanpa login.

Form login menyediakan ikon mata untuk menampilkan atau menyembunyikan kata sandi tanpa mengubah isinya atau mengirim form. Login admin memakai kontrol bawaan Filament. Pada workspace kaprodi, daftar dokumen seluruh prodi bernama Dokumen Prodi; Dokumen & Revisi pada Bimbingan Saya tetap terpisah.

Dashboard setiap peran mendahulukan tindakan berikutnya. Progres mahasiswa, jumlah bimbingan, aktivitas terakhir, riwayat revisi/ujian ulang, dan masa aktif bimbingan memakai data yang sama di portal terkait. Tampilan menyediakan skema warna aksesibel, mode terang/gelap, tabel ringkas di ponsel, dan kalender yang dapat diciutkan. Portal dioptimalkan untuk desktop; layar ponsel tetap dapat dipakai dengan pemberitahuan batasnya.

Pada ponsel, pencarian daftar tetap terlihat di samping ikon Filter. Ikon membuka panel inline tepat di bawah pencarian, tanpa modal; pilihan dropdown langsung menyaring daftar. Daftar dengan banyak kriteria dimulai dari "Filter berdasarkan...", lalu pengguna memilih dan menggabungkan kriteria yang diperlukan. Kriteria dapat dihapus satu per satu atau melalui Reset filter. Menutup panel mempertahankan filter aktif, yang ditandai titik pada ikon dan jumlah pada nama aksesibelnya. Metadata pendek pada entri tabel, profil dan detail memakai dua kolom; konten panjang tetap dapat dibaca lengkap. Default pagination ponsel 5 baris, desktop mempertahankan ukuran sebelumnya, dan pilihan manual tetap dihormati saat resize. Rentang dijelaskan sebagai "Menampilkan 1-5 dari 28 mahasiswa" bersama kontrol satu baris dan panah bertarget sentuh 44px; daftar publik dan Progress Seluruh Mahasiswa meminta halaman server lengkap, bukan memotong hasil yang sudah diambil. Hanya di ponsel, navigasi publik menampilkan lima tautan teks dengan garis bawah pada halaman aktif; desktop tetap memakai pill seperti sebelumnya.

Pada dashboard lebar, agenda mendatang dibatasi setinggi bagian tindakan dan dapat discroll tanpa memanjangkan kartu tindakan; pada layar sempit agenda kembali mengikuti alur halaman. Baris tabel jadwal dan riwayat bimbingan dapat dibuka dengan klik, Enter atau Space, selain melalui ikon mata. Kontrol dosen yang sebelumnya berada di tombol Kelola kini tersedia dalam drawer detail sesi yang sama. Form catatan revisi dosen dapat diperbesar tanpa kehilangan tulisan.

Chat mendukung lampiran, tinjauan dokumen, dan badge jumlah pesan belum dibaca yang terpisah dari lonceng notifikasi global. Web Push/PWA tersedia sebagai fitur opt-in yang **default mati**; [persiapan dan batas perangkat](#notifikasi-web-push-dan-pwa) wajib dibaca sebelum aktivasi. Penguji tambahan Sidang Akhir tetap dirahasiakan dari mahasiswa sampai ujian selesai.

### Reply, edit, dan gambar chat

Menu Aksi pesan menyediakan Balas dan Edit teks / caption. Kutipan bersifat **live**: perubahan teks sumber memperbarui kutipan, bukan menyimpan salinan lama. Batal mengembalikan draft kirim; kegagalan edit tetap menyimpan tulisan untuk dicoba lagi. File asli tidak dapat diganti melalui edit. Pesan sistem/review tidak dapat diedit. Percakapan selesai dan arsip hanya dapat dibaca. Caption pada kiriman lama tetap berupa pesan teks terpisah, sesuai kontrak pengiriman yang sudah ada.

Payload pesan menambah `reply` (`null`, `{available:false}`, atau konteks berisi `id`, `author`, `message`, `documentName`) dan `editedAt`. Sumber hanya diambil dari thread yang sama melalui presenter pesan biasa; sumber hilang/tidak terlihat termasuk penguji Sidang rahasia menghasilkan `{available:false}` tanpa identitas, isi, nama file, atau thread. `GET /{mahasiswa|dosen}/pesan/{thread}/messages/{message}` memuat sumber berwenang di luar jendela riwayat terakhir; `PATCH` pada URL yang sama hanya mengubah teks/caption milik sendiri. Event `chat.message.updated` memperbarui bubble dan kutipan tanpa event pesan baru, notifikasi baru, perubahan ID/UUID acknowledgement, waktu kirim, urutan, atau receipt. Nama event kirim dan acknowledgement UUID lama tetap dipakai.
`quoteAvailable` pada pesan memakai keputusan presenter yang sama untuk event edit. Client tidak mengisi ulang kutipan dari sumber yang sudah disamarkan; draft balasan ke sumber tersebut dibatalkan tanpa menghapus teks kirim.

Waktu bubble hanya jam WITA (`HH.mm`). `sentAt` berisi waktu kirim asli dalam ISO 8601 untuk pengelompokan kalender; edit tidak mengubahnya. Tanggal bertahan di bawah pill identitas saat membaca riwayat: Hari ini, Kemarin, lalu tanggal Indonesia lengkap. Batas hari mengikuti Asia/Makassar, termasuk saat zona waktu perangkat berbeda dan ketika lewat tengah malam. Teks/waktu berjarak 12px dari tepi bubble, sedangkan gambar/kutipan 4px.

Migrasi `2026_10_28_234433_add_reply_and_edited_at_to_mentorship_chat_messages_table` menambah `reply_to_id` nullable terindeks dan `edited_at` nullable. ID sumber sengaja tidak memakai cascade/FK: penghapusan sumber mempertahankan konteks tidak tersedia, bukan menghapus balasan. Jalankan migrasi sebelum memakai backend baru. Untuk rollback, hentikan trafik dan kembalikan backend/aset bersama sebelum rollback migrasi tersebut; rollback menghilangkan relasi kutipan dan penanda edit secara permanen, tetapi tidak mengembalikan teks yang sudah diedit. Tidak ada riwayat versi teks. Tidak ada perubahan storage file.

Pemilihan file, drop, dan paste memakai satu persiapan browser native, hanya pada chat:

- Gambar JPEG/PNG/WebP di atas 512 KiB disiapkan dengan sisi maksimum 2048 px (tanpa upscale). JPEG/WebP memakai kualitas 0.84; PNG tetap lossless dengan alpha. File yang lebih kecil tidak dikode ulang. Hasil dipakai hanya jika MIME tetap sama dan byte lebih kecil daripada sumber; nama file tetap, preview/ukuran/progress memakai file yang benar-benar dikirim.
- Decoder native menerapkan orientasi EXIF sebelum resize; hasil menyimpan pixel berorientasi normal. Metadata EXIF tidak dipertahankan pada hasil baru. Transparansi PNG/WebP tidak diratakan menjadi JPEG. APNG dan WebP animasi dilewatkan byte-identik; format/encoder tidak didukung tidak dikonversi diam-diam. Format di luar daftar izin lama (termasuk GIF/HEIC/ZIP) tetap ditolak. ZIP tidak dikompresi atau dibuka ulang; fitur ini tidak menambah izin upload ZIP.
- Signature gambar diperiksa sebelum encode. Isi yang tidak dikenali tidak dikonversi. Bila MIME deklarasi berbeda dari JPEG/PNG/WebP yang terdeteksi, byte sumber dipertahankan dan MIME dikoreksi; PNG yang diberi nama `.jpg` tidak diratakan menjadi JPEG. Server tetap memvalidasi isi, bukan hanya deklarasi browser.
- PDF/DOC/DOCX tidak diproses. Unggahan naskah, tanda tangan, berkas ujian dan dokumen di luar composer chat tidak memakai persiapan gambar ini. Validasi server tetap memeriksa MIME dan maksimum 10 MiB pada file terkirim; optimasi bukan cara melewati pemeriksaan server. Decode/encode gagal ditampilkan dengan Coba siapkan lagi; teks dan lampiran draft sebelumnya tetap ada, dan pengiriman diblokir sampai kegagalan diselesaikan.

Kebijakan dipilih sebelum implementasi memakai fixture kamera/dokumen sintetis deterministik `cameraFixture` (3200x2400, JPEG 2,697,460 byte). Pada 2048x1536, kandidat kualitas 0.80/0.84/0.90 menghasilkan 417,929 / 491,602 / 650,418 byte. Kualitas 0.84 mengurangi 81.8% byte; inspeksi gambar menunjukkan seluruh 17 baris teks tetap terbaca dan gradien/tekstur tetap terlihat. Playwright membuat fixture ini kembali, membandingkan byte upload/drop/paste, serta memeriksa orientasi, alpha, animasi, gambar kecil, kegagalan dan retry. Ukuran tepat dapat berbeda antarversi encoder browser; batas dimensi dan aturan tidak-membesar tetap berlaku.

Nomor HP tersembunyi dari pengguna lain secara default. Pemilik dapat mengizinkan pengguna yang login melihatnya melalui Pengaturan Akun > Profil. Admin dan kaprodi tetap dapat melihat nomor; pembimbing, penguji, dan mahasiswa yang terkait dapat saling melihat nomor sesuai penugasan, termasuk riwayat. Kontak penguji tambahan Sidang tetap mengikuti aturan kerahasiaan sampai ujian selesai.

Catatan hasil bimbingan dibuka lewat ikon mata pada baris sesi, lalu ditampilkan dalam drawer. Mahasiswa dan dosen peserta menyimpan kontribusi masing-masing tanpa menimpa catatan pihak lain; nama penulis, peran dan waktu pembaruan ikut ditampilkan. Catatan hasil terpisah dari catatan penjadwalan. Pada profil mahasiswa, setiap dosen memiliki daftar sesi yang tertutup secara default: expand dosen untuk melihat baris sesi ringkas, lalu buka ikon mata untuk membaca isi lengkap. Kaprodi hanya membaca dalam cakupan prodinya; dosen hanya sesi yang diikutinya; mahasiswa hanya sesi sendiri. Admin membaca melalui tab Catatan pada detail tugas akhir sesuai cakupan administrasinya. Catatan arsip tetap tersedia pada profil mahasiswa dengan hierarki yang sama, bukan menjadi feed tersendiri di halaman jadwal dosen atau kaprodi. Sesi yang ditolak/dibatalkan, tugas akhir tidak aktif, penugasan berakhir, masa aktif bimbingan habis dan data arsip hanya dapat dibaca. Arsip menyimpan salinan catatan sebelum jadwal aktif dibersihkan.

Penyimpanan penilaian penguji menampilkan konfirmasi mahasiswa, jenis/attempt ujian, keputusan, nilai bila berlaku, serta catatan dan lampiran revisi. Sempro non-OBE tetap tanpa nilai; Sinopsis OBE dan Sidang memakai aturan nilai yang sudah ada. Batal pada konfirmasi mempertahankan draft. Admin meninjau ringkasan hasil akhir dan dampak tahap/revisi sebelum konfirmasi terpisah; hasil Lulus Sempro tetap membuka revisi, bukan langsung memindahkan mahasiswa ke riset. Portal kaprodi tetap untuk monitoring dan penjadwalan, bukan penilaian. Kaprodi yang ditugaskan sebagai penguji menilai lewat Bimbingan Saya (portal dosen).

Pekerjaan yang belum selesai dan langkah berikutnya dicatat pada [tracking revisi FT #35](https://github.com/RikiSanjayaa/sita/issues/35):

| Issue                                                                                                                                       | Status dan tindak lanjut                                                                                                                                                                           |
| ------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [#17 Berita acara](https://github.com/RikiSanjayaa/sita/issues/17)                                                                          | Menunggu template resmi Sempro/Sinopsis dan Sidang dari fakultas sebelum implementasi cetak HTML.                                                                                                  |
| [#23 Rekap Excel](https://github.com/RikiSanjayaa/sita/issues/23)                                                                           | Menunggu referensi kolom, pengelompokan dan periode laporan; export mengikuti filter serta cakupan prodi.                                                                                          |
| [#25 Panduan per peran](https://github.com/RikiSanjayaa/sita/issues/25)                                                                     | Panduan mahasiswa per prodi sudah tersedia; panduan umum mahasiswa serta panduan dosen, kaprodi dan admin belum lengkap. Konten akhir mengikuti fitur yang sudah merge.                            |
| [#28 Riset Web Push](https://github.com/RikiSanjayaa/sita/issues/28) / [#144 Implementasi](https://github.com/RikiSanjayaa/sita/issues/144) | Riset dan implementasi sudah merge; matriks perangkat nyata serta diagnosis aktivasi intermittent/HTTP 502 tetap terbuka di #144. Email dan WhatsApp ditunda.                                      |
| [#157 Drawer bimbingan](https://github.com/RikiSanjayaa/sita/issues/157)                                                                    | Kontrol sesi terpadu dan baris interaktif sudah merge melalui #160; akar macet pada Firefox desktop fisik belum terverifikasi. Perlu uji ulang pada perangkat pengguna, bukan hanya hasil otomasi. |

## Teknologi

Laravel 12 (PHP 8.4), Inertia v2 + React 19 + TypeScript, Tailwind CSS v4, Filament untuk panel admin, Laravel Reverb + Echo untuk real-time, Pest dan Playwright untuk pengujian.

## Coba cepat dengan image Docker

Cukup Docker dan Docker Compose; tidak perlu clone repo, PHP, atau Node. Image siap pakai diambil dari GHCR.

```bash
mkdir sita && cd sita
for f in docker-compose.yml docker-compose.deploy.yml .env.docker; do
  curl -fsSLO "https://raw.githubusercontent.com/RikiSanjayaa/sita/main/$f"
done
cp .env.docker .env
echo "base64:$(openssl rand -base64 32)"   # salin hasilnya ke APP_KEY di .env
```

Lalu pilih isi database:

**A. Dengan data contoh.** Set `RUN_DB_SEED=true` di `.env`, lalu jalankan:

```bash
docker compose -f docker-compose.yml -f docker-compose.deploy.yml up -d
```

Seeding pertama butuh sekitar 1-2 menit. Masuk dengan [akun contoh](#akun-contoh).

**B. Database kosong dengan super admin sendiri.** Biarkan `RUN_DB_SEED=false` (default), jalankan perintah `up` yang sama, lalu buat akun pertama secara interaktif:

```bash
docker compose -f docker-compose.yml -f docker-compose.deploy.yml exec app php artisan sita:provision-initial-super-admin
```

Masuk ke `/admin` dengan akun tersebut untuk membuat prodi, dosen, dan mahasiswa (atau import dari Excel).

Aplikasi berjalan di `http://localhost:8088`. Secara default dipakai image `main` (build terbaru); untuk versi rilis tertentu set `APP_IMAGE=ghcr.io/rikisanjayaa/sita-app:1.0.1` dan `WEB_IMAGE=ghcr.io/rikisanjayaa/sita-web:1.0.1` (atau tag `latest`). Matikan dengan `docker compose -f docker-compose.yml -f docker-compose.deploy.yml down` (tambah `-v` untuk menghapus data).

Tag `1.0.1` di atas hanya contoh cara mematok versi; rilis lama itu belum memuat seluruh fitur dan screenshot pada README ini. Petunjuk Reverb runtime berikut berlaku untuk image `main` setelah [#116](https://github.com/RikiSanjayaa/sita/pull/116) dan rilis yang memasukkan perubahan tersebut.

> Chat real-time memakai konfigurasi Reverb saat runtime, termasuk pada image GHCR. Untuk mencoba lewat HTTP lokal, gunakan `REVERB_HOST=localhost`, `REVERB_PORT=8089`, dan `REVERB_SCHEME=http` di `.env` server; browser dan port WebSocket harus dapat saling menjangkau. Tidak perlu build ulang asset untuk mengganti host/key Reverb. Konfigurasi yang belum diisi dapat menyebabkan chat tidak memperbarui secara langsung.

### Build image sendiri

Dari hasil clone repo, stack yang sama bisa di-build dari source:

```bash
cp .env.docker .env       # isi APP_KEY seperti di atas; atur RUN_DB_SEED sesuai pilihan A/B
docker compose build --pull
docker compose up -d --no-build
```

Konfigurasi Reverb untuk browser dibaca saat runtime dari `REVERB_APP_KEY`, `REVERB_HOST`, `REVERB_PORT`, dan `REVERB_SCHEME` (default lokal `ws://localhost:8089`); alamat antar-container ada di `REVERB_INTERNAL_*`.

Image PHP dipatok ke 8.4.27 atau rilis lebih baru pada cabang 8.4 untuk perbaikan CVE-2026-109618 dan CVE-2026-109619. Build memeriksa versi PHP pada stage asset dan runtime; `scripts/deploy-via-compose.sh` juga menolak image lama sebelum menjalankan init. Setelah pembaruan image, buat ulang `app`, `queue`, `scheduler`, dan `reverb`; restart container lama saja tidak mengganti PHP. Pada aaPanel, perbarui PHP-FPM site dan PHP CLI ke versi yang sudah diperbaiki, lalu restart seluruh worker terkait. Tag `main`/`latest` dan waktu build bukan bukti versi PHP; periksa binary yang benar-benar melayani aplikasi.

Lockfile juga memuat patch keamanan `league/commonmark` 2.10.4, `symfony/http-foundation` 7.4.21, dan `symfony/yaml` 7.4.21. Untuk deployment dari source, jalankan `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader` memakai `composer.lock` yang ikut revisi tersebut; jangan menjalankan update dependency bebas di produksi. Untuk Docker, tarik image rilis yang sudah dibangun ulang dan buat ulang service aplikasi beserta worker. Audit lockfile dengan `composer audit --locked` sebelum rilis berikutnya; audit bersih tidak menggantikan pengujian aplikasi.

Jika tag Docker resmi belum tersedia, build akan gagal daripada memakai PHP lama. Build dari source resmi yang checksum-nya telah diverifikasi dapat memakai `--build-arg PHP_ASSETS_IMAGE=<image-cli-lokal>` dan `--build-arg PHP_RUNTIME_IMAGE=<image-fpm-lokal>`; kedua stage tetap wajib memenuhi pemeriksaan versi di atas.

## Menjalankan untuk pengembangan

Prasyarat: PHP 8.4, Composer, Node.js 22. Database default SQLite, tidak perlu server database.

```bash
composer run setup        # install dependency, buat .env, APP_KEY, migrasi, build asset
php artisan db:seed       # opsional: akun dan data contoh
composer run dev          # server, queue, Reverb, dan Vite sekaligus
```

Buka `http://localhost:9000` (samakan `APP_URL` di `.env`). Tanpa seeder, buat akun pertama dengan `php artisan sita:provision-initial-super-admin`.

## Akun contoh

Tersedia setelah seeding; semua memakai password `password`.

| Peran                  | Email                 |
| ---------------------- | --------------------- |
| Mahasiswa              | `mahasiswa@sita.test` |
| Dosen                  | `dosen@sita.test`     |
| Kaprodi                | `kaprodi@sita.test`   |
| Admin (panel `/admin`) | `admin@sita.test`     |

## Import pengguna dari Excel

Di panel admin, buka **Users > Unduh template Excel**, lalu pilih **Template Mahasiswa**, **Template Dosen** atau **Template Admin**. Setiap pilihan mengunduh file XLSX terpisah dengan kolom, satu contoh demo dan sheet Panduan sesuai peran. Ganti atau hapus baris contoh sebelum mengimpor; jangan ubah nama header. Format nomor HP, NIM dan NIK sebagai teks agar angka nol awal tetap ada.

Buka **Import Excel**, unggah file, pilih Tipe Import dan Program Studi tujuan. Kolom `role` menentukan peran setiap baris; Tipe Import hanya dipakai bila nilai `role` kosong. File lama yang mencampur peran sah tetap didukung. Email yang sudah ada diperbarui, termasuk password dan peran; hasil import menampilkan jumlah berhasil dan kegagalan per baris.

- Mahasiswa: isi NIM, angkatan, jenjang (`d3`, `s1`, `s2`) dan konsentrasi sesuai prodi tujuan. `kurikulum_obe` menerima `ya/tidak`, `1/0`, `true/false`; kosong berarti non-OBE.
- Dosen: isi NIK dan konsentrasi untuk prodi dropdown, atau `penempatan_dosen` berformat `Slug Prodi:Konsentrasi|Slug Prodi 2:Konsentrasi 2`, misalnya `ilkom:Jaringan|ti:Umum`. Nama prodi juga diterima, tetapi gunakan slug bila nama yang sama dipakai beberapa jenjang. Penempatan pertama menjadi prodi utama. Bidang keilmuan harus tersedia dan aktif; beberapa nilai dipisahkan `|`. Sesuaikan contoh penempatan dan bidang dengan data master.
- Admin: hanya kolom identitas akun; Program Studi diambil dari dialog import. Hanya super admin dapat mengimpor akun admin. Admin prodi tetap memakai cakupan prodinya.

## Notifikasi Web Push dan PWA

Web Push menjangkau perangkat yang sudah didaftarkan, tanpa tab SiTA harus tetap terbuka. Riwayat notifikasi dalam aplikasi dan pembaruan Reverb tetap berjalan. Email notifikasi dan WhatsApp tidak termasuk; email autentikasi tidak berubah.

Di **Pengaturan Akun > Notifikasi**, **Aktifkan notifikasi di perangkat ini** meminta izin, menghubungkan perangkat, lalu menyalakan pengiriman akun ke semua perangkat yang sudah terhubung. Dampak seluruh perangkat dijelaskan sebelum tombol ditekan. Sukses aktivasi baru ditampilkan setelah perangkat dan pengiriman akun terkonfirmasi. Sakelar **Kirim ke semua perangkat terhubung** menjeda atau melanjutkan pengiriman akun; perangkat yang terhubung saat sakelar mati berstatus **Terhubung, pengiriman dijeda**. Izin browser saja bukan langganan aktif. Setiap perangkat harus dihubungkan sendiri; **Matikan di perangkat ini** tidak mematikan perangkat lain. Logout mencabut langganan sesi itu, dan berganti akun tidak memindahkan langganan lama. Kegagalan aktivasi menampilkan tahap serta kode aman (nama error browser yang dibatasi atau status HTTP), tanpa endpoint atau kunci. Coba aktivasi lagi tanpa refresh; sesi yang kedaluwarsa tetap perlu login ulang. Akar kegagalan intermittent pada perangkat pengguna belum ditetapkan hanya dari regresi otomatis.

| Perangkat terhubung (1280px, terang)                                                                                                                                                       | Perangkat terhubung (390px, gelap)                                                                                                                                                      |
| ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ![Pengaturan Web Push desktop](https://raw.githubusercontent.com/RikiSanjayaa/sita/ab1e444ab898439f912ce3d4d1544765f924fc81/pr-149-notification-receipts-activation/active-1280-light.png) | ![Pengaturan Web Push ponsel](https://raw.githubusercontent.com/RikiSanjayaa/sita/ab1e444ab898439f912ce3d4d1544765f924fc81/pr-149-notification-receipts-activation/active-390-dark.png) |

Screenshot pengaturan memakai akun smoke demo pada patch review PR #149, bukan katalog CI di galeri pembuka. [Galeri state belum aktif, gagal, aktif, dijeda, dan chat](https://github.com/RikiSanjayaa/sita/tree/ab1e444ab898439f912ce3d4d1544765f924fc81/pr-149-notification-receipts-activation) tersedia pada 1280/390px, terang/gelap. Permission dan transport provider pada screenshot ini diemulasi; service worker, IndexedDB dan API nyata. Screenshot UI tidak membuktikan notifikasi OS atau menutup matriks perangkat #144.

### Mengaktifkan di server

Default `WEB_PUSH_ENABLED=false`. Gunakan HTTPS pada origin utama (instalasi subpath belum didukung oleh scope service worker `/`). Jalankan migrasi dan buat kunci VAPID **sekali**:

```bash
php artisan migrate --force
php artisan webpush:vapid --show
```

Simpan `VAPID_PUBLIC_KEY` dan `VAPID_PRIVATE_KEY` yang dihasilkan pada konfigurasi rahasia deployment; jangan commit atau mengganti pasangan kunci pada setiap deploy. Isi `VAPID_SUBJECT` dengan kontak pengelola seperti `mailto:admin@domain-kampus.ac.id`, lalu set `WEB_PUSH_ENABLED=true`. Kunci privat tidak pernah dikirim ke browser. Untuk Docker, jalankan perintah Artisan melalui service `app` dan simpan nilai pada `.env` server yang diterima semua service, bukan hanya di dalam container sementara. Untuk aaPanel gunakan PHP CLI site yang sama dengan aplikasi.

Untuk Docker, override deployment juga membaca `.env.webpush` bila file tersebut tersedia. Simpan `WEB_PUSH_ENABLED`, `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, dan `VAPID_PRIVATE_KEY` pada file privat di luar checkout (mode `0600`), lalu tautkan sebagai `.env.webpush` pada setiap checkout deployment. File ini mengesampingkan nilai push dari `.env` tanpa mengubah secret yang sudah ada. Jangan commit file/kunci tersebut atau membuat pasangan kunci baru saat mengganti image. Recreate service `app`, `queue`, `scheduler`, dan `reverb` agar semuanya menerima environment yang sama; restart proses saja tidak memuat ulang environment container.

```bash
php artisan config:cache
php artisan queue:restart
php artisan queue:work database --queue=default --tries=1
```

Worker database harus tetap dijalankan oleh pengelola proses; service `queue` bawaan Docker sudah memakai koneksi database dari template. Bila `QUEUE_CONNECTION` aplikasi diganti ke Redis atau koneksi lain, jalankan worker database tambahan untuk Web Push. Push selalu asynchronous pada koneksi database, sekalipun kanal lain memakai `sync`. `CACHE_STORE` produksi harus persisten dan dibagi semua worker (bawaan `database`), bukan `array`, agar penggabungan chat lintas request bekerja. Periksa `php artisan queue:failed` bila ada kegagalan; riwayat notifikasi dalam SiTA tetap tersedia.

Kirim kategori akademik langsung; chat digabung per penerima dan percakapan dalam jendela 5 detik. Worker memeriksa ulang preferensi, status baca, dan langganan sebelum mengirim. Payload layar kunci selalu generik, tanpa nama, isi chat, judul tugas akhir, atau identitas penguji. Pesan provider memiliki TTL 15 menit untuk chat dan 1 jam untuk akademik sejak dikirim ke provider; antrean yang sudah lebih tua dari batas kategori itu dibuang. TTL bukan bukti pesan tampil atau dibaca. Endpoint kedaluwarsa (404/410) dibersihkan channel. Endpoint baru dibatasi pada provider HTTPS Google FCM (`fcm.googleapis.com` dan host alternatif `jmt17.google.com`, keduanya harus cocok persis), Mozilla dan Apple; provider lain ditolak, bukan dipanggil sebagai URL bebas.

Manifest mendukung pemasangan PWA dan service worker menerima push serta membuka tujuan same-origin. Tidak ada cache halaman privat atau dukungan membuka aplikasi offline. Worker menjadi satu-satunya sumber notifikasi OS; saat jendela SiTA fokus, umpan balik tetap melalui notifikasi dalam aplikasi.

### Batas perangkat dan pemeriksaan sebelum produksi

- Chrome/Firefox desktop memerlukan browser atau penerima background yang aktif. Tab ditutup tidak sama dengan browser benar-benar berhenti; PWA tidak menjamin autostart saat boot.
- Safari macOS memiliki integrasi push sistem. Di iPhone/iPad, tambahkan SiTA ke Layar Utama dan buka dari ikon tersebut sebelum mengaktifkan izin (iOS/iPadOS 16.4 atau lebih baru).
- Perangkat mati/offline tidak menerima saat itu. Pengiriman saat online lagi bergantung pada TTL dan kebijakan provider. Penolakan izin diubah melalui pengaturan situs browser, bukan dengan meminta izin berulang-ulang dari aplikasi.
- Logout/opt-out menghentikan pengiriman berikutnya, tetapi pesan yang sudah diterima provider tidak dapat ditarik kembali. Worker memeriksa pemilik lokal sebelum menampilkan atau membuka pesan; tidak ada konten sensitif pada layar kunci.

Sebelum aktivasi produksi, isi bukti perangkat nyata pada [issue implementasi #144](https://github.com/RikiSanjayaa/sita/issues/144): OS/versi browser, izin dan langganan, waktu kirim/TTL, hasil tab ditutup dan browser background, browser berhenti, restart sebelum/sesudah browser dibuka, offline lalu online, serta iOS Home Screen. Test server, emulasi push, dan respons sukses provider tidak menggantikan bukti tampilan notifikasi OS. Batas platform dan sumber riset ada di [#28](https://github.com/RikiSanjayaa/sita/issues/28).

[Uji perangkat lokal](https://github.com/RikiSanjayaa/sita/pull/145#issuecomment-6399684839) membuktikan banner Firefox headed pada Linux saat jendela tidak fokus, tab SiTA ditutup, serta setelah profil penerima dibuka kembali. [Uji ulang audio](https://github.com/RikiSanjayaa/sita/pull/145#issuecomment-6405191291) dikonfirmasi terdengar oleh pengguna. Ini bukan bukti reboot OS, offline/online, Safari/iOS, atau pengiriman Chromium setelah koreksi host alternatif. Matriks produksi #144 tetap terbuka; jangan menyamakan merge kode yang default mati dengan persetujuan aktivasi produksi.

## Berkas pada detail ujian dosen

Detail Sempro/Sinopsis dan Sidang pada portal dosen menampilkan naskah dan lampiran yang dipilih mahasiswa untuk attempt tersebut. Versi unggahan terbaru tidak otomatis mengganti pilihan. Jenis, nama berkas, versi dan tanggal unggah ditampilkan bersama aksi Buka dan Unduh; tanpa pilihan, detail menyatakan belum ada berkas ujian.

Unduhan memakai snapshot `ThesisDocument`, bukan file workspace yang mungkin sudah berubah. Penguji hanya memperoleh akses melalui penugasan pada attempt yang sesuai; hak pembimbing, admin dan kaprodi tetap mengikuti cakupan masing-masing. Anotasi penguji yang belum diterbitkan tidak termasuk berkas ujian. Identitas penguji tambahan Sidang tetap dirahasiakan dari mahasiswa sampai ujian selesai.

Tombol chat membuka grup ujian yang sudah tersedia dan dapat diakses dosen. Jika tidak ada, tombol membuka chat pribadi mahasiswa atau pemilih kontak dengan mahasiswa tersebut sudah dipilih. Membuka detail tidak membuat grup atau chat baru.

Nama berkas membuka halaman detail snapshot yang dapat direload dan kembali ke attempt ujian yang sama. Jika dosen memiliki salinan review dari versi sumber tersebut, halaman menyediakan tautan ke review bimbingan miliknya. Persetujuan dokumen bimbingan tetap terpisah dari keputusan ujian; tidak ada tautan ke reviewer lain atau penggantian diam-diam ke versi terbaru.

## Detail dan review dokumen bimbingan

Daftar dokumen mahasiswa, dosen dan kaprodi, notifikasi, serta aksi Tinjau pada lampiran chat menuju halaman detail ber-URL tetap sesuai portal dan versi. Halaman menampilkan metadata, seluruh catatan tiap dosen dan riwayat versi. Akses tetap diperiksa server; kaprodi memerlukan capability lihat dokumen dalam prodinya, sedangkan buka/unduh memerlukan capability unduh terpisah.

Seluruh halaman detail dirapatkan: metadata pendek memakai dua kolom pada ponsel, judul dan nama berkas panjang tetap lebar penuh, editor catatan dan butir berdampingan pada desktop, serta aksi keputusan tetap di akhir form. Riwayat versi memakai satu baris untuk versi, nama berkas dan status. Tautan nama membuka versi yang tepat; panah di kanan membuka nama lengkap dan tanggal unggah, termasuk lewat keyboard. Pindah versi, reload dan kembali tidak beralih diam-diam ke unggahan terbaru, termasuk snapshot berkas ujian.

Dosen penerima mengisi catatan atau butir revisi langsung pada halaman. Minta revisi wajib disertai catatan atau butir; Setujui boleh tanpa catatan atau menyimpan masukan persetujuan. Setiap dosen mengubah salinannya sendiri. Navigasi keluar dari draft yang belum disimpan meminta konfirmasi; membatalkannya mempertahankan isi. Dosen tidak dapat mengubah review versi lama atau penugasan berakhir; mahasiswa tidak aktif, masa aktif habis dan data selesai/arsip tidak dapat menyelesaikan butir.

Tombol Minta revisi dan Setujui berada di akhir bagian Review Anda, setelah catatan dan editor butir. Dosen tidak perlu kembali ke header untuk menyimpan keputusan; tombol Unduh tetap di header.

Mahasiswa pemilik dapat menandai setiap butir revisi dosen selesai dan membukanya kembali bila keliru. Unggah pembaruan berkas tetap tanggung jawab mahasiswa, bukan syarat penyelesaian butir. Dosen melihat status dan waktu penyelesaian setiap butir; tidak ada langkah persetujuan tambahan. `Revisi selesai` berarti seluruh permintaan revisi pada versi tersebut selesai, bukan dokumen `approved` atau bukti berkas pengganti telah diunggah. Butir baru atau uraian yang diubah kembali terbuka; butir yang tidak berubah mempertahankan penyelesaiannya. Review dosen lain dan versi lain tidak ikut berubah. Hak review dosen dan pembacaan kaprodi tetap mengikuti scope sebelumnya; kartu Hanya baca dihapus tanpa melonggarkan akses.

Migrasi `add_revision_items_to_mentorship_documents_table` menyimpan butir pada tiap salinan review. Catatan revisi lama menjadi satu butir utuh, termasuk baris dan tanda bacanya; masukan persetujuan tidak menjadi permintaan revisi. Jalankan migrasi sebelum memakai fitur ini. Rollback migrasi menghapus data penyelesaian per butir; simpan backup sebelum rollback. Ini terpisah dari revisi ujian `ThesisRevisionItem`.

## Workspace percakapan

Grup bimbingan/ujian dan chat pribadi muncul dalam satu daftar dengan pencarian bersama di balik tombol Cari percakapan. Avatar baris menampilkan lawan chat pribadi atau dua anggota grup selain diri sendiri; label konteks akademik dan peserta membedakan percakapan. Urutan mengikuti aktivitas terakhir, dengan ID sebagai pembeda saat waktunya sama. Percakapan tugas akhir selesai tetap berada dalam bagian Selesai yang hanya dapat dibaca. Parameter `thread` membuka percakapan yang dituju tanpa perlu memilih tab jenis chat.

Desktop menampilkan daftar dan percakapan berdampingan tanpa divider atau gutter. Thread aktif memakai card netral beradius kecil; panel chat lebih gelap pada tema gelap, dengan profil dan nama di tengah header overlay yang memudar ke riwayat. Bubble dari pengirim dan menit yang sama dirapatkan. Pesan masuk hanya menggulir otomatis bila pengguna sudah berada di bawah; saat membaca riwayat, tombol Pesan terbaru mengembalikan ke bawah. Pencarian pesan tetap berada di atas riwayat tanpa menutupi hasil. Pada ponsel hanya daftar atau percakapan yang tampil; tombol kembali membuka daftar. Pengiriman, lampiran, receipt, unread dan batas akses tetap memakai alur yang sama.

Header percakapan menyatukan avatar anggota berwenang, nama dan jenis percakapan dalam pill setinggi 44px. Pada grup, maksimal dua avatar tampil bertumpuk, termasuk pengguna sendiri, lalu `+N` untuk sisanya. Pada chat pribadi, avatar, jumlah dan daftar anggota hanya menampilkan lawan bicara. Hover, klik atau keyboard membuka daftar anggota tepat di bawah tengah pill; setiap anggota memiliki tautan profil. Jenis percakapan tidak diulang bila sama dengan namanya. Pesan berurutan dari pengirim dan menit yang sama berbagi ruang avatar; pada ponsel lebar bubble masuk/keluar tetap seimbang.

Shell portal memakai header ringkas. Tabel, kalender dan halaman detail tetap mengikuti alur scroll halaman; toast berada di bawah header, dengan tombol tutup yang tetap terjangkau pada ponsel.

Feedback simpan memakai flash sesi sampai respons halaman berikutnya. `StartSessionWithoutBackgroundWrites` tetap memuat sesi untuk autentikasi, tetapi tidak menyimpan snapshot dari `GET /chat/unread` dan autentikasi broadcasting yang hanya membaca sesi. Keduanya tidak boleh menghabiskan flash atau menimpa perubahan dari request simpan yang berjalan bersamaan. Respons halaman tetap mengonsumsi flash sekali; notifikasi realtime dan status autentikasi tidak dialihkan ke jalur ini.

Sesi baru atau ID sesi yang berubah, termasuk pemulihan login dari cookie Remember me, tetap disimpan. Panel Filament memakai middleware sesi yang sama dengan grup web agar request Livewire tidak menjalankan dua middleware sesi berbeda. Tidak ada migrasi atau perubahan driver sesi; rollback kode mengembalikan perilaku lama.

Peringatan "Tampilan SiTA paling nyaman di laptop atau komputer" tidak ditampilkan di halaman Pesan mahasiswa/dosen, termasuk lewat tautan langsung dan navigasi dalam aplikasi. Chat memakai tinggi layar penuh tanpa ruang bekas peringatan; kunjungan ke chat tidak menutup peringatan pada halaman lain.

Pada Mahasiswa Dosen, dropdown Chat memuat chat grup, pribadi dan WhatsApp bila kontak tersedia; WhatsApp tidak lagi berada pada Aksi lainnya. Jumlah tampil sebagai `x bimbingan` dalam informasi mahasiswa, tanpa kolom berulang atau perubahan aturan minimum. Perbarui profil menandai nomor HP dengan badge WhatsApp, tanpa tautan chat ke nomor sendiri; edit, konfirmasi dan salin nomor tetap tersedia.

## Indikator pesan belum dibaca

Menu Pesan pada portal mahasiswa, dosen, dan kaprodi menampilkan jumlah pesan dari orang lain yang belum dibaca dalam percakapan yang dapat diakses, bukan jumlah percakapan. Badge merah memakai warna yang sama dengan lonceng: tepat setelah label pada sidebar/drawer, atau menempel ikon saat sidebar diciutkan. Nilai di atas 99 ditampilkan sebagai `99+`; label aksesibel tetap menyebut jumlah lengkap. Nilai nol tidak menampilkan badge. Pesan sendiri dan event dokumen sistem tidak dihitung. Membuka daftar percakapan saja tidak mengurangi jumlah; receipt disimpan untuk pesan terakhir yang benar-benar tampil pada percakapan aktif, saat tab terlihat dan jendela fokus. Jendela yang terlihat tetapi tidak fokus tidak memajukan receipt atau membaca notifikasi chat secara otomatis. Saat fokus kembali, hanya notifikasi untuk percakapan yang sedang terbuka ditandai dibaca; notifikasi percakapan lain dan kategori akademik tetap terpisah. Lonceng menghitung notifikasi global, bukan hanya chat.

Notifikasi pesan pada lonceng dibaca melalui acknowledgement receipt percakapan yang benar-benar terlihat dan fokus, termasuk saat memilih thread tanpa reload. URL berparameter `thread` saja tidak cukup: kembali fokus pada daftar percakapan mobile tidak membaca panel chat yang tersembunyi. Pembacaan server mencakup seluruh notifikasi `pesanBaru` dengan identitas thread/pesan sampai watermark yang terlihat, bukan hanya 15 item header. Notifikasi pesan yang lebih baru, thread/akun lain, dan pembaruan akademik tetap belum dibaca. Notifikasi lama tanpa identitas pesan hanya dicocokkan lewat URL bertarget dan waktu yang aman; URL tanpa thread tidak ditebak.

`MentorshipChatRead` menjadi sumber status baca. `last_read_message_id` membatasi pesan yang sudah terlihat, termasuk ketika dua pesan dikirim pada detik yang sama; receipt lama tanpa ID tetap memakai `last_read_at`. Jalankan migrasi sebelum memakai kode baru. `GET /chat/unread` mengembalikan `{ "unreadMessageCount": 3 }` (jumlah sebenarnya, tidak dibatasi 99), dan channel privat `chat.unread.{userId}` hanya mengirim invalidasi tanpa identitas percakapan atau penguji. Muat awal, navigasi, reload, kembali fokus, koneksi ulang Reverb, dan perubahan receipt menyinkronkan jumlah dari server.

Respons POST baca membawa snapshot jumlah pesan tersisa pada thread, total sidebar untuk akun/peran aktif, dan `readNotificationIds` yang sudah dibaca server. UI menerapkannya begitu receipt berhasil disimpan, tanpa menunggu GET penghitung berikutnya. Respons GET yang dimulai sebelum acknowledgement baca tidak boleh mengembalikan badge lama. Pesan yang datang setelah batas ID yang terlihat tetap belum dibaca, dan membaca satu thread tidak mengosongkan jumlah dari thread lain.

Pengiriman teks/lampiran mengembalikan HTTP 201 JSON berisi `threadId`, `clientMessageId`, dan `messages` (ID serta payload pesan yang benar-benar tersimpan), tanpa redirect atau render ulang halaman/daftar penerima. Setiap kiriman memakai UUID `client_message_id`; HTTP dan Echo direkonsiliasi dengan UUID tersebut, lalu dideduplikasi berdasarkan ID pesan, bukan isi teks. Pending bubble hilang ketika Echo mengonfirmasi kiriman atau respons HTTP diterima. Kegagalan validasi/jaringan mempertahankan draft dan lampiran untuk dicoba lagi. Penggabungan Web Push menunggu 5 detik dan tetap memeriksa unread ketika job berjalan.

## Direktori dan aktivitas mahasiswa

Halaman **Progress Seluruh Mahasiswa** (`/progres-mahasiswa`) adalah daftar lintas mahasiswa untuk seluruh peran login, bukan progres pribadi. Mahasiswa melihat barisnya berpenanda Anda paling atas pada halaman pertama, termasuk bila belum mempunyai tugas akhir. Prioritas diterapkan sebelum pagination dan hanya di dalam hasil pencarian/filter yang memenuhi cakupan; reset mengembalikannya. Urutan mahasiswa lain tetap berdasarkan progres terbaru, nama lalu ID profil. Urutan non-mahasiswa, jumlah hasil, ukuran halaman dan aturan kerahasiaan penguji Sidang tidak berubah.

Super admin memakai navigasi Portal Admin yang sama saat membuka halaman bersama ini. Tipe peran frontend mencakup `super_admin` dari server agar halaman tidak kosong karena konfigurasi navigasi yang hilang.

Direktori publik dan portal non-admin memakai tugas akhir terkini per mahasiswa, berdasarkan `started_at` lalu ID; tanggal mulai kosong berada paling akhir. Tugas akhir cancelled atau yang sudah digantikan tidak muncul sebagai tugas akhir berjalan, tetapi tetap tersimpan dan dapat dibuka di admin. Pembatalan tugas akhir terbaru tidak mengaktifkan kembali data lama. Publikasi tugas akhir completed yang belum digantikan tetap mengikuti aturan visibilitas judul. Perubahan judul pada tugas akhir yang sama tetap menyimpan riwayat versinya.

Mahasiswa tetap menerima status dan alasan penolakan pengajuan terakhir miliknya agar dapat mengajukan ulang. Ini bukan akses ke daftar arsip: setelah ada tugas akhir baru, pengajuan lama tidak dipakai kembali sebagai tugas akhir berjalan.

Profil dosen menampilkan mahasiswa pengujian pada tugas akhir aktif terkini dengan ujian draft, terjadwal, atau menunggu finalisasi. Ujian selesai/cancelled dan tugas akhir lulus tidak menumpuk di daftar aktif; mahasiswa yang juga dibimbing tidak ditampilkan dua kali.

Satu definisi aktivitas terakhir mahasiswa (`StudentActivityService`) dipakai di daftar Mahasiswa Dosen, daftar Mahasiswa Prodi kaprodi, kartu mahasiswa di profil, dan halaman Progress Seluruh Mahasiswa. Nilainya waktu paling baru dari dokumen terkait, pesan mahasiswa pada grup terkait atau chat pribadi dengan salah satu pembimbing/penguji tugas akhir itu, dan bimbingan yang dikonfirmasi selesai oleh dosen. Pesan dosen/sistem, chat pribadi dengan dosen di luar tugas akhir itu, serta permintaan atau penjadwalan bimbingan yang belum selesai tidak dihitung. Data bimbingan lama yang belum memiliki ID tugas akhir dibatasi menggunakan masa tugas akhir dan penugasan pembimbing. Baris riwayat dosen (pembimbing sudah berakhir) hanya menampilkan aktivitas selama dosen itu terkait dengan mahasiswa. Nilai itu ditampilkan sebagai "Terakhir aktif 3 hari yang lalu", atau "Belum ada aktivitas" bila belum ada; tidak ada tag "Tidak aktif" dan tidak ada ambang hari.

## Arsip tugas akhir setelah lulus

Tugas akhir `completed` yang melewati masa tenggang (`SITA_ARCHIVE_GRACE_DAYS`, bawaan 30 hari sejak `completed_at`) diarsipkan oleh `php artisan sita:archive-completed-theses`. Yang disimpan: data tugas akhir, judul, pembimbing, penguji, hasil dan nilai ujian, revisi, event, dan naskah akhir versi terakhir. Yang **dihapus permanen**: chat mahasiswa (pembimbing, sempro, sidang, pribadi dengan dosen) beserta lampirannya, jadwal bimbingan, dokumen draft, dan dokumen tugas akhir selain naskah akhir terakhir. Sebelum menghapus, command menyimpan ringkasan di `thesis_projects.archive_summary` (bimbingan selesai per pembimbing, jadwal per status, jumlah dokumen dan pesan, aktivitas terakhir) yang kemudian dibaca `MentorshipProgressService` dan `StudentActivityService`, sehingga angka mahasiswa terarsip tidak berubah.

- `--dry-run` mencetak persis apa yang akan dihapus (baris, file, dan file yang dipertahankan karena masih dipakai baris lain) tanpa mengubah apa pun. Jalankan ini lebih dulu.
- Satu tugas akhir per transaksi; file dihapus setelah commit dan hanya bila tidak ada baris lain yang mengacunya. Menjalankan ulang aman (idempoten). Tugas akhir `cancelled` dan `active` tidak pernah disentuh. Bila mahasiswa punya tugas akhir lain, hanya baris dalam rentang waktu tugas akhir ini yang dihapus.
- Selama masa tenggang chat tugas akhir selesai tampil di bagian "Selesai" yang read-only.
- Jadwal harian di `routes/console.php` hanya terdaftar bila `SITA_ARCHIVE_ENABLED=true` (bawaan `false`). Penghapusan tidak bisa dibatalkan: **jangan aktifkan di produksi sebelum backup database dikonfirmasi**.

## Pengujian dan kualitas kode

| Perintah                                          | Fungsi                                                                       |
| ------------------------------------------------- | ---------------------------------------------------------------------------- |
| `php artisan test --compact`                      | Test Pest (backend)                                                          |
| `npm run e2e`                                     | Test Playwright end-to-end lintas peran (`e2e:headed`, `e2e:ui` untuk debug) |
| `vendor/bin/pint --dirty`                         | Format PHP                                                                   |
| `npm run lint`, `npm run types`, `npm run format` | Lint, cek tipe, dan format frontend                                          |
| `npm run hooks:install`                           | Pasang git hook yang menjalankan test sebelum push                           |

Playwright memakai database SQLite terpisah (`database/playwright.sqlite`) yang di-reset dan di-seed otomatis, jadi data lokal tidak tersentuh. Install browser sekali dengan `npx playwright install`.

## Deployment

- **Docker**: setiap push ke `main` yang lolos test dipublikasikan sebagai image `ghcr.io/rikisanjayaa/sita-app` dan `ghcr.io/rikisanjayaa/sita-web` (tag `main` dan `sha-<commit>`; tag git `vX.Y.Z` menghasilkan `X.Y.Z` dan `latest`). Di server, `bash scripts/deploy-via-compose.sh` menarik image itu, menjalankan migrasi, me-restart service, lalu healthcheck (`HEALTHCHECK_URL` opsional). Pin versi lewat `APP_IMAGE` dan `WEB_IMAGE`. Image tidak membakar konfigurasi Reverb: satu image yang sama berjalan di server mana pun, dan key/host/port/scheme Reverb untuk browser diambil dari `REVERB_*` di `.env` server saat runtime.
- **aaPanel**: lihat [docs/AAPANEL_DEPLOYMENT.md](docs/AAPANEL_DEPLOYMENT.md).

## Troubleshooting

- **Layar kosong dengan error `You must pass your app key when you instantiate Pusher`**: isi `REVERB_APP_KEY`, `REVERB_HOST`, `REVERB_PORT`, dan `REVERB_SCHEME` di `.env` server, lalu `php artisan config:clear` (tidak perlu build ulang asset).
- **Perubahan frontend tidak muncul**: jalankan `npm run dev` atau `npm run build`.
- **Chat tidak real-time**: pastikan proses Reverb berjalan (sudah termasuk di `composer run dev`).

## Berkontribusi

Pekerjaan direncanakan lewat GitHub Issues; perubahan masuk lewat pull request ke `main` dengan pesan commit [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `docs:`, ...). Panduan untuk coding agent ada di [AGENTS.md](AGENTS.md).

## Rilis

Versi mengikuti [Semantic Versioning](https://semver.org/). [release-please](https://github.com/googleapis/release-please) menjaga satu PR rilis yang berisi versi berikutnya dan isi [CHANGELOG.md](CHANGELOG.md): `fix` menaikkan patch, `feat` menaikkan minor, perubahan breaking menaikkan major. Saat PR itu di-merge, tag `vX.Y.Z`, GitHub Release, dan image `X.Y.Z` serta `latest` dibuat otomatis.

## Lisensi

Dirilis di bawah [MIT License](LICENSE). Hak cipta (c) 2026 Universitas Bumigora.
