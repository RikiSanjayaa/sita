<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertMentorshipAssignmentRequest;
use App\Models\User;
use App\Services\MentorshipAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PenugasanController extends Controller
{
    public function __construct(
        private readonly MentorshipAssignmentService $assignmentService,
    ) {}

    public function index(Request $request): Response
    {
        $students = User::query()
            ->whereHas('roles', static fn ($query) => $query->where('name', AppRole::Mahasiswa->value))
            ->with([
                'mahasiswaProfile:user_id,nim,program_studi,status_akademik',
                'mentorshipAssignmentsAsStudent' => static fn ($query) => $query
                    ->where('status', AssignmentStatus::Active->value)
                    ->with('lecturer:id,name')
                    ->orderByDesc('id'),
            ])
            ->orderBy('name')
            ->get();

        $lecturers = User::query()
            ->whereHas('roles', static fn ($query) => $query->where('name', AppRole::Dosen->value))
            ->with('dosenProfile:user_id,homebase,is_active')
            ->orderBy('name')
            ->get();

        $queue = $students->map(function (User $student): array {
            $assignments = $student->mentorshipAssignmentsAsStudent
                ->keyBy('advisor_type');
            $primary = $assignments->get(AdvisorType::Primary->value);
            $secondary = $assignments->get(AdvisorType::Secondary->value);
            $activeAdvisorCount = collect([$primary, $secondary])
                ->filter()
                ->count();

            $status = match ($activeAdvisorCount) {
                0 => 'Pending',
                1 => 'Partial',
                default => 'Assigned',
            };

            return [
                'id' => sprintf('asg-%s', $student->id),
                'studentUserId' => $student->id,
                'nim' => $student->mahasiswaProfile?->nim ?? '-',
                'mahasiswa' => $student->name,
                'program' => $student->mahasiswaProfile?->program_studi ?? 'Belum diisi',
                'statusAkademik' => $student->mahasiswaProfile?->status_akademik ?? 'aktif',
                'primaryAdvisor' => $primary?->lecturer === null ? null : [
                    'id' => $primary->lecturer->id,
                    'name' => $primary->lecturer->name,
                ],
                'secondaryAdvisor' => $secondary?->lecturer === null ? null : [
                    'id' => $secondary->lecturer->id,
                    'name' => $secondary->lecturer->name,
                ],
                'status' => $status,
            ];
        })->values();

        $lecturerOptions = $lecturers->map(function (User $lecturer): array {
            $load = $this->assignmentService->activeStudentCountForLecturer(
                $lecturer->id,
            );
            $isActive = $lecturer->dosenProfile?->is_active ?? true;

            return [
                'id' => $lecturer->id,
                'name' => $lecturer->name,
                'homebase' => $lecturer->dosenProfile?->homebase,
                'load' => $load,
                'limit' => MentorshipAssignmentService::MAX_ACTIVE_STUDENTS_PER_LECTURER,
                'isActive' => $isActive,
                'atCapacity' => $load >= MentorshipAssignmentService::MAX_ACTIVE_STUDENTS_PER_LECTURER,
            ];
        })->values();

        return Inertia::render('admin/penugasan', [
            'queue' => $queue,
            'lecturers' => $lecturerOptions,
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function store(UpsertMentorshipAssignmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $student = User::query()
            ->with('mahasiswaProfile:user_id,status_akademik')
            ->findOrFail((int) $data['student_user_id']);

        if (! $student->hasRole(AppRole::Mahasiswa)) {
            throw ValidationException::withMessages([
                'student_user_id' => ['User terpilih bukan mahasiswa.'],
            ]);
        }

        if (
            $this->assignmentService->isInactiveStudentStatus(
                $student->mahasiswaProfile?->status_akademik,
            )
        ) {
            throw ValidationException::withMessages([
                'student_user_id' => ['Mahasiswa dengan status akhir tidak dapat diberi assignment baru.'],
            ]);
        }

        $primaryLecturerId = (int) $data['primary_lecturer_user_id'];
        $secondaryLecturerId = isset($data['secondary_lecturer_user_id'])
            ? (int) $data['secondary_lecturer_user_id']
            : null;

        if ($secondaryLecturerId !== null && $secondaryLecturerId === $primaryLecturerId) {
            throw ValidationException::withMessages([
                'secondary_lecturer_user_id' => ['Pembimbing 1 dan Pembimbing 2 harus berbeda.'],
            ]);
        }

        try {
            $this->assignmentService->syncStudentAdvisors(
                studentUserId: (int) $data['student_user_id'],
                assignedBy: $request->user()->id,
                primaryLecturerUserId: $primaryLecturerId,
                secondaryLecturerUserId: $secondaryLecturerId,
                notes: $data['notes'] ?? null,
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            if (array_key_exists('lecturer_user_id', $errors)) {
                $errors['primary_lecturer_user_id'] = $errors['lecturer_user_id'];
                $errors['secondary_lecturer_user_id'] = $errors['lecturer_user_id'];
                unset($errors['lecturer_user_id']);
            }

            throw ValidationException::withMessages($errors);
        }

        return redirect()
            ->route('admin.penugasan')
            ->with('success', 'Penugasan pembimbing berhasil diperbarui.');
    }
}
