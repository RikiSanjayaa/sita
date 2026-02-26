<?php

use App\Http\Controllers\Mahasiswa\JadwalBimbinganController;
use App\Http\Controllers\Mahasiswa\PesanController;
use App\Http\Controllers\Mahasiswa\UploadDokumenController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('tugas-akhir', function () {
        return Inertia::render('tugas-akhir');
    })->name('tugas-akhir');

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
    Route::post('pesan/messages', [PesanController::class, 'storeMessage'])->name('pesan.messages.store');

    Route::get('panduan', function () {
        return Inertia::render('panduan');
    })->name('panduan');
});
