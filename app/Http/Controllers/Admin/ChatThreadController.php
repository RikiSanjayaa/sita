<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MentorshipChatThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatThreadController extends Controller
{
    public function show(Request $request, MentorshipChatThread $thread): JsonResponse
    {
        abort_unless($request->boolean('escalated') || $thread->is_escalated, 403);

        $thread->load('messages.sender');

        return response()->json([
            'thread_id' => $thread->id,
            'messages' => $thread->messages
                ->sortBy('created_at')
                ->values()
                ->map(fn ($message): array => [
                    'author' => $message->sender?->name ?? 'Sistem',
                    'message' => $message->message,
                    'time' => $message->created_at->format('Y-m-d H:i'),
                    'attachment_name' => $message->attachment_name,
                    'attachment_url' => $message->attachment_path === null
                        ? ($message->related_document_id === null
                            ? null
                            : route('files.documents.download', [
                                'document' => $message->related_document_id,
                                'escalated' => 1,
                            ]))
                        : route('files.chat-attachments.download', [
                            'message' => $message->id,
                            'escalated' => 1,
                        ]),
                ])
                ->all(),
        ]);
    }
}
