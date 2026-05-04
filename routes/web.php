<?php

use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

$basePath = trim((string) config('app.base_path'), '/');

Route::prefix($basePath)->group(function (): void {
    Route::get('/', [PageController::class, 'home'])->name('home');

    Route::middleware(['auth', 'verified'])->group(function (): void {
        Route::get('dashboard', [PageController::class, 'dashboard'])->name('dashboard');

        Route::get('edit-profile', [PageController::class, 'editProfileRedirect'])->name('edit-profile');

        Route::get('settings', [PageController::class, 'settingNotifikasi'])->name('setting-notifikasi');

        Route::get('tugas-akhir', [PageController::class, 'tugasAkhir'])->name('tugas-akhir');

        Route::get('jadwal-bimbingan', [PageController::class, 'jadwalBimbingan'])->name('jadwal-bimbingan');

        Route::get('jadwal-bimbingan/ajukan', [PageController::class, 'jadwalBimbinganCreateRedirect'])
            ->name('jadwal-bimbingan.create');

        Route::get('upload-dokumen', [PageController::class, 'uploadDokumen'])->name('upload-dokumen');

        Route::get('upload-dokumen/unggah', [PageController::class, 'uploadDokumenCreateRedirect'])
            ->name('upload-dokumen.create');

        Route::get('pesan', [PageController::class, 'pesan'])->name('pesan');

        Route::get('panduan', [PageController::class, 'panduan'])->name('panduan');
    });

    require __DIR__.'/settings.php';
});
