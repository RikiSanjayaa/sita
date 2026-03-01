<?php

namespace App\Filament\Resources\MentorshipAssignments\Pages;

use App\Filament\Resources\MentorshipAssignments\MentorshipAssignmentResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMentorshipAssignment extends EditRecord
{
  protected static string $resource = MentorshipAssignmentResource::class;

  protected function getHeaderActions(): array
  {
    return [
      ViewAction::make(),
    ];
  }
}
