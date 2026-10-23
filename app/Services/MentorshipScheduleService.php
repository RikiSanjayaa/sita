<?php

namespace App\Services;

use App\Models\MentorshipSchedule;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class MentorshipScheduleService
{
    /**
     * Mahasiswa yang sedang dibimbing dosen ini: pembimbing aktif pada tugas akhir yang aktif.
     *
     * @return Collection<int, User>
     */
    public function supervisedStudents(User $lecturer): Collection
    {
        return User::query()
            ->whereIn('id', ThesisProject::query()
                ->where('state', 'active')
                ->whereIn('id', ThesisSupervisorAssignment::query()
                    ->where('lecturer_user_id', $lecturer->id)
                    ->where('status', 'active')
                    ->whereIn('role', ['primary', 'secondary'])
                    ->select('project_id'))
                ->select('student_user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Membuat satu jadwal atau seri jadwal berulang dengan atribut bersama.
     *
     * @param  array<string, mixed>  $attributes  Atribut yang sama untuk setiap jadwal dalam seri.
     * @param  bool  $scheduleOccurrences  Jika true, setiap jadwal juga disetel scheduled_for ke tanggal kemunculannya.
     * @return list<MentorshipSchedule>
     */
    public function createSeries(
        array $attributes,
        \DateTime $firstOccurrence,
        bool $scheduleOccurrences,
        ?string $recurringPattern,
        int $recurringCount,
    ): array {
        $isRecurring = $recurringPattern !== null;
        $recurringGroupId = $isRecurring ? Str::uuid()->toString() : null;
        $schedules = [];

        for ($index = 0; $index < $recurringCount; $index++) {
            $occurrence = $index > 0 && $isRecurring
                ? $this->calculateNextDate($firstOccurrence, $recurringPattern, $index)
                : clone $firstOccurrence;

            $schedules[] = MentorshipSchedule::query()->create([
                ...$attributes,
                'requested_for' => $occurrence->format('Y-m-d H:i:s'),
                'scheduled_for' => $scheduleOccurrences ? $occurrence->format('Y-m-d H:i:s') : null,
                'is_recurring' => $isRecurring,
                'recurring_pattern' => $recurringPattern,
                'recurring_count' => $recurringCount,
                'recurring_group_id' => $recurringGroupId,
                'recurring_index' => $isRecurring ? $index + 1 : null,
            ]);
        }

        return $schedules;
    }

    private function calculateNextDate(\DateTime $baseDate, string $pattern, int $occurrence): \DateTime
    {
        $nextDate = clone $baseDate;

        return match ($pattern) {
            'weekly' => $nextDate->modify("+{$occurrence} weeks"),
            'biweekly' => $nextDate->modify('+'.($occurrence * 2).' weeks'),
            'monthly' => $nextDate->modify("+{$occurrence} months"),
            default => $nextDate,
        };
    }
}
