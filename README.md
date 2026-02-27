# SITA

SITA adalah aplikasi manajemen bimbingan tugas akhir berbasis Laravel + Inertia + React.
Aplikasi ini mendukung alur kerja berbasis peran untuk mahasiswa, dosen, dan admin,
termasuk chat real-time, pembaruan jadwal, dan notifikasi dalam aplikasi.

## Tumpukan Teknologi

- Backend: Laravel 12, PHP 8.4, Pest 4
- Frontend: Inertia v2, React 19, TypeScript, Tailwind CSS v4
- Real-time: Laravel Echo + Laravel Reverb
- Tooling: Vite, ESLint, Prettier, Pint

## Prasyarat

- PHP 8.4+
- Composer
- Node.js 20+ dan npm
- MySQL/PostgreSQL (sesuai konfigurasi di `.env`)

## Menjalankan Dengan Docker Compose (Production-like)

Stack Docker sudah disiapkan agar langsung jalan dengan sekali perintah, termasuk:

- `db` (PostgreSQL)
- `init` (migrate + cache optimize)
- `app` (PHP-FPM)
- `web` (Nginx)
- `queue` (queue worker)
- `scheduler` (Laravel scheduler)
- `reverb` (WebSocket server)

Jalankan:

```bash
docker compose up --build -d
```

Layanan utama:

- App HTTP: `http://localhost`
- Reverb WS: `ws://localhost:8089`

Catatan penting:

- Konfigurasi container memakai `.env.docker`.
- Nilai `VITE_REVERB_*` di `.env.docker` ikut dipakai saat image build, jadi frontend tidak kehilangan app key Pusher/Reverb.
- Seeder bisa diaktifkan tanpa mengubah `APP_ENV`: set `RUN_DB_SEED=true` di `.env.docker` (akan dijalankan saat service `init`).
- Default `SEED_IF_EMPTY_ONLY=true` agar `docker compose up --build -d` tidak gagal saat data sudah ada; set `false` kalau memang ingin memaksa re-seed.
- Jika seeder memakai Faker/factory dev dependency, biarkan `INIT_INSTALL_DEV_DEPENDENCIES=true` agar hanya service `init` yang install dev dependency; service runtime tetap lean.
- Untuk environment server/CI, override nilai sensitif (`APP_KEY`, password DB, dsb) lewat secret/variable pipeline.
- Saat pertama kali naik, service `init` akan otomatis menjalankan migrasi dan cache (`config:cache`, `route:cache`, `view:cache`).

Matikan stack:

```bash
docker compose down
```

Hapus volume data DB + storage:

```bash
docker compose down -v
```

## Mulai Cepat

1. Install dependensi

```bash
composer install
npm install
```

2. Buat file environment dan app key

```bash
cp .env.example .env
php artisan key:generate
```

3. Konfigurasi database dan env real-time di `.env`

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=local-app
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

4. Jalankan migrasi

```bash
php artisan migrate
```

5. Jalankan layanan development

```bash
composer run dev
```

## Perintah Umum

- Menjalankan stack dev: `composer run dev`
- Build frontend: `npm run build`
- Lint/format PHP: `composer run lint` atau `vendor/bin/pint --dirty`
- Lint JS/TS: `npm run lint`
- Type check: `npm run types`
- Menjalankan semua test: `composer run test`
- Menjalankan test spesifik: `php artisan test --compact tests/Feature/...`
- Verifikasi CI lokal: `composer run ci:verify`

## Git Hook (Verifikasi CI sebelum Push)

Install pre-push hook lokal sekali saja:

```bash
npm run hooks:install
```

Perintah ini memasang `.githooks/pre-push` ke `.git/hooks/pre-push`
dan menjalankan `composer run ci:verify` sebelum setiap push.

## Notifikasi

- Preferensi notifikasi dikelola di `Settings > Notifikasi`.
- Notifikasi dalam aplikasi disimpan di database dan ditampilkan di panel header.
- Notifikasi browser membutuhkan izin browser dan opsi `browserNotifications` aktif.

## Struktur Proyek

- `app/` - kode utama Laravel (controller, model, service, notification)
- `routes/` - route HTTP dan channel broadcast
- `database/` - migration, seeder, factory
- `resources/js/` - halaman/komponen/type Inertia React
- `tests/` - test Feature/Unit berbasis Pest

## Troubleshooting

- Layar blank dengan error `You must pass your app key when you instantiate Pusher.`
    - Pastikan `VITE_REVERB_APP_KEY` dan variabel `VITE_REVERB_*` sudah terisi.
    - Bersihkan cache konfigurasi: `php artisan config:clear`.
    - Jalankan ulang proses frontend: `npm run dev` atau `npm run build`.

- Perubahan frontend tidak muncul
    - Jalankan ulang Vite dengan `npm run dev`.

## CI/CD (GitHub Actions + Tailscale)

Workflow deploy ada di `.github/workflows/deploy.yml` dengan alur:

1. Verify (`composer install`, `npm ci`, `npm run build`, `php artisan test`, `npm run types`)
2. Build + push image ke GHCR (`app` dan `web`)
3. Join Tailscale dari GitHub Actions
4. SSH ke server private, pull image terbaru, jalankan `scripts/deploy-via-compose.sh`

### File deploy penting

- `docker-compose.deploy.yml`: override agar service pakai image dari registry (bukan build lokal)
- `scripts/deploy-via-compose.sh`: pull image, jalankan init (migrate/cache), restart service, healthcheck

### GitHub Secrets yang wajib

- `TS_OAUTH_CLIENT_ID`
- `TS_OAUTH_SECRET`
- `DEPLOY_HOST` (hostname/IP Tailscale target)
- `DEPLOY_USER`
- `DEPLOY_PATH` (path repo di server)
- `DEPLOY_SSH_PRIVATE_KEY`
- `GHCR_USERNAME`
- `GHCR_READ_TOKEN` (token read:packages untuk pull di server)
- `VITE_REVERB_APP_KEY`

### GitHub Variables yang disarankan

- `FRONTEND_REVERB_HOST` (default: `localhost`)
- `FRONTEND_REVERB_PORT` (default: `8089`)
- `VITE_REVERB_SCHEME` (default: `http`)
- `DEPLOY_HEALTHCHECK_URL` (contoh: `http://localhost:8088/up`)

### Bootstrap server (sekali saja)

Di server homeserver:

1. Clone repo ke `DEPLOY_PATH`
2. Siapkan `.env.docker` production values
3. Login GHCR sekali untuk validasi (`docker login ghcr.io`)
4. Pastikan Docker + Compose plugin aktif

Setelah itu deploy feature cukup push ke `main` (tanpa SSH manual).
