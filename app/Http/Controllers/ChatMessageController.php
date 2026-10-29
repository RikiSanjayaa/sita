<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageUpdated;
use App\Http\Requests\Chat\UpdateChatMessageRequest;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Services\ChatMessagePayloadBuilder;
use App\Services\ChatUnreadService;
use App\Services\CompletedChatThreadService;
use App\Services\MentorshipTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ChatMessageController extends Controller
{
    public function show(Request $request, MentorshipChatThread $thread, MentorshipChatMessage $message, ChatUnreadService $unread, ChatMessagePayloadBuilder $payloads): JsonResponse
    {
        $this->authorizeThread($request, $thread, $message, $unread);
        $context = new MentorshipChatMessage(['mentorship_chat_thread_id' => $thread->id]);
        $context->setRelation('replyTo', $message);
        abort_unless($payloads->replyContext($context)['available'], 404);

        return response()->json(['threadId' => $thread->id, 'message' => $payloads->build($message)])
            ->header('Cache-Control', 'no-store, private');
    }

    public function update(UpdateChatMessageRequest $request, MentorshipChatThread $thread, MentorshipChatMessage $message, ChatUnreadService $unread, ChatMessagePayloadBuilder $payloads, CompletedChatThreadService $completed, MentorshipTicketService $tickets): JsonResponse
    {
        $role = $this->authorizeThread($request, $thread, $message, $unread);
        abort_unless((int) $message->sender_user_id === (int) $request->user()->id, 403);
        abort_unless(in_array($message->message_type, ['text', 'attachment', 'revision_suggestion'], true), 403);

        if ($completed->isReadOnly($thread, $request->user())) {
            throw ValidationException::withMessages(['message' => CompletedChatThreadService::READ_ONLY_MESSAGE]);
        }
        if ($role === 'mahasiswa' && ($suspension = $tickets->suspensionMessageForStudent($request->user()->id)) !== null) {
            throw ValidationException::withMessages(['message' => $suspension]);
        }

        $text = trim((string) $request->validated('message'));
        if ($text === '' && $message->attachment_path === null) {
            throw ValidationException::withMessages(['message' => 'Pesan teks tidak boleh kosong.']);
        }
        if ($message->message !== $text) {
            $message->update(['message' => $text, 'edited_at' => now()]);
            try {
                broadcast(new ChatMessageUpdated($thread->id, $payloads->build($message)))->toOthers();
            } catch (Throwable $exception) {
                Log::warning('Chat edit broadcast unavailable.', ['thread_id' => $thread->id, 'error' => $exception->getMessage()]);
            }
        }

        return response()->json(['threadId' => $thread->id, 'message' => $payloads->build($message)])
            ->header('Cache-Control', 'no-store, private');
    }

    private function authorizeThread(Request $request, MentorshipChatThread $thread, MentorshipChatMessage $message, ChatUnreadService $unread): string
    {
        $user = $request->user();
        abort_if($user === null, 401);
        $role = $user->resolveActiveRole($request->session()->get('active_role'));
        abort_unless($unread->canSeeThread($user, $role, $thread), 403);
        abort_unless((int) $message->mentorship_chat_thread_id === (int) $thread->id, 404);

        return $role;
    }
}
