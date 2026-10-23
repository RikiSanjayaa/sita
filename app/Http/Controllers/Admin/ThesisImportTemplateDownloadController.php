<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AcademicTerminology;
use App\Support\SimpleXlsxWriter;
use Illuminate\Http\Response;

class ThesisImportTemplateDownloadController extends Controller
{
    public function __invoke(): Response
    {
        $phases = AcademicTerminology::phaseLabels();

        $templateRows = [
            ['nim', 'judul', 'pembimbing_1_nik', 'pembimbing_2_nik', 'tahap', 'tanggal_mulai_tiket'],
            ['2210510001', 'Rancang Bangun Sistem Informasi Akademik', '7301010101010001', '7301010101010002', $phases['research'], '2026-02-01'],
            ['2210510002', 'Analisis Sentimen Ulasan Aplikasi Mobile', '', '', $phases['title_review'], ''],
        ];

        $guideRows = [
            ['Panduan Import Tugas Akhir Berjalan', '', ''],
            ['Kolom', 'Wajib', 'Catatan'],
            ['nim', 'ya', 'Mahasiswa harus sudah ada (import user lebih dulu) dan belum punya tugas akhir aktif.'],
            ['judul', 'ya', 'Menjadi judul versi 1 (disetujui, kecuali tahap '.$phases['title_review'].').'],
            ['pembimbing_1_nik', 'ya, kecuali tahap '.$phases['title_review'], 'NIK dosen yang mengajar di program studi mahasiswa.'],
            ['pembimbing_2_nik', 'tidak', 'Harus berbeda dari pembimbing 1.'],
            ['tahap', 'ya', implode(', ', $phases).'. Kode '.implode('/', array_keys($phases)).' juga diterima.'],
            ['tanggal_mulai_tiket', 'tidak', 'Format YYYY-MM-DD. Mengisi masa berlaku tiket bimbingan; kosong berarti tiket dimulai hari import (tahap setelah pembimbing ditetapkan).'],
            ['Catatan', '', 'Jika ada satu baris yang salah, tidak ada data yang tersimpan. Perbaiki file lalu unggah ulang.'],
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
            'Content-Disposition' => 'attachment; filename="thesis-import-template.xlsx"',
        ]);
    }
}
