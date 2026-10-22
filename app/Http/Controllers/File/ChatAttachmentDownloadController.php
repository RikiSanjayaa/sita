<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Models\MentorshipChatMessage;
use App\Services\MentorshipAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ChatAttachmentDownloadController extends Controller
{
    public function __construct(
        private readonly MentorshipAccessService $mentorshipAccessService,
    ) {}

    public function __invoke(Request $request, MentorshipChatMessage $message): Response
    {
        $attachment = $this->authorizedAttachment($request, $message);

        return Storage::disk($attachment->attachment_disk)->download(
            $attachment->attachment_path,
            $attachment->attachment_name ?? basename($attachment->attachment_path),
        );
    }

    public function preview(Request $request, MentorshipChatMessage $message): Response
    {
        $attachment = $this->authorizedAttachment($request, $message);
        abort_unless(str_starts_with((string) $attachment->attachment_mime, 'image/'), 404);

        return Storage::disk($attachment->attachment_disk)->response(
            $attachment->attachment_path,
            $attachment->attachment_name ?? basename($attachment->attachment_path),
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }

    private function authorizedAttachment(Request $request, MentorshipChatMessage $message): MentorshipChatMessage
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $message->loadMissing('thread');
        $thread = $message->thread;
        abort_if($thread === null, 404);

        if ($user->hasRole('admin')) {
            abort_unless($request->boolean('escalated') || $thread->is_escalated, 403);
        } else {
            abort_unless($this->mentorshipAccessService->canAccessThread($user, $thread), 403);
        }

        abort_if($message->attachment_disk === null || $message->attachment_path === null, 404);
        abort_unless(Storage::disk($message->attachment_disk)->exists($message->attachment_path), 404);

        return $message;
    }
}
