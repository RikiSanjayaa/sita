# Deployment SITA di aaPanel

Dokumen ini menjelaskan deployment SITA untuk aaPanel Free, Nginx, PHP-FPM, MariaDB/MySQL, dan Node.js untuk build asset.

## Ringkasan Audit

- Backend: Laravel 12, PHP minimal 8.4 untuk lock dependency saat ini, Fortify untuk auth, Inertia Laravel, Filament untuk admin.
- Frontend: React 19, Inertia React, Vite 7, Tailwind CSS v4.
- Build frontend: `npm ci` lalu `npm run build`.
- Runtime backend: Nginx mengarah ke folder `public/`, PHP-FPM menjalankan `public/index.php`.
- Database lokal saat audit: SQLite. Production direkomendasikan MariaDB/MySQL dari aaPanel.
- Queue/cache/session: default repo memakai driver `database`, sehingga tabel `jobs`, `cache`, dan `sessions` harus dimigrasikan.
- Storage: `storage/` dan `bootstrap/cache/` wajib writable oleh user PHP-FPM. Jalankan `php artisan storage:link`.
- Realtime chat/notifikasi: project memakai Laravel Reverb. Di aaPanel perlu proses Reverb terpisah jika ingin chat realtime seperti Docker.
- Scheduler: project punya command reminder. Cron scheduler perlu diaktifkan.
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
- PHP 8.4 atau lebih baru. Samakan PHP CLI, Composer, PHP-FPM site, dan PATH build Vite ke PHP 8.4.
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

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=sita-production
REVERB_APP_KEY=isi_key_panjang
REVERB_APP_SECRET=isi_secret_panjang
REVERB_HOST=sita.kampus.ac.id
REVERB_PORT=8080
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Jangan commit `.env`.

## Precheck Server

Jalankan:

```bash
bash deploy/aapanel-doctor.sh
```

Jika ingin sekaligus cek kecocokan domain:

```bash
DOMAIN=sita.kampus.ac.id PHP_BIN=/www/server/php/84/bin/php bash deploy/aapanel-doctor.sh
```

Semua item `[FAIL]` harus diperbaiki sebelum deploy.

## Deploy

Deploy normal:

```bash
DOMAIN=sita.kampus.ac.id PHP_BIN=/www/server/php/84/bin/php bash deploy/aapanel-deploy.sh
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

Untuk panel admin Filament, pastikan request `/livewire/*` diteruskan ke Laravel. Jika aaPanel masih memakai rule static bawaan untuk `.js`/`.css`, tambahkan blok ini sebelum rule static asset:

```nginx
location ^~ /livewire {
    try_files $uri $uri/ /index.php?$query_string;
}
```
- `fastcgi_pass unix:/tmp/php-cgi-84.sock;` sesuai socket PHP aaPanel. Pada beberapa server aaPanel, include bawaan `enable-php-84.conf` bisa dipakai menggantikan blok PHP manual.

Contoh root wajib tetap ke `public/`, bukan folder project utama.

## Reverb, Queue, dan Cron

Chat realtime membutuhkan Laravel Reverb. Jika Reverb tidak jalan, halaman chat masih bisa terbuka tetapi update realtime antar user tidak akan masuk sampai fallback/request berikutnya.

Jalankan Reverb dengan Supervisor/systemd/PM2 yang tersedia di server:

```bash
/www/server/php/84/bin/php /www/wwwroot/sita.kampus.ac.id/artisan reverb:start --host=0.0.0.0 --port=8080
```

Queue worker tetap direkomendasikan untuk notification/event background:

```bash
/www/server/php/84/bin/php /www/wwwroot/sita.kampus.ac.id/artisan queue:work database --sleep=3 --tries=3 --timeout=90
```

Tambahkan cron scheduler Laravel agar fitur schedule masa depan langsung aktif:

```cron
* * * * * cd /www/wwwroot/sita.kampus.ac.id && /www/server/php/84/bin/php artisan schedule:run >> /dev/null 2>&1
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
- Admin login tidak merespons dan console menampilkan `livewire.min.js 404`: tambahkan rule Nginx `/livewire` seperti bagian Nginx di atas, lalu reload Nginx.
- Login/reset password email tidak terkirim: cek konfigurasi SMTP dan firewall kampus.
- Migration gagal: cek kredensial DB, permission user DB, dan extension `pdo_mysql`.
