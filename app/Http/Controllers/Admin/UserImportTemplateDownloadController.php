<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SimpleXlsxWriter;
use Illuminate\Http\Response;

class UserImportTemplateDownloadController extends Controller
{
    public function __invoke(string $kind): Response
    {
        abort_unless(in_array($kind, ['mahasiswa', 'dosen', 'admin'], true), 404);

        [$columns, $example, $required, $guidance] = match ($kind) {
            'mahasiswa' => [
                ['nim', 'angkatan', 'jenjang', 'konsentrasi', 'kurikulum_obe'],
                ['2210519901', '2022', 's1', 'Jaringan', 'ya'],
                'nama, email, password, nim, angkatan, jenjang, konsentrasi',
                [
                    ['Jenjang', 'd3, s1, s2', 'Harus tersedia pada Program Studi yang dipilih saat import.'],
                    ['Konsentrasi', 'Sesuai Program Studi', 'Ganti contoh Jaringan dengan konsentrasi yang tersedia pada prodi tujuan.'],
                    ['Kurikulum OBE', 'ya/tidak, 1/0, true/false', 'Kosong berarti tidak OBE. Kolom ini hanya untuk mahasiswa.'],
                ],
            ],
            'dosen' => [
                ['konsentrasi', 'penempatan_dosen', 'nik', 'kuota_bimbingan', 'bidang_keilmuan'],
                ['', 'ilkom:Jaringan|ti:Umum', '7301010101990001', '12', 'Jaringan Komputer|Keamanan Siber'],
                'nama, email, password, nik, konsentrasi atau penempatan_dosen',
                [
                    ['Penempatan dosen', 'Slug Prodi:Konsentrasi|Slug Prodi 2:Konsentrasi 2', 'Nama atau slug prodi dan konsentrasi harus tersedia. Gunakan slug jika beberapa prodi memiliki nama sama, misalnya ilkom dan s2-ilmu-komputer. Pisahkan dengan | atau ;. Penempatan pertama menjadi prodi utama, menggantikan dropdown import.'],
                    ['Satu penempatan', 'konsentrasi', 'Jika penempatan_dosen kosong, isi konsentrasi sesuai Program Studi pada dropdown import.'],
                    ['Bidang keilmuan', 'Nama, slug atau ID bidang aktif', 'Opsional; pisahkan beberapa bidang yang tersedia dengan |. Ganti atau hapus contoh sesuai data master.'],
                    ['Kuota bimbingan', 'Bilangan bulat minimal 1', 'Opsional; kosong mempertahankan kuota akun lama atau memakai 14 untuk akun baru.'],
                ],
            ],
            'admin' => [
                [],
                [],
                'nama, email, password',
                [
                    ['Otorisasi', 'Super admin', 'Hanya super admin dapat mengimpor akun admin.'],
                    ['Penempatan admin', 'Program Studi pada dropdown import', 'Akun admin ditempatkan pada prodi yang dipilih; tidak memiliki kolom profil mahasiswa atau dosen.'],
                ],
            ],
        };

        $label = ucfirst($kind);
        $templateRows = [
            ['nama', 'email', 'no_hp', 'role', 'password', ...$columns],
            ["Contoh {$label}", "contoh-{$kind}@sita.test", '081234567890', $kind, 'Rahasia123!', ...$example],
        ];
        $guideRows = [
            ["Panduan import {$label}", '', ''],
            ['Bagian', 'Isian', 'Catatan'],
            ['Field wajib', $required, 'Isi data pada sheet pertama tanpa mengubah header. Hapus atau ganti baris contoh sebelum import.'],
            ['Role', $kind, "Pertahankan nilai {$kind}; kolom role menentukan peran setiap baris. Tipe Import hanya menjadi default jika role kosong."],
            ['Program Studi', 'Pilih pada dialog Import Excel', 'Tidak perlu ditulis sebagai kolom file. Admin prodi tetap memakai cakupan prodinya.'],
            ['Identitas akun', 'nama, email, password; no_hp opsional', 'Password minimal 8 karakter. Email yang sudah ada akan diperbarui, termasuk password dan perannya. Gunakan format teks untuk nomor HP, NIM atau NIK agar angka nol awal tidak hilang.'],
            ...$guidance,
        ];

        $xlsx = (new SimpleXlsxWriter)->build([
            ['name' => 'Template Import', 'rows' => $templateRows, 'headerRows' => [1]],
            ['name' => 'Panduan', 'rows' => $guideRows, 'headerRows' => [1, 2]],
        ]);

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="user-import-'.$kind.'.xlsx"',
        ]);
    }
}
