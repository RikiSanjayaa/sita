<?php

use App\Http\Controllers\Kaprodi\DashboardController;
use App\Http\Controllers\Kaprodi\DokumenController;
use App\Http\Controllers\Kaprodi\DosenTerlibatController;
use App\Http\Controllers\Kaprodi\MahasiswaController;
use App\Http\Controllers\Kaprodi\MahasiswaDetailController;
use App\Http\Controllers\Kaprodi\ProjectWorkflowController;
use App\Http\Controllers\Kaprodi\SemproSidangController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:kaprodi'])->prefix('kaprodi')->name('kaprodi.')->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('mahasiswa', MahasiswaController::class)->name('mahasiswa.index');
    Route::get('mahasiswa/{student}', MahasiswaDetailController::class)->name('mahasiswa.show');
    Route::get('sempro-sidang', SemproSidangController::class)->name('sempro-sidang');
    Route::post('projects/{project}/supervisors', [ProjectWorkflowController::class, 'assignSupervisors'])->name('projects.supervisors');
    Route::post('projects/{project}/sempro', [ProjectWorkflowController::class, 'scheduleSempro'])->name('projects.sempro');
    Route::post('projects/{project}/sidang', [ProjectWorkflowController::class, 'scheduleSidang'])->name('projects.sidang');
    Route::get('dokumen', DokumenController::class)->name('dokumen');
    Route::redirect('dosen-terlibat', '/kaprodi/dosen-prodi')->name('dosen-terlibat.redirect');
    Route::get('dosen-prodi', DosenTerlibatController::class)->name('dosen-prodi');
    Route::redirect('arsip', '/kaprodi/mahasiswa')->name('arsip');
});
