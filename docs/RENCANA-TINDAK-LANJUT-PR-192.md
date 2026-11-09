# Rencana tindak lanjut PR #192

Rencana ini mengikuti keputusan review terbaru pada commit `0b7358a`. Status saat rencana dibuat adalah 8 dari 13 percakapan review ditutup. Tindak lanjut mencakup sufiks antrean Kaprodi, koreksi individual No SK setelah finalisasi, dan bukti penulis Excel. Status lokal dicatat pada tahap yang sudah dikerjakan. [Keputusan terbaru](https://github.com/RikiSanjayaa/sita/pull/192#issuecomment-6719550070)

| Urutan | Pekerjaan                 | Hasil yang dapat diperiksa                                                                                                                   |
| ------ | ------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| 1      | Kartu antrean Kaprodi     | Satu nama contoh, ditambah jumlah sisanya ketika antrean lebih besar. Tautan membuka antrean yang sama.                                      |
| 2      | Koreksi No SK final       | Pengelola yang berwenang dapat mengoreksi satu sidang dengan konfirmasi dan riwayat. Nilai dan hasil final tetap sama.                       |
| 3      | Bukti Excel dan OpenSpout | Bukti kesesuaian template dan pemakaian memori dapat diulang. Deklarasi langsung yang tidak digunakan dapat dihapus sesuai keputusan review. |
| 4      | Paket pemeriksaan         | Panduan, deskripsi PR, hasil tes, dan tangkapan layar merujuk revisi dan keputusan yang sama.                                                |

## 1. Kartu antrean Kaprodi

**Status lokal:** komponen bersama menampilkan nama contoh dan sisa antrean. Filter Finalisasi memakai syarat yang sama dengan kartu, yaitu ujian menunggu finalisasi pada tugas akhir aktif yang belum diarsipkan. Riwayat tetap tersedia melalui Semua Status.

- Gunakan `KaprodiPortalService::actionItems()` sebagai sumber total, nama contoh dan tautan. Service sudah menghitung `value` sebelum memotong preview `names` menjadi satu. Periksa bahwa daftar tujuan menerapkan cakupan prodi, status dan kapabilitas yang sama. Jika ditemukan selisih, perbaiki sumber filter sebelum memperbarui teks.
- Tambahkan dukungan preview secara opsional pada pola bersama `resources/js/components/patterns/action-queue.tsx`. Render kartu unggulan dari `resources/js/pages/kaprodi/dashboard.tsx` memakai total dan nama yang ditampilkan. Hitung sisa dari `value - jumlah nama yang dirender`. Aturan ini berlaku untuk kartu unggulan mana pun yang memiliki nama, termasuk kartu dosen melebihi kuota jika menjadi kartu pertama.
- Untuk total 1, tampilkan “Terkait: Putra Mahendra”. Untuk total 3, tampilkan “Terkait: Putra Mahendra dan 2 lainnya”. Antrean kosong memakai kondisi kosong yang sudah ada. Kartu sekunder tetap ringkas sesuai aturan pola antrean. Perbarui bagian Dashboard actions pada `DESIGN.md`. Breadcrumb mengikuti pola ringkas yang sudah disetujui.
- Perluas `KaprodiDashboardTest` dan `kaprodi-dashboard.spec.ts` untuk antrean 0, 1, dan lebih dari 1. Uji tautan antrean lengkap, prodi lain yang tidak ikut terhitung, serta kondisi ketika kapabilitas mengubah kartu teratas. Periksa nama panjang di desktop dan HP.

[Catatan kartu Kaprodi](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4505627565)

## 2. Koreksi individual No SK setelah finalisasi

**Status lokal:** koreksi individual tersedia di Berkas Sidang untuk Admin, Super Admin, dan Kaprodi yang berwenang. Riwayat memakai event tugas akhir yang sudah ada. Pest memverifikasi nilai dan hasil final tetap sama, perubahan dari tab lama ditolak, dan Excel membaca nomor terbaru. Skenario Berkas Sidang di Chromium memverifikasi konfirmasi, batal, riwayat, dan kontrol nilai yang tetap terkunci.

- Tambahkan aksi **Koreksi No SK** pada detail Berkas Sidang yang sudah final. Admin dan Super Admin memakai aksi native di `ManageSidangPaper.php`. Kaprodi memakai dialog bersama di `resources/js/pages/sidang/berkas.tsx` dan endpoint khusus melalui `SidangPaperController` serta Form Request. Keduanya memanggil satu metode koreksi di `SidangPaperService`.
- Dialog menampilkan mahasiswa dan percobaan sidang, No SK saat ini, serta input nomor baru. Isi dipangkas, wajib terisi, dan maksimal 150 karakter. Pengelola melihat nomor lama dan baru sebelum menekan **Simpan koreksi**. Penolakan validasi atau data yang sudah berubah mempertahankan draf dan memberi pesan yang dapat ditindaklanjuti.
- Di service, gunakan otorisasi `SidangPaperPolicy::manage` dan cek sidang terjadwal yang berstatus `completed` pada percobaan yang ditampilkan. Tolak percobaan yang dibatalkan atau sudah digantikan oleh percobaan aktif lain. Kunci baris tugas akhir dan sidang mengikuti urutan yang dipakai finalisasi. Cocokkan No SK saat konfirmasi dengan nilai yang dibaca di dalam transaksi untuk mencegah penimpaan dari tab lama.
- Simpan perubahan `sidang_sk` dan event koreksi dalam satu transaksi. Utamakan `ThesisProjectEvent` dengan jenis event khusus dan payload yang menyimpan ID sidang, nomor lama dan baru. Aktor dan waktu memakai kolom event yang tersedia. Tampilkan riwayat per sidang di detail Berkas Sidang untuk pengelola yang berwenang. Riwayat nomor lengkap memakai payload khusus agar tidak ikut tersebar melalui ringkasan aktivitas umum. Nomor yang sama tidak membuat event duplikat.
- Jalur koreksi hanya menulis No SK dan riwayat. Jalur nilai tetap terkunci setelah finalisasi. Aksi massal tetap menolak nomor berbeda. Setelah simpan, muat ulang nomor dan riwayat sambil menjaga filter saat kembali. Ekspor selanjutnya membaca No SK baru dari data sidang.
- Pest harus membuktikan koreksi dari kedua portal, larangan lintas prodi dan akses Dosen/Mahasiswa, penolakan untuk percobaan yang tidak memenuhi syarat, konflik dua editor, input kosong/panjang, nomor yang sama, dan riwayat lama/baru/aktor/waktu. Bandingkan skor panel, snapshot, huruf, hasil dan status sebelum dan sesudah. Uji bahwa aksi massal tidak menimpa nomor yang berbeda dan Excel memakai nomor terbaru. Playwright mencakup batal tanpa perubahan, konfirmasi, riwayat yang tampil, dan nilai final yang tetap terkunci.

[Persetujuan koreksi No SK](https://github.com/RikiSanjayaa/sita/pull/192#issuecomment-6719550070)

## 3. Excel bertahap dan bukti penerimaan

**Status lokal:** dua ekspor sintetis dengan jumlah prodi yang sama lulus pemeriksaan isi dan struktur workbook. Tes mencakup cakupan prodi, filter, dan pembersihan file sementara. Deklarasi langsung OpenSpout dihapus sesuai keputusan issue. Filament tetap memakai versi 4.32.0 dari lockfile. [Hasil dan cara mengulang benchmark](PANDUAN-PERBAIKAN-PR-192.md#bukti-ekspor-ulang)

- Pertahankan `SidangReportWriter` berbasis template dan pembacaan `lazyById` yang sudah tersedia. Pakai fixture sintetis pada beberapa volume, minimal 1.000 dan 10.000 entri untuk jumlah prodi yang sama. Jalankan tiap volume pada proses PHP terpisah, catat puncak memori PHP tambahan, waktu, dan ukuran berkas. Angka lama di panduan menjadi pembanding, bukan hasil uji revisi berikutnya.
- Pakai kasus yang melebihi kapasitas awal template dan mencakup dua prodi. Periksa jumlah baris, nilai final dan rumus pembulatan, referensi sel, merge, gaya, blok tanda tangan, area cetak dan pemisahan sheet. Cocokkan isi dengan filter serta otorisasi Admin/Kaprodi. Periksa file sementara setelah unduhan berhasil, filter kosong, ekspor panel tanpa profil, dan kegagalan saat penulisan berlangsung.
- Setelah memastikan tidak ada pemakaian OpenSpout langsung oleh aplikasi dan tes, hapus hanya deklarasi langsung `openspout/openspout` dari `composer.json`. Segarkan lock tanpa menaikkan versi paket. Pastikan Filament masih menarik OpenSpout transitif di `composer.lock`, versi paket tetap sama, dan import maupun ekspor tetap berfungsi. Jika pemeriksaan menemukan pemakaian langsung, catat hasilnya dan pertahankan deklarasi.
- Profil yang belum tersedia tetap memunculkan alasan di ekspor. Dataset campuran yang berisi sidang yang belum bisa dihitung harus tetap memberi pesan dan membatalkan unduhan, sesuai perilaku yang diterima reviewer.

[Acceptance terbaru issue #23](https://github.com/RikiSanjayaa/sita/issues/23)

## 4. Bukti, pengujian dan review ulang

**Status verifikasi:** Pest lengkap dan Playwright untuk Dashboard Kaprodi, Berkas Sidang, serta golden path lulus secara lokal. Bukti dan cakupan masing-masing perubahan dicatat di [peta pemeriksaan PR](STATUS-REVIEW-PR-192.md#bukti-tindak-lanjut).

- Tautkan commit `48d192e` dan `0b7358a` untuk pekerjaan yang sudah tersedia. Gunakan kembali bukti sembilan lembar Word/PDF, dialog Rekap Admin/Kaprodi, dan validasi NIDN serta tampilan Admin. Jelaskan bahwa persetujuan teks yang mengikuti contoh fakultas sudah tercatat, sementara penerimaan seluruh hasil render masih perlu diperiksa.
- Perbarui `docs/STATUS-REVIEW-PR-192.md`, `docs/PANDUAN-PERBAIKAN-PR-192.md`, `tests/e2e/COVERAGE.md` dan deskripsi PR pada saat implementasi. Pisahkan keputusan yang sudah disetujui, kode yang sudah masuk, hasil yang sudah diuji, dan persetujuan reviewer.
- Jalankan Pest terarah, Playwright untuk alur yang berubah, pemeriksaan format/tipe/lint, detektor desain dan golden path sebelum demo. Sertakan tangkapan layar data demo pada 1280 dan 390 px, terang dan gelap, termasuk dialog dan riwayat koreksi. Setelah CI pada commit terbaru lulus, uji di server testing dengan data yang sudah ada.
- Kelima catatan tetap terbuka sampai reviewer memeriksa bukti masing-masing. PR #192 dan issue #17/#23 punya cakupan berbeda. Sempro/Sinopsis di #17 dan laporan progres atau belum selesai di #23 tetap ditangani sebagai cakupan tersendiri.

## Keputusan panel 9 November 2026

Pemilik produk mengganti rencana dukungan satu penguji dengan acuan Ilmu Komputer untuk seluruh prodi. Minimal dua penguji berbeda dan satu pembimbing aktif diwajibkan. Nilai konfigurasi lama 0/1 dibaca sebagai 2, sementara minimum lebih tinggi tetap berlaku. Admin dan Kaprodi memperingatkan bahwa berkas dan ketentuan non-Ilkom belum diverifikasi.

Panel belum final dapat dilengkapi selama jadwal masih dapat diubah, tanpa mengeluarkan dosen yang sudah menilai. Jadwal Menunggu Finalisasi dan hasil final tidak dibuka ulang. Tidak ada bobot alternatif atau pemetaan pembimbing menjadi penguji. Ekspor panel yang belum memenuhi syarat tetap ditolak dengan identitas mahasiswa. Profil resmi non-Ilkom dapat dibahas setelah berkas kampus tersedia dan tidak lagi menjadi syarat merge PR #192.
