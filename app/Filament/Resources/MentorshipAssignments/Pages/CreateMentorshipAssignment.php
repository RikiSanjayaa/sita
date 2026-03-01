<?php

namespace App\Filament\Resources\MentorshipAssignments\Pages;

use App\Enums\AdvisorType;
use App\Enums\AssignmentStatus;
use App\Enums\ThesisSubmissionStatus;
use App\Filament\Resources\MentorshipAssignments\MentorshipAssignmentResource;
use App\Models\MentorshipAssignment;
use App\Services\MentorshipAssignmentService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMentorshipAssignment extends CreateRecord
{
  protected static string $resource = MentorshipAssignmentResource::class;

  protected function handleRecordCreation(array $data): Model
  {
    try {
      app(MentorshipAssignmentService::class)->syncStudentAdvisors(
        studentUserId: (int) $data['student_user_id'],
        assignedBy: auth()->id(),
        primaryLecturerUserId: (int) $data['pembimbing_1'],
        secondaryLecturerUserId: (int) $data['pembimbing_2'],
        notes: $data['notes'] ?? null,
      );

      // Update thesis submission status
      $student = \App\Models\User::find($data['student_user_id']);
      $student?->thesisSubmissions()
        ->where('status', ThesisSubmissionStatus::SemproSelesai->value)
        ->update(['status' => ThesisSubmissionStatus::PembimbingDitetapkan->value]);

      // Return the primary assignment (required by Filament)
      return MentorshipAssignment::query()
        ->where('student_user_id', $data['student_user_id'])
        ->where('advisor_type', AdvisorType::Primary->value)
        ->where('status', AssignmentStatus::Active->value)
        ->latest('id')
        ->firstOrFail();
    } catch (\Exception $e) {
      Notification::make()
        ->title('Gagal menetapkan pembimbing')
        ->body($e->getMessage())
        ->danger()
        ->send();

      throw $e;
    }
  }
}
