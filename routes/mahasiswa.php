<?php

use App\Http\Controllers\Mahasiswa\DashboardController;
use App\Http\Controllers\Mahasiswa\JadwalBimbinganController;
use App\Http\Controllers\Mahasiswa\PanduanController;
use App\Http\Controllers\Mahasiswa\PesanController;
use App\Http\Controllers\Mahasiswa\TugasAkhirController;
use App\Http\Controllers\Mahasiswa\UploadDokumenController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('tugas-akhir', [TugasAkhirController::class, 'index'])->name('tugas-akhir');
    Route::post('tugas-akhir', [TugasAkhirController::class, 'store'])->name('tugas-akhir.store');
    Route::patch('tugas-akhir/{project}', [TugasAkhirController::class, 'update'])->name('tugas-akhir.update');
    Route::patch('tugas-akhir/{project}/sempro-documents', [TugasAkhirController::class, 'updateSemproDocuments'])->name('tugas-akhir.sempro-documents.update');
    Route::patch('tugas-akhir/{project}/sidang-documents', [TugasAkhirController::class, 'updateSidangDocuments'])->name('tugas-akhir.sidang-documents.update');

    Route::get('jadwal-bimbingan', [JadwalBimbinganController::class, 'index'])->name('jadwal-bimbingan');
    Route::post('jadwal-bimbingan', [JadwalBimbinganController::class, 'store'])->name('jadwal-bimbingan.store');

    Route::redirect('jadwal-bimbingan/ajukan', '/mahasiswa/jadwal-bimbingan?open=ajukan')
        ->name('jadwal-bimbingan.create');

    Route::get('upload-dokumen', [UploadDokumenController::class, 'index'])->name('upload-dokumen');
    Route::post('upload-dokumen', [UploadDokumenController::class, 'store'])->name('upload-dokumen.store');
    Route::delete('upload-dokumen/{document}', [UploadDokumenController::class, 'destroy'])->name('upload-dokumen.destroy');

    Route::redirect('upload-dokumen/unggah', '/mahasiswa/upload-dokumen?open=unggah')
        ->name('upload-dokumen.create');

    Route::get('pesan', [PesanController::class, 'index'])->name('pesan');
    Route::post('pesan/{thread}/messages', [PesanController::class, 'storeMessage'])->name('pesan.messages.store');

    Route::get('panduan', PanduanController::class)->name('panduan');
});
