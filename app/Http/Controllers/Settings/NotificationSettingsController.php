<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationSettingsUpdateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function update(NotificationSettingsUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $validated = $request->validated();

        $user->forceFill([
            'browser_notifications_enabled' => $validated['browserNotifications'],
            'notification_preferences' => [
                'pesanBaru' => $validated['pesanBaru'],
                'statusTugasAkhir' => $validated['statusTugasAkhir'],
                'jadwalBimbingan' => $validated['jadwalBimbingan'],
                'feedbackDokumen' => $validated['feedbackDokumen'],
                'reminderDeadline' => $validated['reminderDeadline'],
                'pengumumanSistem' => $validated['pengumumanSistem'],
                'konfirmasiBimbingan' => $validated['konfirmasiBimbingan'],
            ],
        ])->save();

        return back()->with('success', 'Pengaturan notifikasi berhasil diperbarui.');
    }

    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $user->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        if ($request->header('X-Inertia') === 'true') {
            return back();
        }

        return response()->json(['ok' => true]);
    }

    public function markAsRead(Request $request, string $notificationId): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $notification = $user->notifications()->where('id', $notificationId)->first();
        abort_if($notification === null, 404);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        if ($request->header('X-Inertia') === 'true') {
            return back();
        }

        return response()->json(['ok' => true]);
    }

    public function deleteReadNotifications(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $deleted = $user->readNotifications()->delete();

        if ($request->header('X-Inertia') === 'true') {
            return back();
        }

        return response()->json([
            'ok' => true,
            'deleted' => $deleted,
        ]);
    }

    public function deleteReadNotification(Request $request, string $notificationId): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $notification = $user->notifications()->where('id', $notificationId)->first();
        abort_if($notification === null, 404);

        if ($notification->read_at === null) {
            return response()->json([
                'message' => 'Hanya notifikasi yang sudah dibaca yang dapat dihapus.',
            ], 422);
        }

        $notification->delete();

        if ($request->header('X-Inertia') === 'true') {
            return back();
        }

        return response()->json(['ok' => true]);
    }
}
