<?php

use App\Http\Controllers\Admin\ChatThreadController;
use App\Http\Controllers\Admin\PenugasanController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('admin/dashboard');
    })->name('dashboard');

    Route::get('penugasan', [PenugasanController::class, 'index'])
        ->name('penugasan');
    Route::post('penugasan', [PenugasanController::class, 'store'])
        ->name('penugasan.store');

    Route::get('beban-dosen', function () {
        return Inertia::render('admin/beban-dosen');
    })->name('beban-dosen');

    Route::get('mahasiswa', function () {
        return Inertia::render('admin/mahasiswa');
    })->name('mahasiswa');

    Route::get('dosen', function () {
        return Inertia::render('admin/dosen');
    })->name('dosen');

    Route::get('aktivitas-sistem', function () {
        return Inertia::render('admin/aktivitas-sistem');
    })->name('aktivitas-sistem');

    Route::get('chat/threads/{thread}', [ChatThreadController::class, 'show'])
        ->name('chat.threads.show');
});
