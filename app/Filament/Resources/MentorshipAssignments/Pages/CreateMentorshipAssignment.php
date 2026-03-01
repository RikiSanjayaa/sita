<?php

namespace App\Filament\Resources\MentorshipAssignments\Pages;

use App\Filament\Resources\MentorshipAssignments\MentorshipAssignmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMentorshipAssignment extends CreateRecord
{
  protected static string $resource = MentorshipAssignmentResource::class;

  protected function mutateFormDataBeforeCreate(array $data): array
  {
    $data['assigned_by'] = auth()->id();
    $data['started_at'] = now();

    return $data;
  }
}
