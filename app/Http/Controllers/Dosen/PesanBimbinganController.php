<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Services\DosenBimbinganService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PesanBimbinganController extends Controller
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
    ) {}

    public function index(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $studentIds = $this->dosenBimbinganService->activeStudentIds($lecturer);

        $threads = MentorshipChatThread::query()
            ->with([
                'student',
                'latestMessage.sender',
                'latestMessage.relatedDocument',
                'messages' => fn ($query) => $query->with(['sender', 'relatedDocument'])->latest('created_at')->limit(30),
            ])
            ->whereIn('student_user_id', $studentIds)
            ->get()
            ->map(function (MentorshipChatThread $thread) use ($lecturer): array {
                $latestMessage = $thread->latestMessage;
                $unreadCount = $thread->messages
                    ->where('sender_user_id', '!=', $lecturer->id)
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count();

                return [
                    'id' => $thread->id,
                    'student' => $thread->student?->name ?? '-',
                    'unread' => $unreadCount,
                    'preview' => $latestMessage?->message ?? 'Belum ada pesan',
                    'lastTime' => $latestMessage?->created_at?->diffForHumans() ?? '-',
                    'isEscalated' => $thread->is_escalated,
                    'messages' => $thread->messages
                        ->sortBy('created_at')
                        ->values()
                        ->map(function (MentorshipChatMessage $message): array {
                            return [
                                'id' => $message->id,
                                'author' => $message->sender?->name ?? 'Sistem',
                                'message' => $message->message,
                                'time' => $message->created_at->format('d M Y H:i'),
                                'type' => $message->message_type,
                                'documentName' => $message->relatedDocument?->file_name,
                                'documentUrl' => $message->relatedDocument?->file_url,
                            ];
                        })
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('dosen/pesan-bimbingan', [
            'threads' => $threads,
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function storeMessage(Request $request, MentorshipChatThread $thread): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $studentIds = $this->dosenBimbinganService->activeStudentIds($lecturer);
        abort_unless(in_array($thread->student_user_id, $studentIds, true), 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $thread->messages()->create([
            'sender_user_id' => $lecturer->id,
            'message_type' => 'text',
            'message' => trim($data['message']),
            'sent_at' => now(),
        ]);

        return back()->with('success', 'Pesan berhasil dikirim.');
    }
}
