# SiTA

SiTA (Sistem Informasi Tugas Akhir) Universitas Bumigora mencatat perjalanan tugas akhir mahasiswa dari pengajuan judul sampai lulus: bimbingan, dokumen, seminar proposal, sidang, dan revisi. Mahasiswa, dosen, kaprodi, dan admin bekerja di satu aplikasi dengan chat dan notifikasi real-time.

| Portal publik                                                       | Dashboard mahasiswa                                              |
| ------------------------------------------------------------------- | ---------------------------------------------------------------- |
| ![Portal publik](docs/screenshots/publik-beranda.png)               | ![Dashboard mahasiswa](docs/screenshots/mahasiswa-dashboard.png) |
| **Chat bimbingan, sempro, dan sidang**                              | **Dashboard dosen**                                              |
| ![Chat](docs/screenshots/mahasiswa-pesan.png)                       | ![Dashboard dosen](docs/screenshots/dosen-dashboard.png)         |
| **Pemantauan mahasiswa oleh kaprodi**                               | **Panel admin**                                                  |
| ![Daftar mahasiswa kaprodi](docs/screenshots/kaprodi-mahasiswa.png) | ![Panel admin](docs/screenshots/admin-dashboard.png)             |

Tangkapan layar memakai data contoh dari seeder.

## Fitur

| Peran     | Yang bisa dilakukan                                                                                                                                        |
| --------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Mahasiswa | Mengajukan judul dan naskah proposal, mengajukan jadwal bimbingan, mengunggah dokumen, chat dengan pembimbing/penguji, memantau sempro, sidang, dan revisi |
| Dosen     | Mengelola permintaan bimbingan, me-review dokumen, menilai ujian, memverifikasi revisi                                                                     |
| Kaprodi   | Memantau mahasiswa prodi, menetapkan pembimbing, menjadwalkan sempro dan sidang, mengatur kuota dosen                                                      |
| Admin     | Mengelola pengguna (termasuk import Excel), prodi, data tugas akhir, pengumuman, dan audit sistem lewat panel Filament                                     |

Halaman publik (`/jadwal`, `/topik`, `/pembimbing`, `/mahasiswa-aktif`) bisa dibuka tanpa login.

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

Aplikasi berjalan di `http://localhost:8088`. Secara default dipakai image `main` (build terbaru); untuk versi rilis tertentu set `APP_IMAGE=ghcr.io/rikisanjayaa/sita-app:1.0.0` dan `WEB_IMAGE=ghcr.io/rikisanjayaa/sita-web:1.0.0` (atau tag `latest`). Matikan dengan `docker compose -f docker-compose.yml -f docker-compose.deploy.yml down` (tambah `-v` untuk menghapus data).

> Image GHCR dibangun untuk server produksi berbasis HTTPS, sehingga chat real-time tidak tersambung saat dicoba di `http://localhost`. Halaman tetap berfungsi; pesan baru muncul setelah halaman dimuat ulang. Untuk real-time di lokal, build image sendiri (lihat di bawah). Perbaikannya dilacak di [#42](https://github.com/RikiSanjayaa/sita/issues/42).

### Build image sendiri

Dari hasil clone repo, stack yang sama bisa di-build dari source:

```bash
cp .env.docker .env       # isi APP_KEY seperti di atas; atur RUN_DB_SEED sesuai pilihan A/B
docker compose build --pull
docker compose up -d --no-build
```

Konfigurasi Reverb untuk browser dibaca saat runtime dari `REVERB_APP_KEY`, `REVERB_HOST`, `REVERB_PORT`, dan `REVERB_SCHEME` (default lokal `ws://localhost:8089`); alamat antar-container ada di `REVERB_INTERNAL_*`.

Image PHP dipatok ke 8.4.27 atau rilis lebih baru pada cabang 8.4 untuk perbaikan CVE-2026-109618 dan CVE-2026-109619. Build memeriksa versi PHP pada stage asset dan runtime; `scripts/deploy-via-compose.sh` juga menolak image lama sebelum menjalankan init. Setelah pembaruan image, buat ulang `app`, `queue`, `scheduler`, dan `reverb`; restart container lama saja tidak mengganti PHP. Pada aaPanel, perbarui PHP-FPM site dan PHP CLI ke versi yang sudah diperbaiki, lalu restart seluruh worker terkait. Tag `main`/`latest` dan waktu build bukan bukti versi PHP; periksa binary yang benar-benar melayani aplikasi.

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

## Indikator pesan belum dibaca

Menu Pesan pada portal mahasiswa, dosen, dan kaprodi menampilkan jumlah pesan dari orang lain yang belum dibaca dalam percakapan yang dapat diakses, bukan jumlah percakapan. Badge merah memakai warna yang sama dengan lonceng: tepat setelah label pada sidebar/drawer, atau menempel ikon saat sidebar diciutkan. Nilai di atas 99 ditampilkan sebagai `99+`; label aksesibel tetap menyebut jumlah lengkap. Nilai nol tidak menampilkan badge. Pesan sendiri dan event dokumen sistem tidak dihitung. Membuka daftar percakapan saja tidak mengurangi jumlah; receipt disimpan untuk pesan terakhir yang benar-benar tampil pada percakapan aktif, saat tab terlihat. Lonceng menghitung notifikasi global, termasuk pengumuman dan aktivitas akademik, bukan hanya chat. Membaca chat tidak menandai notifikasi lonceng sebagai dibaca, dan sebaliknya.

`MentorshipChatRead` menjadi sumber status baca. `last_read_message_id` membatasi pesan yang sudah terlihat, termasuk ketika dua pesan dikirim pada detik yang sama; receipt lama tanpa ID tetap memakai `last_read_at`. Jalankan migrasi sebelum memakai kode baru. `GET /chat/unread` mengembalikan `{ "unreadMessageCount": 3 }` (jumlah sebenarnya, tidak dibatasi 99), dan channel privat `chat.unread.{userId}` hanya mengirim invalidasi tanpa identitas percakapan atau penguji. Muat awal, navigasi, reload, kembali fokus, koneksi ulang Reverb, dan perubahan receipt menyinkronkan jumlah dari server.

## Direktori dan aktivitas mahasiswa

Direktori publik dan portal non-admin memakai tugas akhir terkini per mahasiswa, berdasarkan `started_at` lalu ID; tanggal mulai kosong berada paling akhir. Tugas akhir cancelled atau yang sudah digantikan tidak muncul sebagai tugas akhir berjalan, tetapi tetap tersimpan dan dapat dibuka di admin. Pembatalan tugas akhir terbaru tidak mengaktifkan kembali data lama. Publikasi tugas akhir completed yang belum digantikan tetap mengikuti aturan visibilitas judul. Perubahan judul pada tugas akhir yang sama tetap menyimpan riwayat versinya.

Mahasiswa tetap menerima status dan alasan penolakan pengajuan terakhir miliknya agar dapat mengajukan ulang. Ini bukan akses ke daftar arsip: setelah ada tugas akhir baru, pengajuan lama tidak dipakai kembali sebagai tugas akhir berjalan.

Profil dosen menampilkan mahasiswa pengujian pada tugas akhir aktif terkini dengan ujian draft, terjadwal, atau menunggu finalisasi. Ujian selesai/cancelled dan tugas akhir lulus tidak menumpuk di daftar aktif; mahasiswa yang juga dibimbing tidak ditampilkan dua kali.

Satu definisi aktivitas terakhir mahasiswa (`StudentActivityService`) dipakai di daftar Mahasiswa Dosen, daftar Mahasiswa Prodi kaprodi, kartu mahasiswa di profil, dan halaman Progres Mahasiswa. Nilainya waktu paling baru dari dokumen terkait, pesan mahasiswa pada grup terkait atau chat pribadi dengan salah satu pembimbing/penguji tugas akhir itu, dan bimbingan yang dikonfirmasi selesai oleh dosen. Pesan dosen/sistem, chat pribadi dengan dosen di luar tugas akhir itu, serta permintaan atau penjadwalan bimbingan yang belum selesai tidak dihitung. Data bimbingan lama yang belum memiliki ID tugas akhir dibatasi menggunakan masa tugas akhir dan penugasan pembimbing. Baris riwayat dosen (pembimbing sudah berakhir) hanya menampilkan aktivitas selama dosen itu terkait dengan mahasiswa. Nilai itu ditampilkan sebagai "Terakhir aktif 3 hari yang lalu", atau "Belum ada aktivitas" bila belum ada; tidak ada tag "Tidak aktif" dan tidak ada ambang hari.

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
