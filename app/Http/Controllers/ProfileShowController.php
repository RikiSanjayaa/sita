<?php

namespace App\Http\Controllers;

use App\Enums\AppRole;
use App\Models\ThesisProject;
use App\Models\User;
use App\Services\MentorshipSessionNoteService;
use App\Services\PrivateChatService;
use App\Services\UserProfilePresenter;
use Illuminate\Database\Eloquent\Builder;
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
        if ($user->hasRole(AppRole::Mahasiswa)) {
            $projects = ThesisProject::query()
                ->where('student_user_id', $user->id)
                ->latest('started_at')
                ->get();
            $sessions = $projects
                ->flatMap(fn (ThesisProject $project): array => $this->sessionNoteService->forProject($project, $viewer))
                ->values();

            if ($viewer->is($user) || $sessions->isNotEmpty()
                || $projects->contains(fn (ThesisProject $project): bool => $this->sessionNoteService->canMonitor($project, $viewer))
                || ($viewer->hasRole(AppRole::Dosen) && ThesisProject::query()
                    ->whereIn('id', $projects->modelKeys())
                    ->where(fn (Builder $query) => $query
                        ->whereHas('supervisorAssignments', fn (Builder $assignment) => $assignment->where('lecturer_user_id', $viewer->id))
                        ->orWhereHas('defenses.examiners', fn (Builder $examiner) => $examiner->where('lecturer_user_id', $viewer->id)))
                    ->exists())) {
                $monitoringProps['monitoringSessionNotes'] = $sessions->all();
                $monitoringProps['monitoringLecturers'] = User::query()
                    ->whereIn('id', $sessions->pluck('lecturerUserId')->unique())
                    ->get()
                    ->map(fn (User $lecturer): ?array => $this->userProfilePresenter->summary($lecturer))
                    ->filter()
                    ->values()
                    ->all();
            }
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
