<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('tugas-akhir', function () {
        return Inertia::render('tugas-akhir');
    })->name('tugas-akhir');

    Route::get('jadwal-bimbingan', function () {
        return Inertia::render('jadwal-bimbingan');
    })->name('jadwal-bimbingan');

    Route::redirect('jadwal-bimbingan/ajukan', '/mahasiswa/jadwal-bimbingan?open=ajukan')
        ->name('jadwal-bimbingan.create');

    Route::get('upload-dokumen', function () {
        return Inertia::render('upload-dokumen');
    })->name('upload-dokumen');

    Route::redirect('upload-dokumen/unggah', '/mahasiswa/upload-dokumen?open=unggah')
        ->name('upload-dokumen.create');

    Route::get('pesan', function () {
        return Inertia::render('pesan');
    })->name('pesan');

    Route::get('panduan', function () {
        return Inertia::render('panduan');
    })->name('panduan');
});
