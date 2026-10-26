<?php

namespace App\Http\Controllers;

use App\Enums\AppRole;
use App\Models\ThesisProject;
use App\Models\User;
use App\Services\MentorshipSessionNoteService;
use App\Services\PrivateChatService;
use App\Services\UserProfilePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileShowController extends Controller
{
    public function __construct(
        private readonly PrivateChatService $privateChatService,
        private readonly UserProfilePresenter $userProfilePresenter,
        private readonly MentorshipSessionNoteService $sessionNoteService,
    ) {}

    public function __invoke(Request $request, User $user): Response
    {
        $viewer = $request->user();
        abort_if($viewer === null, 401);

        if (! $viewer->is($user) && ! $user->hasAnyRole([AppRole::Mahasiswa->value, AppRole::Dosen->value])) {
            abort(404);
        }

        $monitoringProps = [];
        $programStudiId = $viewer->hasRole(AppRole::Kaprodi)
            ? $viewer->kaprodiAssignment?->program_studi_id
            : null;

        if ($programStudiId !== null
            && $user->hasRole(AppRole::Mahasiswa)
            && (int) $user->mahasiswaProfile?->program_studi_id === (int) $programStudiId) {
            $monitoringProps['monitoringSessionNotes'] = ThesisProject::query()
                ->where('program_studi_id', $programStudiId)
                ->where('student_user_id', $user->id)
                ->latest('started_at')
                ->get()
                ->flatMap(fn (ThesisProject $project): array => $this->sessionNoteService->forProject($project, $viewer))
                ->values()
                ->all();
        }

        return Inertia::render('profile/show', [
            ...$monitoringProps,
            'profile' => $this->userProfilePresenter->detail($user, $viewer),
            'canEditProfile' => $viewer->is($user),
            'canStartPrivateChat' => ! $viewer->is($user)
                && $this->privateChatService->canUsePrivateChat($viewer)
                && $this->privateChatService->canUsePrivateChat($user),
            'privateChatEndpoint' => $viewer->hasRole(AppRole::Mahasiswa)
                ? route('mahasiswa.pesan.private.store')
                : ($viewer->hasAnyRole([AppRole::Dosen->value, AppRole::Kaprodi->value])
                    ? route('dosen.pesan.private.store')
                    : null),
        ]);
    }
}
