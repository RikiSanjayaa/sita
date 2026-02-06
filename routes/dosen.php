<?php

use App\Http\Controllers\Dosen\DashboardController;
use App\Http\Controllers\Dosen\DokumenRevisiController;
use App\Http\Controllers\Dosen\JadwalBimbinganController;
use App\Http\Controllers\Dosen\MahasiswaBimbinganController;
use App\Http\Controllers\Dosen\PesanBimbinganController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:dosen'])->prefix('dosen')->name('dosen.')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('mahasiswa-bimbingan', MahasiswaBimbinganController::class)->name('mahasiswa-bimbingan');

    Route::get('jadwal-bimbingan', [JadwalBimbinganController::class, 'index'])->name('jadwal-bimbingan');
    Route::post('jadwal-bimbingan/{schedule}/decision', [JadwalBimbinganController::class, 'decide'])
        ->name('jadwal-bimbingan.decision');

    Route::get('dokumen-revisi', [DokumenRevisiController::class, 'index'])->name('dokumen-revisi');
    Route::post('dokumen-revisi/{document}/review', [DokumenRevisiController::class, 'review'])
        ->name('dokumen-revisi.review');

    Route::get('pesan-bimbingan', [PesanBimbinganController::class, 'index'])->name('pesan-bimbingan');
    Route::post('pesan-bimbingan/{thread}/messages', [PesanBimbinganController::class, 'storeMessage'])
        ->name('pesan-bimbingan.messages.store');
});
