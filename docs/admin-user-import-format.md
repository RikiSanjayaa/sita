# Admin User Import Format

Gunakan tombol `Download CSV Template` atau `Download Excel Template` di halaman `Admin > Users`.
Template `.xls` dibuat agar mudah dibuka di Excel, lalu simpan ke CSV untuk proses import.

Header wajib mengikuti format ini:

- `name`
- `email`
- `role` (opsional jika sudah memilih `Import Type`)
- `password`
- `nim`
- `prodi`
- `angkatan`
- `nik`
- `kurikulum_obe` (opsional, khusus mahasiswa)

## Aturan

- Kolom wajib untuk semua: `name`, `email`, `password`
- Role valid: `mahasiswa`, `dosen`, `admin`
- Mahasiswa wajib mengisi: `nim`, `prodi`, `angkatan`
- Dosen wajib mengisi: `nik`, `prodi`
- `kurikulum_obe` menerima `ya`/`tidak`, `1`/`0`, atau `true`/`false` (tidak peka huruf besar/kecil). Kosong berarti tidak OBE. Nilai lain menghasilkan error pada baris tersebut. File tanpa kolom ini tetap bisa diimport dan tidak mengubah flag yang sudah ada.

## Contoh Baris

```csv
name,email,role,password,nim,prodi,angkatan,nik
Muhammad Akbar,akbar@sita.test,mahasiswa,,2210510001,Informatika,2022,
Dr. Budi Santoso,budi@sita.test,dosen,,,Informatika,,,7301010101010001
Admin SITA,admin2@sita.test,admin,,,,,,,
```
