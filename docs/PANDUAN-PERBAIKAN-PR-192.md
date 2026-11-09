# Panduan perbaikan PR #192

**Acuan:** review Riki tanggal 6 November 2026 WITA. Panduan ini menjabarkan pekerjaan yang perlu dilakukan beserta cara memeriksanya. Poin bertanda **Keputusan** adalah pilihan yang perlu disepakati sebelum perilaku tersebut diterapkan.

Untuk keputusan review 8 November dan pekerjaan yang belum diterapkan, ikuti [rencana tindak lanjut terbaru](RENCANA-TINDAK-LANJUT-PR-192.md).

Keputusan 9 November menggantikan dukungan panel satu penguji pada rencana awal di bawah. Seluruh prodi memakai minimal dua penguji dan satu pembimbing aktif. Pengaturan lama 0/1 dibaca sebagai 2; minimum yang lebih tinggi dipertahankan. Non-Ilkom mendapat peringatan acuan sementara pada Admin dan Kaprodi. Tidak ada bobot alternatif atau penghitungan ulang hasil final. Panel yang belum final dapat dilengkapi selama jadwal belum terkunci. Lihat [keputusan kerja terbaru](STATUS-REVIEW-PR-192.md#keputusan-kerja-terbaru).

[PR #192](https://github.com/RikiSanjayaa/sita/pull/192) · [Catatan awal](https://github.com/RikiSanjayaa/sita/pull/192#issuecomment-6686316595) · [Review manual](https://github.com/RikiSanjayaa/sita/pull/192#pullrequestreview-5802638014) · [Tracking #35](https://github.com/RikiSanjayaa/sita/issues/35)

## 1. Mulai dari branch yang sudah memuat perubahan Riki

### Pengerjaan tahap pertama

Perubahan Riki pada commit `45d0efe8` sudah diselaraskan. Tahap pertama mencakup minimal penguji yang dapat diatur per prodi, migrasi awal Ilkom, pesan kesalahan dialog pengaturan, penghitung penguji, serta validasi jadwal yang mempertahankan input saat ditolak. Tahap ini memakai angka pengaturan untuk syarat jadwal. Form rekap tiga nilai masih memiliki persyaratan panel tersendiri dan penjelasannya ditampilkan pada pengaturan prodi.

Tes regresi tahap ini mencakup pengaturan 0/1/2/3, migrasi yang mempertahankan data, pengaturan seeder yang tidak menimpa pilihan Admin, error total bobot/ambang/rubrik, penolakan pembimbing sebagai penguji, serta perilaku jadwal ketika minimal penguji diubah dari 2 ke 0. Status akhir harus mengikuti bukti CI pada commit yang memuat perbaikan.

1. Periksa `git status -sb`. Kalau ada perubahan lokal, simpan sesuai alur kerja sebelum menyelaraskan branch.
2. Jalankan `git fetch origin`, lalu fast-forward branch `fitur/berita-acara-dan-rekap-sidang` dari branch remote dengan `git merge --ff-only origin/fitur/berita-acara-dan-rekap-sidang`. Jika fast-forward gagal, periksa commit lokal dan remote sebelum melanjutkan.
3. Pastikan commit `45d0efe8` atau penerusnya ada pada branch kerja. Saat panduan dibuat, checkout lokal masih di `2be0048`. Commit Riki membawa perbaikan seeder panel, penilai kedua pada skenario pemulihan, dan uji akses lintas prodi.
4. Jalankan uji terfokus sebagai baseline. Pertahankan bukti yang sudah diterima reviewer, terutama kop Word dinamis, sembilan lembar Word, kerahasiaan panel, penolakan penggantian penilai yang sudah menilai, dan periode rekap yang telah tersimpan.

[Commit Riki](https://github.com/RikiSanjayaa/sita/commit/45d0efe8200d680cf0625f663f8dd4d76966bab7)

| Urutan | Paket pekerjaan | Ketergantungan |
| --- | --- | --- |
| 1 | Pengaturan prodi, validasi, dan dialog penjadwalan | Bisa dimulai segera |
| 2 | Satu sumber nilai, jejak input, dan finalisasi | Tentukan aturan peran penilai lama |
| 3 | Rekap Excel dan kasus data kosong | Memakai hasil dari paket 2 |
| 4 | Halaman Admin, navigasi Kaprodi, dan perapian antarmuka | Form nilai memakai kontrak dari paket 2. Perapian mandiri bisa berjalan lebih awal |
| 5 | Koreksi Word, pilihan template per prodi, dan cetak HTML/PDF | Koreksi teks bisa dikerjakan awal. Data nilai cetak memakai hasil paket 2 |

Peta terbaru seluruh 13 percakapan, keputusan pemilik produk, dan langkah pemeriksaan bersama tersedia pada [Status Review PR #192](STATUS-REVIEW-PR-192.md). Review 8 November sudah menutup delapan percakapan pada `84158aa4`; lima sisanya tidak dianggap selesai hanya karena paket implementasi sudah ada.

## 2. Pengaturan prodi dan penjadwalan

**File utama** — `app/Support/SidangSettings.php`, `app/Filament/Resources/ProgramStudis/Pages/EditProgramStudi.php`, `app/Services/ThesisProjectAdminService.php`, `app/Filament/Resources/ThesisProjects/Pages/ViewThesisProject.php`, `app/Services/KaprodiPortalService.php`, dan `resources/js/pages/kaprodi/sempro-sidang.tsx`.

- Baca `minimum_paper_examiners` dengan batas bawah 2 untuk seluruh prodi. Data lama 0/1 tidak membuka jalur penjadwalan satu penguji; minimum yang lebih tinggi tetap berlaku. Selalu wajibkan satu pembimbing aktif dan penguji berbeda di luar pembimbing. Service dan kedua formulir memakai angka yang sama.
- Jumlah penguji minimum dan slot rumus nilai tetap terpisah. Rekap memakai Penguji 1, Penguji 2, dan satu Pembimbing penilai. Berkas dan bobot resmi non-Ilkom belum diverifikasi; tampilkan peringatan acuan Ilmu Komputer tanpa menambahkan profil satu penguji.
- Hitung penguji dari ID unik di luar daftar pembimbing. Saring pembimbing dari hasil pencarian Kaprodi dan Filament, termasuk pilihan yang baru berubah, dan ulangi validasi di service. Pertahankan penjagaan agar penilai yang telah mengirim nilai atau keputusan tetap di panel. Validasi perubahan yang gagal harus menjaga jadwal, panel, dan input form.
- Tampilkan hitungan dekat kolom penguji dan alasan dekat **Simpan Jadwal**, misalnya *1 dari minimal 2 penguji* dan *Tambahkan 1 penguji*. Tampilkan alasan terpisah bila pembimbing aktif belum ada. Kaitkan alasan ke tombol Kaprodi dengan `aria-describedby`. Di Admin, gunakan validasi pada field dan tahan modal tetap terbuka ketika service menolak perubahan. Pilih satu tempat untuk pesan yang sama agar helper dan error tidak muncul ganda. Gunakan **Simpan Jadwal** dan **Batal**, termasuk konsistensi tombol batal pada dua langkah finalisasi.
- Petakan kegagalan validasi dialog pengaturan ke state field modal yang benar. Tampilkan pesan saat total bobot bukan 100%, ambang huruf tidak berurutan, atau total rubrik salah. Kalau pesan ditampilkan sebagai notifikasi, tahan modal terbuka dan pertahankan input. Buat dua textarea rubrik memakai lebar penuh atau susun keduanya sejajar dalam satu baris pada desktop.

Selesai ketika pengaturan lama 0/1 tetap menolak satu penguji di Admin dan Kaprodi, minimum 2/3 dipertahankan, dan pembimbing aktif diwajibkan. Pada ukuran 1280 px dan 390 px, penghitung penguji dan warning terbaca. Penambahan penguji mempertahankan nilai dosen lama. Jadwal Menunggu Finalisasi dan hasil final tidak dibuka ulang. Error bobot, ambang dan rubrik tetap mempertahankan input.

[Aturan per prodi dan hitungan](https://github.com/RikiSanjayaa/sita/pull/192#issuecomment-6686316595) · [Validasi dialog pengaturan](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294033) · [Modal tertutup saat gagal](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294079) · [Pesan ganda dan label tombol](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294094) · [Susunan rubrik](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294117)

## 3. Nilai, riwayat input, dan finalisasi

### Penerapan tahap kedua

`SidangScoreService` membaca nilai panel untuk seluruh portal, Word, dan Excel. Input berkas menyimpan pilihan penilai serta penanda pencocokan. Koreksi Admin dan Dosen dicatat pada `thesis_defense_score_changes`, dengan penginput terakhir dan waktu pada baris panel. Perubahan skor membatalkan pencocokan lama. Form menolak isian yang sudah tertinggal dari nilai terbaru dan mempertahankan draf ketika ditolak.

Pembacaan memakai bobot dan ambang prodi yang berlaku sampai finalisasi. Finalisasi menyimpan hasil turunan, ID penilai, bobot, dan ambang. Usulan otomatis memilih dua penguji pertama serta pembimbing pertama berdasarkan urutan panel. Admin dapat mengganti pilihan pembimbing penilai sebelum finalisasi. Panel lama tanpa dua penguji tidak dipetakan ke rumus baru. Snapshot final eksplisit dipertahankan, bukan diganti rata-rata atau dihitung ulang.

Migrasi data hanya mengisi skor panel yang kosong dari JSON lama dengan ID dan peran yang valid. Skor panel yang berbeda dan snapshot final lama dipertahankan. Hasil digital lama yang selesai, lengkap, dan belum punya snapshot memakai baseline bobot prodi saat migrasi, ditandai `settings_origin = program_at_migration`. Tanggal finalisasi hanya diisi bila sudah ada pada data lama. Jalankan `php artisan sidang:audit-scores` untuk melihat JSON per baris konflik atau panel yang belum dapat dipetakan. Metadata lama ditampilkan sebatas informasi yang tersedia.

**File utama** — `app/Services/SidangPaperService.php`, `app/Services/ThesisDefenseExaminerDecisionService.php`, `app/Services/ThesisProjectAdminService.php`, `app/Models/ThesisDefenseExaminer.php`, `app/Http/Requests/StoreSidangPaperRequest.php`, `app/Policies/SidangPaperPolicy.php`, dan controller yang memasok hasil ke Admin, Dosen, Kaprodi, serta Mahasiswa.

1. Jadikan `thesis_defense_examiners.score` sumber skor setiap dosen. Jalur dari berkas dan jalur portal dosen sama-sama menulis baris panel yang berwenang. Pertahankan rentang 0–100, tiga penilai berbeda untuk profil rekap tiga slot, validasi bahwa setiap ID berasal dari attempt yang sama, dan pembatasan akses per prodi. Dosen hanya mengubah skornya sendiri. Pisahkan jalur koreksi skor dari pengiriman keputusan dosen agar koreksi tidak menghapus keputusan, revisi, atau lampiran sebelumnya. Pertahankan aturan Sempro dan Sinopsis.
2. Izinkan koreksi nilai pada `scheduled` dan `awaiting_finalization` untuk pengguna berwenang. Cek status di dalam transaksi. Gunakan urutan lock yang seragam di semua jalur, utamakan lock pada sidang sebelum mengubah baris panel, lalu hitung ulang kesiapan. Koreksi skor saat menunggu finalisasi tidak mengembalikan sidang ke Terjadwal hanya karena keputusan digital kosong. Tentukan kapan nilai dari berkas cukup untuk siap finalisasi melalui aturan yang sudah berlaku, dan kapan panel jalur digital masih perlu mengirim keputusan. Cegah penyimpanan koreksi bersamaan dengan finalisasi melewati lock dan pemeriksaan status. Pertahankan konfirmasi hasil akhir yang sudah ada.
3. Catat aktor, waktu, dan jalur perubahan skor per anggota panel. Gunakan catatan audit untuk koreksi yang menimpa nilai. Jika nilai portal cocok dengan berkas, simpan penanda pencocokan terpisah dari skor, berisi siapa yang mengecek dan kapan. Jika skor diubah, hapus atau tandai pencocokan lama sebagai perlu dicek ulang. Jika pencocokan memerlukan dua angka, angka dari berkas dipakai sebagai pembanding pada saat pencocokan atau dicatat pada log audit.
4. Pusatkan pemilihan peran dan perhitungan nilai akhir serta huruf pada satu service. Gunakan urutan panel yang stabil sebagai usulan default, simpan ID penilai yang dipilih, dan pakai bobot prodi yang berlaku sesuai kebijakan. **Usulan:** simpan hasil akhir, susunan penilai, bobot dan ambang nilai saat finalisasi agar perubahan pengaturan prodi tidak mengubah nilai historis. Snapshot tersebut menyimpan hasil turunan dan metadata, sedangkan skor mentah tetap dibaca dari baris panel.
5. Migrasikan secara bertahap. Inventarisasikan JSON `sidang_paper` lama, skor panel, peran, dan attempt. Gunakan ID panel saat mencocokkan data, isi metadata historis hanya ketika ada bukti dari snapshot, lalu buat daftar skor yang berbeda atau panel yang tidak bisa dipetakan. Pertahankan keputusan dan skor yang sudah difinalisasi. Tunda pembersihan JSON lama sampai pembaca baru, data lama, dan ekspor sudah lulus perbandingan.
6. Tampilkan sumber yang faktual. Bila skor diisi dari berkas tetapi keputusan digital kosong, gunakan label seperti **Nilai dari berkas** pada halaman Dosen dan Mahasiswa. Pertahankan hasil akhir sebagai keputusan final yang terpisah. Penyajian ke mahasiswa tetap memakai penyaring penguji sebelum sidang selesai, termasuk pada jejak pencatatan dan notifikasi.
7. Keputusan 9 November menunda profil satu penguji sampai berkas resmi prodi tersedia. Tiga angka lengkap tidak cukup untuk menentukan slot Penguji 2. Jangan menghitung bobot alternatif atau mengganti hasil final lama. Rekap berjalan untuk panel yang memenuhi profil dua penguji dan satu pembimbing penilai; panel lain ditolak dengan alasan yang jelas.

**Selesai ketika:** dosen mengirim nilai, Admin mencocokkan atau memperbaikinya dari berkas, dosen mengoreksi skornya sendiri sebelum finalisasi, dan semua tampilan membaca hasil yang sama. Uji angka nol, skor desimal, tiga penilai yang berbeda, konflik koreksi dan finalisasi, penolakan perubahan sesudah finalisasi, serta sidang lama dengan dan tanpa JSON.

[Arah desain nilai](https://github.com/RikiSanjayaa/sita/pull/192#pullrequestreview-5802638014) · [Keputusan tampak pending](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294902) · [Panel prodi jalur lama](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294151) · [Konfirmasi penilaian #172](https://github.com/RikiSanjayaa/sita/issues/172)

## 4. Rekap Excel

### Penerapan tahap ketiga

Rekap mengikuti filter tabel dan rentang jadwal WITA. Periode tersimpan mengambil alih tanggal dan judul manual. Endpoint mengecek scope prodi, pemilik token pilihan, attempt, dan kelengkapan tiga nilai sebelum mengirim file. Hasil kosong memberi respons 422 dan pesan yang sama di dialog Admin dan Kaprodi, dengan isian tetap tersedia.

Penulis rekap memakai XML worksheet dari template resmi dan menulis blok baris tambahan serta merge ke file sementara. Endpoint membaca data dalam kelompok 200 lewat `lazyById`. Issue #23 menyetujui pendekatan ini dan penghapusan deklarasi langsung OpenSpout jika aplikasi tidak memakainya. Filament tetap membutuhkan OpenSpout 4.32.0 melalui lockfile. Laporan progres untuk sidang belum final tetap menjadi bagian terbuka dari issue. [Keputusan issue #23](https://github.com/RikiSanjayaa/sita/issues/23)

**File utama** — `app/Http/Controllers/SidangReportController.php`, Form Request baru untuk rekap, `app/Services/SidangReportWriter.php`, `app/Services/KaprodiPortalService.php`, `app/Services/SidangReportPeriodService.php`, `app/Filament/Resources/ThesisProjects/Pages/ListThesisProjects.php`, dan `resources/js/pages/kaprodi/sempro-sidang.tsx`.

- Pindahkan validasi rentang tanggal, periode, pilihan, dan filter ke Form Request. Pertahankan otorisasi periode, cakupan prodi, keterikatan token pilihan ke pengguna, dan filter yang sedang dipakai. Tentukan prioritas antara periode tersimpan dan tanggal manual secara eksplisit. Pertahankan batas tanggal sidang WITA dan pengecualian attempt lama jika ada attempt lebih baru yang relevan.
- Buang syarat `whereNotNull('sidang_paper')`. Pakai kriteria hasil final dan nilai lengkap dari paket 2. Selaraskan pembentuk daftar pilihan di Admin, `readyForReport` di Kaprodi, dan filter endpoint supaya sidang dari jalur portal dosen ikut terambil bila memenuhi syarat. Pakai konteks prodi dan periode untuk judul dan blok tanda tangan. Saat ekspor beberapa prodi, ambil metadata masing-masing sheet dari prodi terkait.
- Bila tidak ada baris yang layak, beri pesan **Tidak ada sidang lulus yang sudah final pada filter ini** dan biarkan petugas mengoreksi pilihan. Gunakan respons yang ditangani secara konsisten oleh Admin dan Kaprodi. Cek set data layak sebelum mengirim header unduhan. Jika dialog ditutup setelah ekspor, lakukan setelah aplikasi menerima respons file yang berhasil. Pertahankan dialog pada error dan hasil kosong.
- **Keputusan:** #23 meminta filter selesai dan belum selesai, sedangkan review terakhir menyebut syarat Excel nilai berupa sidang lulus, final, dan tiga nilai. Usulan kerja adalah menjaga Excel nilai sebagai rekap final, memberi informasi pada filter yang tidak menghasilkan baris, dan meminta konfirmasi apakah dibutuhkan laporan progres terpisah untuk status belum selesai. Catat pilihan di #23 sebelum menganggap acceptance filter lengkap.
- Batasi DOM pada template dasar per sheet. Tulis baris tambahan dan merge ke file sementara. Uji volume, konsistensi rumus, pembulatan, gaya, blok tanda tangan, sheet per prodi, serta area cetak. Uji pembersihan file sementara saat unduhan selesai, data kosong, panel tanpa profil, atau pembuatan file terputus. Nilai teks pengguna yang diawali karakter rumus tetap ditulis sebagai teks.

**Selesai ketika:** hasil ekspor mengikuti filter yang disepakati dan hanya berisi prodi yang diizinkan. Jalur berkas dan digital menghasilkan baris yang sama untuk panel dan nilai yang sama. Filter kosong memberi pesan. Uji rekap lebih dari kapasitas template awal serta beberapa prodi memastikan rumus, NIDN, blok tanda tangan, dan area cetak masih benar. Bukti uji volume menunjukkan penulisan baris tidak menumpuk seluruh sheet di memori.

[Issue #23](https://github.com/RikiSanjayaa/sita/issues/23) · [Ekspor kosong dan jalur digital](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294068) · [Pengaturan ekspor Admin dan Kaprodi](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294129)

### Bukti ekspor ulang

Setiap perintah menjalankan proses PHP terpisah. Fixture sintetis membagi data sama rata ke dua prodi dan menghasilkan No SK berbeda tiap 100 entri. Argumen nama berkas menyimpan salinan hasil untuk diperiksa.

```text
php scripts/benchmark-sidang-report.php 1000 rekap-1000.xlsx
php scripts/benchmark-sidang-report.php 10000 rekap-10000.xlsx
```

| Entri | Sheet | Puncak tambahan PHP | Waktu writer | Ukuran XLSX |
| ----- | ----- | ------------------- | ------------ | ----------- |
| 1.000 | 2     | 7.608.504 byte      | 1,844 detik  | 249.624 byte |
| 10.000 | 2    | 7.608.504 byte      | 15,608 detik | 2.374.305 byte |

Pengukuran lokal Windows dengan PHP 8.4.10 mencakup generator fixture dan writer setelah Laravel siap. Angka ini mengukur alokasi PHP dari `memory_get_peak_usage()` terhadap baseline sebelum ekspor. Waktu dan memori permintaan HTTP, basis data, serta RSS proses berada di luar pengukuran.

Pemeriksaan kedua XLSX membandingkan setiap entri dengan fixture dan mengecek urutan serta referensi sel, rumus nilai berbobot dan pembulatan, nilai huruf, gaya, merge No SK, blok tanda tangan, pemisahan prodi, area cetak, serta pengaturan printer. Microsoft Excel 16.0 membuka kedua file dan menghitung ulang nilai akhir contoh 79,55, nilai bulat 80, dan B+. Area cetak kedua prodi mencapai baris 1.522 pada 1.000 entri dan baris 15.022 pada 10.000 entri.

`SidangPaperWorkflowTest` mencakup filter, cakupan Admin/Kaprodi, unduhan dan pembersihan file sementara. Uji impor dan unduhan template lulus setelah pembaruan deklarasi Composer. Paket pengujian backend terarah lulus 156 tes dengan 1.557 asersi.

## 5. Halaman kerja Admin dan Kaprodi

**File utama** — `app/Filament/Resources/ThesisProjects/ThesisProjectResource.php`, `Tables/ThesisProjectsTable.php`, `Schemas/ThesisProjectInfolist.php`, `app/Http/Controllers/SidangPaperController.php`, `resources/js/pages/sidang/berkas.tsx`, `resources/js/pages/kaprodi/sempro-sidang.tsx`, dan layout Kaprodi.

- **Usulan untuk Admin:** tambahkan halaman Filament khusus di resource Tugas Akhir. Halaman ini memberi ruang untuk paket cetak, nilai, Nomor SK, dan finalisasi tanpa memecah alur ke beberapa modal. Arahkan daftar dan detail ke halaman yang sama. Gunakan policy/service yang sama dengan Kaprodi dan pastikan defense memang milik proyek yang diminta. Perbarui tautan lama secara aman untuk pengguna yang berwenang. Kaprodi tetap memakai halaman Inertia.
- Di Kaprodi, tampilkan breadcrumb dari menu **Sempro / Sinopsis & Sidang Akhir**, lalu nama dan NIM mahasiswa, lalu berkas dengan nomor attempt. Judul tab memuat mahasiswa dan attempt. Tandai menu induk sebagai aktif. Simpan parameter pencarian/filter yang diizinkan saat berpindah halaman dan pulihkan melalui **Kembali**. Bentuk URL kembali di aplikasi untuk menghindari pengalihan ke alamat luar.
- Tampilkan **Input Nilai** hanya bila status, template yang berlaku, dan susunan panel mendukung input berkas. Untuk prodi yang memakai penilaian digital, tampilkan tindakan yang bisa dijalankan, misalnya **Lihat Berkas** jika ada template, atau **Finalisasi** saat siap. Hak akses server tetap berlaku walau pengguna membuka URL langsung.
- Di form nilai, keluarkan penguji terpilih dari opsi penguji lain dan tampilkan alasan bila pilihan masih ganda, kosong, atau di luar rentang. Tampilkan status penyimpanan/koreksi dari paket 2. Pakai token desain yang sama untuk select, Nomor SK, dan input angka. Sejajarkan label dan kontrol pada desktop, lalu tumpuk rapi pada HP.
- Seragamkan tanggal periode dan ekspor dengan date picker masing-masing platform, format Indonesia, label dan urutan tindakan yang setara. Pertahankan saran semester/tahun setelah tanggal awal dipilih dan pilihan manual bila petugas sudah mengubahnya. Sediakan **Batal** di kedua dialog dan pertahankan input ketika unduhan ditolak atau kosong.

**Selesai ketika:** dari daftar dan detail, Admin tetap melihat sidebar serta header Filament. Kaprodi melihat breadcrumb dan judul tab yang lengkap, lalu kembali ke filter sebelumnya. Penguji ganda mendapat penjelasan. Prodi dengan panel lama melihat tindakan yang sesuai. Periksa tema terang dan gelap pada 1280 px dan 390 px.

[Navigasi dan halaman Filament](https://github.com/RikiSanjayaa/sita/pull/192#issuecomment-6686316595) · [Penguji dipilih dua kali](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294047) · [Gaya isian](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294104) · [Kontrol tanggal](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294117) · [Dialog ekspor](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294129) · [Aksi pada prodi jalur lama](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294151)

## 6. Word, template per prodi, dan cetak PDF

### Penerapan tahap kelima

Pemilihan format ada di `config/sidang-templates.php`, dengan pasangan DOCX dan view Blade per slug prodi. Template contoh tetap menjadi baseline semua prodi sesuai arahan pemilik produk. Admin melihat status Template standar, Template khusus atau Template belum tersedia. Kontrak placeholder dan lembar dijelaskan di `resources/templates/sidang/README.md`.

`SidangPaperPresenter` menyediakan isi yang sama untuk Word dan cetak HTML A4. Jadwal memuat hari Indonesia dan waktu mulai sampai selesai dalam WITA. Alamat, kontak dan situs kop dapat diatur per prodi. Rubrik khusus mengisi komponen pada kedua format. Nilai dan bobot final memakai snapshot yang sudah disimpan. Judul Seminar Skripsi serta pilihan penanda tangan 1 / 2 dipertahankan dari contoh fakultas.

Admin, Kaprodi dan anggota panel Dosen memperoleh opsi Cetak / Simpan PDF dan unduhan Word. Paket dan setiap lembar memakai pemeriksaan akses yang sama. Format Sempro/Sinopsis masih dilacak dalam #17.

**File utama** — `app/Services/SidangWordWriter.php`, `app/Http/Controllers/SidangPaperController.php`, `app/Support/WitaDateTime.php`, `app/Support/AcademicTerminology.php`, `resources/templates/sidang/`, dan `routes/web.php`.

- Perbaiki Word yang sudah ada. Tambahkan nama hari Indonesia pada jadwal tunggal maupun rentang dalam WITA. Perbaiki `tNO` menjadi `NO`. Cocokkan judul **Seminar Skripsi** dengan berkas resmi sebelum menggantinya memakai jenis ujian dan terminologi jenjang. Periksa tulisan `Penguji 1 / 2` dan `Pembimbing 1 / 2` pada lembar yang memakai satu penanda tangan. Isi peran atau nama yang tepat jika lembar memang untuk satu orang. Pertahankan pilihan umum bila format resmi meminta petugas menandai salah satu. Periksa alamat, kontak, situs web, dan penyebutan institusi dalam rubrik. Gunakan nilai resmi yang berlaku untuk prodi/fakultas atau konfigurasi yang telah disetujui. Kop nama fakultas dan prodi yang sudah bekerja menjadi regresi wajib.
- **Arah review terakhir:** simpan template DOCX khusus per prodi di repo. Buat pemilih template dengan pemetaan yang diperiksa aplikasi. Tampilkan status **Template khusus**, **Template standar**, atau **Template belum tersedia** di pengaturan prodi. Gunakan fallback standar hanya untuk prodi yang disetujui memakai format tersebut. Dokumentasikan placeholder, ID lembar, dan data wajib. **Keputusan:** fakultas perlu menetapkan prodi yang memakai fallback dan menyerahkan template yang berbeda. Panduan ini mengusulkan template Excel tetap mengikuti ketentuan #23 sampai ada format lain yang disetujui.
- Untuk #17, sediakan cetak HTML A4 melalui Blade dan CSS print. Pertahankan opsi Word. Buat nama route dan tautan yang membedakan cetak dan unduh DOCX dengan jelas. Gunakan policy yang sama untuk paket dan lembar satuan, serta data presentasi yang sama untuk kop, jadwal, identitas, panel, nilai, hasil, dan revisi. Gunakan dialog cetak browser untuk **Simpan PDF**. Tambahkan opsi di detail ujian Admin, Kaprodi, dan Dosen yang berwenang. Isi hasil dan nilai final mengikuti status ujian.
- Issue #17 juga mencakup Sempro/Sinopsis dan nilai Sinopsis OBE. Jika PR #192 tetap difokuskan ke Sidang, pisahkan pengerjaan format Sempro/Sinopsis dan lacak di #17. Lampirkan PDF atau tangkapan layar cetak dari data demo, dibandingkan dengan referensi resmi pada tiap format yang dikerjakan.

**Selesai ketika:** paket Word standar masih sembilan lembar, unduhan satu lembar tetap benar, dan template khusus dipilih untuk prodi yang memiliki berkas. PDF cetak yang dihasilkan browser punya kop, halaman, tanda tangan, dan isi sesuai referensi. Anggota panel mendapat akses, sedangkan dosen di luar panel dan pengguna lintas prodi ditolak.

[Issue #17](https://github.com/RikiSanjayaa/sita/issues/17) · [Koreksi teks Word](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294051) · [Pilihan cetak dan template](https://github.com/RikiSanjayaa/sita/pull/192#issuecomment-6686316595) · [Arah template per prodi](https://github.com/RikiSanjayaa/sita/pull/192#pullrequestreview-5802638014)

## 7. Nomor SK dan perapian lintas halaman

| Poin | Implementasi | Verifikasi |
| --- | --- | --- |
| Nomor SK setelah nilai tersimpan | Pisahkan kemampuan menyimpan SK dari kemampuan mengubah nilai. **Usulan:** izinkan petugas berwenang mengisi SK yang kosong secara individual saat Menunggu Finalisasi atau Selesai, dengan alasan yang jelas saat tindakan terkunci. Untuk mengganti SK yang sudah ada, **keputusan** bersama Riki tentang izin, konfirmasi, dan audit. Pertahankan penolakan konflik pada aksi isi massal sampai kebijakan koreksi disetujui. Lakukan simpan kelompok secara atomik. | Simpan SK setelah nilai tersimpan. Cek nomor yang sama, nomor berbeda, lintas prodi, dan rollback kelompok yang memuat baris tidak layak. |
| NIDN ganda | Di `UserForm.php`, jalur simpan/provisioning, dan model profil dosen, samakan normalisasi string agar nol di depan tetap utuh. Izinkan nilai kosong. Periksa duplikat yang sudah ada sebelum menambah unique constraint. Validasi edit mengabaikan profil yang sedang diubah. | NIDN kosong pada beberapa profil diterima. Duplikat ditolak pada tambah/edit. NIDN sendiri tetap bisa disimpan. |
| Fokus Saat Ini dan kartu Kaprodi | Di `ThesisProjectInfolist.php`, utamakan status Selesai atau Arsip sebelum peringatan pembimbing aktif. Di `KaprodiPortalService.php` dan tampilan dashboard, ambil hitungan dan nama dari cakupan yang sama, lalu tampilkan misalnya *Putra Mahendra dan 1 lainnya*. | Proyek selesai tidak memunculkan saran menambah pembimbing. Kartu dan daftar tujuan memakai jumlah yang sama, termasuk kondisi 0, 1, dan lebih dari 1. |
| Daftar Berkas Sidang | Di `ThesisProjectsTable.php`, cek tooltip No SK yang sudah ada pada tema gelap, keyboard, dan HP. Sediakan cara membuka teks penuh pada sentuhan. Selaraskan posisi aksi unduh antarbaris dengan komponen Filament. Di `ListThesisProjects.php`, letakkan **Atur Periode Rekap** dan **Ekspor Rekap Nilai** dalam kelompok yang konsisten. | No SK panjang terbaca penuh. Aksi unduh sejajar dan tombol utama terlihat pada 1280 px. Tab lain tetap bisa diakses. |
| Aksi di drawer Dosen | Di `resources/js/pages/dosen/seminar-proposal.tsx`, kelompokkan tombol chat dan unduhan dengan jarak konsisten, tambahkan ikon aksesibel, samakan baseline dan tinggi, lalu izinkan bungkus pada HP. Saat opsi cetak ditambahkan, gunakan **Cetak / Simpan PDF** sebagai aksi utama dokumen dan Word sebagai unduhan sekunder. | Cek dua tema di 1280 px dan 390 px, dengan target sentuh 44 px dan aksi tetap terlihat. |

[Pertanyaan SK](https://github.com/RikiSanjayaa/sita/pull/192#pullrequestreview-5802638014) · [Catatan SK dan gaya input](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294104) · [NIDN, fokus, daftar, dan toolbar](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294141) · [Tombol Dosen](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494294902) · [Kartu Kaprodi](https://github.com/RikiSanjayaa/sita/pull/192#discussion_r4494295255)

## 8. Pengujian sebagai syarat selesai

Untuk pekerjaan ini dan perbaikan berikutnya, tulis **tes regresi untuk setiap bug** dan **unit test untuk aturan bisnis baru atau yang berubah**. Mulai dengan menulis ulang skenario yang ditemukan reviewer sebagai tes. Jika memungkinkan, jalankan tes itu pada kode lama untuk membuktikan bug terdeteksi, lalu jalankan kembali setelah perbaikan. Pilih jenis tes berdasarkan bagian yang gagal.

| Jenis uji | Yang dibuktikan | Contoh untuk PR #192 |
| --- | --- | --- |
| **Pest Unit** di `tests/Unit/` | Perhitungan, aturan, dan transformasi data yang bisa diperiksa secara terpisah. Gunakan dataset untuk variasi yang memakai aturan sama. | Bobot 30/30/40 dengan nilai 80, 90, dan 70 menghasilkan 79. Uji skor nol, desimal, nilai tepat di ambang huruf, pengaturan minimal penguji kosong/0/2, dan batas tanggal rekap WITA. |
| **Pest Feature** di `tests/Feature/` | Jalur simpan yang dipakai aplikasi, izin, status, transaksi, dan isi dokumen. | Admin dapat mengoreksi skor sebelum finalisasi. Setelah finalisasi, skor terkunci. Penolakan akses lintas prodi, rollback No SK massal, dan ekspor digital ikut diuji. Periksa isi Word dan Excel dari unduhan, termasuk nama prodi, rumus, dan baris yang memenuhi filter. |
| **Playwright** di `tests/e2e/specs/` | Tindakan pengguna di browser serta pesan yang muncul saat tindakan gagal. | Satu penguji membuat alasan penolakan terlihat, input tetap ada setelah validasi gagal, dan pilihan filter pulih saat kembali dari Berkas Sidang. Uji alur penjadwalan sampai finalisasi pada `golden-path`. |
| **Cek visual dan cetak** | Keterbacaan dan kesesuaian dengan referensi pada hasil yang dilihat atau dicetak pengguna. | Periksa dialog 1280 px dan 390 px, terang dan gelap. Periksa Word dan PDF A4 dengan data demo, termasuk kop panjang, tanda tangan, dan pembagian halaman. |

### Checklist sebelum dinyatakan selesai

- [ ] Setiap bug yang ditangani punya tes regresi di lapisan yang mendeteksinya. Setiap aturan bisnis yang diubah punya unit test untuk kondisi normal, kondisi batas, dan input yang harus ditolak.
- [ ] Uji terfokus lulus, termasuk jalur yang datanya tersimpan dan jalur yang ditolak. Perubahan status, izin, atau transaksi juga punya uji Pest Feature.
- [ ] Perubahan yang terlihat pengguna punya skenario Playwright yang sesuai. Jalankan golden path sebelum demo dan simpan bukti visual jika halaman, dialog, atau dokumen berubah.
- [ ] Pemeriksaan wajib di CI lulus pada **commit terbaru yang akan direview**. Skenario penerimaan yang gagal, terlewat, atau belum dijalankan masih berstatus **Dalam pengujian**.
- [ ] Deskripsi PR atau thread review mencantumkan skenario, hasil uji, commit yang diuji, dan bukti yang relevan. Temuan yang masih menghambat penerimaan ditangani sebelum tugas ditandai **Selesai**.

## 9. Matriks uji dan penutupan review

| Area | Uji otomatis | Bukti yang dicatat |
| --- | --- | --- |
| Pengaturan prodi dan panel | Pest `SidangPaperWorkflowTest`, `ThesisProjectResourceTest`, dan `KaprodiPortalTest`. Playwright `admin-sidang`, `kaprodi-portal`, dan tahap jadwal pada `golden-path`. | Pengaturan 0/2, panel tidak lengkap, pesan inline, dan input tetap ada setelah ditolak. |
| Koreksi skor dan finalisasi | Pest untuk akses, audit, data lama, dan transaksi. Jalankan `SecretSidangExaminersTest`. Playwright `dosen-penilaian`, `thesis-recovery`, dan `golden-path`. | Kedua jalur skor, hasil berbobot, revisi, nilai yang cocok, serta kerahasiaan penguji sebelum selesai. |
| Excel dan SK | Pest `SidangPaperWorkflowTest` untuk kedua sumber nilai, prodi, periode, filter kosong, dan nilai final. `SidangSkVersionTest` untuk versi konfirmasi No SK. Playwright `sidang-paper` untuk dialog, koreksi dan unduhan. | Workbook satu/multi-prodi, hasil digital yang sah, pesan kosong, volume dan memori, nomor lama/baru serta penguncian nilai final. |
| Navigasi dan tampilan | Playwright dari daftar ke berkas, lalu kembali dengan filter. Jalankan detector untuk area terkait sesuai AGENTS.md. | Tangkapan layar data demo 1280 px dan 390 px dalam tema terang dan gelap, termasuk dialog/error yang berubah. |
| Word dan cetak A4 | Pest untuk isi dan otorisasi paket serta lembar. Uji hasil render Word dan print dari browser. | PDF atau tangkapan layar pembanding untuk template yang dikerjakan, termasuk lintas prodi dan dokumen sebelum/sesudah finalisasi. |

- Jalankan pemeriksaan PHP, TypeScript, lint, dan skenario yang berubah sesuai `AGENTS.md`. Perbarui `tests/e2e/COVERAGE.md`, jalankan golden path sebelum demo, lalu unggah tangkapan layar PR dari data demo memakai `scripts/pr-screenshots.sh`.
- Cantumkan commit dan bukti untuk setiap percakapan review yang ditangani. Selesaikan percakapan setelah perbaikan terverifikasi dan sesuai keputusan maintainer. Perbarui deskripsi PR dengan cakupan yang sudah selesai serta bagian yang dipisah. Tutup #17 dan #23 hanya setelah acceptance masing-masing dipenuhi atau cakupannya disepakati ulang.
