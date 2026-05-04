# Panduan Seeder S2 Sastra Inggris

Class seeder:
`Database\Seeders\S2SasingSeeder`

Perintah menjalankan seeder:

```bash
php artisan db:seed --class=Database\\Seeders\\S2SasingSeeder
```

Password semua akun seed:
`password`

Informasi program studi:

- nama: `S2 Sastra Inggris`
- slug: `s2-sastra-inggris`
- konsentrasi default: `Umum`

Format email:

- admin: `admin.s2.sasing@gmail.com`
- super admin: `superadmin.s2.sasing@gmail.com`
- dosen menggunakan email berurutan: `dosen1` sampai `dosen8`
- mahasiswa menggunakan email berurutan: `siswa1` sampai `siswa15`
- status tesis dijelaskan di panduan ini, tidak dimasukkan ke email

## Akun Admin

| Peran       | Nama                    | Email                            | Keterangan                             |
| ----------- | ----------------------- | -------------------------------- | -------------------------------------- |
| Super Admin | Super Admin SiTA        | `superadmin.s2.sasing@gmail.com` | Akses penuh ke seluruh aplikasi        |
| Admin Prodi | Admin S2 Sastra Inggris | `admin.s2.sasing@gmail.com`      | Akses admin khusus `S2 Sastra Inggris` |

## Akun Dosen

| Kode   | Nama                        | Email                        | Fungsi utama untuk demo                           |
| ------ | --------------------------- | ---------------------------- | ------------------------------------------------- |
| dosen1 | Dr. Diana Permata, M.Hum.   | `dosen1.s2.sasing@gmail.com` | Pembimbing utama, sempro, proyek selesai          |
| dosen2 | Rudi Hartono, M.A.          | `dosen2.s2.sasing@gmail.com` | Sempro terjadwal, sidang menunggu finalisasi      |
| dosen3 | Dr. Maya Salsabila, M.Phil. | `dosen3.s2.sasing@gmail.com` | Sempro menunggu finalisasi, rotasi pembimbing     |
| dosen4 | Prof. Arman Wijaya, Ph.D.   | `dosen4.s2.sasing@gmail.com` | Penguji dan panel proyek selesai                  |
| dosen5 | Dr. Nisa Rahmawati, M.Hum.  | `dosen5.s2.sasing@gmail.com` | Revisi sempro, riset belum lengkap, on hold       |
| dosen6 | Bintang Prakoso, M.A.       | `dosen6.s2.sasing@gmail.com` | Revisi sempro, sidang terjadwal, proyek restart   |
| dosen7 | Dr. Sekar Lestari, M.Litt.  | `dosen7.s2.sasing@gmail.com` | Sempro gagal, sidang menunggu finalisasi, on hold |
| dosen8 | Fikri Mahendra, M.Hum.      | `dosen8.s2.sasing@gmail.com` | Sempro gagal, riset belum lengkap, revisi sidang  |

## Matriks Akun Mahasiswa

| Kode    | Nama            | Email                         | Status utama tesis                    | Keterangan                                              |
| ------- | --------------- | ----------------------------- | ------------------------------------- | ------------------------------------------------------- |
| siswa1  | Alya Nurfadila  | `siswa1.s2.sasing@gmail.com`  | Baru mulai tesis                      | Sudah membuat pengajuan `title_review`                  |
| siswa2  | Bagus Ramadhan  | `siswa2.s2.sasing@gmail.com`  | Baru mulai tesis                      | Sudah membuat pengajuan `title_review`                  |
| siswa3  | Citra Maharani  | `siswa3.s2.sasing@gmail.com`  | Baru mulai tesis                      | Sudah membuat pengajuan `title_review`                  |
| siswa4  | Erina Putri     | `siswa4.s2.sasing@gmail.com`  | Sempro terjadwal                      | Sempro akan tampil di agenda publik                     |
| siswa5  | Faris Hidayat   | `siswa5.s2.sasing@gmail.com`  | Sempro menunggu finalisasi            | Nilai penguji sudah masuk                               |
| siswa6  | Ghea Lestari    | `siswa6.s2.sasing@gmail.com`  | Revisi sempro terbuka                 | Ada revisi sempro dan aktivitas workspace               |
| siswa7  | Hanif Kurniawan | `siswa7.s2.sasing@gmail.com`  | Sempro gagal                          | Menunggu perbaikan untuk attempt berikutnya             |
| siswa8  | Intan Maharani  | `siswa8.s2.sasing@gmail.com`  | Riset aktif                           | Pembimbing lengkap dan workspace aktif                  |
| siswa9  | Jovan Saputra   | `siswa9.s2.sasing@gmail.com`  | Riset aktif, pembimbing belum lengkap | Baru punya satu pembimbing aktif                        |
| siswa10 | Kirana Azzahra  | `siswa10.s2.sasing@gmail.com` | Sidang terjadwal                      | Ada riwayat rotasi pembimbing                           |
| siswa11 | Luthfi Maulana  | `siswa11.s2.sasing@gmail.com` | Sidang menunggu finalisasi            | Panel sudah memberi keputusan                           |
| siswa12 | Mentari Puspita | `siswa12.s2.sasing@gmail.com` | Revisi sidang terbuka                 | Ada revisi sidang dan aktivitas workspace               |
| siswa13 | Nabila Paramita | `siswa13.s2.sasing@gmail.com` | Selesai                               | Akan tampil di daftar topik lulus                       |
| siswa14 | Oka Prasetyo    | `siswa14.s2.sasing@gmail.com` | On hold                               | Proyek riset sedang ditunda                             |
| siswa15 | Putri Anindita  | `siswa15.s2.sasing@gmail.com` | Riwayat restart tesis                 | Punya proyek lama yang dibatalkan dan proyek baru aktif |

## Rekomendasi Akun Untuk Demo Cepat

| Tujuan demo                            | Login yang disarankan                                         |
| -------------------------------------- | ------------------------------------------------------------- |
| Demo jadwal publik                     | Tidak perlu login, sempro dan sidang mendatang sudah tersedia |
| Alur mahasiswa tahap awal              | `siswa1.s2.sasing@gmail.com`                                  |
| Alur mahasiswa revisi sempro           | `siswa6.s2.sasing@gmail.com`                                  |
| Alur mahasiswa revisi sidang           | `siswa12.s2.sasing@gmail.com`                                 |
| Alur mahasiswa selesai                 | `siswa13.s2.sasing@gmail.com`                                 |
| Dashboard dosen dengan mahasiswa aktif | `dosen1.s2.sasing@gmail.com`                                  |
| Dosen sebagai penguji/reviewer         | `dosen5.s2.sasing@gmail.com`                                  |
| Dashboard admin dan operasi tesis      | `admin.s2.sasing@gmail.com`                                   |
| Akses admin penuh                      | `superadmin.s2.sasing@gmail.com`                              |

## Data Tambahan Yang Juga Diseed

- 1 pengumuman terbit untuk `S2 Sastra Inggris`
- dokumen tesis dan naskah akhir
- dokumen mentorship
- jadwal mentorship
- thread chat mentorship dan pesan
- event riwayat tesis
- panel penguji sempro dan sidang
- data revisi tesis yang masih terbuka

## Catatan

- Seeder memakai tanggal relatif terhadap hari saat ini, jadi sempro dan sidang mendatang akan tetap relevan setelah reseed.
- Role `penguji` tetap dibuat, tetapi tidak ada user khusus penguji saja.
- Semua profil mahasiswa dan dosen memakai konsentrasi `Umum`.
- Karena email memakai format Gmail yang terlihat nyata, email keluar sebaiknya dimatikan atau diarahkan ke sink yang aman di environment showcase.
