# Template dokumen Sidang

Contoh Fakultas Teknik menjadi baseline semua prodi sesuai arahan pemilik produk. Kop, identitas, jadwal, panel, nilai, hasil dan catatan diisi dari data Sidang. Pengaturan kop dan rubrik tersedia melalui Program Studi > Atur Dokumen dan Nilai Sidang.

## Pemilihan format

`config/sidang-templates.php` memetakan slug prodi ke pasangan template DOCX dan view Blade. Prodi tanpa entri memakai `standard`. Entri `null`, berkas hilang, berkas di luar direktori ini, atau view tidak tersedia menghasilkan status Template belum tersedia. Aplikasi menolak cetak dan unduhan untuk format yang belum tersedia.

Contoh konfigurasi setelah prodi menyerahkan format resmi dan perubahan diuji.

```php
'programs' => [
    'slug-prodi' => [
        'label' => 'Format resmi prodi',
        'word' => 'slug-prodi/berkas-sidang.docx',
        'view' => 'sidang.print.slug-prodi',
    ],
    'prodi-menunggu-format' => null,
],
```

File DOCX berada relatif terhadap direktori ini. View Blade berada di `resources/views`. Perubahan susunan lembar disimpan, direview dan dideploy bersama kode. Excel tetap memakai `rekap-nilai-sidang.xlsx` sesuai cakupan #23.

## Kontrak template Word

Placeholder berbentuk `{{student}}`. Simpan seluruh placeholder dalam satu elemen `w:t`, tanpa pemisahan format di tengah placeholder. `SidangPaperPresenter` menyediakan nilai yang sama untuk Word dan cetak HTML.

| Isi             | Placeholder                                                                                                                                     |
| --------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| Identitas       | `student`, `nim`, `nim1` sampai `nim10`, `program`, `title`                                                                                     |
| Istilah jenjang | `finalWork`, `finalWorkLower`, `finalWorkUpper`, `examLabel`, `seminarTitle`, `examTitle`                                                       |
| Kop             | `institution`, `letterheadInstitution`, `letterheadFaculty`, `letterheadProgram`, `letterheadAddress`, `letterheadContact`, `letterheadWebsite` |
| Jadwal          | `schedule`, `placeDate`                                                                                                                         |
| Panel           | `supervisor1`, `supervisor2`, `examiner1`, `examiner2`, `moderator`, `head`, `headNik`                                                          |
| Hasil final     | `pass`, `fail`, `total`                                                                                                                         |
| Tiga penilai    | `examiner1Score`, `examiner2Score`, `supervisorScore`, akhiran `Weight` dan `Weighted` untuk masing-masing penilai                              |
| Ambang huruf    | `gradeRange0` sampai `gradeRange6` untuk A sampai E                                                                                             |
| Catatan final   | `examinerNotes`, `supervisorNotes`                                                                                                              |

Hari dan waktu memakai WITA. Jadwal rentang memuat waktu selesai dan tanggal akhir jika berganti hari. Sebelum finalisasi, nilai akhir dan catatan final dikosongkan. Dua pilihan LULUS dan TIDAK LULUS tetap tercetak agar dapat ditandai pada berkas kertas. Setelah finalisasi, lulus dengan revisi ditulis LULUS pada dokumen. Bobot dan ambang nilai mengikuti snapshot final. Pilihan Penguji 1 / 2 dan Pembimbing 1 / 2 pada lembar individual dipertahankan sesuai contoh resmi, untuk ditandai petugas penilai.

Setiap lembar dimulai dengan komentar XML langsung di dalam `w:body`, misalnya `<!--sita:berita-acara-->`. Sembilan ID yang didukung mengikuti urutan berikut.

1. `berita-acara`
2. `revisi-penguji`
3. `daftar-hadir`
4. `nilai-penguji`
5. `nilai-pembimbing`
6. `rekap-nilai`
7. `masukan`
8. `tanya-jawab`
9. `revisi-pembimbing`

Paket standar mempertahankan judul Seminar Skripsi dari contoh fakultas. Istilah karya menyesuaikan jenjang melalui `AcademicTerminology`. Contoh yang tersedia mengatur Sidang. Format Sempro/Sinopsis dilacak terpisah dalam #17.

Tabel rubrik standar memiliki `w:tblCaption` bernilai `sita:rubric:examiner` atau `sita:rubric:supervisor`. Baris pertama adalah header, baris kedua contoh baris lima kolom, dan baris terakhir total. Saat rubrik diubah, baris komponen diganti dengan label dan bobot pengaturan prodi. Kolom nilai komponen tetap kosong karena sistem menyimpan nilai total tiap dosen.

## Kontrak cetak HTML

View menerima `$defense`, `$fields`, `$forms`, `$selectedForm` dan `$settings`. Gunakan escaping Blade untuk seluruh data pengguna. Tetapkan ukuran A4 melalui CSS `@page`, sembunyikan toolbar saat mencetak, dan uji paket serta lembar tunggal di browser. Pengguna mencetak melalui dialog browser dan memilih Simpan PDF. Logo standar diambil dari logo pada contoh Word.

Route `sidang.paper.preview` menampilkan HTML. Route `sidang.paper.download` mengunduh Word. Route lama `sidang.paper.print` tetap melayani unduhan Word untuk tautan yang sudah tersimpan. Ketiganya memakai policy prodi dan anggota panel yang sama. Mahasiswa tidak memperoleh akses melalui route ini.

Sebelum menerima format baru, periksa nama, NIM, judul, jenjang, program studi, pembimbing, dua penguji, tanggal, waktu, ruangan, kop, tanda tangan, nilai final, revisi, pembagian halaman, serta akses pengguna lain. Lampirkan Word dan PDF dari data demo pada bukti PR.
