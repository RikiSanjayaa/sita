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

## Menjalankan secara lokal

Prasyarat: PHP 8.4, Composer, Node.js 22. Database default SQLite, tidak perlu server database.

```bash
composer run setup        # install dependency, buat .env, APP_KEY, migrasi, build asset
php artisan db:seed       # opsional: akun dan data contoh
composer run dev          # server, queue, Reverb, dan Vite sekaligus
```

Buka `http://localhost:9000` (samakan `APP_URL` di `.env`). Setelah seeding, semua akun contoh memakai password `password`:

| Peran                  | Email                 |
| ---------------------- | --------------------- |
| Mahasiswa              | `mahasiswa@sita.test` |
| Dosen                  | `dosen@sita.test`     |
| Kaprodi                | `kaprodi@sita.test`   |
| Admin (panel `/admin`) | `admin@sita.test`     |

## Menjalankan dengan Docker

Stack lengkap (PostgreSQL, PHP-FPM, Nginx, queue, scheduler, Reverb) tersedia di `docker-compose.yml`:

```bash
cp .env.docker .env       # isi APP_KEY: php artisan key:generate --show
docker compose up --build -d
```

Aplikasi berjalan di `http://localhost:8088` dan Reverb di `ws://localhost:8089`. Seeder tidak dijalankan secara default; set `RUN_DB_SEED=true` di `.env` untuk mengisi data contoh. Matikan dengan `docker compose down` (tambah `-v` untuk menghapus data).

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

- **Docker**: setiap push ke `main` yang lolos test dipublikasikan sebagai image `ghcr.io/rikisanjayaa/sita-app` dan `ghcr.io/rikisanjayaa/sita-web` (tag `main` dan `sha-<commit>`; tag git `vX.Y.Z` menghasilkan `X.Y.Z` dan `latest`). Di server, `bash scripts/deploy-via-compose.sh` menarik image itu, menjalankan migrasi, me-restart service, lalu healthcheck (`HEALTHCHECK_URL` opsional). Pin versi lewat `APP_IMAGE` dan `WEB_IMAGE`. Image dibangun dengan `VITE_REVERB_APP_KEY` dari GitHub variable, jadi `REVERB_APP_KEY` di server harus sama.
- **aaPanel**: lihat [docs/AAPANEL_DEPLOYMENT.md](docs/AAPANEL_DEPLOYMENT.md).
- Database baru tanpa seeder: buat akun super admin pertama dengan `php artisan sita:provision-initial-super-admin`.

## Troubleshooting

- **Layar kosong dengan error `You must pass your app key when you instantiate Pusher`**: isi variabel `VITE_REVERB_*` di `.env`, lalu `php artisan config:clear` dan build ulang asset (`npm run build` atau `npm run dev`).
- **Perubahan frontend tidak muncul**: jalankan `npm run dev` atau `npm run build`.
- **Chat tidak real-time**: pastikan proses Reverb berjalan (sudah termasuk di `composer run dev`).

## Berkontribusi

Pekerjaan direncanakan lewat GitHub Issues; perubahan masuk lewat pull request ke `main`. Panduan untuk coding agent ada di [AGENTS.md](AGENTS.md).

## Lisensi

Dirilis di bawah [MIT License](LICENSE). Hak cipta (c) 2026 Universitas Bumigora.
