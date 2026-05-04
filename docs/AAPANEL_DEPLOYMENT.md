# Deployment SITA di aaPanel

Dokumen ini menjelaskan deployment SITA untuk aaPanel Free, Nginx, PHP-FPM, MariaDB/MySQL, dan Node.js untuk build asset.

## Ringkasan Audit

- Backend: Laravel 12, PHP minimal 8.2, Fortify untuk auth, Inertia Laravel.
- Frontend: React 19, Inertia React, Vite 7, Tailwind CSS v4.
- Build frontend: `npm ci` lalu `npm run build`.
- Runtime backend: Nginx mengarah ke folder `public/`, PHP-FPM menjalankan `public/index.php`.
- Database lokal saat audit: SQLite. Production direkomendasikan MariaDB/MySQL dari aaPanel.
- Queue/cache/session: default repo memakai driver `database`, sehingga tabel `jobs`, `cache`, dan `sessions` harus dimigrasikan.
- Storage: `storage/` dan `bootstrap/cache/` wajib writable oleh user PHP-FPM. Jalankan `php artisan storage:link`.
- Scheduler: belum ada schedule khusus selain command contoh Laravel. Cron scheduler tetap disiapkan agar aman saat fitur bertambah.
- Email: Fortify reset password dan verifikasi email butuh SMTP production.
- SSR: repo punya entry SSR, tetapi production aaPanel dibuat default `INERTIA_SSR_ENABLED=false` agar tidak membutuhkan proses Node runtime.
- Dependency eksternal: font dari `fonts.bunny.net`. Aplikasi tetap punya fallback system font jika akses internet server/client dibatasi.

## Arsitektur yang Direkomendasikan

Gunakan satu subdomain, misalnya:

```text
https://sita.kampus.ac.id
```

Struktur folder:

```text
/www/wwwroot/sita.kampus.ac.id
├── app
├── bootstrap
├── config
├── database
├── deploy
├── docs
├── public
├── resources
├── routes
├── storage
├── vendor
├── .env
├── artisan
├── composer.json
├── package.json
└── package-lock.json
```

Document root aaPanel harus diarahkan ke:

```text
/www/wwwroot/sita.kampus.ac.id/public
```

Frontend dan backend tidak perlu dipisah subdomain karena Inertia menyajikan React dari Laravel yang sama.

## Requirement Server

- aaPanel Free dengan Nginx.
- PHP 8.2 atau lebih baru. Rekomendasi PHP 8.2/8.3 yang stabil di server kampus.
- Extension PHP: `ctype`, `dom`, `fileinfo`, `filter`, `json`, `mbstring`, `openssl`, `pcre`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, `xml`.
- Composer 2.
- Node.js 20 LTS atau lebih baru untuk build.
- MariaDB/MySQL.
- Git dan Bash.

## Setup Pertama

1. Buat website di aaPanel dengan domain `sita.kampus.ac.id`.
2. Set document root ke `/www/wwwroot/sita.kampus.ac.id/public`.
3. Clone repo ke `/www/wwwroot/sita.kampus.ac.id`.
4. Buat database dan user MySQL/MariaDB dari aaPanel.
5. Salin env production:

```bash
cp .env.production.example .env
php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"
```

Isi `APP_KEY` dengan output command tersebut, lalu sesuaikan minimal:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sita.kampus.ac.id
APP_BASE_PATH=
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sita
DB_USERNAME=sita_user
DB_PASSWORD=isi_password_database
MAIL_MAILER=smtp
MAIL_HOST=smtp.kampus.ac.id
MAIL_SCHEME=null
MAIL_PORT=587
MAIL_USERNAME=isi_user_smtp
MAIL_PASSWORD=isi_password_smtp
MAIL_FROM_ADDRESS=noreply@sita.kampus.ac.id
```

Jangan commit `.env`.

## Precheck Server

Jalankan:

```bash
bash deploy/aapanel-doctor.sh
```

Jika ingin sekaligus cek kecocokan domain:

```bash
DOMAIN=sita.kampus.ac.id bash deploy/aapanel-doctor.sh
```

Semua item `[FAIL]` harus diperbaiki sebelum deploy.

## Deploy

Deploy normal:

```bash
DOMAIN=sita.kampus.ac.id bash deploy/aapanel-deploy.sh
```

Script akan:

- validasi `.env` production;
- `git pull --ff-only` jika folder adalah git checkout;
- masuk maintenance mode;
- install Composer production dependency;
- install dependency Node dan build asset Vite;
- menyiapkan permission storage;
- menjalankan `storage:link`;
- menjalankan migration;
- membuat cache config, route, view, dan event;
- restart queue worker Laravel;
- keluar dari maintenance mode.

Untuk deploy tanpa pull:

```bash
GIT_PULL=false DOMAIN=sita.kampus.ac.id bash deploy/aapanel-deploy.sh
```

Untuk deploy tanpa migration:

```bash
RUN_MIGRATIONS=false DOMAIN=sita.kampus.ac.id bash deploy/aapanel-deploy.sh
```

## Nginx aaPanel

Gunakan template `deploy/aapanel-nginx.conf`. Ganti:

- `DOMAIN` menjadi domain production.
- `root /www/wwwroot/DOMAIN/public;` menjadi path production.
- `fastcgi_pass unix:/tmp/php-cgi-82.sock;` sesuai socket PHP aaPanel. Pada beberapa server aaPanel, include bawaan `enable-php-82.conf` bisa dipakai menggantikan blok PHP manual.

Contoh root wajib tetap ke `public/`, bukan folder project utama.

## Queue dan Cron

Saat ini fitur queue belum memproses job khusus, tetapi `QUEUE_CONNECTION=database` sudah disiapkan.

Jika queue mulai dipakai, buat Supervisor di aaPanel atau systemd:

```bash
php /www/wwwroot/sita.kampus.ac.id/artisan queue:work database --sleep=3 --tries=3 --timeout=90
```

Tambahkan cron scheduler Laravel agar fitur schedule masa depan langsung aktif:

```cron
* * * * * cd /www/wwwroot/sita.kampus.ac.id && php artisan schedule:run >> /dev/null 2>&1
```

## Deploy di Subpath

Subdomain tetap opsi paling aman. Jika kampus mewajibkan subpath seperti `https://domain-kampus.ac.id/admin`, isi:

```dotenv
APP_URL=https://domain-kampus.ac.id/admin
APP_BASE_PATH=admin
ASSET_URL=https://domain-kampus.ac.id/admin
SESSION_PATH=/admin
```

Lalu jalankan ulang:

```bash
DOMAIN=domain-kampus.ac.id bash deploy/aapanel-deploy.sh
```

Catatan: Nginx subpath Laravel butuh konfigurasi rewrite yang lebih teliti dibanding subdomain. Gunakan subdomain jika masih bisa dipilih.

## Troubleshooting

- 404 semua halaman: document root belum mengarah ke `public/` atau `try_files` Nginx belum benar.
- Blank page setelah deploy: cek `storage/logs/laravel.log`, lalu jalankan `php artisan optimize:clear`.
- Asset CSS/JS 404: pastikan `npm run build` sukses dan `public/build` ada. Untuk subpath, pastikan `ASSET_URL` sesuai `APP_URL`.
- Login/reset password email tidak terkirim: cek konfigurasi SMTP dan firewall kampus.
- Migration gagal: cek kredensial DB, permission user DB, dan extension `pdo_mysql`.
