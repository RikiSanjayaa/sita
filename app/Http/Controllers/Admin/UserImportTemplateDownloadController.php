<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SimpleXlsxWriter;
use Illuminate\Http\Response;

class UserImportTemplateDownloadController extends Controller
{
    public function __invoke(string $format): Response
    {
        abort_unless($format === 'xlsx', 404);

        $templateRows = [
            ['nama', 'email', 'no_hp', 'role', 'password', 'nim', 'angkatan', 'jenjang', 'konsentrasi', 'penempatan_dosen', 'nik', 'kuota_bimbingan', 'bidang_keilmuan', 'kurikulum_obe'],
            ['Muhammad Akbar', 'akbar@sita.test', '081234567890', 'mahasiswa', 'Rahasia123!', '2210510001', '2022', 's1', 'Jaringan', '', '', '', '', 'tidak'],
            ['Dr. Budi Santoso', 'budi@sita.test', '081298765432', 'dosen', 'Rahasia123!', '', '', '', '', 'Ilmu Komputer:Jaringan|Teknologi Informasi:Umum', '7301010101010001', '12', 'Jaringan Komputer|Keamanan Siber', ''],
            ['Admin SITA', 'admin2@sita.test', '', 'admin', 'Rahasia123!', '', '', '', '', '', '', '', '', ''],
        ];

        $guideRows = [
            ['Panduan Import User', '', ''],
            ['Peran', 'Field wajib', 'Catatan'],
            ['Mahasiswa', 'nama, email, password, nim, angkatan, jenjang, konsentrasi', 'Jenjang harus tersedia pada Program Studi yang dipilih dari dropdown import. Kolom kurikulum_obe diisi ya/tidak, 1/0, atau true/false; kosong berarti tidak OBE.'],
            ['Dosen', 'nama, email, password, nik, konsentrasi atau penempatan_dosen', 'Bidang keilmuan dapat dipisahkan dengan |. Penempatan multi prodi memakai format Prodi:Konsentrasi|Prodi 2:Konsentrasi 2.'],
            ['Admin', 'nama, email, password', 'Kolom NIM, angkatan, konsentrasi, NIK, dan kuota bimbingan dikosongkan.'],
            ['Nilai role', 'mahasiswa, dosen, admin', 'Gunakan salah satu nilai role ini agar data masuk ke profil yang sesuai.'],
            ['Nilai jenjang', 'd3, s1, s2', 'Wajib sesuai dengan daftar jenjang pada Program Studi mahasiswa.'],
        ];

        $xlsx = (new SimpleXlsxWriter)->build([
            [
                'name' => 'Template Import',
                'rows' => $templateRows,
                'headerRows' => [1],
            ],
            [
                'name' => 'Panduan',
                'rows' => $guideRows,
                'headerRows' => [1, 2],
            ],
        ]);

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="user-import-template.xlsx"',
        ]);
    }
}
