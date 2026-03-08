<?php

use App\Http\Controllers\Admin\UserImportTemplateDownloadController;
use App\Http\Controllers\File\ChatAttachmentDownloadController;
use App\Http\Controllers\File\DocumentDownloadController;
use App\Http\Controllers\File\ThesisDocumentDownloadController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProfileShowController;
use App\Http\Controllers\RoleSwitchController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [WelcomeController::class, 'index'])->name('home');
Route::get('/jadwal', [WelcomeController::class, 'schedules'])->name('public.schedules');
Route::get('/pembimbing', [WelcomeController::class, 'advisors'])->name('public.advisors');
Route::get('/topik', [WelcomeController::class, 'topics'])->name('public.topics');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', PortalController::class)->name('dashboard');
    Route::get('portal', PortalController::class)->name('portal');
    Route::post('role/switch', RoleSwitchController::class)->name('role.switch');
    Route::get('profil/{user}', ProfileShowController::class)->name('users.profile.show');
    Route::get('files/documents/{document}/download', DocumentDownloadController::class)->name('files.documents.download');
    Route::get('files/chat-attachments/{message}/download', ChatAttachmentDownloadController::class)->name('files.chat-attachments.download');
    Route::get('files/thesis-documents/{document}/download', ThesisDocumentDownloadController::class)->name('files.thesis-documents.download');
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

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('admin/import-template.{format}', UserImportTemplateDownloadController::class)
        ->whereIn('format', ['csv', 'xlsx'])
        ->name('admin.users.import-template');
});

require __DIR__.'/mahasiswa.php';
require __DIR__.'/dosen.php';
require __DIR__.'/settings.php';
