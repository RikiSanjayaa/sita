<?php

namespace App\Filament\Resources\MentorshipAssignments\Pages;

use App\Enums\AdvisorType;
use App\Enums\AssignmentStatus;
use App\Filament\Resources\MentorshipAssignments\MentorshipAssignmentResource;
use App\Models\MentorshipAssignment;
use App\Services\MentorshipAssignmentService;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMentorshipAssignment extends EditRecord
{
    protected static string $resource = MentorshipAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Populate pembimbing_1 from the current record (primary)
        $data['pembimbing_1'] = $data['lecturer_user_id'];

        // Find the secondary advisor for this student
        $secondary = MentorshipAssignment::query()
            ->where('student_user_id', $data['student_user_id'])
            ->where('advisor_type', AdvisorType::Secondary->value)
            ->where('status', AssignmentStatus::Active->value)
            ->first();

        $data['pembimbing_2'] = $secondary?->lecturer_user_id;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            app(MentorshipAssignmentService::class)->syncStudentAdvisors(
                studentUserId: $record->student_user_id,
                assignedBy: auth()->id(),
                primaryLecturerUserId: (int) $data['pembimbing_1'],
                secondaryLecturerUserId: isset($data['pembimbing_2']) ? (int) $data['pembimbing_2'] : null,
                notes: $data['notes'] ?? null,
            );

            // Re-fetch the updated primary record
            return MentorshipAssignment::query()
                ->where('student_user_id', $record->student_user_id)
                ->where('advisor_type', AdvisorType::Primary->value)
                ->where('status', AssignmentStatus::Active->value)
                ->latest('id')
                ->firstOrFail();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal memperbarui pembimbing')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }
}
