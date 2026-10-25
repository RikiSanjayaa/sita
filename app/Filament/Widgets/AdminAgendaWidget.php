<?php

namespace App\Filament\Widgets;

use App\Models\ThesisDefense;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class AdminAgendaWidget extends Widget
{
    public const DAYS_AHEAD = 7;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];

    protected string $view = 'filament.widgets.admin-agenda';

    /** @return Collection<int, ThesisDefense> */
    public function getExams(): Collection
    {
        /** @var User|null $user */
        $user = Auth::user();

        return self::agendaQuery($user)->orderBy('scheduled_for')->get();
    }

    /**
     * Scheduled ujian from now through the next seven days, scoped to the admin's program studi.
     *
     * @return Builder<ThesisDefense>
     */
    public static function agendaQuery(?User $user): Builder
    {
        $query = ThesisDefense::query()
            ->with(['project.student.mahasiswaProfile', 'project.programStudi'])
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_for')
            ->whereBetween('scheduled_for', [now(), now()->copy()->addDays(self::DAYS_AHEAD)]);

        if ($user?->adminProgramStudiId() !== null) {
            $query->whereHas('project', fn (Builder $project): Builder => $project
                ->where('program_studi_id', $user->adminProgramStudiId()));
        }

        return $query;
    }
}
