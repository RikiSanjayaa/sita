<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Models\MentorshipChatMessage;
use App\Services\MentorshipAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatAttachmentDownloadController extends Controller
{
    public function __construct(
        private readonly MentorshipAccessService $mentorshipAccessService,
    ) {}

    public function __invoke(Request $request, MentorshipChatMessage $message): StreamedResponse
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

        return Storage::disk($message->attachment_disk)->download(
            $message->attachment_path,
            $message->attachment_name ?? basename($message->attachment_path),
        );
    }
}
