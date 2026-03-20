<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AppRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreCsatResponseRequest;
use App\Models\CsatResponse;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CsatResponseController extends Controller
{
    public function show(Request $request): Response
    {
        [$user, $activeRole, $programStudi] = $this->resolveRespondentContext($request);

        $latestResponse = CsatResponse::query()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->first();

        $nextAvailableAt = $latestResponse?->cooldownEndsAt();

        return Inertia::render('settings/csat', [
            'csat' => [
                'respondentRole' => $activeRole,
                'programStudi' => $programStudi->name,
                'cooldownDays' => CsatResponse::COOLDOWN_DAYS,
                'canSubmit' => $nextAvailableAt === null || $nextAvailableAt->lte(now()),
                'nextAvailableAt' => $nextAvailableAt?->toIso8601String(),
                'lastSubmittedAt' => $latestResponse?->created_at?->toIso8601String(),
                'lastScore' => $latestResponse?->score,
            ],
            'status' => $request->session()->get('success'),
        ]);
    }

    public function store(StoreCsatResponseRequest $request): RedirectResponse
    {
        [$user, $activeRole, $programStudi] = $this->resolveRespondentContext($request);
        $validated = $request->validated();

        CsatResponse::query()->create([
            'user_id' => $user->id,
            'program_studi_id' => $programStudi->id,
            'respondent_role' => $activeRole,
            'score' => (int) $validated['score'],
            'kritik' => $this->nullableText($validated['kritik'] ?? null),
            'saran' => $this->nullableText($validated['saran'] ?? null),
        ]);

        return redirect()
            ->route('settings.csat.show')
            ->with('success', 'Terima kasih. Umpan balik Anda sudah tersimpan.');
    }

    /**
     * @return array{0: User, 1: string, 2: ProgramStudi}
     */
    private function resolveRespondentContext(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();
        abort_if($user === null, 401);

        $user->loadMissing(['mahasiswaProfile.programStudi', 'dosenProfile.programStudi']);

        $activeRole = $user->resolveActiveRole($request->session()->get('active_role'));
        abort_unless(in_array($activeRole, [AppRole::Mahasiswa->value, AppRole::Dosen->value], true), 403);

        $programStudi = match ($activeRole) {
            AppRole::Mahasiswa->value => $user->mahasiswaProfile?->programStudi,
            AppRole::Dosen->value => $user->dosenProfile?->programStudi,
            default => null,
        };

        abort_if($programStudi === null, 422, 'Program studi belum terhubung ke akun ini.');

        return [$user, $activeRole, $programStudi];
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
