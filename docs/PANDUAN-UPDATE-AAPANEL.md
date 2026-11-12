# Panduan Update SITA di aaPanel (dari `2652d34` ke `main` terbaru)

Panduan singkat untuk tim IT kampus. Server saat ini berada di commit `2652d34` ("Harden aaPanel deployment checks"). Ikuti langkah 1 sampai 9 secara berurutan. Referensi lengkap (instalasi baru, subpath, detail Nginx) ada di [AAPANEL_DEPLOYMENT.md](AAPANEL_DEPLOYMENT.md).

Selama script deploy berjalan (install dependency, build asset, migration), aplikasi masuk maintenance mode dan menampilkan halaman 503.

## Yang Perlu Diketahui Dulu

| Perubahan                                                                                                                                                                           | Dampak ke server                                                                                                                                      |
| ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Riwayat branch `main` di GitHub ditulis ulang (force-push). Commit `2652d34` tidak ada lagi di `main`; isinya identik dengan commit `a6c3931` di `main` baru.                       | `git pull` dan `deploy/aapanel-deploy.sh` akan gagal dengan pesan `Not possible to fast-forward`. Perlu `git reset --hard` **satu kali** (langkah 4). |
| 24 migration baru. Semuanya menambah tabel/kolom, tetapi beberapa juga mengubah data (mengisi ronde revisi, menghapus kolom `proposed_secondary_lecturer_user_id`, merapikan NIDN). | **Wajib backup database** (langkah 3). Rollback hanya bisa dengan restore backup.                                                                     |
| PHP 8.4 sebelum 8.4.27 terkena CVE-2026-109618 dan CVE-2026-109619 (remote code execution).                                                                                         | Perbarui PHP 8.4 di aaPanel ke 8.4.27 atau lebih baru (langkah 2).                                                                                    |
| Konfigurasi Reverb untuk browser sekarang dibaca saat runtime. Ada variabel wajib baru `REVERB_ALLOWED_ORIGINS`.                                                                    | Ubah `.env` (langkah 5). `VITE_REVERB_*` tidak dipakai lagi.                                                                                          |
| Service Reverb, queue, dan scheduler sekarang berjalan sebagai user `www`.                                                                                                          | Pasang ulang service (langkah 7).                                                                                                                     |
| Template Nginx diperbarui: `/storage/`, proxy WebSocket, dan header keamanan.                                                                                                       | Sesuaikan vhost (langkah 8).                                                                                                                          |

## 1. Isi Variabel

Login SSH sebagai `root`, lalu sesuaikan nilai berikut. Semua perintah berikutnya memakai variabel ini, jadi jalankan di sesi SSH yang sama.

```bash
export DOMAIN=sita.kampus.ac.id                  # domain aplikasi
export APP_DIR=/www/wwwroot/sita.kampus.ac.id    # folder checkout repo
export PHP_BIN=/www/server/php/84/bin/php        # PHP 8.4 milik aaPanel
export PHP_FPM_SERVICE=php-fpm-84
cd "$APP_DIR"
```

## 2. Cek Versi Runtime

```bash
$PHP_BIN -v | head -1          # wajib 8.4.27 atau lebih baru
node -v                        # wajib 20.19+ atau 22.12+ (disarankan 22)
composer --version             # Composer 2
git status --short             # sebaiknya kosong
```

- PHP masih di bawah 8.4.27: perbarui lewat aaPanel > App Store > PHP 8.4 > Update. CLI dan PHP-FPM ikut diperbarui karena memakai binary yang sama.
- `git status` tidak kosong berarti ada file yang diubah langsung di server. Catat atau simpan dulu (`git stash`), karena langkah 4 menimpa perubahan tersebut.

## 3. Backup

```bash
BACKUP_DIR=/www/backup/sita-$(date +%Y%m%d-%H%M)
mkdir -p "$BACKUP_DIR"

# Database (nama DB dan user diambil dari .env, password diminta interaktif)
DB_NAME=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2-)
DB_USER=$(grep -E '^DB_USERNAME=' .env | cut -d= -f2-)
mysqldump --single-transaction --routines -u "$DB_USER" -p "$DB_NAME" > "$BACKUP_DIR/database.sql"

# Konfigurasi dan file upload
cp .env "$BACKUP_DIR/env.backup"
tar -czf "$BACKUP_DIR/storage-app.tar.gz" storage/app

# Penanda commit lama untuk rollback
git tag -f sebelum-update-2652d34 2652d34

ls -lh "$BACKUP_DIR"
```

Pastikan `database.sql` tidak kosong. Jika `mysqldump` tidak ditemukan, pakai `/www/server/mysql/bin/mysqldump`, atau backup dari aaPanel > Databases > Backup.

## 4. Ambil Kode Terbaru (Sekali Saja)

```bash
git fetch origin
git reset --hard origin/main
git log --oneline -1

# Wajib tampil satu baris. Jika kosong, berhenti dan hubungi pengembang.
grep -n "thesis_titles_proposed_secondary_lecturer_foreign" \
  database/migrations/2026_10_23_090000_add_proposed_supervisors_to_thesis_project_titles_table.php
```

`reset --hard` hanya mengganti file yang dilacak git. `.env`, `storage/`, `vendor/`, `node_modules/`, dan `public/storage` tidak tersentuh.

Pemeriksaan `grep` di atas memastikan perbaikan migration untuk MySQL/MariaDB sudah ada. Tanpa perbaikan ini migration berhenti dengan error `1059 Identifier name ... is too long`.

Setelah langkah ini, update berikutnya cukup memakai langkah 6, karena `git pull` kembali normal.

## 5. Ubah `.env`

Buka `.env` lalu:

1. Tambahkan hostname aplikasi saja, tanpa `https://` dan tanpa `*`:

    ```dotenv
    REVERB_ALLOWED_ORIGINS=sita.kampus.ac.id
    ```

2. Pastikan nilai berikut sudah ada dan benar:

    ```dotenv
    APP_ENV=production
    APP_DEBUG=false
    APP_TIMEZONE=Asia/Makassar
    DB_CONNECTION=mysql
    REVERB_HOST=sita.kampus.ac.id
    REVERB_PORT=443
    REVERB_SCHEME=https
    REVERB_INTERNAL_HOST=127.0.0.1
    REVERB_INTERNAL_PORT=8080
    REVERB_INTERNAL_SCHEME=http
    REVERB_SERVER_HOST=127.0.0.1
    REVERB_SERVER_PORT=8080
    ```

    Tetap gunakan `DB_CONNECTION=mysql` walaupun server memakai MariaDB. Nilai `mariadb` membuat migration lama `2026_03_01_070000` gagal.

3. Opsional: hapus baris `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`, dan `FRONTEND_REVERB_HOST`. Baris ini tidak dipakai lagi.

4. Biarkan nilai default fitur baru berikut kecuali sudah diputuskan lain:

    ```dotenv
    WEB_PUSH_ENABLED=false
    SITA_ARCHIVE_ENABLED=false
    ```

    `SITA_ARCHIVE_ENABLED=true` menghapus permanen chat dan draf dokumen dari tugas akhir yang sudah selesai lebih dari 30 hari. Aktifkan hanya setelah backup rutin berjalan. Web Push bisa diaktifkan belakangan (lihat bagian "Opsional").

Cek konfigurasi:

```bash
DOMAIN=$DOMAIN PHP_BIN=$PHP_BIN PHP_FPM_SERVICE=$PHP_FPM_SERVICE bash deploy/aapanel-doctor.sh
```

Semua `[FAIL]` wajib diperbaiki sebelum lanjut. `[WARN]` boleh diabaikan bila alasannya jelas.

## 6. Deploy

Cek dulu cara Reverb dan queue dijalankan saat ini:

```bash
systemctl list-units 'sita-*' --no-pager
```

**A. Jika muncul unit `sita-...` (systemd), atau belum ada service sama sekali:**

```bash
DOMAIN=$DOMAIN \
PHP_BIN=$PHP_BIN \
PHP_FPM_SERVICE=$PHP_FPM_SERVICE \
HEALTHCHECK_URL=https://$DOMAIN/up \
INSTALL_SERVICES=true \
bash deploy/aapanel-deploy.sh
```

**B. Jika Reverb dan queue dijalankan dari aaPanel Supervisor/Process Manager:** jalankan perintah yang sama **tanpa** baris `INSTALL_SERVICES=true`, lalu restart proses Reverb dan queue dari menu Supervisor (user proses: `www`). Jangan memakai keduanya sekaligus, karena dua proses Reverb akan berebut port 8080.

Script akan: maintenance mode, `composer install`, `npm ci` dan build asset, set permission `storage/` untuk group `www`, `migrate --force`, cache config/route/view, restart queue dan PHP-FPM, keluar dari maintenance mode, lalu healthcheck `/up`.

Output yang diharapkan di akhir:

```text
Healthcheck berhasil: https://sita.kampus.ac.id/up
Deploy selesai untuk sita.kampus.ac.id.
```

Bila script berhenti di tengah, aplikasi otomatis keluar dari maintenance mode. Baca pesan error terakhir dan lihat bagian "Masalah Umum".

## 7. Pastikan Service Berjalan

```bash
systemctl list-units 'sita-*' --no-pager
```

Harus ada tiga unit aktif: `...-reverb.service`, `...-queue.service`, `...-schedule.timer`. Ketiganya berjalan sebagai user `www`.

Scheduler cukup satu: timer systemd **atau** cron `schedule:run`. Jika sebelumnya ada cron `artisan schedule:run` di aaPanel > Cron, hapus cron tersebut saat memakai timer systemd. Bila keduanya aktif, reminder dikirim dua kali.

## 8. Sesuaikan Nginx

Buka aaPanel > Website > situs SITA > Config. Bandingkan dengan `deploy/aapanel-nginx.conf`, lalu pastikan bagian berikut ada, ditaruh **sebelum** rule static `.js/.css` bawaan aaPanel:

```nginx
location ~ ^/livewire(?:-|/) {
    try_files $uri $uri/ /index.php?$query_string;
}

location ^~ /storage/ {
    try_files $uri =404;
    expires 30d;
    access_log off;
}

location ~ ^/(app|apps)(?:/|$) {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Forwarded-Port $server_port;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_read_timeout 3600;
    proxy_send_timeout 3600;
    proxy_pass http://127.0.0.1:8080;
}
```

Perubahan dibanding versi lama:

- Proxy Reverb memakai regex `^/(app|apps)(?:/|$)` dan timeout `3600` (sebelumnya `60`, sehingga WebSocket putus setiap menit).
- Blok `/storage/` baru, agar foto profil dan lampiran tidak terblokir rule deny folder `storage`.
- Opsional: empat header keamanan di level `server` (lihat template baris 17-20).

Klik Save di aaPanel; panel memvalidasi dan me-reload Nginx. Dari SSH:

```bash
/www/server/nginx/sbin/nginx -t && /etc/init.d/nginx reload
```

Root situs tetap `$APP_DIR/public`. Batas upload aplikasi 20 MB, jadi `client_max_body_size` serta `upload_max_filesize` dan `post_max_size` PHP minimal 25M (default aaPanel 50M sudah cukup).

## 9. Verifikasi

```bash
curl -fsS https://$DOMAIN/up && echo OK
$PHP_BIN artisan migrate:status | grep -c Pending     # harus 0
$PHP_BIN artisan about --only=environment             # Environment production, Debug off
DOMAIN=$DOMAIN PHP_BIN=$PHP_BIN CHECK_SERVICES=true bash deploy/aapanel-doctor.sh
```

Lalu cek dari browser:

- [ ] Halaman publik `https://DOMAIN/` tampil.
- [ ] Login mahasiswa, dosen, dan admin berhasil. Panel admin (Filament) bisa diklik.
- [ ] Buka menu Pesan di dua browser dengan akun berbeda. Kirim pesan; pesan muncul di sisi lain tanpa refresh (Reverb).
- [ ] Foto profil atau lampiran lama tetap bisa dibuka (`/storage/...`).
- [ ] DevTools > Console tidak menampilkan error `404` untuk `/build/` atau `/livewire/`.

## Rollback

Jika aplikasi tidak bisa dipakai setelah update (variabel `$APP_DIR`, `$PHP_BIN`, `$DB_USER`, `$DB_NAME`, `$BACKUP_DIR` berasal dari langkah 1 dan 3; isi ulang bila sesi SSH sudah berganti):

```bash
cd "$APP_DIR"
$PHP_BIN artisan down
git reset --hard sebelum-update-2652d34
mysql -u "$DB_USER" -p "$DB_NAME" < "$BACKUP_DIR/database.sql"
cp "$BACKUP_DIR/env.backup" .env
GIT_PULL=false RUN_MIGRATIONS=false DOMAIN=$DOMAIN PHP_BIN=$PHP_BIN bash deploy/aapanel-deploy.sh
```

Jangan memakai `migrate:rollback`. Sebagian migration baru mengubah data dan tidak bisa dikembalikan tanpa backup.

Catatan: database perlu dikosongkan dulu sebelum restore agar tabel baru ikut terhapus. Cara paling mudah: aaPanel > Databases > hapus lalu buat ulang database dengan nama dan user yang sama, kemudian jalankan perintah `mysql ... < database.sql`.

## Update Berikutnya

Setelah update ini, setiap update cukup:

```bash
cd "$APP_DIR"
DOMAIN=$DOMAIN PHP_BIN=$PHP_BIN PHP_FPM_SERVICE=$PHP_FPM_SERVICE \
HEALTHCHECK_URL=https://$DOMAIN/up INSTALL_SERVICES=true \
bash deploy/aapanel-deploy.sh
```

Backup database tetap disarankan sebelum setiap update.

## Opsional: Web Push

Notifikasi push ke browser/HP (HTTPS wajib). Buat kunci **sekali**, simpan, dan jangan dibuat ulang setiap deploy:

```bash
$PHP_BIN artisan webpush:vapid --show
```

Isi `.env`:

```dotenv
WEB_PUSH_ENABLED=true
VAPID_SUBJECT=mailto:admin@kampus.ac.id
VAPID_PUBLIC_KEY=...hasil perintah di atas...
VAPID_PRIVATE_KEY=...hasil perintah di atas...
```

Lalu jalankan ulang perintah deploy pada bagian "Update Berikutnya".

## Masalah Umum

| Gejala                                                     | Penyebab dan solusi                                                                                                                                          |
| ---------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `Not possible to fast-forward, aborting.`                  | Langkah 4 belum dijalankan.                                                                                                                                  |
| `fatal: detected dubious ownership in repository`          | Pemilik folder berbeda dengan user SSH. Jalankan `git config --global --add safe.directory $APP_DIR`.                                                        |
| Migration `2026_10_23_090000` gagal `1059 ... is too long` | Kode belum memuat perbaikan migration (cek `grep` di langkah 4). Jangan ulangi deploy; hubungi pengembang. Kolom yang sempat terbuat perlu dibersihkan dulu. |
| Migration `2026_11_08_150849` gagal `NIDN ganda`           | Ada dua dosen dengan NIDN sidang yang sama. Perbaiki data dosen pada ID yang disebut di pesan error, lalu deploy ulang.                                      |
| Doctor `[FAIL] REVERB_ALLOWED_ORIGINS`                     | Isi hostname tanpa `https://` (langkah 5).                                                                                                                   |
| Chat harus di-refresh agar pesan baru muncul               | Reverb mati (`systemctl status sita-*-reverb.service`), atau proxy `/app` di Nginx belum ada.                                                                |
| WebSocket putus setiap 60 detik                            | `proxy_read_timeout` masih `60`. Ubah ke `3600` (langkah 8).                                                                                                 |
| Foto/lampiran `403` atau `404`                             | Blok `location ^~ /storage/` belum ada, atau jalankan `$PHP_BIN artisan storage:link`.                                                                       |
| Admin Filament tidak merespons, console `livewire ... 404` | Blok `/livewire` belum ada atau posisinya setelah rule static aaPanel.                                                                                       |
| `Permission denied` di `storage/logs`                      | Jalankan ulang deploy sebagai root, atau `chgrp -R www storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache`.                                  |
| Halaman blank setelah deploy                               | Lihat `tail -n 100 storage/logs/laravel.log`, lalu `$PHP_BIN artisan optimize:clear` dan deploy ulang.                                                       |
