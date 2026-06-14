<?php

namespace App\Http\Controllers;

use App\Enums\AppRole;
use App\Models\User;
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
    ) {}

    public function __invoke(Request $request, User $user): Response
    {
        $viewer = $request->user();
        abort_if($viewer === null, 401);

        if (! $viewer->is($user) && ! $user->hasAnyRole([AppRole::Mahasiswa->value, AppRole::Dosen->value])) {
            abort(404);
        }

        return Inertia::render('profile/show', [
            'profile' => $this->userProfilePresenter->detail($user),
            'canEditProfile' => $viewer->is($user),
            'canStartPrivateChat' => ! $viewer->is($user)
                && $this->privateChatService->canUsePrivateChat($viewer)
                && $this->privateChatService->canUsePrivateChat($user),
            'privateChatEndpoint' => $viewer->hasRole(AppRole::Mahasiswa)
                ? route('mahasiswa.pesan.private.store')
                : ($viewer->hasAnyRole([AppRole::Dosen->value, AppRole::Kaprodi->value])
                    ? route('dosen.pesan-bimbingan.private.store')
                    : null),
        ]);
    }
}
