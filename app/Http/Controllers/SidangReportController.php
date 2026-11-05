<?php

namespace App\Http\Controllers;

use App\Models\SidangReportPeriod;
use App\Models\ThesisDefense;
use App\Models\User;
use App\Services\SidangReportWriter;
use App\Support\WitaDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SidangReportController extends Controller
{
    public function __invoke(Request $request, SidangReportWriter $writer): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasAnyRole(['super_admin', 'admin', 'kaprodi']), 403);
        $filters = $request->validate([
            'from' => ['nullable', 'required_with:until', 'date_format:Y-m-d'],
            'until' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
            'period' => ['nullable', 'string', 'max:80'],
            'period_id' => ['nullable', 'integer'],
            'selection' => ['nullable', 'string', 'max:80'],
            'ids' => ['nullable', 'string', 'max:500000', 'regex:/^\d+(,\d+)*$/'],
        ]);
        $query = ThesisDefense::query()
            ->where('type', 'sidang')
            ->where('status', 'completed')
            ->whereIn('result', ['pass', 'pass_with_revision'])
            ->whereNotNull('sidang_paper')
            ->whereNotExists(DB::table('thesis_defenses as later')
                ->whereColumn('later.project_id', 'thesis_defenses.project_id')
                ->whereColumn('later.attempt_no', '>', 'thesis_defenses.attempt_no')
                ->where('later.type', 'sidang')->where('later.status', '!=', 'cancelled'));

        if ($user->hasRole('super_admin')) {
            $programId = null;
        } elseif ($user->hasRole('admin')) {
            $programId = $user->adminProgramStudiId();
            abort_if($programId === null, 403);
        } else {
            $programId = $user->kaprodiAssignment?->program_studi_id;
            abort_if($programId === null, 403);
            $query->whereHas('project', fn (Builder $project): Builder => $project->current());
        }
        if (isset($filters['period_id'])) {
            $period = SidangReportPeriod::query()->findOrFail($filters['period_id']);
            abort_unless($programId === null || $programId === (int) $period->program_studi_id, 403);
            $programId = (int) $period->program_studi_id;
            $filters['from'] = $period->starts_on->toDateString();
            $filters['until'] = $period->ends_on->toDateString();
            $filters['period'] = mb_strtoupper($period->label());
        }
        if ($programId !== null) {
            $query->whereHas('project', fn (Builder $project): Builder => $project->where('program_studi_id', $programId));
        }
        if (isset($filters['selection'])) {
            $selection = Cache::store('database')->get('sidang-export:'.$filters['selection']);
            abort_unless(is_array($selection) && $selection['user'] === $user->id, 403);
            $query->whereRaw('thesis_defenses.project_id in ('.$selection['sql'].')', $selection['bindings']);
        }
        if (isset($filters['ids'])) {
            $query->whereIntegerInRaw('thesis_defenses.id', explode(',', $filters['ids']));
        }
        if (isset($filters['from'], $filters['until'])) {
            $from = CarbonImmutable::parse($filters['from'], WitaDateTime::TIMEZONE)->startOfDay()->setTimezone(config('app.timezone'));
            $until = CarbonImmutable::parse($filters['until'], WitaDateTime::TIMEZONE)->addDay()->startOfDay()->setTimezone(config('app.timezone'));
            $query->where('scheduled_for', '>=', $from)->where('scheduled_for', '<', $until);
        }
        $query->with([
            'project.student.mahasiswaProfile', 'project.programStudi.faculty',
            'project.programStudi.kaprodiAssignments.user.dosenProfile', 'titleVersion',
            'examiners.lecturer.dosenProfile',
        ])->orderBy('thesis_defenses.id');

        return response()->streamDownload(
            fn () => $writer->write($query->lazyById(200, 'thesis_defenses.id', 'id'), $filters['period'] ?? ''),
            'rekap-nilai-sidang.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store'],
        );
    }
}
