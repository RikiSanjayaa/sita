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
