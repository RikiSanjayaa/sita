# Admin Thesis Import Format

Untuk mahasiswa yang tugas akhirnya sudah berjalan sebelum memakai SITA. Buka `Admin > Thesis Projects`, klik `Download Template`, isi, lalu pakai `Import Tugas Akhir`.

Mahasiswa dan dosen harus sudah ada sebagai user. Import user lebih dulu (lihat `docs/admin-user-import-format.md`).

## Kolom

| Kolom                 | Wajib                          | Keterangan                                                                                                                                                                                                                                                                                                                                                     |
| --------------------- | ------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `nim`                 | ya                             | Mahasiswa harus sudah ada dan belum punya tugas akhir aktif.                                                                                                                                                                                                                                                                                                   |
| `judul`               | ya                             | Menjadi judul versi 1. Berstatus disetujui, kecuali tahap Review Judul (menunggu review).                                                                                                                                                                                                                                                                      |
| `pembimbing_1_nik`    | ya, kecuali tahap Review Judul | Dicocokkan ke NIK dosen. Dosen harus aktif dan mengajar di prodi mahasiswa.                                                                                                                                                                                                                                                                                    |
| `pembimbing_2_nik`    | tidak                          | Harus berbeda dari pembimbing 1 dan tidak boleh diisi tanpa pembimbing 1.                                                                                                                                                                                                                                                                                      |
| `tahap`               | ya                             | `Review Judul`, `Tahap Proposal`, `Riset`, `Tahap Sidang`, `Selesai`. Kode `title_review`, `sempro`, `research`, `sidang`, `completed` juga diterima. Huruf besar/kecil tidak berpengaruh.                                                                                                                                                                     |
| `tanggal_mulai_tiket` | tidak                          | `YYYY-MM-DD` (sel tanggal Excel juga terbaca). Mengisi masa berlaku tiket bimbingan: mulai pada tanggal itu, berakhir sesuai `SITA_THESIS_TICKET_MONTHS` (12 bulan). Tanggal lampau berarti tiket sudah habis dan bimbingan tertahan sampai diperpanjang. Kosong: tiket mulai hari import saat pembimbing ditetapkan, dan tidak ada tiket untuk tahap Selesai. |

Kolom lain di file diabaikan. Baris kosong dilewati.

## Contoh

`docs/samples/contoh-import-tugas-akhir.xlsx` siap diunggah apa adanya. Isinya tiga mahasiswa Ilmu Komputer dari seeder tinjauan (`php artisan db:seed --class=ReviewScenarioSeeder --force`, aman diulang), masing-masing belum punya tugas akhir:

| NIM          | Mahasiswa        | Tahap        | Pembimbing (NIK seed)                  |
| ------------ | ---------------- | ------------ | -------------------------------------- |
| `2210510111` | Putri Maharani   | Review Judul | -                                      |
| `2210510112` | Rizal Firmansyah | Riset        | `7301010101010005`, `7301010101010006` |
| `2210510113` | Siska Amelia     | Tahap Sidang | `7301010101010003`                     |

Login: email `putri.maharani@sita.test`, `rizal.firmansyah@sita.test`, `siska.amelia@sita.test`, kata sandi `password`. Unggah sebagai admin Ilmu Komputer (`admin@sita.test`). Setelah berhasil, unggah ulang file yang sama ditolak (aturan 4). Seeder tidak membuat ulang tugas akhir yang sudah diimpor.

Seeder yang sama membuat tiga mahasiswa untuk meninjau tiket bimbingan 1 tahun: `dewi.anggraini@sita.test` (tiket berlaku sekitar 10 bulan), `fajar.ramadhan@sita.test` (berakhir dalam 9 hari), dan `hendra.gunawan@sita.test` (berakhir kemarin). Tanggal tiket disegarkan setiap seeder dijalankan.

## Aturan

1. Semua baris divalidasi lebih dulu. Jika ada satu error, tidak ada yang tersimpan dan error ditampilkan per baris (`Baris N: ...`, nomor baris sama dengan baris di Excel). Perbaiki file lalu unggah ulang.
2. Error yang dilaporkan: NIM tidak ada, NIM bukan mahasiswa prodi admin (admin prodi), NIM dobel di file, mahasiswa sudah punya tugas akhir aktif, judul sama sudah ada pada mahasiswa tersebut, NIK tidak ditemukan, dosen tidak aktif atau beda prodi, kuota pembimbing penuh, tahap tidak dikenal, tanggal salah.
3. Mahasiswa dianggap punya tugas akhir aktif bila tugas akhir terbarunya (yang berlaku saat ini) berstatus aktif. Mahasiswa yang terbarunya sudah selesai atau dibatalkan boleh diimpor lagi.
4. Re-import file yang sama ditolak: tahap aktif ditolak karena sudah punya tugas akhir aktif, tahap Selesai karena judul yang sama sudah ada.
5. Kuota pembimbing dihitung seperti penetapan pembimbing biasa, termasuk baris lain di file yang sama. Baris tahap Selesai tidak dihitung ke kuota.

## Data yang dibuat per baris

- Tugas akhir dengan `phase` sesuai tahap (`state = completed` untuk Selesai).
- Judul versi 1.
- Penugasan pembimbing lewat `ThesisProjectAdminService::assignSupervisors` (pembimbing diakhiri otomatis pada tahap Selesai).
- Untuk Riset, Tahap Sidang, dan Selesai: defense sempro placeholder (`completed`, `pass`, catatan `Data import: dilaksanakan sebelum SITA`). Untuk Selesai ditambah defense sidang dengan hasil yang sama.
- Event `project_imported` pada riwayat tugas akhir.

Notifikasi ke mahasiswa dan dosen tidak dikirim, kecuali kotak `Kirim notifikasi ke mahasiswa dan dosen pembimbing` dicentang di modal import. Notifikasi ke admin tidak pernah dikirim.
