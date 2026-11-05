<?php

namespace App\Services;

use App\Models\ProgramStudi;
use App\Models\SidangReportPeriod;
use App\Models\User;
use App\Support\WitaDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SidangReportPeriodService
{
    public const SEMESTERS = ['ganjil' => 'Ganjil', 'genap' => 'Genap'];

    public function suggestion(string $start): array
    {
        $date = CarbonImmutable::parse($start, WitaDateTime::TIMEZONE);
        $semester = $date->month >= 7 || $date->month === 1 ? 'ganjil' : 'genap';

        return [
            'semester' => $semester,
            'start_year' => $date->year - (int) ($date->month <= 6),
        ];
    }

    /** @return Collection<int, SidangReportPeriod> */
    public function periods(?int $programId): Collection
    {
        return SidangReportPeriod::query()
            ->when($programId !== null, fn ($query) => $query->where('program_studi_id', $programId))
            ->with('programStudi')
            ->latest('starts_on')
            ->get();
    }

    public function activeId(Collection $periods): ?int
    {
        $today = now(WitaDateTime::TIMEZONE)->toDateString();

        return $periods->first(fn (SidangReportPeriod $period): bool => $period->starts_on->toDateString() <= $today
            && $period->ends_on->toDateString() >= $today)?->id;
    }

    public function save(User $actor, array $input): SidangReportPeriod
    {
        $programId = $actor->hasRole('super_admin') ? (int) ($input['program_studi_id'] ?? 0) : $actor->adminProgramStudiId();
        abort_unless($actor->hasAnyRole(['super_admin', 'admin']) && $programId, 403);
        $data = Validator::make($input, [
            'period_id' => ['nullable', 'integer'],
            'semester' => ['required', 'in:ganjil,genap'],
            'start_year' => ['required', 'integer', 'between:1900,9998'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ])->validate();

        return DB::transaction(function () use ($data, $programId): SidangReportPeriod {
            ProgramStudi::query()->lockForUpdate()->findOrFail($programId);
            $periods = SidangReportPeriod::query()->where('program_studi_id', $programId);
            $period = isset($data['period_id']) ? (clone $periods)->findOrFail($data['period_id']) : new SidangReportPeriod;
            unset($data['period_id']);
            if ($period->exists) {
                $periods->whereKeyNot($period->id);
            }
            if ((clone $periods)->whereDate('starts_on', '<=', $data['ends_on'])->whereDate('ends_on', '>=', $data['starts_on'])->exists()) {
                throw ValidationException::withMessages(['starts_on' => 'Rentang tanggal bertumpang tindih dengan periode rekap lain di prodi ini.']);
            }
            if ($periods->where('semester', $data['semester'])->where('start_year', $data['start_year'])->exists()) {
                throw ValidationException::withMessages(['semester' => 'Periode semester dan tahun akademik ini sudah ada. Pilih periode tersebut untuk mengubahnya.']);
            }
            $period->fill($data + ['program_studi_id' => $programId])->save();

            return $period;
        });
    }
}
