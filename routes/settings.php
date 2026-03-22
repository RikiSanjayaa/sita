<?php

use App\Http\Controllers\Settings\CsatResponseController;
use App\Http\Controllers\Settings\NotificationSettingsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/notifications', function () {
        return Inertia::render('settings/notifications');
    })->name('setting-notifikasi');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/csat', [CsatResponseController::class, 'show'])
        ->name('settings.csat.show');

    Route::post('settings/csat', [CsatResponseController::class, 'store'])
        ->name('settings.csat.store');

    Route::patch('settings/notifications', [NotificationSettingsController::class, 'update'])
        ->name('settings.notifications.update');

    Route::post('settings/notifications/read-all', [NotificationSettingsController::class, 'markAllAsRead'])
        ->name('settings.notifications.read-all');

    Route::post('settings/notifications/{notificationId}/read', [NotificationSettingsController::class, 'markAsRead'])
        ->name('settings.notifications.read');

    Route::delete('settings/notifications/read-items', [NotificationSettingsController::class, 'deleteReadNotifications'])
        ->name('settings.notifications.delete-read');

    Route::delete('settings/notifications/{notificationId}', [NotificationSettingsController::class, 'deleteReadNotification'])
        ->name('settings.notifications.delete-one');
});
