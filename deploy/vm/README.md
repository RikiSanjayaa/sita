# Deployment SITA di VM

`docker-compose.vm.yml` adalah compose produksi untuk VM produksi. File ini berbeda dari compose dev karena:

- database memakai MariaDB 10.11, mengikuti dump dari server lama;
- image diambil dari GHCR, bukan build di server;
- env dibaca dari `app.env` dan `db.env`;
- Reverb tidak membuka port host, akses WebSocket lewat nginx di `/app/`;
- seeder selalu mati.

## Image

Jangan memakai `latest` atau `main` untuk produksi. Keduanya bergerak setiap build. Gunakan `sha-<commit>` agar deploy dan rollback menunjuk commit yang pasti. Untuk pinning paling kuat, gunakan digest `@sha256:...`.

Contoh:

```dotenv
SITA_APP_IMAGE=ghcr.io/rikisanjayaa/sita-app:sha-<commit>
SITA_WEB_IMAGE=ghcr.io/rikisanjayaa/sita-web:sha-<commit>
SITA_PULL_POLICY=always
SITA_WEB_BIND=0.0.0.0
```

Salin `deploy.env.example` menjadi `deploy.env` di folder deployment. Jangan commit `deploy.env`.

## File di server

```text
docker-compose.vm.yml
deploy.env
app.env
db.env
```

## Deploy

```bash
docker compose --env-file deploy.env -f docker-compose.vm.yml pull
docker compose --env-file deploy.env -f docker-compose.vm.yml up -d --remove-orphans
docker compose --env-file deploy.env -f docker-compose.vm.yml ps
```

Service `init` menjalankan migrasi, `storage:link`, dan cache config sebelum `app`, `queue`, `scheduler`, dan `reverb` mulai. `RUN_DB_SEED=false` mencegah seeder.

## Rollback

Ganti `SITA_APP_IMAGE` dan `SITA_WEB_IMAGE` ke tag commit sebelumnya, lalu:

```bash
docker compose --env-file deploy.env -f docker-compose.vm.yml pull
docker compose --env-file deploy.env -f docker-compose.vm.yml up -d --remove-orphans
```

Volume `sita_database` dan `sita_storage` tidak berubah.

## Setelah aaPanel aktif

Ubah `SITA_WEB_BIND=127.0.0.1` agar port 8088 hanya bisa diakses aaPanel.
