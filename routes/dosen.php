<?php

use App\Http\Controllers\Dosen\DashboardController;
use App\Http\Controllers\Dosen\DokumenRevisiController;
use App\Http\Controllers\Dosen\JadwalBimbinganController;
use App\Http\Controllers\Dosen\MahasiswaBimbinganController;
use App\Http\Controllers\Dosen\PesanBimbinganController;
use App\Http\Controllers\Dosen\SeminarProposalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:dosen'])->prefix('dosen')->name('dosen.')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('seminar-proposal', [SeminarProposalController::class, 'index'])->name('seminar-proposal');
    Route::post('seminar-proposal/{defense}/decision', [SeminarProposalController::class, 'submitDecision'])
        ->name('seminar-proposal.decision');
    Route::post('seminar-proposal/revisions/{revision}/resolve', [SeminarProposalController::class, 'resolveRevision'])
        ->name('seminar-proposal.revisions.resolve');

    Route::get('mahasiswa-bimbingan', MahasiswaBimbinganController::class)->name('mahasiswa-bimbingan');

    Route::get('jadwal-bimbingan', [JadwalBimbinganController::class, 'index'])->name('jadwal-bimbingan');
    Route::post('jadwal-bimbingan/{schedule}/decision', [JadwalBimbinganController::class, 'decide'])
        ->name('jadwal-bimbingan.decision');
    Route::post('jadwal-bimbingan/recurring/{groupId}/decision', [JadwalBimbinganController::class, 'decideRecurringGroup'])
        ->name('jadwal-bimbingan.recurring.decision');

    Route::get('dokumen-revisi', [DokumenRevisiController::class, 'index'])->name('dokumen-revisi');
    Route::post('dokumen-revisi/{document}/review', [DokumenRevisiController::class, 'review'])
        ->name('dokumen-revisi.review');

    Route::get('pesan', [PesanBimbinganController::class, 'index'])->name('pesan');
    Route::post('pesan/private', [PesanBimbinganController::class, 'storePrivateThread'])
        ->name('pesan.private.store');
    Route::post('pesan/{thread}/messages', [PesanBimbinganController::class, 'storeMessage'])
        ->name('pesan.messages.store');
    Route::post('pesan/{thread}/read', [PesanBimbinganController::class, 'markAsRead'])
        ->name('pesan.read');

    Route::get('pesan-bimbingan', function (Request $request) {
        return redirect()->to('/dosen/pesan'.($request->getQueryString() ? '?'.$request->getQueryString() : ''));
    })->name('pesan-bimbingan');
    Route::post('pesan-bimbingan/private', [PesanBimbinganController::class, 'storePrivateThread']);
    Route::post('pesan-bimbingan/{thread}/messages', [PesanBimbinganController::class, 'storeMessage']);
    Route::post('pesan-bimbingan/{thread}/read', [PesanBimbinganController::class, 'markAsRead']);
});
