<?php

use App\Http\Controllers\PortalController;
use App\Http\Controllers\RoleSwitchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', PortalController::class)->name('dashboard');
    Route::get('portal', PortalController::class)->name('portal');
    Route::post('role/switch', RoleSwitchController::class)->name('role.switch');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('edit-profile', '/settings/profile')->name('edit-profile');

    Route::get('settings', function () {
        return Inertia::render('setting-notifikasi');
    })->name('setting-notifikasi');

    Route::redirect('tugas-akhir', '/mahasiswa/tugas-akhir')->name('tugas-akhir');
    Route::redirect('jadwal-bimbingan', '/mahasiswa/jadwal-bimbingan')->name('jadwal-bimbingan');

    Route::redirect('jadwal-bimbingan/ajukan', '/mahasiswa/jadwal-bimbingan?open=ajukan')
        ->name('jadwal-bimbingan.create');

    Route::redirect('upload-dokumen', '/mahasiswa/upload-dokumen')->name('upload-dokumen');

    Route::redirect('upload-dokumen/unggah', '/mahasiswa/upload-dokumen?open=unggah')
        ->name('upload-dokumen.create');

    Route::redirect('pesan', '/mahasiswa/pesan')->name('pesan');
    Route::redirect('panduan', '/mahasiswa/panduan')->name('panduan');
});

require __DIR__.'/mahasiswa.php';
require __DIR__.'/dosen.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
